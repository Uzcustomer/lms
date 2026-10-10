<?php

namespace App\Services;

use App\Models\LessonOpening;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * O'qituvchining baho qo'yilmay qolgan darslari — dashboard popupi uchun.
 *
 * "Dars belgilash" hisoboti bilan bir xil qoida: o'tgan kun (bugun va
 * kelajak emas), amaliy dars (ma'ruza, MT, ON, OSKI, test emas). Juftlik
 * faqat BARCHA faol talaba baho YOKI NB olgandagina "hisobga olingan"
 * bo'ladi. Agar juftlikda hatto bitta faol talabada ham na baho na NB
 * bo'lmasa — o'sha juftlik baho qo'yilmagan hisoblanadi. Kun ikki
 * juftlikdan iborat bo'lib, birortasida ham to'liq baho bo'lmasa, kun
 * ochilishi kerak. Bunday kunga o'qituvchi faqat dars ochish so'rovi
 * orqali baho qo'ya oladi.
 *
 * Baho qo'yilmaydigan fanlar (o'quv amaliyoti, tanishuv amaliyoti) chiqarib
 * tashlanadi — ular config('app.grade_excluded_subject_patterns') da sanalgan
 * va Telegram hisobotlari allaqachon shu ro'yxatga tayanadi. Bu fanlarda
 * davomat olinadi, lekin har bir darsga baho qo'yilmaydi.
 *
 * Faqat shu o'qituvchi nomiga jadvalda qo'yilgan darslar, joriy semestr
 * boshidan (LessonOpening::periodStart) kechagacha. Allaqachon so'rov
 * yuborilgan (rad etilmagan) kunlar chiqarilmaydi; rad etilgani qayta
 * yuborish mumkinligi uchun belgi bilan qoladi.
 *
 * Xuddi shu qoida registrator ofisining Excel hisobotida ham ishlaydi
 * (LessonOpeningTeacherReport): u barcha o'qituvchilar uchun pastSlots() va
 * markedPairs() ni chaqiradi, shuning uchun popup va hisobot bir xil javob beradi.
 */
class TeacherMissedLessons
{
    /** Jurnaldagi $excludedTrainingTypes bilan bir xil */
    private const EXCLUDED_TYPE_NAMES = ["Ma'ruza", "Mustaqil ta'lim", 'Oraliq nazorat', 'Oski', 'Yakuniy test', 'Quiz test'];

    /**
     * Katak "hisobga olingan"mi — effectiveGrade() ning SQL ko'rinishi.
     *
     * PHP tomoni: reason = 'absent' (NB) YOKI effectiveGrade() null emas.
     * Quyidagi CASE effectiveGrade() ning shoxlarini AYNAN o'sha tartibda
     * takrorlaydi va natijasi null emasligini tekshiradi. Tartib muhim:
     * birinchi mos kelgan shox g'olib chiqadi.
     *
     * Ikki tomon teng ekani testda tasdiqlanadi (TeacherMissedLessonsSqlTest).
     */
    private const PROCESSED_SQL = <<<'SQL'
        (
            sg.reason = 'absent'
            OR (CASE
                WHEN sg.grade IS NOT NULL AND sg.grade < 60 AND sg.retake_grade IS NOT NULL THEN sg.retake_grade
                WHEN sg.status = 'pending' AND sg.reason = 'low_grade' AND sg.grade IS NOT NULL THEN sg.grade
                WHEN sg.status = 'pending' THEN NULL
                WHEN sg.reason = 'absent' AND sg.grade IS NULL THEN sg.retake_grade
                WHEN sg.status = 'closed' AND sg.reason = 'teacher_victim' AND sg.grade = 0 AND sg.retake_grade IS NULL THEN NULL
                WHEN sg.status IN ('recorded', 'closed') THEN sg.grade
                ELSE sg.retake_grade
            END) IS NOT NULL
        )
        SQL;

    /** @return Collection<int, array> eng yangi sana birinchi */
    public function forTeacher(Teacher $teacher): Collection
    {
        if (empty($teacher->hemis_id)) {
            return collect();
        }

        $today = now('Asia/Tashkent')->toDateString();
        $from = LessonOpening::periodStart()->toDateString();

        // 1. O'qituvchining o'tgan amaliy dars juftliklari
        //    (guruh + fan + semestr + sana + juftlik)
        $slots = $this->pastSlots($from, $today, $teacher->hemis_id);

        if ($slots->isEmpty()) {
            return collect();
        }

        $groupIds = $slots->pluck('group_id')->unique()->values()->all();
        $subjectIds = $slots->pluck('subject_id')->unique()->values()->all();

        // Faol talabasi yo'q guruh uchun "baho qo'yilmagan" ma'nosiz
        $groupsWithStudents = $this->groupsWithActiveStudents($groupIds);

        // 2. Juftliklar tahlili: qaysilari to'liq (marked) va to'liq
        //    bo'lmaganlarida kimlar baho olmagan (missing). $slots ham
        //    uzatiladi — bahosi umuman yo'q juftlikda butun ro'yxat sanalsin.
        $analysis = $this->analyzePairs($groupIds, $subjectIds, $from, $today, $slots);
        $marked = $analysis['marked'];
        $missing = $analysis['missing'];

        // Kun bo'yicha noyob talabalar: bir talaba ikki juftlikda ham
        // qoldirilsa bir marta sanaladi (hisobot bilan bir xil qoida)
        $missingByDay = [];
        foreach ($slots as $slot) {
            $pairKey = $this->pairKey($slot->group_id, $slot->subject_id, $slot->semester_code, $slot->lesson_day, $slot->lesson_pair_code);
            if (isset($missing[$pairKey])) {
                $dayKey = $this->key($slot->group_id, $slot->subject_id, $slot->semester_code, $slot->lesson_day);
                $missingByDay[$dayKey] = ($missingByDay[$dayKey] ?? []) + $missing[$pairKey];
            }
        }

        // 3. Allaqachon so'rov yuborilgan kunlar: rad etilmaganlari chiqariladi.
        // Muddati tugagan so'rov kuni (baho hali to'liq emas) yana ko'rinadi —
        // o'qituvchi qayta so'rov yuboradi. id tartibi: kunning eng oxirgi so'rovi.
        LessonOpening::expireOverdue();
        $openings = LessonOpening::query()
            ->whereIn('group_hemis_id', $groupIds)
            ->whereIn('subject_id', $subjectIds)
            ->whereDate('lesson_date', '>=', $from)
            ->orderBy('id')
            ->get(['group_hemis_id', 'subject_id', 'semester_code', 'lesson_date', 'status'])
            ->mapWithKeys(fn ($o) => [
                $this->key($o->group_hemis_id, $o->subject_id, $o->semester_code, $o->lesson_date?->format('Y-m-d')) => $o->status,
            ]);

        // Juftlik hisobga olinmagan bo'lsa kun ro'yxatga tushadi; so'rov esa
        // kunga yuboriladi, shuning uchun bir kun bir marta ko'rinadi
        return $slots
            ->filter(function ($slot) use ($marked, $openings, $groupsWithStudents) {
                $key = $this->key($slot->group_id, $slot->subject_id, $slot->semester_code, $slot->lesson_day);
                $pairKey = $this->pairKey($slot->group_id, $slot->subject_id, $slot->semester_code, $slot->lesson_day, $slot->lesson_pair_code);
                $status = $openings[$key] ?? null;

                return ! isset($marked[$pairKey])
                    && isset($groupsWithStudents[(string) $slot->group_id])
                    && in_array($status, [null, LessonOpening::STATUS_REJECTED, LessonOpening::STATUS_EXPIRED], true);
            })
            ->unique(fn ($slot) => $this->key($slot->group_id, $slot->subject_id, $slot->semester_code, $slot->lesson_day))
            ->map(function ($slot) use ($openings, $missingByDay) {
                $key = $this->key($slot->group_id, $slot->subject_id, $slot->semester_code, $slot->lesson_day);

                return [
                    'group_name' => $slot->group_name,
                    'subject_name' => $slot->subject_name,
                    'lesson_date' => $slot->lesson_day,
                    'rejected' => ($openings[$key] ?? null) === LessonOpening::STATUS_REJECTED,
                    // Shu kunda nechta talabada baho yo'q (popupda ko'rsatiladi)
                    'ungraded_students' => count($missingByDay[$key] ?? []),
                    // Jurnal shu kunga o'tib ochiladi. So'rov oynasi O'ZI
                    // ochilmaydi: o'qituvchi avval jurnalni ko'rsin, so'ng
                    // kerak bo'lsa ustundagi "!" ni bossin.
                    'url' => route('admin.journal.show', [$slot->group_db_id, $slot->subject_id, $slot->semester_code])
                        .'?focus_lesson='.$slot->lesson_day,
                ];
            })
            ->sortByDesc('lesson_date')
            ->values();
    }

    /**
     * Davr boshidan kechagacha o'tgan amaliy dars juftliklari: guruh + fan +
     * semestr + sana + juftlik, har biri o'qituvchisi bilan. $employeeHemisId
     * berilsa — faqat shu o'qituvchining, aks holda jadvaldagi barchasiniki
     * (registrator hisoboti uchun).
     *
     * @return Collection<int, object>
     */
    public function pastSlots(string $from, string $today, string|int|null $employeeHemisId = null): Collection
    {
        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);
        // Baho qo'yilmaydigan fanlar — Telegram hisobotlaridagi ro'yxat bilan bir xil
        $excludedSubjects = config('app.grade_excluded_subject_patterns', []);

        return DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->when(
                $employeeHemisId !== null,
                fn ($query) => $query->where('sch.employee_id', $employeeHemisId),
                fn ($query) => $query->whereNotNull('sch.employee_id')
            )
            ->where('sch.education_year_current', true)
            ->whereNull('sch.deleted_at')
            ->whereNotNull('sch.lesson_date')
            ->whereNotIn('sch.training_type_name', self::EXCLUDED_TYPE_NAMES)
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            // O'quv/tanishuv amaliyoti: davomat bor, baho yo'q
            ->when($excludedSubjects !== [], function ($query) use ($excludedSubjects) {
                foreach ($excludedSubjects as $pattern) {
                    $query->whereRaw('LOWER(sch.subject_name) NOT LIKE ?', ['%'.mb_strtolower($pattern).'%']);
                }
            })
            // Indeksdan foydalanish uchun DATE() emas, sana chegaralari bilan
            ->where('sch.lesson_date', '>=', $from.' 00:00:00')
            ->where('sch.lesson_date', '<', $today.' 00:00:00')
            ->groupBy('g.id', 'g.name', 'sch.employee_id', 'sch.group_id', 'sch.subject_id', 'sch.semester_code', DB::raw('DATE(sch.lesson_date)'), 'sch.lesson_pair_code')
            ->select(
                'g.id as group_db_id',
                'g.name as group_name',
                'sch.employee_id',
                DB::raw('MAX(sch.employee_name) as employee_name'),
                'sch.group_id',
                'sch.subject_id',
                'sch.semester_code',
                DB::raw('MAX(sch.subject_name) as subject_name'),
                DB::raw('MAX(sch.semester_name) as semester_name'),
                DB::raw('DATE(sch.lesson_date) as lesson_day'),
                'sch.lesson_pair_code',
                DB::raw('MAX(sch.lesson_pair_name) as lesson_pair_name'),
                DB::raw('MAX(sch.lesson_pair_start_time) as lesson_pair_start_time'),
                DB::raw('MAX(sch.lesson_pair_end_time) as lesson_pair_end_time')
            )
            ->get();
    }

    /**
     * Faol (O'qimoqda) talabasi bor guruhlar: [group_id => index].
     * $bachelorOnly — faqat bakalavr talabasi bor guruhlar (hisobot uchun).
     *
     * @return array<string, int>
     */
    public function groupsWithActiveStudents(array $groupIds, bool $bachelorOnly = false): array
    {
        return DB::table('students')
            ->whereIn('group_id', $groupIds)
            ->where('student_status_code', 11)
            // Faqat bakalavr: hisobot magistr/ordinatura guruhlarini chiqarmaydi.
            // LMS bakalavrni ikki xil belgilaydi — education_type_code = '11'
            // (rasmiy kod), yoki education_type_name ichida "bakalavr"/"bakalavriat".
            // Ikkalasini ham qamraymiz (nomi bo'sh yoki boshqa yozuvda bo'lsa ham).
            ->when($bachelorOnly, fn ($q) => $q->where(function ($w) {
                $w->where('education_type_code', '11')
                    ->orWhereRaw('LOWER(education_type_name) LIKE ?', ['%bakalavr%']);
            }))
            ->distinct()
            ->pluck('group_id')
            ->map(fn ($id) => (string) $id)
            ->flip()
            ->all();
    }

    /**
     * Juftliklar tahlili — "Dars belgilash" hisoboti bilan bir xil qoida:
     * juftlik faqat BARCHA faol talaba baho YOKI NB olgandagina "hisobga
     * olingan" (marked) bo'ladi. Kimdadir ikkalasi ham yo'q bo'lsa — juftlik
     * baho qo'yilmagan hisoblanadi va nechta talabada yo'qligi (missing) qaytadi.
     *
     * $slots berilsa (pastSlots natijasi), bahosi UMUMAN yo'q juftliklar ham
     * hisobga olinadi: ularda butun ro'yxat baho kutayotgan bo'ladi. Berilmasa
     * faqat kamida bitta bahosi bor juftliklar ko'riladi.
     *
     * @param  iterable<object>  $slots  pastSlots() qaytargan jadval juftliklari
     * @return array{marked: array<string,true>, missing: array<string,array<string,true>>}
     *                missing: pairKey => [baho qo'yilmagan talaba hemis_id => true]
     */
    /** Oxirgi analyzePairs bosqichlari vaqti (soniya) — sekinlikni aniqlash uchun. */
    public array $lastTimings = [];

    public function analyzePairs(array $groupIds, array $subjectIds, string $from, string $today, iterable $slots = []): array
    {
        // 1. Har juftlikda ishlov berilgan (baho yoki NB olgan) distinct talabalar
        $processedSets = [];
        $started = microtime(true);
        $gradeRows = 0;

        // "Hisobga olingan" shartini SQL bajaradi: jadvalda 17 mln+ qator bor va
        // hammasini PHP ga tortib olish hisobotning asosiy sekinligi edi. Endi
        // baza faqat shartni qanoatlantirgan qatorlarni qaytaradi — mantiq
        // effectiveGrade() bilan bir xil (ProcessedGradeCondition da tasvirlangan).
        //
        // students bilan JOIN qilinmaydi: JOIN bo'lsa MySQL so'rovni talabalardan
        // boshlab, har birining BARCHA yillardagi baholarini o'qib chiqardi
        // (~70 s). Baholar faqat (subject_id, lesson_date) indeksi bo'yicha
        // olinadi, talabaning guruhi esa PHP da shu ro'yxatdan topiladi.
        $groupsOf = [];   // hemis_id => [group_id, ...]
        DB::table('students')
            ->whereIn('group_id', $groupIds)
            ->select('hemis_id', 'group_id')
            ->get()
            ->each(function ($st) use (&$groupsOf) {
                $groupsOf[(string) $st->hemis_id][(string) $st->group_id] = true;
            });

        DB::table('student_grades as sg')
            ->whereIn('sg.subject_id', $subjectIds)
            ->whereNull('sg.deleted_at')
            ->whereNotNull('sg.lesson_date')
            ->whereNotIn('sg.training_type_code', [99, 100, 101, 102, 103])
            ->where('sg.lesson_date', '>=', $from.' 00:00:00')
            ->where('sg.lesson_date', '<', $today.' 00:00:00')
            ->whereRaw(self::PROCESSED_SQL)
            ->select('sg.subject_id', 'sg.semester_code', 'sg.lesson_pair_code', 'sg.student_hemis_id', DB::raw('DATE(sg.lesson_date) as lesson_day'))
            // Oqim bilan o'qiymiz: bo'laklash har safar "id > oxirgi" shartini
            // qo'shib qayta saralar edi; cursor() bitta so'rov bilan kifoya
            // qiladi va qatorlarni birma-bir beradi (xotira o'smaydi).
            ->cursor()
            ->each(function ($row) use (&$processedSets, &$gradeRows, $groupsOf) {
                $groups = $groupsOf[(string) $row->student_hemis_id] ?? null;
                if ($groups === null) {
                    return;   // tanlangan guruhlarda bo'lmagan talaba
                }
                $gradeRows++;
                foreach ($groups as $groupId => $_) {
                    $pk = $this->pairKey($groupId, $row->subject_id, $row->semester_code, $row->lesson_day, $row->lesson_pair_code);
                    $processedSets[$pk][$row->student_hemis_id] = true;
                }
            });
        $this->lastTimings['grades'] = round(microtime(true) - $started, 2);
        $this->lastTimings['grade_rows'] = $gradeRows;

        // 2. Jadvaldagi juftliklar: bahosi umuman yo'qlari ham ro'yxatga kirsin.
        //    Aks holda ular $processedSets da bo'lmaydi va "nechta talabada baho
        //    yo'q" ustuni bo'sh qolardi, holbuki butun guruh baho kutayotgan bo'ladi.
        foreach ($slots as $slot) {
            $pk = $this->pairKey($slot->group_id, $slot->subject_id, $slot->semester_code, $slot->lesson_day, $slot->lesson_pair_code);
            $processedSets[$pk] ??= [];
        }

        // 3. Guruh+fan+semestr bo'yicha faol talabalar ro'yxati
        $started = microtime(true);
        [$bySubject, $byGroup] = $this->activeStudentTotals($groupIds, $subjectIds, $from, $today);
        $this->lastTimings['roster'] = round(microtime(true) - $started, 2);

        $marked = [];
        $missing = [];
        foreach ($processedSets as $pk => $set) {
            $parts = explode('|', $pk); // group|subject|semester|day|pair
            $roster = $bySubject[$parts[0].'|'.$parts[1].'|'.$parts[2]] ?? ($byGroup[$parts[0]] ?? []);
            if ($roster === []) {
                continue;
            }
            // Ro'yxatdan baho/NB olganlarni ayiramiz — qolgani baho qo'yilmaganlar
            $left = array_diff_key($roster, $set);
            if ($left === []) {
                $marked[$pk] = true; // barcha faol talaba baho/NB olgan
            } else {
                $missing[$pk] = $left;
            }
        }

        return ['marked' => $marked, 'missing' => $missing];
    }

    /**
     * Faqat to'liq baho/NB olgan juftliklar (barcha faol talaba): [pairKey => true].
     *
     * @return array<string, true>
     */
    public function markedPairs(array $groupIds, array $subjectIds, string $from, string $today): array
    {
        return $this->analyzePairs($groupIds, $subjectIds, $from, $today)['marked'];
    }

    /**
     * Guruh+fan+semestr bo'yicha faol talabalar RO'YXATI (son emas).
     * student_subjects (biriktirilgan faol talabalar), topilmasa guruh ro'yxati.
     *
     * Ro'yxat kerak, chunki bir kunning ikki juftligida turli talabalar baho
     * olmagan bo'lishi mumkin — kun bo'yicha noyob talabalarni sanash uchun
     * kimligini bilish shart (son bilan buni aniqlab bo'lmaydi).
     *
     * @return array{0: array<string,array<string,true>>, 1: array<string,array<string,true>>}
     *                                                           [0] => "group|subject|semester" => [hemis_id => true], [1] => "group" => [hemis_id => true]
     */
    private function activeStudentTotals(array $groupIds, array $subjectIds, ?string $from = null, ?string $to = null): array
    {
        // Ikkala ro'yxat ham oqim bilan o'qiladi: bo'laklash har bo'lakda
        // qayta saralashga majbur qilardi, cursor() esa bitta so'rov bilan
        // kifoya qiladi va qatorlarni birma-bir beradi.
        $bySubject = [];
        DB::table('student_subjects as ss')
            ->join('students as st', 'st.hemis_id', '=', 'ss.student_hemis_id')
            ->whereIn('st.group_id', $groupIds)
            ->whereIn('ss.subject_id', $subjectIds)
            ->where('st.student_status_code', 11)
            ->select('st.group_id', 'ss.subject_id', 'ss.semester_id', 'ss.student_hemis_id')
            ->tap(fn ($q) => StudentSubjectScope::apply($q, 'ss', $from, $to))
            ->cursor()
            ->each(function ($r) use (&$bySubject) {
                $bySubject[$r->group_id.'|'.$r->subject_id.'|'.$r->semester_id][(string) $r->student_hemis_id] = true;
            });

        $byGroup = [];
        DB::table('students')
            ->whereIn('group_id', $groupIds)
            ->where('student_status_code', 11)
            ->whereNotNull('hemis_id')
            ->select('group_id', 'hemis_id')
            ->cursor()
            ->each(function ($r) use (&$byGroup) {
                $byGroup[(string) $r->group_id][(string) $r->hemis_id] = true;
            });

        return [$bySubject, $byGroup];
    }

    /** Kun kaliti: guruh | fan | semestr | sana */
    public function key($groupId, $subjectId, $semesterCode, ?string $day): string
    {
        return $groupId.'|'.$subjectId.'|'.$semesterCode.'|'.$day;
    }

    /** Juftlik kaliti: kun kaliti | juftlik kodi */
    public function pairKey($groupId, $subjectId, $semesterCode, ?string $day, ?string $pair): string
    {
        return $this->key($groupId, $subjectId, $semesterCode, $day).'|'.$pair;
    }

    /**
     * Jurnaldagi $getEffectiveGrade bilan bir xil: katakda ko'rinadigan baho.
     * null — katak bo'sh (NB alohida, reason = absent orqali tekshiriladi).
     */
    private function effectiveGrade(object $row)
    {
        if ($row->grade !== null && (float) $row->grade < 60 && $row->retake_grade !== null) {
            return $row->retake_grade;
        }
        if ($row->status === 'pending' && $row->reason === 'low_grade' && $row->grade !== null) {
            return $row->grade;
        }
        if ($row->status === 'pending') {
            return null;
        }
        if ($row->reason === 'absent' && $row->grade === null) {
            return $row->retake_grade;
        }
        if ($row->status === 'closed' && $row->reason === 'teacher_victim' && $row->grade == 0 && $row->retake_grade === null) {
            return null;
        }
        if ($row->status === 'recorded' || $row->status === 'closed') {
            return $row->grade;
        }

        return $row->retake_grade;
    }
}
