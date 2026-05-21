<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * YN kunini belgilash sahifasida talabaga nega "Pullik" chiqib, 2-urinish
 * sanasi belgilab bo'lmayotganini AYNAN manbalar bilan ko'rsatadi.
 *
 * Sahifa mantiqi: AcademicScheduleController::computeStudentAttemptStatuses()
 *   $isPullik = $jnLow || $mtLow || ($davomatPct >= 25);
 *   pullikBlocked = (urinish > 1) && is_pullik  → sana input bloklanadi
 *
 * Bu command aynan o'sha hisoblashni bitta talaba uchun qadam-baqadam
 * takrorlaydi va qaysi shart "Pullik" ni yoqib yuborganini aytadi.
 *
 * Misol:
 *   php artisan debug:yn-retake-reason "Toremuratova" --subject=Gistolog
 *   php artisan debug:yn-retake-reason "Toremuratova Mahfuza" --subject="Gistologiya"
 */
class DebugYnRetakeReason extends Command
{
    protected $signature = 'debug:yn-retake-reason
        {name : Talaba F.I.Sh (qismi ham bo\'ladi)}
        {--subject= : Fan nomi (qismi) bo\'yicha filtr}
        {--semester= : semester_code bo\'yicha filtr}
        {--group= : Guruh nomi (qismi) bo\'yicha filtr}';

    protected $description = 'YN kunini belgilashda nega "Pullik" chiqayotganini aniq manbalar bilan ko\'rsatadi';

    private const MIN = 60;

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));
        $subjectFilter = $this->option('subject');
        $semFilter = $this->option('semester');
        $groupFilter = $this->option('group');

        // 1) Talabani topish ------------------------------------------------
        $students = DB::table('students')
            ->where('full_name', 'like', '%' . $name . '%')
            ->when($groupFilter, fn ($q) => $q->where('group_name', 'like', '%' . $groupFilter . '%'))
            ->select('hemis_id', 'full_name', 'group_id', 'group_name', 'level_code', 'student_status_code', 'student_status_name')
            ->orderBy('full_name')
            ->limit(25)
            ->get();

        if ($students->isEmpty()) {
            $this->error("Talaba topilmadi: \"{$name}\"");
            return self::FAILURE;
        }
        if ($students->count() > 1) {
            $this->warn("Bir nechta talaba topildi — F.I.Sh ni aniqroq kiriting yoki --group qo'shing:");
            $this->table(
                ['hemis_id', 'F.I.Sh', 'group_id', 'guruh', 'level', 'status'],
                $students->map(fn ($s) => [
                    $s->hemis_id, $s->full_name, $s->group_id, $s->group_name,
                    $s->level_code, $s->student_status_code . ' ' . $s->student_status_name,
                ])->toArray()
            );
            return self::SUCCESS;
        }

        $student = $students->first();
        $hid = (string) $student->hemis_id;
        $groupHid = (string) $student->group_id;
        $levelCode = (string) ($student->level_code ?? '');

        $this->info('================ TALABA ================');
        $this->line("F.I.Sh         : {$student->full_name}");
        $this->line("hemis_id       : {$hid}");
        $this->line("group_id       : {$groupHid}  ({$student->group_name})");
        $this->line("level_code     : {$levelCode}");
        $this->line("status_code    : {$student->student_status_code} ({$student->student_status_name})");
        if ((string) $student->student_status_code !== '11') {
            $this->warn("  ⚠  Sahifa faqat student_status_code=11 talabalarni ko'rsatadi. "
                . "Bu talaba 11 emas — sahifada umuman ko'rinmasligi mumkin.");
        }
        $this->newLine();

        // 2) Talabaning fanlarini topish ------------------------------------
        $subjectRows = DB::table('student_grades as sg')
            ->leftJoin('curriculum_subjects as cs', function ($j) {
                $j->on('cs.subject_id', '=', 'sg.subject_id')
                  ->on('cs.semester_code', '=', 'sg.semester_code');
            })
            ->whereNull('sg.deleted_at')
            ->where('sg.student_hemis_id', $hid)
            ->when($subjectFilter, fn ($q) => $q->where('cs.subject_name', 'like', '%' . $subjectFilter . '%'))
            ->when($semFilter, fn ($q) => $q->where('sg.semester_code', $semFilter))
            ->select('sg.subject_id', 'sg.semester_code', 'cs.subject_name')
            ->distinct()
            ->get();

        if ($subjectRows->isEmpty()) {
            $this->error('Bu talaba uchun mos fan topilmadi (student_grades bo\'sh yoki filtr juda tor).');
            return self::FAILURE;
        }

        foreach ($subjectRows as $row) {
            $this->analyzeSubject($hid, $groupHid, $levelCode, (string) $row->subject_id, (string) $row->semester_code, $row->subject_name);
        }

        return self::SUCCESS;
    }

    /**
     * Bitta (subject, semester) uchun computeStudentAttemptStatuses mantiqini
     * qadam-baqadam takrorlaydi.
     */
    private function analyzeSubject(string $hid, string $groupHid, string $levelCode, string $sid, string $sem, ?string $subjectName): void
    {
        $this->info('================================================================');
        $this->info("FAN: " . ($subjectName ?? '(noma\'lum)') . "  [subject_id={$sid}, semester={$sem}]");
        $this->info('================================================================');

        $year = DB::table('semesters')->where('semester_code', $sem)->value('education_year');
        $relevantYears = $year ? [$year] : [];

        // --- A) JN/MT: snapshot (yn_student_grades) ---------------------------
        $hasYnEduYear = Schema::hasColumn('yn_submissions', 'education_year');
        $hasYnAttempt = Schema::hasColumn('yn_submissions', 'attempt');

        $ynQ = DB::table('yn_student_grades as ysg')
            ->join('yn_submissions as yns', 'yns.id', '=', 'ysg.yn_submission_id')
            ->where('ysg.student_hemis_id', $hid)
            ->where('yns.subject_id', $sid)
            ->where('yns.semester_code', $sem)
            ->where('yns.group_hemis_id', $groupHid);
        if ($hasYnEduYear && !empty($relevantYears)) {
            $ynQ->whereIn('yns.education_year', $relevantYears);
        }
        $ynSelect = ['ysg.id as ysg_id', 'ysg.jn', 'ysg.mt', 'ysg.source', 'ysg.created_at',
            'yns.id as yn_submission_id'];
        if ($hasYnAttempt) $ynSelect[] = 'yns.attempt';
        if ($hasYnEduYear) $ynSelect[] = 'yns.education_year';
        $ynRows = $ynQ->orderByDesc('ysg.created_at')->select($ynSelect)->get();

        $this->newLine();
        $this->line('--- A) SNAPSHOT: yn_student_grades (eng yangisi birinchi) ---');
        if ($ynRows->isEmpty()) {
            $this->line('  (snapshot yo\'q — talaba hali YN ga yuborilmagan yoki snapshot saqlanmagan)');
        } else {
            $this->table(
                ['ysg_id', 'yn_sub_id', 'attempt', 'edu_year', 'jn', 'mt', 'source', 'created_at'],
                $ynRows->map(fn ($r) => [
                    $r->ysg_id, $r->yn_submission_id, $r->attempt ?? '-', $r->education_year ?? '-',
                    $r->jn, $r->mt, $r->source ?? '-', $r->created_at,
                ])->toArray()
            );
        }

        // Sahifa mantiqi: eng yangi snapshot, jn>0 ? jn : null
        $snap = $ynRows->first();
        $snapJn = $snap ? ((int) $snap->jn > 0 ? (int) $snap->jn : null) : null;
        $snapMt = $snap ? ((int) $snap->mt > 0 ? (int) $snap->mt : null) : null;
        $this->line('  → Snapshotdan olingan JN = ' . var_export($snapJn, true)
            . ' , MT = ' . var_export($snapMt, true)
            . '   (0 qiymat "baholanmagan" deb null ga aylanadi)');

        // --- B) JN/MT: tirik manbalar (snapshot null bo'lsa fallback) --------
        $jnAvg = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->where('student_hemis_id', $hid)
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->whereNotIn('training_type_code', [11, 99, 100, 101, 102, 103])
            ->whereRaw('COALESCE(retake_grade, grade) IS NOT NULL')
            ->avg(DB::raw('COALESCE(retake_grade, grade)'));

        $jnComponents = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->where('student_hemis_id', $hid)
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->whereNotIn('training_type_code', [11, 99, 100, 101, 102, 103])
            ->whereRaw('COALESCE(retake_grade, grade) IS NOT NULL')
            ->select('id', 'training_type_code', 'training_type_name', 'grade', 'retake_grade', 'lesson_date')
            ->orderBy('lesson_date')
            ->get();

        $manualMt = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->where('student_hemis_id', $hid)
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->where('training_type_code', 99)
            ->whereNull('lesson_date')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->select('id', 'grade', 'retake_grade', 'updated_at', 'created_at')
            ->get();

        $this->newLine();
        $this->line('--- B) TIRIK JN manbalari (snapshot JN null bo\'lsa ishlatiladi) ---');
        $this->line('  JN = AVG(COALESCE(retake_grade,grade)), training_type_code NOT IN (11,99,100,101,102,103)');
        if ($jnComponents->isEmpty()) {
            $this->line('  (joriy baho yozuvlari yo\'q)');
        } else {
            $this->table(
                ['sg_id', 'tt_code', 'tur', 'grade', 'retake', 'effective', 'lesson_date'],
                $jnComponents->map(fn ($r) => [
                    $r->id, $r->training_type_code, $r->training_type_name,
                    $r->grade, $r->retake_grade, ($r->retake_grade ?? $r->grade), $r->lesson_date,
                ])->toArray()
            );
        }
        $this->line('  → Tirik JN avg = ' . ($jnAvg !== null ? round((float) $jnAvg, 2)
            . '  (yaxlitlangan: ' . (int) round((float) $jnAvg, 0, PHP_ROUND_HALF_UP) . ')' : 'null'));

        $this->newLine();
        $this->line('--- B2) TIRIK MT manbalari (training_type_code=99, lesson_date IS NULL) ---');
        if ($manualMt->isEmpty()) {
            $this->line('  (qo\'lda MT yozuvi yo\'q)');
        } else {
            $this->table(
                ['mt_id', 'grade', 'retake', 'effective', 'updated_at', 'created_at'],
                $manualMt->map(fn ($r) => [
                    $r->id, $r->grade, $r->retake_grade, ($r->retake_grade ?? $r->grade),
                    $r->updated_at, $r->created_at,
                ])->toArray()
            );
        }
        $mtEffective = $manualMt->isNotEmpty()
            ? ($manualMt->first()->retake_grade ?? $manualMt->first()->grade)
            : null;
        $this->line('  → Tirik MT (eng oxirgi yangilangan yozuv) = ' . var_export($mtEffective, true));

        // Yakuniy JN/MT (sahifadagi tartib: snapshot ustun, null bo'lsa tirik)
        $jn = $snapJn;
        if ($jn === null && $jnAvg !== null) {
            $jn = (int) round((float) $jnAvg, 0, PHP_ROUND_HALF_UP);
        }
        $mt = $snapMt;
        if ($mt === null && $mtEffective !== null) {
            $mt = (int) round((float) $mtEffective, 0, PHP_ROUND_HALF_UP);
        }

        // --- C) Davomat -------------------------------------------------------
        $absentRows = DB::table('attendances')
            ->where('student_hemis_id', $hid)
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->whereNotIn('training_type_code', [99, 100, 101, 102])
            ->where(function ($q) {
                $q->where('absent_on', '>', 0)->orWhere('absent_off', '>', 0);
            })
            ->select('id', 'lesson_date', 'training_type_code', 'training_type_name', 'absent_on', 'absent_off')
            ->orderBy('lesson_date')
            ->get();

        $absentOff = (float) DB::table('attendances')
            ->where('student_hemis_id', $hid)
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->whereNotIn('training_type_code', [99, 100, 101, 102])
            ->sum('absent_off');

        $subject = DB::table('curriculum_subjects')
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->select('subject_details', 'total_acload')
            ->first();

        $aud = 0.0;
        $audBreakdown = [];
        if ($subject) {
            $details = is_string($subject->subject_details)
                ? json_decode($subject->subject_details, true)
                : $subject->subject_details;
            if (is_array($details)) {
                foreach ($details as $d) {
                    $tc = (string) ($d['trainingType']['code'] ?? '');
                    $load = (float) ($d['academic_load'] ?? 0);
                    if ($tc !== '17') {
                        $aud += $load;
                        $audBreakdown[] = "tt={$tc}: {$load}";
                    } else {
                        $audBreakdown[] = "tt=17 (chiqarib tashlandi): {$load}";
                    }
                }
            }
            if ($aud <= 0) {
                $aud = (float) ($subject->total_acload ?? 0);
                $audBreakdown[] = "fallback total_acload: {$aud}";
            }
        }
        $davomatPct = $aud > 0 ? round(($absentOff / $aud) * 100, 2) : 0.0;

        $this->newLine();
        $this->line('--- C) DAVOMAT (attendances, training_type_code NOT IN (99,100,101,102)) ---');
        if ($absentRows->isEmpty()) {
            $this->line('  (absent_on/absent_off > 0 bo\'lgan yozuv yo\'q)');
        } else {
            $this->table(
                ['att_id', 'lesson_date', 'tt_code', 'tur', 'absent_on', 'absent_off'],
                $absentRows->map(fn ($r) => [
                    $r->id, $r->lesson_date, $r->training_type_code, $r->training_type_name,
                    $r->absent_on, $r->absent_off,
                ])->toArray()
            );
        }
        $this->line('  absent_off jami      = ' . $absentOff);
        $this->line('  auditoriya soatlari  = ' . $aud . '   [' . implode(', ', $audBreakdown) . ']');
        $this->line('  → davomat foizi      = ' . $davomatPct . '%   (chegara: 25%)');

        // --- D) OSKI / Test baholari (failed1 uchun) -------------------------
        $oski1 = $this->examAvg($hid, $sid, $sem, [101], 1);
        $test1 = $this->examAvg($hid, $sid, $sem, [102], 1);
        $oski2 = $this->examAvg($hid, $sid, $sem, [101], 2);
        $test2 = $this->examAvg($hid, $sid, $sem, [102], 2);

        $naRow = DB::table('exam_schedules')
            ->whereNull('student_hemis_id')
            ->where('group_hemis_id', $groupHid)
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->select('oski_na', 'test_na', 'oski_date', 'test_date', 'oski_resit_date', 'test_resit_date')
            ->first();
        $oskiRequired = !($naRow->oski_na ?? false);
        $testRequired = !($naRow->test_na ?? false);

        $this->newLine();
        $this->line('--- D) OSKI / Test baholari ---');
        $this->line('  OSKI kerakmi : ' . ($oskiRequired ? 'ha' : 'yo\'q (oski_na)')
            . '  | Test kerakmi : ' . ($testRequired ? 'ha' : 'yo\'q (test_na)'));
        $this->line('  1-urinish: OSKI = ' . var_export($oski1, true) . ' , Test = ' . var_export($test1, true));
        $this->line('  2-urinish: OSKI = ' . var_export($oski2, true) . ' , Test = ' . var_export($test2, true));
        $this->line('  Imtihon sanalari: oski_date=' . ($naRow->oski_date ?? '-')
            . ' , test_date=' . ($naRow->test_date ?? '-')
            . ' , oski_resit_date=' . ($naRow->oski_resit_date ?? '-')
            . ' , test_resit_date=' . ($naRow->test_resit_date ?? '-'));

        // --- E) Yakuniy hisoblash (computeStudentAttemptStatuses bilan bir xil) ---
        $jnLow = ($jn !== null) && ($jn < self::MIN);
        $mtLow = ($mt !== null) && ($mt < self::MIN);
        $davFail = $davomatPct >= 25;
        $isPullik = $jnLow || $mtLow || $davFail;

        $today = now()->format('Y-m-d');
        $confirmFailed = function (bool $required, $grade, $date) use ($today): bool {
            if (!$required) return false;
            if ($date === null || ((string) $date) > $today) return false;
            if ($grade !== null) return ((float) $grade) < self::MIN;
            return true;
        };
        $oskiFailed1 = $confirmFailed($oskiRequired, $oski1, $naRow->oski_date ?? null);
        $testFailed1 = $confirmFailed($testRequired, $test1, $naRow->test_date ?? null);
        $failed1 = $isPullik || $oskiFailed1 || $testFailed1;

        $this->newLine();
        $this->info('================ YAKUNIY HISOB ================');
        $this->line('JN  = ' . var_export($jn, true) . '   → jnLow (JN<60)         = ' . $this->b($jnLow));
        $this->line('MT  = ' . var_export($mt, true) . '   → mtLow (MT<60)         = ' . $this->b($mtLow));
        $this->line('davomat = ' . $davomatPct . '%   → davomat≥25%          = ' . $this->b($davFail));
        $this->line('OSKI 1-urinish yiqildi = ' . $this->b($oskiFailed1)
            . '  | Test 1-urinish yiqildi = ' . $this->b($testFailed1));
        $this->newLine();
        $this->line('  isPullik  = jnLow || mtLow || davomat≥25%   = ' . $this->b($isPullik));
        $this->line('  failed1   = isPullik || oskiFailed1 || testFailed1 = ' . $this->b($failed1));
        $this->newLine();

        // --- VERDIKT ---------------------------------------------------------
        $this->info('================ VERDIKT ================');
        if (!$isPullik) {
            $this->line('  Bu talaba "Pullik" EMAS. Agar sahifada Pullik chiqsa — keshlangan/');
            $this->line('  eski yuklanish bo\'lishi mumkin, sahifani yangilang.');
        } else {
            $reasons = [];
            if ($jnLow) $reasons[] = "JN = {$jn} (60 dan past)";
            if ($mtLow) $reasons[] = "MT = {$mt} (60 dan past)";
            if ($davFail) $reasons[] = "davomat = {$davomatPct}% (25% dan yuqori)";
            $this->warn('  "Pullik" sababi: ' . implode('  VA  ', $reasons));
            $this->newLine();
            $this->line('  Talaba 2-urinish badge bilan ko\'rinadi (testdan yiqilgan), lekin');
            $this->line('  is_pullik=true bo\'lgani uchun "pullikBlocked" yoqiladi va sana');
            $this->line('  inputi bloklanadi (index.blade.php:670).');
            $this->newLine();

            // Snapshot vs tirik nomuvofiqligini ko'rsatish
            if ($jnLow && $snapJn !== null && $jnAvg !== null) {
                $liveJn = (int) round((float) $jnAvg, 0, PHP_ROUND_HALF_UP);
                if ($liveJn >= self::MIN) {
                    $this->warn("  ⚠  DIQQAT: snapshotdagi JN={$snapJn} past, lekin tirik JN avg={$liveJn} (≥60).");
                    $this->line('     Snapshot eskirgan bo\'lishi mumkin — JN keyin tuzatilgan, snapshot yangilanmagan.');
                }
            }
            if ($mtLow && $snapMt !== null && $mtEffective !== null) {
                $liveMt = (int) round((float) $mtEffective, 0, PHP_ROUND_HALF_UP);
                if ($liveMt >= self::MIN) {
                    $this->warn("  ⚠  DIQQAT: snapshotdagi MT={$snapMt} past, lekin tirik MT={$liveMt} (≥60).");
                    $this->line('     Snapshot eskirgan bo\'lishi mumkin — MT keyin tuzatilgan, snapshot yangilanmagan.');
                }
            }
        }
        $this->newLine();
    }

    /**
     * OSKI/Test bahosini attempt bo'yicha o'rtachasini hisoblaydi
     * (legacy 103 quiz kodi ham hisobga olinadi).
     */
    private function examAvg(string $hid, string $sid, string $sem, array $typeCodes, int $attempt): ?float
    {
        $hasAttemptCol = Schema::hasColumn('student_grades', 'attempt');

        $q = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->where('student_hemis_id', $hid)
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->whereIn('training_type_code', array_merge($typeCodes, [103]));

        if ($hasAttemptCol) {
            if ($attempt === 1) {
                $q->where(fn ($qq) => $qq->where('attempt', 1)->orWhereNull('attempt'));
            } else {
                $q->where('attempt', $attempt);
            }
        } elseif ($attempt > 1) {
            return null;
        }

        $rows = $q->select('training_type_code', 'grade', 'retake_grade', 'quiz_result_id')->get();

        $quizIds = $rows->where('training_type_code', 103)->pluck('quiz_result_id')->filter()->unique()->values()->all();
        $quizTypeMap = [];
        if (!empty($quizIds)) {
            $quizTypeMap = DB::table('hemis_quiz_results')->whereIn('id', $quizIds)->pluck('quiz_type', 'id')->toArray();
        }
        $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
        $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];

        $list = [];
        foreach ($rows as $r) {
            $tc = (int) $r->training_type_code;
            if ($tc === 103) {
                $qt = $quizTypeMap[$r->quiz_result_id] ?? null;
                if (in_array($qt, $oskiTypes, true)) $tc = 101;
                elseif (in_array($qt, $testTypes, true)) $tc = 102;
                else continue;
            }
            if (!in_array($tc, $typeCodes, true)) continue;
            $eff = $r->retake_grade ?? $r->grade;
            if ($eff === null) continue;
            $list[] = (float) $eff;
        }
        return count($list) ? array_sum($list) / count($list) : null;
    }

    private function b(bool $v): string
    {
        return $v ? 'HA' : 'yo\'q';
    }
}
