<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditorium;
use App\Models\AuditoriumTeacher;
use App\Models\Department;
use App\Models\OqimSnapshot;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableGridSetting;
use App\Models\TimetableRule;
use App\Models\TimetableSubjectSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Dars jadvali tuzish (aSc Timetables uslubida).
 *
 * Oqim tuzilishi tasdiqlangan OqimSnapshot'dan, fanlar ishchi rejalardan
 * olinadi. Har fanning haftalik parasi hisoblanib "dars kartochkalari"
 * yaratiladi: ma'ruza — oqimga (oqimdagi barcha guruhchalarni band qiladi),
 * amaliy — har guruhchaga alohida. Kartochkalar panjaraga qo'lda joylanadi,
 * har joylashda guruh/o'qituvchi/auditoriya konfliktlari tekshiriladi.
 */
class TimetableController extends Controller
{
    public function index()
    {
        $boards = TimetableBoard::withCount('cards')->orderByDesc('id')->get();

        // O'quv yillari — ishchi rejalardan (plan_year boshi + kurs - 1)
        $years = DB::table('manual_curricula')
            ->where('type', 'ishchi')->whereNotNull('plan_year')->whereNotNull('level_code')
            ->get(['plan_year', 'level_code'])
            ->map(function ($c) {
                $start = (int) substr($c->plan_year, 0, 4);
                $course = (int) $c->level_code >= 11 ? (int) $c->level_code - 10 : (int) $c->level_code;
                if (!$start || $course < 1) {
                    return null;
                }
                $as = $start + $course - 1;
                return $as . '-' . ($as + 1);
            })->filter()->unique()->sortDesc()->values();

        $faculties = \App\Models\Department::where('structure_type_code', 11)
            ->where('active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.timetable.index', compact('boards', 'years', 'faculties'));
    }

    public function storeBoard(Request $request)
    {
        $data = $request->validate([
            'academic_year'   => 'required|string|max:20',
            'semester_parity' => 'required|in:kuzgi,bahorgi',
            'kind'            => 'required|in:plan,real',
            'faculty_id'      => 'nullable|integer',
            'days'            => 'required|integer|min:1|max:7',
            'pairs_per_day'   => 'required|integer|min:1|max:10',
            'weeks'           => 'required|integer|min:1|max:30',
        ]);

        $facName = $data['faculty_id']
            ? optional(\App\Models\Department::find($data['faculty_id']))->name
            : null;

        $board = TimetableBoard::create([
            'name'            => $data['academic_year'] . ' · ' . ($data['semester_parity'] === 'kuzgi' ? 'Kuzgi' : 'Bahorgi')
                                 . ' · ' . ($data['kind'] === 'plan' ? 'Reja' : 'Real')
                                 . ($facName ? ' · ' . $facName : ' · Barcha fakultetlar'),
            'academic_year'   => $data['academic_year'],
            'semester_parity' => $data['semester_parity'],
            'kind'            => $data['kind'],
            'faculty_id'      => $data['faculty_id'] ?? null,
            'faculty_name'    => $facName,
            'days'            => $data['days'],
            'pairs_per_day'   => $data['pairs_per_day'],
            'weeks'           => $data['weeks'],
            // Umumiy sozlamalar sukut qiymatlari
            'institution_name' => $facName,
            'bell_schedule'    => TimetableBoard::defaultBellSchedule((int) $data['pairs_per_day']),
            'day_names'        => array_slice(TimetableBoard::DEFAULT_DAY_NAMES, 0, (int) $data['days']),
            'settings'         => ['days_off' => ['Yakshanba'], 'allow_zero' => false, 'show_day_number' => false],
            'created_by'      => Auth::id(),
        ]);

        return response()->json(['ok' => true, 'board_id' => $board->id]);
    }

    public function destroyBoard(TimetableBoard $board)
    {
        $board->delete();
        return response()->json(['ok' => true]);
    }

    // ── Fan nomi normallashtirish + kafedra xaritasi (fanlar moduli bilan bir xil mantiq) ──

    private function normSubject(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(["'", '’', 'ʻ', 'ʼ', '`', '´'], '', $s);
        $s = preg_replace('/[.,;:()\-\–\/]/u', ' ', $s);
        $s = preg_replace('/\b\d+([.,]\d+)?\b/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    private function kafedraFor(array $overrides, array $kafMap, string $subject): ?string
    {
        $k = $this->normSubject($subject);
        return ($overrides[$k] ?? null) ?: ($kafMap[$k] ?? null);
    }

    /** Fan → kafedra xaritasi: [$kafMap, $overrides] (assembleRows va subjects uchun umumiy). */
    private function buildKafedraMap(): array
    {
        $kafRows = DB::table('curriculum_subjects')
            ->whereNotNull('department_name')->where('department_name', '!=', '')
            ->selectRaw('subject_name, department_name, COUNT(*) as c')
            ->groupBy('subject_name', 'department_name')->get();
        $acc = [];
        foreach ($kafRows as $r) {
            $k = $this->normSubject($r->subject_name);
            if ($k !== '') {
                $acc[$k][$r->department_name] = ($acc[$k][$r->department_name] ?? 0) + (int) $r->c;
            }
        }
        $kafMap = [];
        foreach ($acc as $k => $deps) {
            arsort($deps);
            $kafMap[$k] = array_key_first($deps);
        }
        $overrides = DB::table('subject_kafedra_overrides')
            ->where('kafedra_name', '!=', '')
            ->pluck('kafedra_name', 'norm_name')->all();

        return [$kafMap, $overrides];
    }

    private function specKey(?string $name): string
    {
        return preg_replace('/[^a-z0-9]/u', '', mb_strtolower(trim((string) $name)));
    }

    /** Tasdiqlangan oqim snapshotlari (fakultet kontekstida dedup — eng so'nggisi). */
    private function boardSnapshots(TimetableBoard $board): array
    {
        $q = OqimSnapshot::where('status', 'approved');
        if ($board->kind === 'plan') {
            $q->where('context->projection', 1)
              ->where('context->academic_year', $board->academic_year);
        } else {
            $q->where(function ($w) {
                $w->whereNull('context->projection')->orWhere('context->projection', 0);
            });
        }
        if ($board->faculty_id) {
            $q->where('context->faculty', (string) $board->faculty_id);
        }
        $byFaculty = [];
        foreach ($q->get() as $snap) {
            $fk = (string) ($snap->context['faculty'] ?? '');
            if (!isset($byFaculty[$fk]) || $snap->approved_at > $byFaculty[$fk]->approved_at) {
                $byFaculty[$fk] = $snap;
            }
        }
        if (count($byFaculty) > 1) {
            unset($byFaculty['']);
        }
        return $byFaculty;
    }

    /**
     * Guruh bandligi qamrovi kaliti: fakultet + yo'nalish + kurs.
     *
     * Guruh NOMI fakultetlar bo'ylab takrorlanishi mumkin — masalan 1-kurs
     * guruhlari avtomatik nomlanadi ("1K-01a (o'z)") va ayni nom ham 1-son, ham
     * 2-son davolashda uchraydi (2-kursdan boshlab nomlar "d1/…", "d2/…" prefiksli
     * bo'lgani uchun noyob). Shuning uchun guruh bandligi faqat nom bo'yicha emas,
     * shu qamrov ichida tekshiriladi — aks holda bir fakultetning darsi ikkinchisining
     * guruhini "band" qilib qo'yadi.
     */
    private function groupScopeKey(TimetableCard $c): string
    {
        return ($c->faculty_name ?? '') . '|' . $c->specialty_name . '|' . (int) $c->course;
    }

    /**
     * Fan ma'ruza soatlari xaritasi: "specKey|course|normSubject" => ma'ruza soati.
     * Ma'ruza necha hafta davom etishini hisoblash uchun (1 para = 2 soat = 1 hafta).
     */
    private function lectureHoursMap(TimetableBoard $board): array
    {
        $start = (int) substr($board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;
        $rows = DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + GREATEST(CAST(mc.level_code AS UNSIGNED) - 10, 0) - 1) = ?", [$start])
            ->groupBy('mc.specialty_name', 'mc.level_code', 's.subject_name')
            ->selectRaw('mc.specialty_name, mc.level_code, s.subject_name, MAX(s.lecture) as lecture')
            ->get();
        $map = [];
        foreach ($rows as $r) {
            $course = (int) $r->level_code >= 11 ? (int) $r->level_code - 10 : (int) $r->level_code;
            $map[$this->specKey($r->specialty_name) . '|' . $course . '|' . $this->normSubject((string) $r->subject_name)] = (float) $r->lecture;
        }
        return $map;
    }

    /**
     * Ma'ruza necha hafta davom etadi. Ma'ruza oqimga haftada BIR marta o'tiladi
     * (bo'linmaydi), shuning uchun hafta soni = jami paralar = soat / 2.
     * Masalan 8 soat ma'ruza = 4 para = 4 hafta; 30 soat = 15 hafta.
     */
    private function lectureWeeks(float $hours): int
    {
        if ($hours <= 0) {
            return 0;
        }
        return max(1, (int) round($hours / 2));   // 1 para = 2 soat = 1 hafta
    }

    /**
     * "Rejada fani bor, lekin guruh proyeksiyasi yo'q" yo'nalish+kurslar.
     * Karta faqat reja fani VA tasdiqlangan guruh snapshoti birga bo'lganda
     * yaratiladi; shuning uchun rejada bor, lekin snapshotda guruhi yo'q bo'lgan
     * yo'nalish+kurslar doskada umuman chiqmaydi (masalan yangi qabul 1-kurs
     * proyeksiyasi tasdiqlanmagan). Diagnostika shu holatlarni ko'rsatadi.
     *
     * @return array<int,array{specialty_name:string,course:int}>
     */
    private function missingGroupSpecs(TimetableBoard $board): array
    {
        // 1) Ishchi rejada shu doska yili + semestr paritetiga mos fani bor
        //    yo'nalish+kurslar (subjects() bilan bir xil filtr).
        $start = (int) substr($board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;
        $curr = DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + GREATEST(CAST(mc.level_code AS UNSIGNED) - 10, 0) - 1) = ?", [$start])
            ->groupBy('mc.specialty_name', 'mc.level_code')
            ->selectRaw('mc.specialty_name, mc.level_code')
            ->get();
        $currSet = [];   // "specKey|course" => ko'rinadigan yo'nalish nomi
        foreach ($curr as $r) {
            $course = (int) $r->level_code >= 11 ? (int) $r->level_code - 10 : (int) $r->level_code;
            $currSet[$this->specKey($r->specialty_name) . '|' . $course] = $r->specialty_name;
        }

        // 2) Guruh snapshotida (kamida bitta guruhli oqim) bor yo'nalish+kurslar.
        $covered = [];
        foreach ($this->boardSnapshots($board) as $snap) {
            foreach ($snap->data ?? [] as $bl) {
                $specName = trim(explode('|', $bl['merge_key'] ?? '')[1] ?? '') ?: ($bl['title'] ?? '');
                $sk = $this->specKey($specName);
                foreach ($bl['courses'] ?? [] as $co) {
                    $lvl = (int) ($co['level_code'] ?? 0);
                    $course = $lvl >= 11 ? $lvl - 10 : $lvl;
                    foreach ($co['oqims'] ?? [] as $oq) {
                        foreach ($oq['rows'] ?? [] as $rw) {
                            if (trim((string) ($rw['name'] ?? '')) !== '') {
                                $covered[$sk . '|' . $course] = true;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        // 3) Rejada bor, lekin guruhi yo'q — farqi.
        $out = [];
        foreach ($currSet as $key => $specName) {
            if (empty($covered[$key])) {
                $course = (int) substr($key, strrpos($key, '|') + 1);
                $out[] = ['specialty_name' => $specName, 'course' => $course];
            }
        }
        usort($out, fn($a, $b) => [$a['specialty_name'], $a['course']] <=> [$b['specialty_name'], $b['course']]);
        return $out;
    }

    /**
     * Kartochka qatorlarini yig'ish. $filterSpecKey/$filterCourse berilsa — faqat
     * o'sha yo'nalish+kurs. Haftalik para har yo'nalish+kurs uchun alohida
     * sozlanadigan hafta soniga qarab hisoblanadi. $specsFound — topilgan
     * (yo'nalish, kurs) larni yig'adi (grid sozlamalarini yaratish uchun).
     * Snapshot topilmasa null qaytaradi.
     */
    private function assembleRows(
        TimetableBoard $board,
        ?string $filterSpecKey,
        ?int $filterCourse,
        array &$specsFound,
        ?string $filterFaculty = null
    ): ?array
    {
        $byFaculty = $this->boardSnapshots($board);
        if (empty($byFaculty)) {
            return null;
        }

        // Fanlar: o'quv yili + semestr juft/toqligi bo'yicha
        $start = (int) substr($board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;
        $subjects = DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + GREATEST(CAST(mc.level_code AS UNSIGNED) - 10, 0) - 1) = ?", [$start])
            ->groupBy('mc.specialty_name', 'mc.level_code', 's.subject_name')
            ->selectRaw("mc.specialty_name, mc.level_code, s.subject_name,
                MAX(s.lecture) as lecture,
                MAX(s.practice) as practice, MAX(s.laboratory) as laboratory, MAX(s.seminar) as seminar")
            ->get();

        // Kafedra xaritasi
        [$kafMap, $overrides] = $this->buildKafedraMap();

        // Fanlarni yo'nalish+kurs bo'yicha guruhlash.
        //
        // MUHIM: yuqoridagi SQL guruhlash XOM mc.specialty_name va mc.level_code
        // bo'yicha ketadi, bu yerda esa ular normallashtiriladi (specKey) va
        // level_code 11 ham, 1 ham -> 1-kurs bo'ladi. Shu sababli bir xil fan
        // bir necha marta tushib qolishi mumkin edi — natijada bitta guruhga
        // o'sha fanning karta to'plami bir necha marta yaratilib, haftalik yuk
        // bir necha barobar oshib ketardi. Endi (yo'nalish|kurs|fan) bo'yicha
        // birlashtiramiz: soatlarning eng kattasi olinadi.
        $subjBySpec = [];
        $seenSubj = [];   // "specKey|course|normSubject" => $subjBySpec dagi indeks
        foreach ($subjects as $s) {
            $course = (int) $s->level_code >= 11 ? (int) $s->level_code - 10 : (int) $s->level_code;
            $sk = $this->specKey($s->specialty_name);
            $key = $sk . '|' . $course . '|' . $this->normSubject((string) $s->subject_name);

            if (isset($seenSubj[$key])) {
                // Takror fan — soatlarni birlashtiramiz (eng katta qiymat)
                $prev = $subjBySpec[$sk][$course][$seenSubj[$key]];
                foreach (['lecture', 'practice', 'laboratory', 'seminar'] as $f) {
                    $prev->$f = max((float) $prev->$f, (float) $s->$f);
                }
                continue;
            }
            $subjBySpec[$sk][$course][] = $s;
            $seenSubj[$key] = count($subjBySpec[$sk][$course]) - 1;
        }

        // Har yo'nalish+kurs uchun hafta soni (alohida sozlama yoki doska sukut qiymati)
        $gset = TimetableGridSetting::where('board_id', $board->id)->get()
            ->mapWithKeys(fn($g) => [
                ($g->faculty_name ?? '') . '|' . $this->specKey($g->specialty_name) . '|' . $g->course => (int) $g->weeks,
            ])
            ->all();

        // Fakultet id → nomi (snapshot fakultet kontekstidan kartaga yozish uchun)
        $facMap = \App\Models\Department::where('structure_type_code', 11)
            ->pluck('name', 'id')->all();

        $now = now();
        $rows = [];
        // Takrorlanishdan himoya: bir guruhga bir fan bo'yicha karta to'plami FAQAT
        // BIR MARTA yaratiladi. Snapshotda bir guruh bir necha blok/oqimda uchrashi
        // mumkin (turli fakultet konteksti, bir yo'nalishning bir necha bloki) —
        // aks holda o'sha fanning kartalari necha marta uchrasa shuncha ko'payib,
        // haftalik yuk bir necha barobar oshib ketardi.
        $madePractice = [];   // "spec|kurs|guruh|fan" => true
        $madeLecture  = [];   // "fakultet|spec|kurs|oqim|fan" => $rows indeksi
        $lectureGroupStudents = []; // shu mantiqiy oqimdagi noyob guruh => talaba soni
        // Paralar soni endi weeklyPlan() da hisoblanadi (haftalik yuk chegarasi bilan)

        foreach ($byFaculty as $fk => $snap) {
            $facName = $facMap[(int) $fk] ?? ($facMap[$fk] ?? null);
            foreach ($snap->data ?? [] as $bl) {
                $specName = trim(explode('|', $bl['merge_key'] ?? '')[1] ?? '') ?: ($bl['title'] ?? '');
                $sk = $this->specKey($specName);
                // HAQIQIY fakultet — blokning department_name'i (snapshot faculty
                // konteksti "Barcha fakultetlar"da bo'sh bo'lgani uchun undan olamiz).
                $blockFac = trim((string) ($bl['department_name'] ?? '')) ?: $facName;
                foreach ($bl['courses'] ?? [] as $co) {
                    $lvl = (int) ($co['level_code'] ?? 0);
                    $course = $lvl >= 11 ? $lvl - 10 : $lvl;
                    if ($filterSpecKey !== null && ($sk !== $filterSpecKey || $course !== $filterCourse
                        || ($filterFaculty !== null && $blockFac !== $filterFaculty))) {
                        continue;
                    }
                    $subs = $subjBySpec[$sk][$course] ?? null;
                    if (!$subs) {
                        continue;
                    }
                    $scopeKey = $blockFac . '|' . $sk . '|' . $course;
                    $specsFound[$scopeKey] = [
                        'faculty' => $blockFac,
                        'name' => $specName,
                        'course' => $course,
                    ];
                    $weeks = $gset[$scopeKey]
                        ?? $gset['|' . $sk . '|' . $course]
                        ?? (int) $board->weeks;
                    foreach ($co['oqims'] ?? [] as $oq) {
                        $groupNames = array_values(array_filter(array_map(
                            fn($r) => trim((string) ($r['name'] ?? '')), $oq['rows'] ?? []
                        )));
                        if (empty($groupNames)) {
                            continue;
                        }
                        $oqTotal = (int) ($oq['total'] ?? 0);
                        foreach ($subs as $s) {
                            $kaf = $this->kafedraFor($overrides, $kafMap, $s->subject_name);
                            $prcHours = (float) $s->practice + (float) $s->laboratory + (float) $s->seminar;
                            // Haftalik yuk taqsimoti: jami soat / hafta = haftalik yuk.
                            // Ma'ruza 2 soat egallagani uchun ma'ruzali haftada amaliy
                            // kamayadi — shuning uchun "qo'shimcha" amaliy kartalar faqat
                            // ma'ruzasiz haftalarda o'tiladi (kartada weeks bilan belgilanadi).
                            $wp = $this->weeklyPlan((float) $s->lecture, $prcHours, $weeks);

                            // Ma'ruza — bitta oqimga BITTA karta; necha hafta o'tilishi
                            // ma'ruza soatidan (1 para = 2 soat = 1 hafta).
                            $subjKey = $this->normSubject((string) $s->subject_name);
                            // Bir xil ko'rinadigan oqim bir nechta snapshot blokida kelishi mumkin.
                            // Ma'ruza bunday bo'laklarga ajralmasin: fakultet+yo'nalish+kurs+
                            // oqim+til+fan bo'yicha bitta karta, guruhlar esa birlashtiriladi.
                            $flowLabel = trim((string) ($oq['label'] ?? ''));
                            // UI ham oqimlarni til yoki snapshot bo'lagi bo'yicha ajratmaydi.
                            // Shu sababli ular ma'ruza kalitida ham alohida bo'lmasligi kerak.
                            $lecKey = ($blockFac ?? '') . '|' . $sk . '|' . $course . '|'
                                . $flowLabel . '|' . $subjKey;
                            if ((float) $s->lecture > 0) {
                                foreach ($oq['rows'] ?? [] as $lectureGroup) {
                                    $lectureGroupName = trim((string) ($lectureGroup['name'] ?? ''));
                                    if ($lectureGroupName === '') {
                                        continue;
                                    }
                                    $lectureGroupStudents[$lecKey][$lectureGroupName] = max(
                                        (int) ($lectureGroupStudents[$lecKey][$lectureGroupName] ?? 0),
                                        (int) ($lectureGroup['count'] ?? 0)
                                    );
                                }
                                $mergedLectureGroups = array_keys($lectureGroupStudents[$lecKey] ?? []);
                                $mergedLectureStudents = array_sum($lectureGroupStudents[$lecKey] ?? []);

                                if (!isset($madeLecture[$lecKey])) {
                                    $madeLecture[$lecKey] = count($rows);
                                    $rows[] = [
                                        'board_id' => $board->id,
                                        'specialty_name' => $specName, 'course' => $course, 'faculty_name' => $blockFac,
                                        'oqim_label' => $oq['label'] ?? null, 'lang' => $oq['lang'] ?? 'uz',
                                        'training_type' => 'lecture',
                                        'group_name' => null, 'group_names' => json_encode($mergedLectureGroups ?: $groupNames),
                                        'subject_name' => $s->subject_name, 'kafedra_name' => $kaf,
                                        'students' => $mergedLectureStudents > 0 ? $mergedLectureStudents : $oqTotal,
                                        // Ma'ruza — 2 soat (1 para). MUHIM: ustunlar to'plami
                                        // amaliy qatorlar bilan bir xil bo'lishi shart, aks holda
                                        // ommaviy insert() ustunlarni birinchi qatordan olib,
                                        // qolganlarida mos kelmay SQL xatosi beradi.
                                        'len_half' => 2,
                                        'weeks' => max(1, (int) $wp['lecture_weeks']),
                                        'created_at' => $now, 'updated_at' => $now,
                                    ];
                                } else {
                                    $lectureRowIndex = $madeLecture[$lecKey];
                                    $rows[$lectureRowIndex]['group_names'] = json_encode($mergedLectureGroups);
                                    $rows[$lectureRowIndex]['students'] = $mergedLectureStudents > 0
                                        ? $mergedLectureStudents
                                        : max((int) $rows[$lectureRowIndex]['students'], $oqTotal);
                                }
                            }

                            // Amaliy darslar SOAT bo'yicha taqsimlanadi (len_half = soat):
                            //  - har hafta o'tiladigan soat        -> weeks ta haftada
                            //  - ma'ruzasiz haftalardagi qo'shimcha -> plain_weeks ta haftada
                            //  - qoldiq +1 soat                     -> remainder_weeks ta haftada
                            // Shu bilan reja soati qoldiqsiz to'ldiriladi.
                            $cardPlan = [];   // [len_half, necha hafta]
                            foreach ($this->splitPracticeHours((int) $wp['practice_hours_base']) as $lh) {
                                $cardPlan[] = [$lh, $weeks];
                            }
                            if ((int) $wp['practice_extra_weeks'] > 0) {
                                foreach ($this->splitPracticeHours((int) $wp['practice_hours_extra']) as $lh) {
                                    $cardPlan[] = [$lh, (int) $wp['practice_extra_weeks']];
                                }
                            }
                            if ((int) $wp['practice_remainder_weeks'] > 0) {
                                $cardPlan[] = [1, (int) $wp['practice_remainder_weeks']];
                            }

                            if ($cardPlan) {
                                foreach ($oq['rows'] ?? [] as $gr) {
                                    $gn = trim((string) ($gr['name'] ?? ''));
                                    if ($gn === '') {
                                        continue;
                                    }
                                    // Bu guruhga shu fan bo'yicha kartalar allaqachon yaratilganmi?
                                    $prcKey = $sk . '|' . $course . '|' . $gn . '|' . $subjKey;
                                    if (isset($madePractice[$prcKey])) {
                                        continue;
                                    }
                                    $madePractice[$prcKey] = true;
                                    foreach ($cardPlan as [$lenHalf, $cardWeeks]) {
                                        $rows[] = [
                                            'board_id' => $board->id,
                                            'specialty_name' => $specName, 'course' => $course, 'faculty_name' => $blockFac,
                                            'oqim_label' => $oq['label'] ?? null, 'lang' => $oq['lang'] ?? 'uz',
                                            'training_type' => 'practice',
                                            'group_name' => $gn, 'group_names' => null,
                                            'subject_name' => $s->subject_name, 'kafedra_name' => $kaf,
                                            'students' => (int) ($gr['count'] ?? 0),
                                            'len_half' => $lenHalf,
                                            'weeks' => max(1, $cardWeeks),
                                            'created_at' => $now, 'updated_at' => $now,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Migratsiya kechiksa — timetable_cards da hali yo'q ustunlarni insert
     * qatorlaridan olib tashlash. Shu bilan birga BARCHA qatorlarni bir xil
     * ustun to'plamiga keltiradi: ommaviy insert() ustunlarni birinchi qatordan
     * oladi, qolgan qatorlarda kalitlar farq qilsa SQL xatosi beradi.
     */
    private function stripUnsupportedColumns(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }
        $drop = [];
        foreach (['faculty_name', 'weeks', 'len_half'] as $col) {
            if (!Schema::hasColumn('timetable_cards', $col)) {
                $drop[] = $col;
            }
        }

        // Barcha qatorlarda uchraydigan ustunlar birlashmasi (tartibi barqaror)
        $cols = [];
        foreach ($rows as $r) {
            foreach ($r as $k => $_) {
                if (!isset($cols[$k])) {
                    $cols[$k] = true;
                }
            }
        }
        foreach ($drop as $col) {
            unset($cols[$col]);
        }
        $cols = array_keys($cols);

        return array_map(function ($r) use ($cols) {
            $out = [];
            foreach ($cols as $c) {
                $out[$c] = $r[$c] ?? null;   // yetishmagan ustun — NULL
            }
            return $out;
        }, $rows);
    }

    /** Topilgan yo'nalish+kurslar uchun grid sozlamasini (bo'lmasa) doska sukutidan yaratish. */
    private function ensureGridSettings(TimetableBoard $board, array $specsFound): void
    {
        foreach ($specsFound as $info) {
            TimetableGridSetting::firstOrCreate(
                ['board_id' => $board->id, 'faculty_name' => $info['faculty'] ?? null,
                 'specialty_name' => $info['name'], 'course' => $info['course']],
                ['days' => $board->days, 'pairs_per_day' => $board->pairs_per_day, 'weeks' => $board->weeks]
            );
        }
    }

    /**
     * Kartochkalarni yaratish: tasdiqlangan oqim + ishchi reja fanlari.
     * Mavjud kartochkalar o'chirilib qaytadan yaratiladi (joylashuvlar yo'qoladi).
     */
    public function generateCards(TimetableBoard $board)
    {
        $specsFound = [];
        $rows = $this->assembleRows($board, null, null, $specsFound);
        if ($rows === null) {
            return response()->json(['error' => "Tasdiqlangan oqim topilmadi. Avval Oqim sahifasida "
                . ($board->kind === 'plan' ? "kelasi yil (reja) oqimini" : "joriy oqimni") . " tasdiqlang."], 422);
        }
        $rows = $this->stripUnsupportedColumns($rows);

        DB::transaction(function () use ($board, $rows, $specsFound) {
            TimetableCard::where('board_id', $board->id)->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                TimetableCard::insert($chunk);
            }
            $this->ensureGridSettings($board, $specsFound);
        });

        // Qaysi kartochka qaysi HAFTADA o'tilishini belgilaymiz (hafta bo'yicha
        // istisnolar). Ma'ruza va uni almashtiruvchi "qo'shimcha" amaliy karta
        // bir-birini to'ldiradi: ma'ruza bor haftada amaliy yo'q va aksincha.
        $this->assignCardWeeks($board);

        // Fakultet nomini to'ldiramiz (snapshot bloklaridagi department_name →
        // oqim guruhlari orqali). Generatsiya blockFac'ni yozadi; bu esa
        // eski/qo'lda holatlar uchun himoya (faqat NULL qatorlar).
        $this->backfillFacultyNames($board);

        return response()->json(['ok' => true, 'created' => count($rows)]);
    }

    /**
     * Kartochkalarni QAYTA YARATMASDAN fan nomlarini ishchi rejadagi joriy
     * nomga yangilash (joylashuvlar saqlanadi). Moslashtirish yo'nalish+kurs+
     * normallashtirilgan fan nomi bo'yicha — masalan "Biokimyo 1,2" va
     * "Biokimyo" bir xil normaga tushadi, shuning uchun nom yangilanadi.
     */
    public function refreshSubjectNames(TimetableBoard $board)
    {
        $start = (int) substr($board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;

        // Eng so'nggi tahrirlangan avval — bir xil normadagi (mas. "Biokimyo" va
        // "Biokimyo 1,2") dublikatlarda foydalanuvchining oxirgi tahriri (yangi
        // nom) ustuvor bo'lsin.
        $subjects = DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + GREATEST(CAST(mc.level_code AS UNSIGNED) - 10, 0) - 1) = ?", [$start])
            ->orderByDesc('s.updated_at')
            ->orderByDesc('s.id')
            ->get(['mc.specialty_name', 'mc.level_code', 's.subject_name']);

        // Kalit: specKey|kurs|normFan => joriy ko'rinadigan nom (birinchi = eng yangi)
        $map = [];
        foreach ($subjects as $s) {
            $course = (int) $s->level_code >= 11 ? (int) $s->level_code - 10 : (int) $s->level_code;
            $key = $this->specKey($s->specialty_name) . '|' . $course . '|' . $this->normSubject((string) $s->subject_name);
            if (!isset($map[$key])) {
                $map[$key] = $s->subject_name;
            }
        }

        // Kafedra xaritasi ham yangilanadi (nom o'zgarsa kafedra ham to'g'rilansin)
        [$kafMap, $overrides] = $this->buildKafedraMap();

        $updated = 0;
        $touched = [];
        foreach (TimetableCard::where('board_id', $board->id)->get() as $c) {
            $key = $this->specKey($c->specialty_name) . '|' . (int) $c->course . '|' . $this->normSubject((string) $c->subject_name);
            $new = $map[$key] ?? null;
            if ($new === null || $new === $c->subject_name) {
                continue;
            }
            $c->subject_name = $new;
            $c->kafedra_name = $this->kafedraFor($overrides, $kafMap, $new) ?: $c->kafedra_name;
            $touched[] = $c;
            $updated++;
        }

        DB::transaction(function () use ($touched) {
            foreach ($touched as $c) {
                $c->save();
            }
        });

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    /**
     * Dars kartochkalariga fakultet nomini SNAPSHOT ma'lumotidan to'ldirish.
     *
     * Snapshot bloki `department_name` = HAQIQIY fakultet; blok ichidagi har
     * oqimning guruhlari o'sha fakultetга tegishli. Shundan guruh → fakultet
     * xaritasini quramiz:
     *  - amaliy karta: `group_name` bo'yicha;
     *  - ma'ruza karta (group_name = NULL): `group_names` ichidagi birinchi
     *    tanilgan guruh bo'yicha.
     * Faqat NULL qiymatlar yangilanadi. Yo'nalish (specialty_name) bir nechta
     * fakultetга umumiy bo'lganda ham guruh orqali to'g'ri ajraladi.
     */
    private function backfillFacultyNames(TimetableBoard $board): void
    {
        if (!Schema::hasColumn('timetable_cards', 'faculty_name')) {
            return;
        }
        try {
            // 1) Guruh nomi → fakultet (snapshot bloklaridan)
            $groupFac = [];
            foreach ($this->boardSnapshots($board) as $snap) {
                foreach ($snap->data ?? [] as $bl) {
                    $fac = trim((string) ($bl['department_name'] ?? ''));
                    if ($fac === '') {
                        continue;
                    }
                    foreach ($bl['courses'] ?? [] as $co) {
                        foreach ($co['oqims'] ?? [] as $oq) {
                            foreach ($oq['rows'] ?? [] as $gr) {
                                $gn = trim((string) ($gr['name'] ?? ''));
                                if ($gn !== '') {
                                    $groupFac[$gn] = $fac;
                                }
                            }
                        }
                    }
                }
            }
            if (empty($groupFac)) {
                return;
            }

            // 2) Amaliy kartalar — guruh nomi bo'yicha
            foreach ($groupFac as $gn => $fac) {
                DB::table('timetable_cards')->where('board_id', $board->id)
                    ->where('group_name', $gn)->whereNull('faculty_name')
                    ->update(['faculty_name' => $fac]);
            }

            // 3) Ma'ruza kartalar (group_name = NULL) — group_names ichidagi
            //    birinchi tanilgan guruh bo'yicha
            $lecs = DB::table('timetable_cards')->where('board_id', $board->id)
                ->whereNull('faculty_name')->whereNotNull('group_names')
                ->select('id', 'group_names')->get();
            $idsByFac = [];
            foreach ($lecs as $c) {
                $names = json_decode($c->group_names, true) ?: [];
                foreach ($names as $gn) {
                    $gn = trim((string) $gn);
                    if (isset($groupFac[$gn])) {
                        $idsByFac[$groupFac[$gn]][] = $c->id;
                        break;
                    }
                }
            }
            foreach ($idsByFac as $fac => $ids) {
                foreach (array_chunk($ids, 500) as $chunk) {
                    DB::table('timetable_cards')->whereIn('id', $chunk)
                        ->update(['faculty_name' => $fac]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('backfillFacultyNames: ' . $e->getMessage());
        }
    }

    /** Yo'nalish+kurs uchun panjara sozlamasini saqlash (kun/para/hafta). */
    public function saveGrid(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'specialty_name' => 'required|string|max:255',
            'faculty_name'   => 'nullable|string|max:255',
            'course'         => 'required|integer|min:1|max:7',
            'days'           => 'required|integer|min:1|max:7',
            'pairs_per_day'  => 'required|integer|min:1|max:10',
            'weeks'          => 'required|integer|min:1|max:30',
        ]);

        $gs = TimetableGridSetting::firstOrNew([
            'board_id' => $board->id,
            'faculty_name' => $data['faculty_name'] ?? null,
            'specialty_name' => $data['specialty_name'],
            'course' => $data['course'],
        ]);
        $weeksChanged = $gs->exists && (int) $gs->weeks !== (int) $data['weeks'];
        $gs->fill([
            'days' => $data['days'],
            'pairs_per_day' => $data['pairs_per_day'],
            'weeks' => $data['weeks'],
        ])->save();

        // Panjaradan tashqarida qolgan joylashuvlarni bo'shatamiz. Yarim-slot (pair)
        // soni doska qo'ng'iroq jadvalidan olinadi, shuning uchun kunlar chegarasi bilan
        // birga o'sha son bo'yicha tozalanadi (yo'nalish pairs_per_day emas).
        $boardPairs = $board->pairCount();
        TimetableCard::where('board_id', $board->id)
            ->where('specialty_name', $data['specialty_name'])
            ->where('course', $data['course'])
            ->when(!empty($data['faculty_name']), fn($q) => $q->where('faculty_name', $data['faculty_name']))
            ->where(function ($q) use ($data, $boardPairs) {
                $q->where('day', '>', $data['days'])->orWhere('pair', '>', $boardPairs);
            })
            ->update(['day' => null, 'pair' => null]);

        // Hafta soni o'zgargan bo'lsa — shu yo'nalishning kartochkalari qayta yaratiladi
        if ($weeksChanged) {
            $sf = [];
            $rows = $this->stripUnsupportedColumns($this->assembleRows($board, $this->specKey($data['specialty_name']), (int) $data['course'], $sf, $data['faculty_name'] ?? null) ?? []);
            DB::transaction(function () use ($board, $data, $rows) {
                TimetableCard::where('board_id', $board->id)
                    ->where('specialty_name', $data['specialty_name'])
                    ->where('course', $data['course'])
                    ->when(!empty($data['faculty_name']), fn($q) => $q->where('faculty_name', $data['faculty_name']))
                    ->delete();
                foreach (array_chunk($rows ?? [], 500) as $chunk) {
                    TimetableCard::insert($chunk);
                }
            });
        }

        return response()->json(['ok' => true, 'regenerated' => $weeksChanged]);
    }

    /**
     * Avtomatik (optimal) joylashtirish — aSc Timetables uslubidagi generator.
     *
     * Qattiq cheklovlar (hech qachon buzilmaydi):
     *   - guruh bir vaqtda ikki darsda bo'lmaydi (yo'nalish+kurs ichida);
     *   - o'qituvchi biriktirilgan bo'lsa — bir vaqtda bitta darsda (butun doska);
     *   - auditoriya biriktirilsa — sig'imi yetarli va bir vaqtda bo'sh xona.
     * Yumshoq cheklovlar (jarima minimallashtiriladi):
     *   - guruhda "oyna" (bo'sh para) bo'lmasligi — kun ichida paralar zich;
     *   - bir fanni hafta bo'ylab teng taqsimlash (bir kunga to'planmasin);
     *   - kunlar bo'ylab yukni tekislash + ertalabki paralarga ustunlik.
     *
     * Ochko'z (greedy) + jarima baholash: har karta eng kam jarimali bo'sh
     * katakka qo'yiladi. Qo'lda joylashtirilgan kartalar (reset=0 bo'lsa)
     * qo'zg'atilmaydi — ular band katak sifatida hisobga olinadi.
     */
    public function autoPlace(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'specialty_name'    => 'nullable|string|max:255',
            'course'            => 'nullable|integer|min:1|max:7',
            // Ko'p tanlovli qamrov (dropdown checkboxlaridan) — fakultet/yo'nalish/kurs massivlari
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'reset'          => 'nullable|boolean',
            'assign_rooms'   => 'nullable|boolean',
            'lecture_rooms'  => 'nullable|boolean',
            'training_type'  => 'nullable|in:lecture,practice',
        ]);
        // Katta doskada (minglab kartochka) joylash uzoq davom etadi — PHP ning
        // sukut vaqt/xotira chegarasi tugab "Server Error" (500) qaytardi.
        // Bu admin amali uchun chegarani ko'taramiz.
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        // Qamrov to'plamlari: fakultet / yo'nalish / kurs (massiv yoki eski yakka param)
        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $inScope = function ($c) use ($facSet, $specSet, $courseSet) {
            if ($facSet !== null && !isset($facSet[(string) ($c->faculty_name ?? '')])) return false;
            if ($specSet !== null && !isset($specSet[(string) $c->specialty_name])) return false;
            if ($courseSet !== null && !isset($courseSet[(int) $c->course])) return false;
            return true;
        };
        $scopeType = $data['training_type'] ?? null;   // faqat ma'ruza yoki faqat amaliy
        $reset = (bool) ($data['reset'] ?? false);
        $assignRooms = (bool) ($data['assign_rooms'] ?? false);
        // Ma'ruza xonalari: ma'ruzalarga faqat ma'ruza tipidagi auditoriyalarni
        // to'qnashuvsiz biriktirish
        $lectureRooms = (bool) ($data['lecture_rooms'] ?? false);

        // Sozlamalar: bir fanning haftalik paralarini bir kunga / ketma-ket qo'yish
        $set = $board->settings ?? [];
        $sameDay = (bool) ($set['pair_same_day'] ?? false);
        $consecutive = (bool) ($set['pair_consecutive'] ?? false);
        // Auditoriya sig'imi toleransi (%) — oqim xona sig'imidan biroz katta
        // bo'lsa ham joylashtirishga ruxsat (mas. 120 o'rin — 125 oqim, 5%).
        // Katta farq (mas. 80 xona — 120 oqim) baribir rad etiladi.
        $roomTolPct = max(0, min(30, (int) ($set['room_tolerance_pct'] ?? 5)));
        $minVolFor = fn(TimetableCard $c) => (int) ceil((int) $c->students * (100 - $roomTolPct) / 100);

        // Reset — tanlangan qamrovdagi mavjud joylashuvlarni bo'shatamiz.
        // Qamrov: tanlangan fakultet/yo'nalish/kurs to'plamlari bo'yicha; hech
        // biri berilmasa — butun doska.
        if ($reset) {
            $q = TimetableCard::where('board_id', $board->id);
            $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
            if ($scopeType !== null) {
                $q->where('training_type', $scopeType);
            }
            $q->update(['day' => null, 'pair' => null, 'auditorium_code' => null, 'auditorium_name' => null]);
        }

        // Panjara o'lchamlari (yo'nalish+kurs bo'yicha)
        $gridSettings = TimetableGridSetting::where('board_id', $board->id)->get()
            ->keyBy(fn($g) => ($g->faculty_name ?? '') . '|' . $g->specialty_name . '|' . $g->course);
        // Yarim-slot (grid qatori) soni butun doska bo'yicha qo'ng'iroq jadvalidan
        // olinadi (bir "pair" = bir yarim-slot); yo'nalish bo'yicha faqat kun soni
        // (days) farq qilishi mumkin.
        $boardPairs = $board->pairCount();
        $dimsFor = function ($faculty, $spec, $course) use ($gridSettings, $board, $boardPairs) {
            $g = $gridSettings[($faculty ?? '') . '|' . $spec . '|' . $course]
                ?? $gridSettings['|' . $spec . '|' . $course]
                ?? null;
            return [(int) ($g->days ?? $board->days), $boardPairs];
        };
        // Semestr haftalari soni (yo'nalish+kurs bo'yicha) — almashinuvchi kartani
        // aniqlash uchun kerak (ma'ruzasiz haftalar = jami - ma'ruza haftalari).
        $weeksFor = function ($faculty, $spec, $course) use ($gridSettings, $board) {
            $g = $gridSettings[($faculty ?? '') . '|' . $spec . '|' . $course]
                ?? $gridSettings['|' . $spec . '|' . $course]
                ?? null;
            return max(1, (int) ($g->weeks ?? $board->weeks));
        };

        $all = TimetableCard::where('board_id', $board->id)->get();

        // Sikl (4-6 kurs) fanlari HAFTALIK panjaraga tushmaydi — ular sikl
        // kalendarida ketma-ket kunli blok bo'lib turadi (bir guruh N kun bir
        // fan). Shu sababli sikl fanlarini panjaradan bo'shatamiz va avtomatik
        // joylashda o'tkazib yuboramiz (aks holda para-para sochilib ketadi).
        $cycleSubjKeys = [];
        if (Schema::hasTable('timetable_subject_settings')) {
            foreach (TimetableSubjectSetting::where('board_id', $board->id)->where('mode', 'cycle')->get() as $s) {
                $cycleSubjKeys[mb_strtolower(trim((string) $s->specialty_name)) . '|' . (int) $s->course . '|' . mb_strtolower(trim((string) $s->subject_name))] = true;
            }
        }
        $isCycle = fn(TimetableCard $c) => isset($cycleSubjKeys[
            mb_strtolower(trim((string) $c->specialty_name)) . '|' . (int) $c->course . '|' . mb_strtolower(trim((string) $c->subject_name))
        ]);
        if (!empty($cycleSubjKeys)) {
            $cycleIds = $all->filter(fn($c) => $isCycle($c) && ($c->day || $c->pair) && $inScope($c))->pluck('id');
            if ($cycleIds->isNotEmpty()) {
                TimetableCard::whereIn('id', $cycleIds)
                    ->update(['day' => null, 'pair' => null, 'auditorium_code' => null, 'auditorium_name' => null]);
                foreach ($all as $c) {
                    if ($isCycle($c)) {
                        $c->day = null;
                        $c->pair = null;
                    }
                }
            }
        }

        // ══ Kartaning faol haftalari — bitmask ═══════════════════════════════
        // Ikki dars BIR slotni bo'lishishi mumkin, agar ular hech qachon bir
        // haftaga tushmasa. Masalan toq haftalardagi Psixologiya ma'ruzasi va
        // juft haftalardagi Gigiyena ma'ruzasi bitta 08:30 ni, bitta xonani
        // egallay oladi; ma'ruza va uni almashtiruvchi amaliy ham shunday.
        // Shuning uchun bandlik xaritalari "band/bo'sh" emas, QAYSI HAFTALARDA
        // band ekanini saqlaydi va to'qnashuv niqoblar kesishishi bilan
        // aniqlanadi.
        $cancelledWeeks = [];   // card_id => [hafta => true]
        if (Schema::hasTable('timetable_card_overrides')) {
            $rows = DB::table('timetable_card_overrides as o')
                ->join('timetable_cards as c', 'c.id', '=', 'o.card_id')
                ->where('c.board_id', $board->id)
                ->where('o.cancelled', true)
                ->get(['o.card_id', 'o.week']);
            foreach ($rows as $row) {
                $cancelledWeeks[(int) $row->card_id][(int) $row->week] = true;
            }
        }
        $maskCache = [];
        $maskOf = function (TimetableCard $c) use (&$maskCache, $cancelledWeeks, $weeksFor): int {
            $id = (int) $c->id;
            if (isset($maskCache[$id])) {
                return $maskCache[$id];
            }
            $total = max(1, min(60, $weeksFor($c->faculty_name, $c->specialty_name, (int) $c->course)));
            $skip = $cancelledWeeks[$id] ?? [];
            $mask = 0;
            for ($w = 1; $w <= $total; $w++) {
                if (!isset($skip[$w])) {
                    $mask |= 1 << ($w - 1);
                }
            }
            // Hamma haftada bekor qilingan karta (mask 0) hech kim bilan
            // to'qnashmay, xohlagan joyga tushib ketmasin — to'liq band deb olamiz.
            return $maskCache[$id] = ($mask !== 0 ? $mask : ((1 << $total) - 1));
        };

        // Band kataklar — joylashgan (fiks) kartalardan
        $groupBusy = [];   // "spec|course|day|pair" => [guruh => hafta niqobi]
        $teacherBusy = []; // "teacher_id|day|pair" => hafta niqobi
        $roomBusy = [];    // "code|day|pair" => hafta niqobi
        foreach ($all as $c) {
            if ($c->day && $c->pair) {
                $this->markBusy($groupBusy, $teacherBusy, $roomBusy, $c, $maskOf($c));
            }
        }

        // Auditoriya havzasi (sig'im o'sish tartibida — zich joylash uchun)
        $rooms = ($assignRooms || $lectureRooms)
            ? Auditorium::where('active', true)->orderBy('volume')->get(['id', 'code', 'name', 'volume', 'auditorium_type_name'])
            : collect();
        $roomTeacherMap = $this->auditoriumTeacherMap();
        // Ma'ruza xonalari havzasi — tipida "ma'ruza" bo'lganlar (topilmasa — hammasi)
        $lecRooms = collect();
        if ($lectureRooms) {
            $lecRooms = $rooms->filter(fn($r) => mb_stripos((string) ($r->auditorium_type_name ?? ''), 'ruza') !== false)->values();
            if ($lecRooms->isEmpty()) {
                $lecRooms = $rooms;
            }
        }
        // Kartaga mos xona havzasini tanlash (ma'ruza → ma'ruza xonalari)
        $poolFor = function (TimetableCard $c) use ($assignRooms, $lectureRooms, $rooms, $lecRooms, $roomTeacherMap) {
            $pool = ($lectureRooms && $c->training_type === 'lecture')
                ? $lecRooms
                : ($assignRooms ? $rooms : collect());

            // O'qituvchiga biriktirilgan xona faqat shu o'qituvchiga,
            // umumiy xona esa barcha o'qituvchilarga ochiq bo'ladi.
            if ($pool->isEmpty() || empty($roomTeacherMap) || !$c->teacher_id) {
                return $pool;
            }

            return $pool->filter(fn($r) => $this->auditoriumAllowedForCard($r, $c, $roomTeacherMap))->values();
        };

        // Joylanadigan kartalar — qamrovdagi bo'sh (joylashmagan)lar.
        // Sikl fanlari o'tkazib yuboriladi (ular panjaraga tushmaydi).
        $toPlace = $all->filter(function ($c) use ($scopeType, $inScope, $isCycle) {
            if ($c->day && $c->pair) {
                return false;
            }
            if ($isCycle($c)) {
                return false;
            }
            if ($scopeType !== null && $c->training_type !== $scopeType) {
                return false;
            }
            return $inScope($c);
        });

        // Tartib: eng ko'p cheklovli avval — ma'ruza (ko'p guruh band qiladi),
        // ko'proq guruh, ko'proq talaba
        // Amaliylar orasida KAM haftalik karta avval keladi: ma'ruza o'rnini
        // bosuvchi amaliy ma'ruza slotini egallashi, har haftalik amaliy esa
        // uning ketidan tizilishi kerak.
        $toPlace = $toPlace->sort(function ($a, $b) {
            $ka = [$a->specialty_name, (int) $a->course, $a->training_type === 'lecture' ? 0 : 1,
                   (int) $a->weeks, -count($a->occupiedGroups()), -(int) $a->students];
            $kb = [$b->specialty_name, (int) $b->course, $b->training_type === 'lecture' ? 0 : 1,
                   (int) $b->weeks, -count($b->occupiedGroups()), -(int) $b->students];
            return $ka <=> $kb;
        })->values();

        $subjDay = [];    // "spreadKey|day" => count (fan taqsimoti uchun)
        $subjSlots = [];  // "spreadKey" => [[day,pair,len],...] (klaster: bir kun/ketma-ket)
        $placed = 0;
        $unplaced = 0;
        $roomsAssigned = 0;
        $touched = [];

        // Ma'ruza joylashuvi: "yo'nalish|kurs|fan" => [kun, para, ma'ruza haftalari].
        // Ma'ruzani ALMASHTIRUVCHI amaliy karta (ma'ruza yo'q haftalarda o'tiladi)
        // aynan shu slotga qo'yiladi — shunda dars ertaroq boshlanadi va haftalik
        // yuk oshmaydi (ular hech qachon bir haftaga tushmaydi).
        // Fan zanjiri: "yo'nalish|kurs|fan" => ma'ruzadan boshlanadigan blok.
        //   lec  — ma'ruzaning boshlanish yarim-sloti
        //   next — zanjirning navbatdagi bo'sh yarim-sloti (ma'ruzadan keyin)
        //   lw   — ma'ruza necha haftada o'tiladi
        // Reja soati fanni bir necha kartaga bo'ladi:
        //   · har haftalik amaliy soat            (hamma haftada)
        //   · ma'ruzasiz haftalardagi qo'shimcha  (ma'ruza o'tilmaydigan haftalarda)
        //   · qoldiq 1 soat                        (bir nechta haftada)
        // Bular bitta fanning bir kunlik yuki: ma'ruzasiz haftada ma'ruza o'rnini
        // bosuvchi amaliy ma'ruza slotidan boshlanadi, qolgan amaliy soat esa
        // uning ketidan tiziladi — nechchi soat bo'lishidan qat'i nazar
        // (2+2, 2+4, 4+2 …) guruh uchun dars yaxlit va bir vaqtda boshlanadi.
        // Zanjir kaliti FAKULTET va OQIM bilan birga olinadi. Busiz bir yo'nalish+
        // kursdagi barcha fakultet va oqimlar bitta zanjirga qo'shilib ketardi:
        // birinchi joylashgan ma'ruza qolgan hamma fakultet/oqimning amaliylarini
        // o'z kuniga va slotiga tortib, ular u yerga sig'may uzilib qolardi.
        $chain = [];
        $subjOf = fn($c) => (string) ($c->faculty_name ?? '') . '|'
            . $this->specKey($c->specialty_name) . '|' . (int) $c->course
            . '|' . (string) $c->oqim_label
            . '|' . $this->normSubject((string) $c->subject_name);

        // Zanjirdagi aniq joyga qo'yishga urinish. Muvaffaqiyatsiz bo'lsa null —
        // chaqiruvchi odatdagi qidiruvga tushadi.
        $chainSpot = function (array $segs, ?array $ch, bool $standIn, int $days, int $pairs, string $scope)
            use (&$groupBusy, &$teacherBusy, &$roomBusy) {
            if (!$ch) {
                return null;
            }
            $total = array_sum(array_column($segs, 'len'));
            // O'rin bosuvchi blok ma'ruza slotiga tushadi. Ma'ruzadan QISQA bo'lsa
            // (mas. ma'ruza 2 soat, o'rnini bosuvchi amaliy 1 soat) u slotning
            // OXIRIGA tekislanadi: shunda ma'ruzasiz haftada u undan keyingi
            // amaliyga yopishadi va orada bo'sh yarim-slot qolmaydi. Boshiga
            // qo'yilsa, ma'ruzasiz haftada [amaliy][oyna][amaliy] chiqardi.
            // Tekislash blokning BIRINCHI bo'lagi (o'rin bosuvchi karta) bo'yicha
            // hisoblanadi — undan keyingi amaliy soat ma'ruza tugagan joydan
            // boshlansin, aks holda ma'ruzali haftada ma'ruza bilan ustma-ust
            // tushib, butun blok joylasha olmay qolardi.
            $start = $standIn
                ? (int) $ch['lec'] + max(0, (int) ($ch['llen'] ?? 0) - (int) $segs[0]['len'])
                : (int) $ch['next'];
            if ($start < 1 || $start + $total - 1 > $pairs) {
                return null;
            }
            // Soxta "anchor" — blok aynan $start dan boshlanishini majburlaydi.
            $spot = $this->clusterPlacement(
                $segs, $days, $pairs, $scope,
                $groupBusy, $teacherBusy, $roomBusy,
                true, [$ch['day'] => [[$start - 1, $start - 1]]],
                fn(int $d, int $p) => ($d === $ch['day'] && $p === $start) ? 0.0 : 1000.0
            );
            return ($spot && (int) $spot[0]['day'] === (int) $ch['day'] && (int) $spot[0]['pair'] === $start)
                ? $spot : null;
        };

        // ══ Bir dars = bir blok ══════════════════════════════════════════════
        // Reja soati kartalarga bo'lingan (mas. haftasiga 4 soat amaliy → 2 ta
        // 2 soatlik karta), lekin bu — BITTA dars. "Bitta fanning paralarini bir
        // kunga qo'yish" / "Ketma-ket paralarga qo'yish" yoqilgan bo'lsa, shunday
        // kartalar QATTIQ cheklov sifatida birga joylashadi. Sig'masa (guruh,
        // o'qituvchi yoki auditoriya band) — butun blok joylashmagan bo'lib
        // qoladi, ya'ni yarmi u kunga, yarmi bu kunga bo'linib ketmaydi.
        //
        // Klaster kaliti: fan+guruh (spreadKey) — hafta shabloniga QARAMAY.
        // Bir fanning guruh uchun barcha amaliy kartalari (har haftalik soat,
        // ma'ruzani almashtiruvchi soat, qoldiq soat) bitta blok bo'lib
        // joylashadi. Blok boshida ma'ruzani almashtiruvchi karta turadi va
        // butun blok ma'ruza tugaydigan joyga tekislanadi — ma'ruzali haftada
        // ham, ma'ruzasiz haftada ham guruh uchun dars yaxlit chiqadi.
        //
        // Ilgari kalitga "weeks" ham kirardi: o'rin bosuvchi karta alohida
        // joylashib ma'ruza slotini egallardi, keyingi amaliy esa uning yoniga
        // sig'masa JOYLASHMAY qolardi (kun bo'ylab teshik qolardi). Bitta blok
        // bo'lganda esa u boshqa kunga butunligicha ko'chib, joyini topadi.
        $clusterMode = $sameDay || $consecutive;
        $clusterKey = fn(TimetableCard $c) => $this->spreadKey($c);
        // "Bir kunga" cheklovi FAN + GURUH bo'yicha, hafta shabloniga qaramay:
        // har haftalik amaliy va ma'ruza o'rnini bosuvchi amaliy alohida
        // klasterlar, lekin ular baribir bitta kunda, yonma-yon turishi kerak —
        // aks holda fan ikki kunga bo'linib ketadi.
        $anchorKey = fn(TimetableCard $c) => $this->spreadKey($c);

        // Allaqachon joylashgan kartalar — blok ular bilan bir kunga / ularning
        // yoniga tushishi kerak (avtomatik joylash qayta bosilganda muhim).
        $anchors = [];   // anchorKey => [day => [[boshlanish, tugash], ...]]
        if ($clusterMode) {
            foreach ($all as $c) {
                if ($c->day && $c->pair) {
                    $anchors[$anchorKey($c)][(int) $c->day][] =
                        [(int) $c->pair, (int) $c->pair + $this->parasNeeded($c) - 1];
                }
            }
        }

        // Joylanadigan birliklar: klaster rejimida bir klasterning kartalari
        // bitta birlik; aks holda har karta alohida.
        $units = [];
        if ($clusterMode) {
            $byKey = [];
            foreach ($toPlace as $c) {
                $byKey[$clusterKey($c)][] = $c;
            }
            $units = array_values($byKey);
        } else {
            foreach ($toPlace as $c) {
                $units[] = [$c];
            }
        }

        foreach ($units as $unit) {
            // ── Ko'p kartali blok (bir darsning paralari) ────────────────────
            if (count($unit) > 1) {
                // Almashtiruvchi karta (ma'ruza yo'q haftalarda o'tiladigan) blokning
                // BOSHIDA turishi kerak — shunda blok ma'ruza slotidan boshlanadi va
                // ma'ruzali haftada u slotni ma'ruza egallaydi (oyna qolmaydi).
                // Aks holda blok ma'ruzadan KEYIN boshlanib, ma'ruzali haftada
                // ma'ruza bilan amaliy orasida bo'sh para qolardi.
                $u0 = $unit[0];
                $uCh0 = $chain[$subjOf($u0)] ?? null;
                if ($uCh0) {
                    $tw = $weeksFor($u0->faculty_name, $u0->specialty_name, (int) $u0->course);
                    $standInIdx = null;
                    foreach ($unit as $ix => $uc) {
                        // O'rin bosuvchi karta hafta shabloni bo'yicha aniqlanadi:
                        // ma'ruza o'tilmaydigan haftalarda o'tiladi. Uzunligi
                        // ma'ruzanikiga teng bo'lishi shart emas (2 soat ma'ruza
                        // o'rniga 1 soat yoki 4 soat amaliy ham bo'lishi mumkin).
                        if ((int) $uc->weeks === $tw - (int) $uCh0['lw']) {
                            $standInIdx = $ix;
                            break;
                        }
                    }
                    if ($standInIdx !== null && $standInIdx !== 0) {
                        $tmp = $unit[$standInIdx];
                        array_splice($unit, $standInIdx, 1);
                        array_unshift($unit, $tmp);
                    }
                }
                $lead = $unit[0];
                [$uDays, $uPairs] = $dimsFor($lead->faculty_name, $lead->specialty_name, (int) $lead->course);
                $uScope = $this->groupScopeKey($lead);
                $uKey = $anchorKey($lead);

                $segs = [];
                $unionGroups = [];
                $noRoom = false;
                foreach ($unit as $uc) {
                    $ucPool = $poolFor($uc);
                    $ucNeedRoom = $ucPool->isNotEmpty();
                    $ucArr = $ucNeedRoom
                        ? array_values(array_filter($ucPool->all(), fn($r) => (int) ($r->volume ?? 0) >= $minVolFor($uc)))
                        : [];
                    if ($ucNeedRoom && !$ucArr) {
                        $noRoom = true;   // sig'imi yetadigan xona umuman yo'q
                        break;
                    }
                    $ucGroups = $uc->occupiedGroups();
                    $unionGroups = array_merge($unionGroups, $ucGroups);
                    $segs[] = [
                        'card' => $uc,
                        'len' => $this->parasNeeded($uc),
                        'groups' => $ucGroups,
                        'teacher' => $uc->teacher_id,
                        'room_required' => $ucNeedRoom,
                        'pool' => $ucArr,
                        'mask' => $maskOf($uc),
                    ];
                }
                if ($noRoom) {
                    $unplaced += count($unit);
                    continue;
                }
                $unionGroups = array_values(array_unique($unionGroups));

                // Avval fan zanjiri: ma'ruzadan boshlanadigan joyga tizilsin
                // (bir necha kartadan iborat amaliy blok ham shu yo'l bilan
                // ma'ruza slotiga yoki uning ketiga tushadi).
                $spot = null;
                $uChainKey = $subjOf($lead);
                if ($lead->training_type === 'practice' && isset($chain[$uChainKey])) {
                    $uCh = $chain[$uChainKey];
                    $uTotal = $weeksFor($lead->faculty_name, $lead->specialty_name, (int) $lead->course);
                    $spot = $chainSpot(
                        $segs, $uCh, (int) $lead->weeks === $uTotal - (int) $uCh['lw'],
                        $uDays, $uPairs, $uScope
                    );
                    if ($spot !== null) {
                        $blockLen = array_sum(array_column($segs, 'len'));
                        $chain[$uChainKey]['next'] = max((int) $uCh['next'], (int) $spot[0]['pair'] + $blockLen);
                    }
                }
                if ($spot === null) {
                    $penaltyFor = fn(int $d, int $p) => $this->slotPenalty(
                        $lead, $unionGroups, $d, $p, $uPairs, $groupBusy, $subjDay, $sameDay, $consecutive, $subjSlots, $maskOf($lead)
                    );
                    $spot = $this->clusterPlacement(
                        $segs, $uDays, $uPairs, $uScope,
                        $groupBusy, $teacherBusy, $roomBusy,
                        $consecutive, $anchors[$uKey] ?? [], $penaltyFor
                    );
                }
                if ($spot === null) {
                    // Blok butunligicha sig'madi — kartalar joylashmaganlarda qoladi.
                    $unplaced += count($unit);
                    continue;
                }

                foreach ($spot as $item) {
                    /** @var TimetableCard $uc */
                    $uc = $item['card'];
                    $uc->day = $item['day'];
                    $uc->pair = $item['pair'];
                    $uc->start_half = 0;
                    if ($item['room']) {
                        $uc->auditorium_code = $item['room']->code;
                        $uc->auditorium_name = $item['room']->name;
                        $roomsAssigned++;
                    }
                    $this->markBusy($groupBusy, $teacherBusy, $roomBusy, $uc, $maskOf($uc));
                    if ($uc->training_type === 'lecture' && (int) $uc->weeks > 0
                        && !isset($chain[$subjOf($uc)])) {
                        $chain[$subjOf($uc)] = [
                            'day' => (int) $uc->day, 'lec' => (int) $uc->pair,
                            'llen' => $this->parasNeeded($uc),
                            'next' => (int) $uc->pair + $this->parasNeeded($uc), 'lw' => (int) $uc->weeks,
                        ];
                    }
                    $ucSk = $this->spreadKey($uc);
                    $subjDay[$ucSk . '|' . $uc->day] = ($subjDay[$ucSk . '|' . $uc->day] ?? 0) + 1;
                    $subjSlots[$ucSk][] = [(int) $uc->day, (int) $uc->pair, $this->parasNeeded($uc)];
                    $anchors[$uKey][(int) $uc->day][] =
                        [(int) $uc->pair, (int) $uc->pair + $this->parasNeeded($uc) - 1];
                    $touched[] = $uc;
                    $placed++;
                }
                continue;
            }

            $c = $unit[0];
            [$days, $pairs] = $dimsFor($c->faculty_name, $c->specialty_name, (int) $c->course);
            $groups = $c->occupiedGroups();
            $best = null;
            $bestPen = INF;
            $bestRoom = null;

            // Karta bo'yicha o'zgarmas qiymatlarni ichki halqadan tashqariga chiqaramiz
            // (katta doskalarda bu qayta-qayta hisoblanib, sezilarli sekinlashtirardi).
            $scopeKey = $this->groupScopeKey($c);
            $teacherId = $c->teacher_id;
            $cardMask = $maskOf($c);
            $minVol = $minVolFor($c);
            $pool = $poolFor($c);
            // Xona kerakmi (havza bo'sh bo'lmasa — kerak). Sig'imi yetmaydiganlarni
            // bir marta chiqarib tashlaymiz (har katakda qayta tekshirmaslik uchun).
            // MUHIM: havza bo'sh bo'lmasa-yu sig'adigani bo'lmasa — karta joylanmaydi
            // (xonasiz qo'yib yubormaymiz), shuning uchun $roomRequired alohida bayroq.
            $roomRequired = $pool->isNotEmpty();
            $poolArr = $roomRequired
                ? array_values(array_filter($pool->all(), fn($r) => (int) ($r->volume ?? 0) >= $minVol))
                : [];
            if ($roomRequired && !$poolArr) {
                $unplaced++;   // sig'imi yetadigan xona umuman yo'q
                continue;
            }
            $need = $this->parasNeeded($c);

            // ── Fan zanjiri: ma'ruzadan boshlanadigan blok ───────────────────
            // Ma'ruza o'rnini bosuvchi amaliy AYNAN ma'ruza slotiga, qolgan
            // amaliy soat esa uning ketidan tushadi. Guruh uchun natija:
            // ma'ruzali haftada [ma'ruza + amaliy], ma'ruzasiz haftada
            // [amaliy + amaliy] — ikkalasi ham bir vaqtda boshlanadi va
            // uzilmaydi (soat taqsimoti 2+2 bo'ladimi, 2+4 bo'ladimi).
            $sKey = $subjOf($c);
            $ch = $chain[$sKey] ?? null;
            if ($c->training_type === 'practice' && $ch) {
                $total = $weeksFor($c->faculty_name, $c->specialty_name, (int) $c->course);
                $standIn = (int) $c->weeks === $total - (int) $ch['lw'];
                $seg = [[
                    'card' => $c, 'len' => $need, 'groups' => $groups,
                    'teacher' => $teacherId, 'room_required' => $roomRequired,
                    'pool' => $poolArr, 'mask' => $cardMask,
                ]];
                $spot = $chainSpot($seg, $ch, $standIn, $days, $pairs, $scopeKey);
                if ($spot !== null) {
                    $c->day = $spot[0]['day'];
                    $c->pair = $spot[0]['pair'];
                    $c->start_half = 0;
                    if ($spot[0]['room']) {
                        $c->auditorium_code = $spot[0]['room']->code;
                        $c->auditorium_name = $spot[0]['room']->name;
                        $roomsAssigned++;
                    }
                    $this->markBusy($groupBusy, $teacherBusy, $roomBusy, $c, $cardMask);
                    $chain[$sKey]['next'] = max((int) $ch['next'], (int) $c->pair + $need);
                    $skC = $this->spreadKey($c);
                    $subjDay[$skC . '|' . $c->day] = ($subjDay[$skC . '|' . $c->day] ?? 0) + 1;
                    $subjSlots[$skC][] = [(int) $c->day, (int) $c->pair, $need];
                    if ($clusterMode) {
                        $anchors[$anchorKey($c)][(int) $c->day][] = [(int) $c->pair, (int) $c->pair + $need - 1];
                    }
                    $touched[] = $c;
                    $placed++;
                    continue;
                }
            }

            // ── Shu darsning boshqa paralari allaqachon joylashgan bo'lsa ────
            // Klaster rejimida yangi karta ular bilan bir kunga (va ketma-ket
            // sozlamasi yoqilgan bo'lsa — yoniga) tushishi shart. Joy bo'lmasa
            // karta joylashmaganlarda qoladi — boshqa kunga surib yuborilmaydi.
            if ($clusterMode && !empty($anchors[$anchorKey($c)])) {
                $seg = [[
                    'card' => $c,
                    'len' => $need,
                    'groups' => $groups,
                    'teacher' => $teacherId,
                    'room_required' => $roomRequired,
                    'pool' => $poolArr,
                    'mask' => $cardMask,
                ]];
                $spot = $this->clusterPlacement(
                    $seg, $days, $pairs, $scopeKey,
                    $groupBusy, $teacherBusy, $roomBusy,
                    $consecutive, $anchors[$anchorKey($c)],
                    fn(int $d, int $p) => $this->slotPenalty($c, $groups, $d, $p, $pairs, $groupBusy, $subjDay, $sameDay, $consecutive, $subjSlots, $cardMask)
                );
                if ($spot === null) {
                    $unplaced++;
                    continue;
                }
                $c->day = $spot[0]['day'];
                $c->pair = $spot[0]['pair'];
                $c->start_half = 0;
                if ($spot[0]['room']) {
                    $c->auditorium_code = $spot[0]['room']->code;
                    $c->auditorium_name = $spot[0]['room']->name;
                    $roomsAssigned++;
                }
                $this->markBusy($groupBusy, $teacherBusy, $roomBusy, $c, $cardMask);
                if ($c->training_type === 'lecture' && (int) $c->weeks > 0 && !isset($chain[$sKey])) {
                    $chain[$sKey] = [
                        'day' => (int) $c->day, 'lec' => (int) $c->pair, 'llen' => $need,
                        'next' => (int) $c->pair + $need, 'lw' => (int) $c->weeks,
                    ];
                }
                $skA = $this->spreadKey($c);
                $subjDay[$skA . '|' . $c->day] = ($subjDay[$skA . '|' . $c->day] ?? 0) + 1;
                $subjSlots[$skA][] = [(int) $c->day, (int) $c->pair, $need];
                $anchors[$anchorKey($c)][(int) $c->day][] = [(int) $c->pair, (int) $c->pair + $need - 1];
                $touched[] = $c;
                $placed++;
                continue;
            }

            for ($d = 1; $d <= $days; $d++) {
                for ($p = 1; $p <= $pairs - $need + 1; $p++) {
                    // Qattiq: dars egallaydigan barcha paralar bo'sh bo'lishi kerak
                    $freeAll = true;
                    for ($i = 0; $i < $need; $i++) {
                        $gk = $scopeKey . '|' . $d . '|' . ($p + $i);
                        if (!empty($groupBusy[$gk])) {
                            $busyMap = $groupBusy[$gk];
                            foreach ($groups as $g) {
                                if (($busyMap[$g] ?? 0) & $cardMask) {
                                    $freeAll = false;
                                    break 2;
                                }
                            }
                        }
                        if ($teacherId && (($teacherBusy[$teacherId . '|' . $d . '|' . ($p + $i)] ?? 0) & $cardMask)) {
                            $freeAll = false;
                            break;
                        }
                    }
                    if (!$freeAll) {
                        continue;
                    }
                    // Qattiq: auditoriya (sig'im yetarli + barcha paralarda bo'sh) —
                    // kartaga mos havzadan (ma'ruza → ma'ruza xonalari), to'qnashuvsiz.
                    $room = null;
                    if ($roomRequired) {
                        foreach ($poolArr as $r) {   // havza allaqachon sig'im bo'yicha filtrlangan
                            $roomFree = true;
                            for ($i = 0; $i < $need; $i++) {
                                if (($roomBusy[$r->code . '|' . $d . '|' . ($p + $i)] ?? 0) & $cardMask) {
                                    $roomFree = false;
                                    break;
                                }
                            }
                            if (!$roomFree) {
                                continue;
                            }
                            $room = $r;
                            break; // sig'imi yetadigan eng kichik bo'sh xona
                        }
                        if (!$room) {
                            continue; // bu katakka mos bo'sh xona yo'q — boshqa katak
                        }
                    }
                    // Yumshoq jarima
                    $pen = $this->slotPenalty($c, $groups, $d, $p, $pairs, $groupBusy, $subjDay, $sameDay, $consecutive, $subjSlots, $cardMask);

                    // Fan YAXLIT kelsin: amaliy dars o'z fanining ma'ruzasi bilan
                    // bir kunda va yonma-yon bo'lsin. Klasterlash mukofoti (subjSlots)
                    // ma'ruza va amaliyni bog'lamaydi — ularning spreadKey'i har xil
                    // (ma'ruza oqim bo'yicha, amaliy guruh bo'yicha), shu sababli
                    // amaliy ma'ruzadan uzoqqa tushib, orasiga boshqa fan kirib qolardi.
                    if ($c->training_type === 'practice' && isset($chain[$sKey])) {
                        $ld = (int) $chain[$sKey]['day'];
                        $lp = (int) $chain[$sKey]['lec'];
                        if ($d === $ld) {
                            $pen -= 20;   // ma'ruza bilan bir kunda — mukofot
                            // Ikki blok orasidagi bo'shliq (0 = yonma-yon)
                            $gap = $p >= $lp ? ($p - ($lp + 2)) : ($lp - ($p + $need));
                            $pen += max(0, $gap) * 8;   // uzilish — jarima
                        } else {
                            $pen += 12;   // boshqa kunda — yengil jarima
                        }
                    }

                    if ($pen < $bestPen) {
                        $bestPen = $pen;
                        $best = [$d, $p];
                        $bestRoom = $room;
                    }
                }
            }

            if ($best === null) {
                $unplaced++;
                continue;
            }
            [$d, $p] = $best;
            $c->day = $d;
            $c->pair = $p;
            $c->start_half = 0;   // avto-joylash para chegarasidan boshlaydi
            if ($bestRoom) {
                $c->auditorium_code = $bestRoom->code;
                $c->auditorium_name = $bestRoom->name;
                $roomsAssigned++;
            }
            $this->markBusy($groupBusy, $teacherBusy, $roomBusy, $c, $cardMask);
            // Ma'ruza joylashdi — uni almashtiruvchi amaliy shu slotga tushsin
            if ($c->training_type === 'lecture' && (int) $c->weeks > 0 && !isset($chain[$subjOf($c)])) {
                $chain[$subjOf($c)] = ['day' => $d, 'lec' => $p, 'llen' => $need,
                    'next' => $p + $need, 'lw' => (int) $c->weeks];
            }
            $skBase = $this->spreadKey($c);
            $subjDay[$skBase . '|' . $d] = ($subjDay[$skBase . '|' . $d] ?? 0) + 1;
            $subjSlots[$skBase][] = [$d, $p, $need];
            if ($clusterMode) {
                $anchors[$anchorKey($c)][$d][] = [$p, $p + $need - 1];
            }
            $touched[] = $c;
            $placed++;
        }

        // 2-bosqich: ALLAQACHON joylashgan, lekin xonasiz kartalarga xona biriktirish
        // (joylashuvni o'zgartirmasdan). Aks holda hamma karta joylashgan bo'lsa,
        // "Auditoriya"/"Ma'ruza xonasi" belgilansa ham hech narsa biriktirilmasdi —
        // chunki yuqoridagi sikl faqat joylashmagan kartalar bo'yicha yuradi.
        if ($assignRooms || $lectureRooms) {
            $needRoom = $all->filter(function ($c) use ($scopeType, $inScope, $isCycle) {
                if (!$c->day || !$c->pair || $c->auditorium_code) {
                    return false;
                }
                if ($isCycle($c)) {
                    return false;
                }
                if ($scopeType !== null && $c->training_type !== $scopeType) {
                    return false;
                }
                return $inScope($c);
            })->sortByDesc(fn($c) => (int) $c->students);   // kattalari avval — zich joylash

            foreach ($needRoom as $c) {
                $pool = $poolFor($c);
                if ($pool->isEmpty()) {
                    continue;
                }
                $need = $this->parasNeeded($c);
                foreach ($pool as $r) {
                    if ((int) ($r->volume ?? 0) < $minVolFor($c)) {
                        continue;
                    }
                    $mask = $maskOf($c);
                    $free = true;
                    for ($i = 0; $i < $need; $i++) {
                        if (($roomBusy[$r->code . '|' . $c->day . '|' . ((int) $c->pair + $i)] ?? 0) & $mask) {
                            $free = false;
                            break;
                        }
                    }
                    if (!$free) {
                        continue;
                    }
                    $c->auditorium_code = $r->code;
                    $c->auditorium_name = $r->name;
                    for ($i = 0; $i < $need; $i++) {
                        $rk = $r->code . '|' . $c->day . '|' . ((int) $c->pair + $i);
                        $roomBusy[$rk] = ($roomBusy[$rk] ?? 0) | $mask;
                    }
                    $roomsAssigned++;
                    $touched[] = $c;
                    break;
                }
            }
        }

        // Yozish: har karta uchun alohida save() minglab UPDATE so'rovi bo'lib juda
        // sekin edi (katta doskada joylashning o'zidan ham uzoqroq). Bir xil
        // (kun, para, xona) qiymatli kartalarni guruhlab, whereIn bilan yozamiz.
        $batches = [];
        foreach ($touched as $c) {
            $k = $c->day . '|' . $c->pair . '|' . (int) $c->start_half . '|'
                . ($c->auditorium_code ?? '') . '|' . ($c->auditorium_name ?? '');
            if (!isset($batches[$k])) {
                $batches[$k] = ['vals' => [
                    'day'             => $c->day,
                    'pair'            => $c->pair,
                    'start_half'      => (int) $c->start_half,
                    'auditorium_code' => $c->auditorium_code,
                    'auditorium_name' => $c->auditorium_name,
                ], 'ids' => []];
            }
            $batches[$k]['ids'][] = $c->id;
        }
        DB::transaction(function () use ($batches) {
            foreach ($batches as $b) {
                foreach (array_chunk($b['ids'], 1000) as $chunk) {
                    TimetableCard::whereIn('id', $chunk)->update($b['vals']);
                }
            }
        });

        // Haftalik zichlash alohida, kichik HTTP so'rovlarda bajariladi. Katta
        // doskada barcha haftani shu request ichida hisoblash reverse-proxy 504
        // timeoutiga olib kelardi. Frontend quyidagi ro'yxatni avtomatik ketma-ket
        // compact-week endpointiga yuboradi — foydalanuvchi hafta bosmaydi.
        $weeksToCompact = TimetableCardOverride::query()
            ->where('cancelled', true)
            ->whereHas('card', function ($q) use ($board, $facSet, $specSet, $courseSet) {
                $q->where('board_id', $board->id);
                $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
            })
            ->distinct()
            ->orderBy('week')
            ->pluck('week')
            ->map(fn($week) => (int) $week)
            ->values();

        return response()->json([
            'ok' => true,
            'placed' => $placed,
            'unplaced' => $unplaced,
            'rooms_assigned' => $roomsAssigned,
            'compacted' => 0,
            'weeks_to_compact' => $weeksToCompact,
        ]);
    }

    /**
     * Dars egallaydigan yarim-slotlar soni. Bu modelda `pair` — yarim-slot
     * indeksi (grid qatori), shuning uchun dars len_half ta ketma-ket yarim-slotni
     * egallaydi (2 = to'liq para = 2 soat).
     */
    private function parasNeeded(TimetableCard $c): int
    {
        return $c->lenHalf();
    }

    /**
     * Joylashuvlarni bo'shatish (kartochkalarni panelga qaytarish) — qamrov
     * bo'yicha: yo'nalish+kurs / kurs (barcha yo'nalishlar) / butun doska.
     * Kartochkalar o'chirilmaydi; faqat kun/para/auditoriya tozalanadi.
     */
    public function unplaceAll(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'specialty_name'    => 'nullable|string|max:255',
            'course'            => 'nullable|integer|min:1|max:7',
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'training_type'  => 'nullable|in:lecture,practice',
        ]);

        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $q = TimetableCard::where('board_id', $board->id)
            ->where(function ($w) { $w->whereNotNull('day')->orWhereNotNull('pair'); });
        $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
        if (!empty($data['training_type'])) {
            $q->where('training_type', $data['training_type']);
        }

        $count = (clone $q)->count();
        $q->update(['day' => null, 'pair' => null, 'auditorium_code' => null, 'auditorium_name' => null]);

        return response()->json(['ok' => true, 'unplaced' => $count]);
    }

    /**
     * Qamrov to'plamlarini so'rov ma'lumotidan tuzadi: fakultet/yo'nalish/kurs
     * massivlari (dropdown checkboxlaridan). Massivlar berilmasa eski yakka
     * specialty_name/course parametrlari bilan moslashadi. Qaytadi:
     * [facSet|null, specSet|null, courseSet|null] — har biri array_flip xarita
     * (yoki cheklovsizlik uchun null).
     */
    private function scopeSets(array $data): array
    {
        $facs = $data['faculty_names'] ?? null;
        $specs = $data['specialty_names'] ?? null;
        $courses = isset($data['courses']) ? array_map('intval', (array) $data['courses']) : null;

        // Massivlar yo'q — eski yakka parametrlarga qaytamiz
        if ($facs === null && $specs === null && $courses === null) {
            if (!empty($data['specialty_name'])) {
                $specs = [$data['specialty_name']];
                $courses = isset($data['course']) ? [(int) $data['course']] : null;
            } elseif (isset($data['course'])) {
                $courses = [(int) $data['course']];
            }
        }

        return [
            $facs !== null ? array_flip(array_map('strval', $facs)) : null,
            $specs !== null ? array_flip(array_map('strval', $specs)) : null,
            $courses !== null ? array_flip($courses) : null,
        ];
    }

    /** Qamrov to'plamlarini SQL so'roviga qo'llaydi (whereIn). */
    private function applyScopeToQuery($q, ?array $facSet, ?array $specSet, ?array $courseSet): void
    {
        if ($specSet !== null) {
            $q->whereIn('specialty_name', array_keys($specSet));
        }
        if ($courseSet !== null) {
            $q->whereIn('course', array_keys($courseSet));
        }
        if ($facSet !== null) {
            $vals = array_keys($facSet);
            $q->where(function ($w) use ($vals) {
                $w->whereIn('faculty_name', $vals);
                if (in_array('', $vals, true)) {
                    $w->orWhereNull('faculty_name');
                }
            });
        }
    }

    /**
     * Katakni band deb belgilash. Dars uzunligiga qarab len_half ta ketma-ket
     * yarim-slotni band qiladi (avto-joylash konfliktsiz bo'lishi uchun).
     *
     * $groupBusy — "scope|day|pair" => [guruh_nomi => hafta niqobi] (ro'yxat emas,
     * XARITA: katta doskalarda in_array/array_intersect O(n) qidiruvi qimmat edi).
     *
     * $mask — karta qaysi haftalarda o'tilishi (bitmask). Slot "band" emas,
     * MA'LUM HAFTALARDA band bo'ladi: hech qachon bir haftaga tushmaydigan
     * darslar (toq/juft haftalardagi ma'ruzalar, ma'ruza va uni almashtiruvchi
     * amaliy) bitta slotni bemalol bo'lishadi.
     */
    private function markBusy(array &$groupBusy, array &$teacherBusy, array &$roomBusy, TimetableCard $c, int $mask = -1): void
    {
        $need = $this->parasNeeded($c);
        $scope = $this->groupScopeKey($c);
        $groups = $c->occupiedGroups();
        for ($i = 0; $i < $need; $i++) {
            $p = (int) $c->pair + $i;
            $k = $scope . '|' . $c->day . '|' . $p;
            foreach ($groups as $g) {
                $groupBusy[$k][$g] = ($groupBusy[$k][$g] ?? 0) | $mask;
            }
            if ($c->teacher_id) {
                $tk = $c->teacher_id . '|' . $c->day . '|' . $p;
                $teacherBusy[$tk] = ($teacherBusy[$tk] ?? 0) | $mask;
            }
            if ($c->auditorium_code) {
                $rk = $c->auditorium_code . '|' . $c->day . '|' . $p;
                $roomBusy[$rk] = ($roomBusy[$rk] ?? 0) | $mask;
            }
        }
    }

    /**
     * Bir darsning barcha paralarini (kartalarini) birga joylash uchun joy topadi.
     *
     * $segs — [['card'=>TimetableCard, 'len'=>yarim-slot, 'groups'=>[], 'teacher'=>?id,
     *           'room_required'=>bool, 'pool'=>[Auditorium,...]], ...]
     * $anchorRuns — shu darsning ALLAQACHON joylashgan bo'laklari: [kun => [[bosh, oxir], ...]].
     *   Bo'sh bo'lmasa — yangi bo'laklar aynan shu kunga (va $consecutive bo'lsa
     *   mavjud blokning yoniga) tushadi.
     * $consecutive — paralar ketma-ket (yonma-yon) bo'lishi shart.
     *
     * Qaytadi: [['card'=>, 'day'=>, 'pair'=>, 'room'=>?Auditorium], ...] yoki null.
     * null — blok butunligicha sig'madi; chaqiruvchi kartalarni joylashmagan
     * qoldiradi (yarmini boshqa kunga surib yubormaydi).
     *
     * $groupBusy/$teacherBusy/$roomBusy qiymat bo'yicha uzatiladi — bu yerda
     * o'zgartirilmaydi, shuning uchun PHP nusxa olmaydi (copy-on-write).
     */
    private function clusterPlacement(
        array $segs,
        int $days,
        int $pairs,
        string $scopeKey,
        array $groupBusy,
        array $teacherBusy,
        array $roomBusy,
        bool $consecutive,
        array $anchorRuns,
        callable $penaltyFor
    ): ?array {
        if (!$segs) {
            return null;
        }
        // Bir yarim-slot shu segment uchun bo'shmi (guruh + o'qituvchi)?
        $slotFree = function (array $seg, int $d, int $p) use ($scopeKey, $groupBusy, $teacherBusy): bool {
            $mask = $seg['mask'] ?? -1;
            $busy = $groupBusy[$scopeKey . '|' . $d . '|' . $p] ?? null;
            if ($busy !== null) {
                foreach ($seg['groups'] as $g) {
                    if (($busy[$g] ?? 0) & $mask) {
                        return false;
                    }
                }
            }
            return !($seg['teacher'] && (($teacherBusy[$seg['teacher'] . '|' . $d . '|' . $p] ?? 0) & $mask));
        };
        // Segment $d kunida $p dan boshlab joylasha oladimi? Qaytadi: xona (yoki null),
        // joylashmasa — false.
        $fit = function (array $seg, int $d, int $p) use ($slotFree, $roomBusy) {
            for ($i = 0; $i < $seg['len']; $i++) {
                if (!$slotFree($seg, $d, $p + $i)) {
                    return false;
                }
            }
            if (!$seg['room_required']) {
                return null;   // xona kerak emas
            }
            $mask = $seg['mask'] ?? -1;
            foreach ($seg['pool'] as $r) {   // havza sig'im bo'yicha saralangan
                $free = true;
                for ($i = 0; $i < $seg['len']; $i++) {
                    if (($roomBusy[$r->code . '|' . $d . '|' . ($p + $i)] ?? 0) & $mask) {
                        $free = false;
                        break;
                    }
                }
                if ($free) {
                    return $r;
                }
            }
            return false;   // bo'sh xona yo'q
        };

        $total = array_sum(array_column($segs, 'len'));
        $best = null;
        $bestPen = INF;

        for ($d = 1; $d <= $days; $d++) {
            // Mavjud bo'laklar boshqa kunda bo'lsa — bu kun mumkin emas
            if ($anchorRuns && empty($anchorRuns[$d])) {
                continue;
            }
            for ($p = 1; $p + $total - 1 <= $pairs; $p++) {
                // Ketma-ketlik: mavjud blok bilan yonma-yon bo'lishi shart
                if ($consecutive && !empty($anchorRuns[$d])) {
                    $touching = false;
                    foreach ($anchorRuns[$d] as [$as, $ae]) {
                        if ($p === $ae + 1 || $p + $total === $as) {
                            $touching = true;
                            break;
                        }
                    }
                    if (!$touching) {
                        continue;
                    }
                }
                $off = 0;
                $items = [];
                foreach ($segs as $seg) {
                    $room = $fit($seg, $d, $p + $off);
                    if ($room === false) {
                        $items = null;
                        break;
                    }
                    $items[] = ['card' => $seg['card'], 'day' => $d, 'pair' => $p + $off, 'room' => $room];
                    $off += $seg['len'];
                }
                if ($items === null) {
                    continue;
                }
                $pen = (float) $penaltyFor($d, $p);
                if ($pen < $bestPen) {
                    $bestPen = $pen;
                    $best = $items;
                }
            }
        }
        if ($best !== null || $consecutive) {
            return $best;   // ketma-ket majburiy bo'lsa — faqat yaxlit blok
        }

        // "Bir kunga" yoqilgan, "ketma-ket" o'chirilgan: yaxlit blok sig'masa,
        // paralar bir kun ichida bo'shliq bilan ham tursa bo'ladi.
        $bestPen = INF;
        for ($d = 1; $d <= $days; $d++) {
            if ($anchorRuns && empty($anchorRuns[$d])) {
                continue;
            }
            $used = [];        // shu birlik band qilgan yarim-slotlar
            $items = [];
            $dayPen = 0.0;
            foreach ($segs as $seg) {
                $pick = null;
                $pickPen = INF;
                $pickRoom = null;
                for ($p = 1; $p + $seg['len'] - 1 <= $pairs; $p++) {
                    $clash = false;
                    for ($i = 0; $i < $seg['len']; $i++) {
                        if (isset($used[$p + $i])) {
                            $clash = true;
                            break;
                        }
                    }
                    if ($clash) {
                        continue;
                    }
                    $room = $fit($seg, $d, $p);
                    if ($room === false) {
                        continue;
                    }
                    $pen = (float) $penaltyFor($d, $p);
                    if ($pen < $pickPen) {
                        $pickPen = $pen;
                        $pick = $p;
                        $pickRoom = $room;
                    }
                }
                if ($pick === null) {
                    $items = null;
                    break;
                }
                for ($i = 0; $i < $seg['len']; $i++) {
                    $used[$pick + $i] = true;
                }
                $items[] = ['card' => $seg['card'], 'day' => $d, 'pair' => $pick, 'room' => $pickRoom];
                $dayPen += $pickPen;
            }
            if ($items === null) {
                continue;
            }
            if ($dayPen < $bestPen) {
                $bestPen = $dayPen;
                $best = $items;
            }
        }

        return $best;
    }

    /**
     * Fan taqsimoti kaliti: ma'ruza — oqim bo'yicha, amaliyot — guruhcha bo'yicha.
     *
     * Kalitga FAKULTET ham kiradi: guruh nomlari fakultetlar bo'ylab takrorlanadi
     * (mas. "25-01a (o'z)" ham 1-son, ham 2-son davolashda bor). Fakultetsiz ikki
     * fakultetning bir xil nomli guruhi bitta klaster deb qabul qilinar, "bir
     * kunga / ketma-ket" cheklovi ular orasida ishlab, dars uzilib qolardi.
     */
    private function spreadKey(TimetableCard $c): string
    {
        $who = $c->training_type === 'lecture' ? ('L' . $c->oqim_label) : ('P' . $c->group_name);
        return (string) ($c->faculty_name ?? '') . '|' . $c->specialty_name . '|' . $c->course
            . '|' . $who . '|' . $this->normSubject((string) $c->subject_name);
    }

    /**
     * Katak jarimasi: oyna + fan taqsimoti + kun yuki + ertalab ustunligi.
     * $sameDay/$consecutive — sozlamalar: bir fanning paralarini bir kunga /
     * ketma-ket joylash (default — kunlar bo'ylab yoyish).
     */
    private function slotPenalty(TimetableCard $c, array $groups, int $d, int $p, int $pairs, array $groupBusy, array $subjDay, bool $sameDay = false, bool $consecutive = false, array $subjSlots = [], int $mask = -1): float
    {
        $spc = $this->groupScopeKey($c);
        $pen = ($p - 1) * 0.2; // ertalabki paralarga yengil ustunlik
        // Kun bo'yicha band xaritalarni bir marta olamiz (har guruh uchun qayta
        // qidirmaslik uchun) — $groupBusy: "scope|day|pair" => [guruh => true].
        $dayBusy = [];
        for ($pp = 1; $pp <= $pairs; $pp++) {
            if ($pp !== $p) {
                $dayBusy[$pp] = $groupBusy[$spc . '|' . $d . '|' . $pp] ?? null;
            }
        }
        foreach ($groups as $g) {
            $used = [$p => true];
            foreach ($dayBusy as $pp => $busy) {
                // Boshqa haftalardagi dars shu kartaning kunida "oyna" yaratmaydi.
                if ($busy !== null && (($busy[$g] ?? 0) & $mask)) {
                    $used[$pp] = true;
                }
            }
            $keys = array_keys($used);
            $holes = (max($keys) - min($keys) + 1) - count($keys);
            $pen += $holes * 10;               // oyna — eng og'ir jarima
            $pen += (count($keys) - 1) * 1;    // kun yukini kunlar bo'ylab tekislash
        }

        $skBase = $this->spreadKey($c);
        if ($sameDay || $consecutive) {
            // Klaster rejimi: shu fan-guruhning oldin joylashgan paralari bilan
            // bir kun / ketma-ket bo'lishni rag'batlantiramiz.
            $slots = $subjSlots[$skBase] ?? [];
            $onSameDay = 0;
            $adjacent = false;
            $currentLen = $this->parasNeeded($c);
            foreach ($slots as $slot) {
                [$sd, $sp] = $slot;
                $slotLen = max(1, (int) ($slot[2] ?? 1));
                if ($sd === $d) {
                    $onSameDay++;
                    // Kartalar yarim-para indeksida turadi: yonma-yon bo'lishi
                    // uchun bir interval ikkinchisining chegarasida boshlanishi kerak.
                    if ($p === $sp + $slotLen || $sp === $p + $currentLen) {
                        $adjacent = true;
                    }
                }
            }
            if ($sameDay) {
                $pen -= $onSameDay * 25;                          // shu kunda — mukofot
                if (!empty($slots) && $onSameDay === 0) {
                    $pen += 15;                                   // yangi kun ochish — jarima
                }
            }
            if ($consecutive && !empty($slots)) {
                $pen += $adjacent ? -35 : 12;                    // ketma-ket bo'lsa — mukofot
            }
        } else {
            $pen += ($subjDay[$skBase . '|' . $d] ?? 0) * 6;      // default: kunlar bo'ylab yoyish
        }
        return $pen;
    }

    /** Doska ma'lumotlari: barcha kartochkalar (konflikt tekshiruvi butun doska bo'ylab). */
    public function data(TimetableBoard $board)
    {
        // Ma'ruza necha hafta davom etishini reja soatlaridan hisoblaymiz (karta
        // qayta yaratmasdan): specKey|course|normSubject => ma'ruza soati.
        $lecHours = $this->lectureHoursMap($board);
        // Auditoriya sig'imi (kod => hajm) — kartada "xona (sig'im)" ko'rsatish uchun.
        $roomVol = Auditorium::pluck('volume', 'code')->all();

        $cards = TimetableCard::where('board_id', $board->id)->get()->map(fn($c) => [
            'id' => $c->id,
            'specialty_name' => $c->specialty_name,
            'course' => $c->course,
            'faculty_name' => $c->faculty_name,
            'oqim_label' => $c->oqim_label,
            'lang' => $c->lang,
            'training_type' => $c->training_type,
            'group_name' => $c->group_name,
            'group_names' => $c->group_names,
            'subject_name' => $c->subject_name,
            'kafedra_name' => $c->kafedra_name,
            'students' => $c->students,
            'teacher_id' => $c->teacher_id,
            'teacher_name' => $c->teacher_name,
            'auditorium_code' => $c->auditorium_code,
            'auditorium_name' => $c->auditorium_name,
            // Biriktirilgan auditoriya sig'imi (kartada "xona (sig'im)" uchun)
            'auditorium_volume' => $c->auditorium_code ? ($roomVol[$c->auditorium_code] ?? null) : null,
            'day' => $c->day,
            'pair' => $c->pair,
            'start_half' => (int) ($c->start_half ?? 0),
            'len_half' => $c->lenHalf(),
            // Karta necha hafta o'tiladi. Yangi kartalarda ustunda saqlanadi
            // (amaliy kartalarda ham — ma'ruzali haftada paralar kamayadi).
            // Eski kartalarda ustun bo'sh — avvalgidek ma'ruza soatidan hisoblanadi.
            'weeks' => $c->weeks !== null
                ? (int) $c->weeks
                : ($c->training_type === 'lecture'
                    ? $this->lectureWeeks(
                        $lecHours[$this->specKey($c->specialty_name) . '|' . $c->course . '|' . $this->normSubject((string) $c->subject_name)] ?? 0
                    )
                    : null),
        ]);

        $grids = TimetableGridSetting::where('board_id', $board->id)
            ->get(['faculty_name', 'specialty_name', 'course', 'days', 'pairs_per_day', 'weeks']);

        // Hafta bo'yicha istisnolar (individual haftalar) — migratsiya kechiksa bo'sh
        $overrides = collect();
        if (Schema::hasTable('timetable_card_overrides')) {
            $hasOverrideRoom = Schema::hasColumn('timetable_card_overrides', 'auditorium_code');
            $overrideColumns = ['o.card_id', 'o.week', 'o.day', 'o.pair', 'o.cancelled'];
            if ($hasOverrideRoom) {
                $overrideColumns[] = 'o.auditorium_code';
                $overrideColumns[] = 'o.auditorium_name';
            }
            $overrides = DB::table('timetable_card_overrides as o')
                ->join('timetable_cards as c', 'c.id', '=', 'o.card_id')
                ->where('c.board_id', $board->id)
                ->get($overrideColumns)
                ->map(fn($o) => [
                    'card_id'   => (int) $o->card_id,
                    'week'      => (int) $o->week,
                    'day'       => $o->day !== null ? (int) $o->day : null,
                    'pair'      => $o->pair !== null ? (int) $o->pair : null,
                    'cancelled' => (bool) $o->cancelled,
                    'auditorium_code' => $hasOverrideRoom ? ($o->auditorium_code ?: null) : null,
                    'auditorium_name' => $hasOverrideRoom ? ($o->auditorium_name ?: null) : null,
                    'auditorium_volume' => $hasOverrideRoom && $o->auditorium_code
                        ? ($roomVol[$o->auditorium_code] ?? null)
                        : null,
                ]);
        }

        return response()->json([
            'board' => array_merge(
                $board->only(['id', 'name', 'institution_name', 'days', 'pairs_per_day', 'weeks',
                    'academic_year', 'semester_parity', 'kind']),
                [
                    'bell_schedule' => $board->bell_schedule ?: TimetableBoard::defaultBellSchedule((int) $board->pairs_per_day),
                    'day_names'     => $board->day_names ?: array_slice(TimetableBoard::DEFAULT_DAY_NAMES, 0, (int) $board->days),
                    'settings'      => $board->settings ?: [],
                ]
            ),
            'cards' => $cards,
            'grids' => $grids,
            'overrides' => $overrides,
            'subject_settings' => $this->subjectSettingsFor($board),
            // Rejada fani bor, lekin guruh proyeksiyasi yo'q yo'nalish+kurslar
            'missing_groups' => $this->missingGroupSpecs($board),
        ])
            // Doska ma'lumoti tez-tez o'zgaradi (kartalar qayta yaratiladi, joylashadi).
            // Brauzer eski GET javobini keshdan bermasin — aks holda yangi kartalar
            // (masalan yangi kurs) ekranda ko'rinmay qoladi.
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /** Doskaning fan-rejim sozlamalari (hafta almashinuvi / sikl) — frontend uchun. */
    private function subjectSettingsFor(TimetableBoard $board): array
    {
        if (!Schema::hasTable('timetable_subject_settings')) {
            return [];
        }
        return TimetableSubjectSetting::where('board_id', $board->id)
            ->get(['specialty_name', 'course', 'subject_name', 'mode', 'rotation_group', 'occurrences', 'cycle_days'])
            ->map(fn($s) => [
                'specialty_name' => $s->specialty_name,
                'course'         => (int) $s->course,
                'subject_name'   => $s->subject_name,
                'mode'           => $s->mode,
                'rotation_group' => $s->rotation_group,
                'occurrences'    => $s->occurrences !== null ? (int) $s->occurrences : null,
                'cycle_days'     => $s->cycle_days !== null ? (int) $s->cycle_days : null,
            ])->all();
    }

    /**
     * Fan bo'yicha jadval rejimini saqlash (hafta almashinuvi / sikl).
     * normal rejim (barcha yordamchi maydonlar bo'sh) — yozuv o'chiriladi.
     */
    public function saveSubjectSetting(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'specialty_name' => 'required|string|max:255',
            'course'         => 'required|integer|min:1|max:7',
            'subject_name'   => 'required|string|max:255',
            'mode'           => 'required|in:normal,alternate,cycle',
            'rotation_group' => 'nullable|string|max:255',
            'occurrences'    => 'nullable|integer|min:1|max:60',
            'cycle_days'     => 'nullable|integer|min:1|max:120',
        ]);

        // Mavjud yozuvni katta-kichik harfga befarq topamiz — reja (mc) nomi
        // "Davolash ishi", karta/snapshot nomi "davolash ishi" bo'lishi mumkin;
        // dublikat yaratmaslik uchun mavjudini yangilaymiz.
        $existing = TimetableSubjectSetting::where('board_id', $board->id)
            ->where('course', (int) $data['course'])
            ->whereRaw('LOWER(TRIM(specialty_name)) = ?', [mb_strtolower(trim($data['specialty_name']))])
            ->whereRaw('LOWER(TRIM(subject_name)) = ?', [mb_strtolower(trim($data['subject_name']))])
            ->first();

        // normal — sozlama shart emas, mavjud yozuvni o'chiramiz
        if ($data['mode'] === 'normal') {
            if ($existing) {
                $existing->delete();
            }
            return response()->json(['ok' => true, 'mode' => 'normal']);
        }

        $values = [
            'mode'           => $data['mode'],
            'rotation_group' => $data['mode'] === 'alternate' ? ($data['rotation_group'] ?? null) : null,
            'occurrences'    => $data['mode'] === 'alternate' ? ($data['occurrences'] ?? null) : null,
            'cycle_days'     => $data['mode'] === 'cycle' ? ($data['cycle_days'] ?? null) : null,
        ];
        if ($existing) {
            $existing->update($values);
        } else {
            TimetableSubjectSetting::create(array_merge([
                'board_id'       => $board->id,
                'specialty_name' => $data['specialty_name'],
                'course'         => (int) $data['course'],
                'subject_name'   => $data['subject_name'],
            ], $values));
        }

        return response()->json(['ok' => true, 'mode' => $data['mode']]);
    }

    // ===================== QOIDALAR (aSc "Взаимосвязи") =====================

    /** Qoidalar ro'yxati + mavjud shartlar lug'ati (dialog uchun). */
    public function rules(TimetableBoard $board)
    {
        $rules = Schema::hasTable('timetable_rules')
            ? TimetableRule::where('board_id', $board->id)
                ->orderBy('position')->orderBy('id')->get()
                ->map(fn(TimetableRule $r) => $this->ruleToArray($r))->all()
            : [];

        return response()->json([
            'rules'      => $rules,
            'conditions' => TimetableRule::CONDITIONS,
            'weights'    => TimetableRule::WEIGHTS,
        ]);
    }

    private function ruleToArray(TimetableRule $r): array
    {
        return [
            'id'          => $r->id,
            'condition'   => $r->condition,
            'description' => $r->describe(),
            'subjects'    => $r->subjects ?: [],
            'scopes'      => $r->scopes ?: [],
            'params'      => $r->params ?: [],
            'weight'      => $r->weight,
            'active'      => (bool) $r->active,
            'position'    => (int) $r->position,
            'note'        => $r->note,
        ];
    }

    /** Qoida yaratish yoki tahrirlash. */
    public function saveRule(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'id'          => 'nullable|integer',
            'condition'   => 'required|string|max:60',
            'subjects'    => 'nullable|array',
            'subjects.*'  => 'string|max:255',
            'scopes'      => 'nullable|array',
            'scopes.*'    => 'string|max:255',
            'params'                 => 'nullable|array',
            'params.distribution'    => 'nullable|in:auto,spread,odd,even',
            'weight'                 => 'nullable|string|max:20',
            'active'      => 'nullable|boolean',
            'note'        => 'nullable|string|max:255',
        ]);

        if (!array_key_exists($data['condition'], TimetableRule::CONDITIONS)) {
            return response()->json(['error' => "Noma'lum shart: " . $data['condition']], 422);
        }

        $distribution = $data['params']['distribution'] ?? null;
        if ($data['condition'] === 'lecture_week_distribution'
            && !in_array($distribution, ['auto', 'spread', 'odd', 'even'], true)) {
            return response()->json(['error' => "Ma'ruza haftalarini taqsimlash turini tanlang."], 422);
        }

        $weight = in_array($data['weight'] ?? '', TimetableRule::WEIGHTS, true) ? $data['weight'] : 'normal';

        $values = [
            'condition' => $data['condition'],
            'subjects'  => array_values($data['subjects'] ?? []),
            'scopes'    => array_values($data['scopes'] ?? []),
            'params'    => $data['condition'] === 'lecture_week_distribution'
                ? ['distribution' => $distribution]
                : ($data['params'] ?? []),
            'weight'    => $weight,
            'active'    => (bool) ($data['active'] ?? true),
            'note'      => $data['note'] ?? null,
        ];

        if (!empty($data['id'])) {
            $rule = TimetableRule::where('board_id', $board->id)->findOrFail($data['id']);
            $rule->update($values);
        } else {
            $values['board_id'] = $board->id;
            $values['position'] = (int) TimetableRule::where('board_id', $board->id)->max('position') + 1;
            $rule = TimetableRule::create($values);
        }

        return response()->json(['ok' => true, 'rule' => $this->ruleToArray($rule->fresh())]);
    }

    /** Qoidani o'chirish. */
    public function deleteRule(TimetableBoard $board, TimetableRule $rule)
    {
        abort_unless((int) $rule->board_id === (int) $board->id, 404);
        $rule->delete();

        return response()->json(['ok' => true]);
    }

    /** Qoidani yoqish/o'chirish yoki ro'yxatda ko'chirish (yuqoriga/pastga). */
    public function updateRuleState(Request $request, TimetableBoard $board, TimetableRule $rule)
    {
        abort_unless((int) $rule->board_id === (int) $board->id, 404);
        $data = $request->validate([
            'active' => 'nullable|boolean',
            'move'   => 'nullable|in:up,down',
        ]);

        if (array_key_exists('active', $data) && $data['active'] !== null) {
            $rule->update(['active' => (bool) $data['active']]);
        }

        if (!empty($data['move'])) {
            $dir = $data['move'] === 'up' ? 'up' : 'down';
            $neighbour = TimetableRule::where('board_id', $board->id)
                ->when($dir === 'up',
                    fn($q) => $q->where('position', '<', $rule->position)->orderByDesc('position'),
                    fn($q) => $q->where('position', '>', $rule->position)->orderBy('position'))
                ->first();
            if ($neighbour) {
                $mine = $rule->position;
                $rule->update(['position' => $neighbour->position]);
                $neighbour->update(['position' => $mine]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Haftalik yuk taqsimoti (tibbiyot universiteti mantig'i).
     *
     * Jami soat (ma'ruza + amaliy) semestr haftalariga teng bo'linadi — bu
     * haftalik yuk chegarasi. Ma'ruza doim 2 soat (1 para), shuning uchun
     * ma'ruzali haftalar soni = ma'ruza_soat / 2. Ma'ruzali haftada amaliyga
     * (haftalik_yuk - 2) soat, ma'ruzasiz haftada esa to'liq haftalik_yuk
     * soat beriladi. Natijada reja soatlari aniq to'ldiriladi.
     *
     * Misol: 12 s ma'ruza + 78 s amaliy = 90 s / 15 hafta = 6 s/hafta.
     * Ma'ruza 12/2 = 6 ta haftada (2 s ma'ruza + 4 s amaliy), qolgan 9 haftada
     * 6 s amaliy → amaliy jami 6*4 + 9*6 = 78 s (rejaga mos).
     *
     * Amaliy soatlar BUTUN sonlarda taqsimlanadi (eng kichik birlik — yarim para,
     * ya'ni 1 soat). Teng bo'linmasa qoldiq aniq taqsimlanadi: extra_weeks ta
     * haftaga +1 soat qo'shiladi. Shu sababli ko'rsatilgan qiymatlar yig'indisi
     * reja soatiga aniq teng bo'ladi (kasrli "4,13 soat" kabi holat chiqmaydi).
     */
    private function weeklyPlan(float $lec, float $prc, int $weeks): array
    {
        $weeks = max(1, $weeks);
        $total = $lec + $prc;
        $empty = [
            'total_hours' => 0, 'per_week_hours' => 0.0,
            'lecture_weeks' => 0, 'plain_weeks' => $weeks,
            'practice_in_lecture_week' => 0, 'practice_in_plain_week' => 0,
            'extra_weeks' => 0,
            'practice_hours_base' => 0, 'practice_hours_extra' => 0,
            'practice_extra_weeks' => 0, 'practice_remainder_weeks' => 0,
            'practice_hours_scheduled' => 0, 'practice_shortfall' => 0,
            'lecture_check' => 0.0, 'practice_check' => 0.0, 'exact' => true,
        ];
        if ($total <= 0) {
            return $empty;
        }

        $perWeek = $total / $weeks;                       // haftalik soat byudjeti (ko'rsatkich)
        $lecWeeks = min((int) round($lec / 2), $weeks);   // ma'ruza 2 soatdan
        $plainWeeks = $weeks - $lecWeeks;

        // Amaliy soat butun songa keltiriladi (reja odatda butun soatda beriladi)
        $prcInt = (int) round($prc);

        // Ideal holat: ma'ruzasiz haftada amaliy ma'ruzali haftadagidan 2 soat ko'p
        // (chunki ma'ruzali haftada 2 soatni ma'ruza egallaydi).
        if ($prcInt - 2 * $plainWeeks >= 0) {
            $prcInLec = intdiv($prcInt - 2 * $plainWeeks, $weeks);
            $prcInPlain = $prcInLec + 2;
        } else {
            // Amaliy soat kam — 2 soatlik farqni saqlab bo'lmaydi, teng taqsimlaymiz
            $prcInLec = intdiv($prcInt, $weeks);
            $prcInPlain = $prcInLec;
        }

        // Qoldiq: shuncha haftaga +1 soat qo'shiladi (yig'indi rejaga aniq tushsin)
        $allocated = $lecWeeks * $prcInLec + $plainWeeks * $prcInPlain;
        $extraWeeks = max(0, $prcInt - $allocated);

        // ── Amaliy paralarga aylantirish (1 para = 2 soat) ─────────────────
        // Har hafta o'tiladigan paralar + faqat ma'ruzasiz haftada o'tiladiganlari.
        // Kartochka yaratish AYNAN shu qiymatlarni ishlatadi (yagona manba).
        $parasLec   = intdiv($prcInLec, 2);
        // ── Amaliy soatlarni kartalarga bo'lish (SOAT aniqligida) ──────────
        // Dars uzunligi len_half birligida = SOAT (2 = 1 para = 2 soat). Shu sababli
        // 3 soatlik byudjetni ham aniq ifodalash mumkin — avval faqat 2 soatlik
        // paralarda taqsimlanib, qoldiq soatlar yo'qolib ketardi.
        //  - hBase  : har hafta o'tiladigan amaliy soat
        //  - hExtra : faqat ma'ruzasiz haftalarda qo'shimcha soat
        //  - qoldiq : extra_weeks ta haftaga +1 soat (yig'indi rejaga aniq tushsin)
        $hBase  = $lecWeeks > 0 ? min($prcInLec, $prcInPlain) : $prcInPlain;
        $hExtra = max(0, $prcInPlain - $hBase);
        $remWeeks = $extraWeeks;
        if ($prcInt <= 0) {
            $hBase = $hExtra = $remWeeks = 0;
        }
        $prcScheduled = $hBase * $weeks + $hExtra * $plainWeeks + $remWeeks;

        $lecCheck = $lecWeeks * 2;
        $prcCheck = $allocated + $extraWeeks;

        return [
            'total_hours'              => round($total, 2),
            'per_week_hours'           => round($perWeek, 2),
            'lecture_weeks'            => $lecWeeks,
            'plain_weeks'              => $plainWeeks,
            'practice_in_lecture_week' => $prcInLec,
            'practice_in_plain_week'   => $prcInPlain,
            // Nechta haftaga qoldiq sifatida +1 soat qo'shilgan
            'extra_weeks'              => $extraWeeks,
            // Amaliy soat taqsimoti (kartochka yaratish shu qiymatlarni ishlatadi)
            'practice_hours_base'      => $hBase,       // har hafta
            'practice_hours_extra'     => $hExtra,      // faqat ma'ruzasiz haftalarda
            'practice_extra_weeks'     => $plainWeeks,  // ma'ruzasiz haftalar soni
            'practice_remainder_weeks' => $remWeeks,    // +1 soatlik qoldiq haftalar
            'practice_hours_scheduled' => $prcScheduled,
            // Haftalik chegarani buzmaslik uchun joylanmay qolgan amaliy soat
            'practice_shortfall'       => max(0, $prcInt - $prcScheduled),
            'lecture_check'            => round($lecCheck, 2),
            'practice_check'           => round($prcCheck, 2),
            // Aniqlik KO'RSATILAYOTGAN taqsimot bo'yicha baholanadi: ma'ruza soati
            // 2 ga bo'linib haftaga sig'dimi va amaliy yig'indi rejaga tushdimi.
            'exact' => abs($lecCheck - $lec) < 0.01
                && abs($prcCheck - $prc) < 0.01
                && abs($prcInt - $prc) < 0.01,
        ];
    }

    /**
     * N ta haftani M ta semestr haftasiga TENG taqsimlaydi (1..M).
     * Masalan 6 ta ma'ruza 15 haftaga: 1, 3, 6, 8, 11, 13.
     */
    private function spreadWeeks(int $total, int $count): array
    {
        if ($count <= 0 || $total <= 0) {
            return [];
        }
        if ($count >= $total) {
            return range(1, $total);
        }
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = (int) floor($i * $total / $count) + 1;
        }
        return array_values(array_unique($out));
    }

    /** Toq/juft/teng/avtomatik qoida bo'yicha faol ma'ruza haftalari. */
    private function lectureWeeksForMode(
        int $total,
        int $count,
        string $mode,
        string $automaticParity = 'odd'
    ): array {
        if ($count <= 0 || $total <= 0) {
            return [];
        }
        $count = min($count, $total);

        if ($mode === 'auto') {
            // Semestrning yarmidan kam ma'ruza bo'lsa fanlar navbat bilan
            // toq/juft haftalarga ajratiladi. Yarim yoki ko'p bo'lsa 1..N.
            if ($count * 2 < $total) {
                $mode = in_array($automaticParity, ['odd', 'even'], true)
                    ? $automaticParity
                    : 'odd';
            } else {
                return range(1, $count);
            }
        }

        if (!in_array($mode, ['odd', 'even'], true)) {
            return $this->spreadWeeks($total, $count);
        }

        $parity = $mode === 'odd' ? 1 : 0;
        $candidates = array_values(array_filter(
            range(1, $total),
            fn($week) => $week % 2 === $parity
        ));

        // Tanlangan parityda yetarli hafta bo'lmasa reja soatini yo'qotmaymiz:
        // xavfsiz zaxira sifatida teng taqsimlash ishlaydi.
        return $count <= count($candidates)
            ? array_slice($candidates, 0, $count)
            : $this->spreadWeeks($total, $count);
    }

    /**
     * Kartochkalar qaysi haftalarda o'tilishini belgilaydi (timetable_card_overrides
     * dagi "cancelled" orqali). "Ma'ruza haftalarini taqsimlash" qoidasi barcha
     * fanlarga umumiy yoki fan/yo'nalish-kurs kesimida berilishi mumkin.
     * Fan/qamrovga xos qoida umumiy qoidadan ustun; bir xil aniqlikda ro'yxatda
     * yuqoriroq turgan qoida olinadi.
     */
    private function assignCardWeeks(TimetableBoard $board): void
    {
        if (!Schema::hasTable('timetable_card_overrides') || !Schema::hasColumn('timetable_cards', 'weeks')) {
            return;
        }

        $gset = TimetableGridSetting::where('board_id', $board->id)->get()
            ->mapWithKeys(fn($g) => [
                ($g->faculty_name ?? '') . '|' . $this->specKey($g->specialty_name) . '|' . $g->course => (int) $g->weeks,
            ])->all();
        $boardWeeks = max(1, (int) $board->weeks);
        $totalFor = function ($c) use ($gset, $boardWeeks): int {
            $sk = $this->specKey($c->specialty_name);
            return max(1, (int) ($gset[($c->faculty_name ?? '') . '|' . $sk . '|' . $c->course]
                ?? $gset['|' . $sk . '|' . $c->course] ?? $boardWeeks));
        };
        $subjectKey = fn($c) => $this->specKey($c->specialty_name) . '|' . (int) $c->course
            . '|' . $this->normSubject((string) $c->subject_name);

        $distributionRules = Schema::hasTable('timetable_rules')
            ? TimetableRule::where('board_id', $board->id)
                ->where('active', true)
                ->where('condition', 'lecture_week_distribution')
                ->orderBy('position')->orderBy('id')->get()
            : collect();
        $distributionFor = function ($c) use ($distributionRules): string {
            $scopeLabel = $c->specialty_name . ' · ' . (int) $c->course;
            $bestMode = 'auto';
            $bestScore = -1;
            $bestPosition = PHP_INT_MAX;

            foreach ($distributionRules as $rule) {
                $subjects = $rule->subjects ?: [];
                $scopes = $rule->scopes ?: [];
                if ($subjects && !in_array($c->subject_name, $subjects, true)) {
                    continue;
                }
                if ($scopes && !in_array($scopeLabel, $scopes, true)) {
                    continue;
                }

                $score = ($subjects ? 2 : 0) + ($scopes ? 1 : 0);
                $position = (int) $rule->position;
                if ($score < $bestScore || ($score === $bestScore && $position >= $bestPosition)) {
                    continue;
                }

                $params = $rule->params ?: [];
                $mode = $params['distribution'] ?? 'auto';
                $bestMode = in_array($mode, ['auto', 'spread', 'odd', 'even'], true) ? $mode : 'auto';
                $bestScore = $score;
                $bestPosition = $position;
            }

            return $bestMode;
        };

        $cards = TimetableCard::where('board_id', $board->id)
            ->whereNotNull('weeks')
            ->get(['id', 'faculty_name', 'specialty_name', 'course', 'subject_name', 'training_type', 'weeks']);
        if ($cards->isEmpty()) {
            return;
        }

        // Fan bo'yicha eng katta ma'ruza kartasi vakil bo'ladi. Uning tanlangan
        // haftalari shu fanni almashtiruvchi qo'shimcha amaliyga teskari qo'llanadi.
        $lectureCardsBySubject = [];
        foreach ($cards as $c) {
            if ($c->training_type !== 'lecture') {
                continue;
            }
            $key = $subjectKey($c);
            if (!isset($lectureCardsBySubject[$key])
                || (int) $c->weeks > (int) $lectureCardsBySubject[$key]->weeks) {
                $lectureCardsBySubject[$key] = $c;
            }
        }

        // Faqat avtomatik va semestr yarmidan kam ma'ruza qilinadigan fanlar
        // barqaror tartibda navbat bilan toq/juftga beriladi.
        $automaticCandidates = [];
        foreach ($lectureCardsBySubject as $key => $lectureCard) {
            $total = $totalFor($lectureCard);
            if ($distributionFor($lectureCard) === 'auto'
                && (int) $lectureCard->weeks * 2 < $total) {
                $automaticCandidates[] = $key;
            }
        }
        sort($automaticCandidates, SORT_NATURAL | SORT_FLAG_CASE);
        $automaticParityBySubject = [];
        foreach ($automaticCandidates as $index => $key) {
            $automaticParityBySubject[$key] = $index % 2 === 0 ? 'odd' : 'even';
        }

        $lectureWeeksBySubject = [];
        $lectureActiveBySubject = [];
        foreach ($lectureCardsBySubject as $key => $lectureCard) {
            $count = (int) $lectureCard->weeks;
            $lectureWeeksBySubject[$key] = $count;
            $lectureActiveBySubject[$key] = $this->lectureWeeksForMode(
                $totalFor($lectureCard),
                $count,
                $distributionFor($lectureCard),
                $automaticParityBySubject[$key] ?? 'odd'
            );
        }

        $now = now();
        $ins = [];
        foreach ($cards as $c) {
            $total = $totalFor($c);
            $cw = (int) $c->weeks;
            if ($cw >= $total) {
                continue;   // har hafta o'tiladi — istisno kerak emas
            }

            $key = $subjectKey($c);
            $lectureCount = (int) ($lectureWeeksBySubject[$key] ?? 0);
            $lectureActive = $lectureActiveBySubject[$key]
                ?? $this->spreadWeeks($total, $lectureCount);

            if ($c->training_type === 'lecture') {
                $active = $lectureActive;
            } elseif ($lectureCount > 0 && $cw === $total - $lectureCount) {
                // Ma'ruzani almashtiruvchi qo'shimcha amaliy — ma'ruzasiz haftalar.
                $active = array_values(array_diff(range(1, $total), $lectureActive));
            } else {
                $active = $this->spreadWeeks($total, $cw);
            }

            $activeSet = array_flip($active);
            for ($week = 1; $week <= $total; $week++) {
                if (!isset($activeSet[$week])) {
                    $ins[] = [
                        'card_id' => $c->id,
                        'week' => $week,
                        'day' => null,
                        'pair' => null,
                        'cancelled' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if ($ins) {
            DB::transaction(function () use ($ins) {
                foreach (array_chunk($ins, 1000) as $chunk) {
                    DB::table('timetable_card_overrides')->insert($chunk);
                }
            });
        }
    }

    /**
     * Haftalik amaliy soatni dars kartalariga bo'ladi. Qaytadi: len_half
     * qiymatlari ro'yxati (len_half birligi = SOAT; 2 = 1 para = 2 soat).
     * Sukut — 2 soatlik paralar; toq soat qolsa oxirgisi 3 soatga uzaytiriladi
     * (amaliyotda 1 soatlik alohida darsdan ko'ra qulayroq), 1 soatning o'zi
     * qolsa — bitta 1 soatlik dars.
     */
    private function splitPracticeHours(int $hours): array
    {
        if ($hours <= 0) {
            return [];
        }
        $out = [];
        while ($hours >= 2) {
            $out[] = 2;
            $hours -= 2;
        }
        if ($hours === 1) {
            if ($out) {
                $out[count($out) - 1] = 3;   // oxirgi 2 soatlikni 3 soatga uzaytiramiz
            } else {
                $out[] = 1;
            }
        }
        return $out;
    }

    /** Guruhcha nomidan asosiy guruh (oxirgi kichik harf — a/b/c — olib tashlanadi). */
    private function baseGroup(string $gn): string
    {
        // "p22-02a" → "p22-02", "d1/23 10a" → "d1/23 10" (raqamdan keyingi bitta harf)
        return preg_replace('/([0-9])\s*[a-z]$/iu', '$1', trim($gn));
    }

    /**
     * Sikl (4-6 kurs) kalendar rejasi: sana × guruh, har guruh o'z sikl fanlarini
     * ketma-ket blok qilib o'taydi (guruhlar surilib — rotatsiya). Birlik: o'quv kuni.
     * Bu — birinchi versiya: bloklar ketma-ket, guruh indeksi bo'yicha aylantiriladi.
     */
    public function cyclePlan(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'start_date'        => 'nullable|date',
            'holidays'          => 'nullable|array',
            'holidays.*'        => 'nullable|date',
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
        ]);
        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $inScope = function ($c) use ($facSet, $specSet, $courseSet) {
            if ($facSet !== null && !isset($facSet[(string) ($c->faculty_name ?? '')])) return false;
            if ($specSet !== null && !isset($specSet[(string) $c->specialty_name])) return false;
            if ($courseSet !== null && !isset($courseSet[(int) $c->course])) return false;
            return true;
        };

        // Semestr boshlanish sanasi (so'rovdan / sozlamadan / o'quv yilidan)
        $set = $board->settings ?? [];
        $start = $data['start_date'] ?? ($set['semester_start'] ?? null);
        if (!$start) {
            $yearStart = (int) preg_replace('/\D.*$/', '', (string) $board->academic_year);
            if ($yearStart < 2000) {
                $yearStart = (int) date('Y');
            }
            $start = $board->semester_parity === 'bahorgi'
                ? sprintf('%04d-02-01', $yearStart + 1)
                : sprintf('%04d-09-01', $yearStart);
        }
        $startC = Carbon::parse($start)->startOfDay();

        // Bayram (dam olish) kunlari — so'rovdan yoki sozlamadan. Y-m-d ga keltiramiz.
        $holInput = $data['holidays'] ?? ($set['holidays'] ?? []);
        $holSet = [];
        foreach ((array) $holInput as $hd) {
            $hd = trim((string) $hd);
            if ($hd === '') {
                continue;
            }
            try {
                $holSet[Carbon::parse($hd)->toDateString()] = true;
            } catch (\Exception $e) { /* noto'g'ri sana — e'tibor bermaymiz */ }
        }
        $holidays = array_keys($holSet);
        sort($holidays);

        // Semestr sanasi va bayramlarni sozlamaga saqlaymiz (keyingi safar eslab qolish uchun)
        if (($set['semester_start'] ?? null) !== $startC->toDateString() || ($set['holidays'] ?? []) !== $holidays) {
            $set['semester_start'] = $startC->toDateString();
            $set['holidays'] = $holidays;
            $board->update(['settings' => $set]);
        }

        // O'quv kunlari kalendari: haftasiga board->days ta ish kuni (Dush=1..),
        // yakshanba (va board->days dan keyingi kunlar) hamda bayram kunlari o'tkaziladi.
        $D = max(1, (int) $board->days);
        $W = max(1, (int) $board->weeks);
        $dates = [];
        $cur = $startC->copy();
        $guard = 0;
        while (count($dates) < $W * $D && $guard < $W * 7 + count($holSet) * 2 + 60) {
            if ((int) $cur->dayOfWeekIso <= $D && !isset($holSet[$cur->toDateString()])) {
                $dates[] = $cur->copy();
            }
            $cur->addDay();
            $guard++;
        }
        $totalDays = count($dates);

        // Nomlarni normallashtiramiz — reja (mc) va karta/snapshot nomlari katta-kichik
        // harf/bo'shliqda farq qilishi mumkin (mas. "Davolash ishi" ↔ "davolash ishi").
        $normKey = fn($spec, $course, $subj) =>
            mb_strtolower(trim((string) $spec)) . '|' . (int) $course . '|' . mb_strtolower(trim((string) $subj));

        // Sikl fanlari: normalized(spec|course|subject) => cycle_days
        // Barcha sikl sozlamalarini (doska bo'yicha) olamiz — qamrovni kartalar
        // (inScope) o'zi cheklaydi, shu sabab bu yerda scope filtri shart emas.
        $cycleKey = [];
        if (Schema::hasTable('timetable_subject_settings')) {
            foreach (TimetableSubjectSetting::where('board_id', $board->id)->where('mode', 'cycle')->get() as $s) {
                $cycleKey[$normKey($s->specialty_name, $s->course, $s->subject_name)] = max(1, (int) ($s->cycle_days ?? 1));
            }
        }

        // Guruhlar (asosiy guruh bo'yicha) va ularning sikl fanlari — kartalardan
        $byGroup = [];
        if (!empty($cycleKey)) {
            foreach (TimetableCard::where('board_id', $board->id)->get() as $c) {
                if ($c->training_type !== 'practice' || !$c->group_name || !$inScope($c)) {
                    continue;
                }
                $ck = $normKey($c->specialty_name, $c->course, $c->subject_name);
                if (!isset($cycleKey[$ck])) {
                    continue;
                }
                $base = $this->baseGroup($c->group_name);
                if (!isset($byGroup[$base])) {
                    $byGroup[$base] = ['name' => $base, 'subs' => [], 'members' => [],
                        'faculty' => $c->faculty_name, 'specialty' => $c->specialty_name, 'course' => (int) $c->course];
                }
                $byGroup[$base]['subs'][$c->subject_name] = $cycleKey[$ck];
                $byGroup[$base]['members'][$c->group_name] = true;
            }
        }

        // Fanlar global tartibi (nomi bo'yicha) — rotatsiya uchun
        $allSubs = [];
        foreach ($byGroup as $g) {
            foreach ($g['subs'] as $sn => $dd) {
                $allSubs[$sn] = $dd;
            }
        }
        ksort($allSubs);
        $subOrder = array_keys($allSubs);

        $groups = array_values($byGroup);
        usort($groups, fn($a, $b) => strnatcmp($a['name'], $b['name']));

        $rows = [];
        foreach ($groups as $gi => $g) {
            // Shu guruh fanlari, global tartibda, guruh indeksi bo'yicha aylantirilgan
            $order = array_merge(array_slice($subOrder, $gi % max(1, count($subOrder))),
                                 array_slice($subOrder, 0, $gi % max(1, count($subOrder))));
            $blocks = [];
            $idx = 0;
            foreach ($order as $sn) {
                if (!isset($g['subs'][$sn])) {
                    continue;
                }
                if ($idx >= $totalDays) {
                    break;
                }
                $days = max(1, (int) $g['subs'][$sn]);
                $to = min($idx + $days - 1, $totalDays - 1);
                $blocks[] = ['subject' => $sn, 'from' => $idx, 'to' => $to, 'days' => $to - $idx + 1];
                $idx = $to + 1;
            }
            $members = array_keys($g['members']);
            sort($members, SORT_NATURAL);
            $rows[] = [
                'group'     => $g['name'],
                'subgroups' => $members,
                'faculty'   => $g['faculty'],
                'specialty' => $g['specialty'],
                'course'    => $g['course'],
                'blocks'    => $blocks,
            ];
        }

        return response()->json([
            'start_date' => $startC->toDateString(),
            'holidays'   => $holidays,
            'total_days' => $totalDays,
            'dates'      => array_map(fn($d) => ['d' => $d->format('d.m'), 'iso' => $d->toDateString(), 'dow' => (int) $d->dayOfWeekIso], $dates),
            'subjects'   => array_map(fn($sn) => ['name' => $sn, 'days' => $allSubs[$sn]], $subOrder),
            'rows'       => $rows,
        ]);
    }

    /**
     * Ekrandagi panjarani haqiqiy .xlsx faylga yozadi.
     *
     * Klient katak ma'lumotini ixcham JSON qilib yuboradi:
     *   {"rows": [[{"t":"matn","cs":2,"rs":1,"bg":"#fde68a","b":1}, ...], ...],
     *    "freeze_rows": 2, "freeze_cols": 2}
     * Ilgari butun jadval inline-uslubli HTML bo'lib kelib, PhpSpreadsheet ning
     * HTML o'quvchisi orqali o'qilardi — katta doskada (o'n minglab katak) bu
     * juda sekin bo'lib, so'rov timeout bo'lardi va klient jimgina HTML ni
     * ".xls" nomi bilan saqlab qo'yardi (Excel "format kengaytmaga mos emas"
     * deb ogohlantirardi). Endi kataklar to'g'ridan-to'g'ri yoziladi.
     *
     * Eski klientlar uchun "html" maydoni ham qabul qilinadi (zaxira yo'l).
     */
    public function excelExport(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'payload'  => 'nullable|string',
            'html'     => 'nullable|string',
            'title'    => 'nullable|string|max:255',
            'filename' => 'nullable|string|max:150',
        ]);
        if (empty($data['payload']) && empty($data['html'])) {
            return response()->json(['error' => 'Yuklash uchun ma\'lumot yuborilmadi.'], 422);
        }

        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $base = preg_replace('/[^\w\-]+/u', '_', (string) ($data['filename'] ?? 'dars-jadvali')) ?: 'dars-jadvali';

        try {
            $spreadsheet = !empty($data['payload'])
                ? $this->buildGridSpreadsheet(
                    json_decode($data['payload'], true, 32, JSON_THROW_ON_ERROR),
                    (string) ($data['title'] ?? '')
                )
                : $this->buildSpreadsheetFromHtml((string) $data['html']);

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $base . '.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Timetable excel-export xatosi: ' . $e->getMessage());
            return response()->json(['error' => 'Excel yaratishda xatolik: ' . $e->getMessage()], 500);
        }
    }

    /** Katak ma'lumotidan (klientdan kelgan JSON) xlsx varaqni tuzadi. */
    private function buildGridSpreadsheet(array $payload, string $title): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $rows = $payload['rows'] ?? [];
        if (!is_array($rows) || !$rows) {
            throw new \RuntimeException('Panjara bo\'sh.');
        }
        // Excel chegaralari — bundan kattasini baribir ocholmaydi.
        if (count($rows) > 10000) {
            throw new \RuntimeException('Jadval juda katta (' . count($rows) . ' qator). Qamrovni kichraytiring.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Dars jadvali');

        $firstRow = 1;
        if ($title !== '') {
            $sheet->setCellValue('A1', $title);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
            $firstRow = 3;
        }

        $occupied = [];   // "qator|ustun" => true (birlashtirilgan kataklar egallagan joy)
        $maxCol = 1;
        $maxRow = $firstRow;

        foreach (array_values($rows) as $r => $cells) {
            if (!is_array($cells)) {
                continue;
            }
            $excelRow = $firstRow + $r;
            $col = 1;
            foreach ($cells as $cell) {
                if (!is_array($cell)) {
                    continue;
                }
                while (isset($occupied[$excelRow . '|' . $col])) {
                    $col++;
                }
                $cs = max(1, min(200, (int) ($cell['cs'] ?? 1)));
                $rs = max(1, min(200, (int) ($cell['rs'] ?? 1)));
                $from = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $excelRow;
                $to = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + $cs - 1) . ($excelRow + $rs - 1);

                $text = trim((string) ($cell['t'] ?? ''));
                if ($text !== '') {
                    $sheet->setCellValueExplicit(
                        $from,
                        $text,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                    );
                }
                if ($cs > 1 || $rs > 1) {
                    $sheet->mergeCells($from . ':' . $to);
                }
                $bg = $this->argbColor($cell['bg'] ?? null);
                $bold = !empty($cell['b']);
                if ($bg !== null || $bold) {
                    $style = $sheet->getStyle($cs > 1 || $rs > 1 ? $from . ':' . $to : $from);
                    if ($bg !== null) {
                        $style->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB($bg);
                    }
                    if ($bold) {
                        $style->getFont()->setBold(true);
                    }
                }

                for ($i = 0; $i < $cs; $i++) {
                    for ($j = 0; $j < $rs; $j++) {
                        $occupied[($excelRow + $j) . '|' . ($col + $i)] = true;
                    }
                }
                $col += $cs;
                $maxCol = max($maxCol, $col - 1);
                $maxRow = max($maxRow, $excelRow + $rs - 1);
            }
        }

        // Umumiy ko'rinish: chegaralar, markazlash, matnni o'rash, ustun kengligi.
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($maxCol);
        $dim = 'A' . $firstRow . ':' . $lastCol . $maxRow;
        $sheet->getStyle($dim)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF888888'));
        $sheet->getStyle($dim)->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($dim)->getFont()->setSize(9);

        for ($i = 1; $i <= $maxCol; $i++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))
                ->setWidth($i <= 2 ? 7 : 16);
        }

        // Sarlavha qatorlari va chap ustunlarni qotirish (skrollda ko'rinib tursin)
        $fr = max(0, (int) ($payload['freeze_rows'] ?? 0));
        $fc = max(0, (int) ($payload['freeze_cols'] ?? 0));
        if (($fr > 0 || $fc > 0) && $fc < $maxCol) {
            $sheet->freezePane(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fc + 1) . ($firstRow + $fr)
            );
        }

        return $spreadsheet;
    }

    /** "#fde68a" → "FFFDE68A" (yaroqsiz bo'lsa null). */
    private function argbColor($hex): ?string
    {
        $hex = ltrim((string) $hex, '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }
        $up = strtoupper($hex);
        return $up === 'FFFFFF' ? null : 'FF' . $up;   // oq — sukut, bo'yash shart emas
    }

    /** Eski klient uchun zaxira yo'l: inline-uslubli HTML jadvalni o'qish. */
    private function buildSpreadsheetFromHtml(string $html): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ttx');
        $tmpHtml = $tmp . '.html';
        @rename($tmp, $tmpHtml);
        file_put_contents($tmpHtml, $html);
        try {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
            $spreadsheet = $reader->load($tmpHtml);
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Dars jadvali');
            $dim = $sheet->calculateWorksheetDimension();
            if ($dim && strpos($dim, ':') !== false) {
                $sheet->getStyle($dim)->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF888888'));
                $sheet->getStyle($dim)->getAlignment()->setVertical(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                );
            }
            return $spreadsheet;
        } finally {
            @unlink($tmpHtml);
        }
    }

    /**
     * Hafta bo'yicha dars istisnosi: shu haftada ko'chirish / bekor qilish / shablonga qaytarish.
     * Faqat tanlangan haftaga ta'sir qiladi — boshqa haftalar shablon bo'yicha qoladi.
     */
    /**
     * Tanlangan HAFTANI tepaga zichlash.
     *
     * Ba'zi ma'ruzalar har haftada o'tilmaydi (shu haftada bekor qilingan) —
     * ular bo'shatgan vaqt bo'sh turadi, amaliy esa pastda qoladi. Bu amal shu
     * hafta uchun darslarni kun boshiga suradi: har karta o'z kunida eng erta
     * bo'sh yarim-slotga ko'chiriladi, guruh / o'qituvchi / auditoriya bandligi
     * hisobga olingan holda. Faqat shu haftaga istisno (override) yoziladi —
     * shablon (barcha haftalar) o'zgarmaydi.
     */
    public function compactWeek(Request $request, TimetableBoard $board)
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
        $data = $request->validate([
            'week'            => 'required|integer|min:1|max:30',
            'faculty_names'   => 'nullable|array',
            'specialty_names' => 'nullable|array',
            'courses'         => 'nullable|array',
            'training_type'   => 'nullable|in:lecture,practice',
        ]);
        $week = (int) $data['week'];
        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $inScope = function ($c) use ($facSet, $specSet, $courseSet) {
            if ($facSet !== null && !isset($facSet[(string) ($c->faculty_name ?? '')])) return false;
            if ($specSet !== null && !isset($specSet[(string) $c->specialty_name])) return false;
            if ($courseSet !== null && !isset($courseSet[(int) $c->course])) return false;
            return true;
        };

        $all = TimetableCard::where('board_id', $board->id)->get()->keyBy('id');
        $ovr = TimetableCardOverride::whereHas('card', fn($q) => $q->where('board_id', $board->id))
            ->where('week', $week)->get()->keyBy('card_id');

        $trainingType = $data['training_type'] ?? null;
        // Shu qamrovdagi eski vaqt/xona ko'chirishlari qayta hisoblanadi. Yangi
        // natija yozilishidan oldin ular bitta transaction ichida almashtiriladi;
        // aks holda eski 3-para override qolib, keyingi karta 7-parada qolishi mumkin.
        $replaceCardIds = $ovr->filter(function ($override) use ($all, $inScope, $trainingType) {
            if ($override->cancelled) {
                return false;
            }
            $card = $all->get($override->card_id);
            return $card && $inScope($card)
                && (!$trainingType || $card->training_type === $trainingType);
        })->pluck('card_id')->map(fn($id) => (int) $id)->values()->all();

        // Qayta zichlash eski ko'chirish override'laridan emas, bazaviy shablondan
        // boshlanadi. Bekor qilishlar va qamrovdan tashqaridagi ko'chirishlar esa
        // saqlanadi — ular shu hafta uchun haqiqiy bandlik hisoblanadi.
        $calcOvr = $ovr->filter(function ($override) use ($all, $inScope, $trainingType) {
            if ($override->cancelled) {
                return true;
            }
            $card = $all->get($override->card_id);
            if (!$card) {
                return false;
            }
            $inTarget = $inScope($card) && (!$trainingType || $card->training_type === $trainingType);
            return !$inTarget;
        });

        $moves = $this->compactWeekMoves($board, $all, $calcOvr, $inScope, $trainingType);
        $this->saveWeekMoves($moves, $week, $replaceCardIds);

        return response()->json(['ok' => true, 'moved' => count($moves)]);
    }

    /**
     * Bir haftani tepaga zichlash uchun ko'chirishlar ro'yxati (DBga yozmaydi).
     * Har karta o'z kunidagi eng erta bo'sh yarim-slotga suriladi; guruh
     * (fakultet+yo'nalish+kurs qamrovida), o'qituvchi va auditoriya bandligi
     * hisobga olinadi — qamrovdan tashqaridagi darslar ham band sanaladi.
     *
     * @param  \Illuminate\Support\Collection  $all  board kartalari (id bo'yicha)
     * @param  \Illuminate\Support\Collection  $ovr  shu haftaning istisnolari (card_id bo'yicha)
     * @return array<int,array{card_id:int,day:int,pair:int}>
     */
    private function compactWeekMoves(TimetableBoard $board, $all, $ovr, callable $inScope, ?string $trainingType): array
    {
        $pairs = $board->pairCount();

        // Shu haftadagi effektiv joylashuv va auditoriya (bekor qilinganlar tushib qoladi).
        $eff = [];
        foreach ($all as $c) {
            $ov = $ovr->get($c->id);
            if ($ov) {
                if ($ov->cancelled) {
                    continue;
                }
                $d = $ov->day;
                $p = $ov->pair;
            } else {
                $d = $c->day;
                $p = $c->pair;
            }
            if (!$d || !$p) {
                continue;
            }
            $roomCode = $ov?->auditorium_code ?: $c->auditorium_code;
            $roomName = $ov?->auditorium_name ?: $c->auditorium_name;
            $eff[$c->id] = [(int) $d, (int) $p, $roomCode, $roomName];
        }

        // Bandlik xaritalari — shu haftadagi BARCHA darslardan.
        $gBusy = [];
        $tBusy = [];
        $rBusy = [];
        $mark = function (TimetableCard $c, int $d, int $p, ?string $roomCode, bool $on) use (&$gBusy, &$tBusy, &$rBusy) {
            $len = $this->parasNeeded($c);
            $scope = $this->groupScopeKey($c);
            for ($i = 0; $i < $len; $i++) {
                $slot = $d . '|' . ($p + $i);
                foreach ($c->occupiedGroups() as $g) {
                    $k = $scope . '|' . $g . '|' . $slot;
                    if ($on) {
                        $gBusy[$k] = ($gBusy[$k] ?? 0) + 1;
                    } elseif (($gBusy[$k] ?? 0) <= 1) {
                        unset($gBusy[$k]);
                    } else {
                        $gBusy[$k]--;
                    }
                }
                if ($c->teacher_id) {
                    $k = 'T' . $c->teacher_id . '|' . $slot;
                    if ($on) {
                        $tBusy[$k] = ($tBusy[$k] ?? 0) + 1;
                    } elseif (($tBusy[$k] ?? 0) <= 1) {
                        unset($tBusy[$k]);
                    } else {
                        $tBusy[$k]--;
                    }
                }
                if ($roomCode) {
                    $k = 'R' . $roomCode . '|' . $slot;
                    if ($on) {
                        $rBusy[$k] = ($rBusy[$k] ?? 0) + 1;
                    } elseif (($rBusy[$k] ?? 0) <= 1) {
                        unset($rBusy[$k]);
                    } else {
                        $rBusy[$k]--;
                    }
                }
            }
        };
        foreach ($eff as $id => [$d, $p, $roomCode, $roomName]) {
            $mark($all[$id], $d, $p, $roomCode, true);
        }

        // Haftalik ko'chirishda joriy xona band bo'lsa, sig'imi yetadigan boshqa
        // faol auditoriya tanlanadi. Ma'ruzaga ma'ruza tipidagi xona afzal.
        $settings = $board->settings ?? [];
        $roomTolPct = max(0, min(30, (int) ($settings['room_tolerance_pct'] ?? 5)));
        $rooms = Auditorium::where('active', true)->orderBy('volume')->get([
            'id', 'code', 'name', 'volume', 'auditorium_type_name',
        ]);
        $roomTeacherMap = $this->auditoriumTeacherMap();
        $roomOptionCache = [];
        $roomOptionsFor = function (TimetableCard $c, ?string $preferredCode) use (
            $rooms, $roomTeacherMap, $roomTolPct, &$roomOptionCache
        ) {
            $cacheKey = $c->id . '|' . ($preferredCode ?? '');
            if (isset($roomOptionCache[$cacheKey])) {
                return $roomOptionCache[$cacheKey];
            }
            $minVolume = (int) ceil((int) $c->students * (100 - $roomTolPct) / 100);
            $eligible = [];
            foreach ($rooms as $room) {
                if ((int) $room->volume < $minVolume) {
                    continue;
                }
                if (!$this->auditoriumAllowedForCard($room, $c, $roomTeacherMap)) {
                    continue;
                }
                $eligible[] = $room;
            }
            usort($eligible, function ($a, $b) use ($c, $preferredCode) {
                $aPreferred = (string) $a->code === (string) $preferredCode ? 0 : 1;
                $bPreferred = (string) $b->code === (string) $preferredCode ? 0 : 1;
                $aLecture = $c->training_type === 'lecture'
                    ? (mb_stripos((string) $a->auditorium_type_name, 'ruza') !== false ? 0 : 1)
                    : 0;
                $bLecture = $c->training_type === 'lecture'
                    ? (mb_stripos((string) $b->auditorium_type_name, 'ruza') !== false ? 0 : 1)
                    : 0;
                return [$aPreferred, $aLecture, (int) $a->volume, (string) $a->code]
                    <=> [$bPreferred, $bLecture, (int) $b->volume, (string) $b->code];
            });
            return $roomOptionCache[$cacheKey] = $eligible;
        };

        // Nomzodlar — qamrovdagi kartalar; erta paradagilar avval suriladi.
        $cands = [];
        foreach ($eff as $id => [$d, $p, $roomCode, $roomName]) {
            $c = $all[$id];
            if (!$inScope($c)) {
                continue;
            }
            if ($trainingType && $c->training_type !== $trainingType) {
                continue;
            }
            $cands[] = [$c, $d, $p, $roomCode, $roomName];
        }
        usort($cands, fn($a, $b) => [$a[1], $a[2]] <=> [$b[1], $b[2]]);

        // Ketma-ket sozlamasi yoqilganida bazaviy shablonda yonma-yon turgan
        // bir fan + bir guruh kartalari bitta atomar blok sifatida suriladi.
        $consecutive = (bool) (($board->settings ?? [])['pair_consecutive'] ?? false);
        $units = [];
        if (!$consecutive) {
            foreach ($cands as $item) {
                $units[] = [$item];
            }
        } else {
            $clusters = [];
            foreach ($cands as $item) {
                [$c, $d, $p] = $item;
                $baseDay = (int) ($c->day ?: $d);
                $key = $this->spreadKey($c) . '|' . $baseDay . '|' . $d;
                $clusters[$key][] = $item;
            }
            foreach ($clusters as $items) {
                usort($items, function ($a, $b) {
                    $ap = (int) ($a[0]->pair ?: $a[2]);
                    $bp = (int) ($b[0]->pair ?: $b[2]);
                    return [$ap, (int) $a[0]->id] <=> [$bp, (int) $b[0]->id];
                });
                $chain = [];
                $previousEnd = null;
                foreach ($items as $item) {
                    [$c, $d, $p] = $item;
                    $basePair = (int) ($c->pair ?: $p);
                    if (!empty($chain) && $basePair !== $previousEnd) {
                        $units[] = $chain;
                        $chain = [];
                    }
                    $chain[] = $item;
                    $previousEnd = $basePair + $this->parasNeeded($c);
                }
                if (!empty($chain)) {
                    $units[] = $chain;
                }
            }
        }

        // ── Hafta ichida FAN bo'yicha guruhlash ──────────────────────────────
        // Bir fanning bo'laklari (ma'ruza / o'rin bosuvchi amaliy / har haftalik
        // amaliy) hafta bo'yicha turli slotlarda qolishi mumkin: ma'ruza sloti
        // boshqa fan bilan almashsa, o'rin bosuvchi amaliy u yerga tusha olmaydi.
        // Shu sababli zichlashda birliklar fan bo'yicha guruhlanadi va ketma-ket
        // tiziladi — guruh uchun dars yaxlit chiqadi. Fanlar tartibi o'zgarmaydi:
        // har fan o'zining eng erta bo'lagi bo'yicha joyda qoladi.
        // Kalitga OQIM ham kiradi. Busiz bir yo'nalish+kursdagi barcha oqimlar
        // bitta hisobga qo'shilib ketardi: boshqa oqimning o'sha fani kun boshida
        // tursa, ikki fanning tartib raqami tenglashib, saralash oddiy para
        // tartibiga tushib qolardi va guruhlash umuman ishlamasdi.
        $subjectKeyOf = fn(TimetableCard $c) => $this->groupScopeKey($c)
            . '|' . (string) $c->oqim_label
            . '|' . $this->normSubject((string) $c->subject_name);
        $unitMeta = [];
        $subjectRank = [];
        foreach ($units as $i => $unit) {
            $day = (int) $unit[0][1];
            $pair = min(array_map(fn($x) => (int) $x[2], $unit));
            $key = $day . '|' . $subjectKeyOf($unit[0][0]);
            $unitMeta[$i] = ['day' => $day, 'pair' => $pair, 'subject' => $key];
            $subjectRank[$key] = isset($subjectRank[$key]) ? min($subjectRank[$key], $pair) : $pair;
        }
        $ordered = [];
        foreach ($units as $i => $unit) {
            $ordered[] = [$unitMeta[$i], $unit];
        }
        // Tartib raqami teng bo'lsa fan nomi bo'yicha ajratiladi — aks holda
        // ikki fanning birliklari yana bir-biriga kirib ketadi.
        usort($ordered, fn($a, $b) => [
            $a[0]['day'], $subjectRank[$a[0]['subject']], $a[0]['subject'], $a[0]['pair'],
        ] <=> [
            $b[0]['day'], $subjectRank[$b[0]['subject']], $b[0]['subject'], $b[0]['pair'],
        ]);
        $units = array_map(fn($x) => $x[1], $ordered);

        // Qayta tizishdan OLDIN barcha ko'chiriladigan birliklar bandlik
        // xaritasidan olib tashlanadi. Aks holda birlik joylashayotganda
        // keyingi birliklar hali eski o'rnida band turib, fan bo'yicha
        // guruhlash ishlamay qolardi (dars o'z fanining yoniga tusholmasdi).
        $pending = [];
        foreach ($units as $unit) {
            $baseStart = min(array_map(fn($x) => (int) ($x[0]->pair ?: $x[2]), $unit));
            $parts = [];
            $blockEnd = $baseStart;
            foreach ($unit as [$c, $effectiveDay, $effectivePair, $effectiveRoomCode, $effectiveRoomName]) {
                $basePair = (int) ($c->pair ?: $effectivePair);
                $len = $this->parasNeeded($c);
                $parts[] = [
                    $c, (int) $effectiveDay, (int) $effectivePair,
                    $effectiveRoomCode, $effectiveRoomName,
                    $basePair - $baseStart, $len,
                ];
                $blockEnd = max($blockEnd, $basePair + $len);
                $mark($c, (int) $effectiveDay, (int) $effectivePair, $effectiveRoomCode, false);
            }
            $pending[] = [(int) $unit[0][1], $parts, $blockEnd - $baseStart];
        }

        // Bitta bo'lak $d kunida $np dan boshlab tura oladimi? Qaytadi:
        //   Auditorium — mos xona topildi
        //   null       — xona umuman kerak emas
        //   false      — sig'madi (guruh, o'qituvchi yoki xona band)
        // Topilgan xona $roomHold ga yoziladi — bitta blokning bo'laklari
        // bir-birining xonasini qayta olmasligi uchun.
        $partFits = function (array $part, int $np, int $d, array &$roomHold) use (
            &$gBusy, &$tBusy, &$rBusy, $roomOptionsFor
        ) {
            [$c, , , $effectiveRoomCode, , , $len] = $part;
            $scope = $this->groupScopeKey($c);
            for ($i = 0; $i < $len; $i++) {
                $slot = $d . '|' . ($np + $i);
                foreach ($c->occupiedGroups() as $g) {
                    if (!empty($gBusy[$scope . '|' . $g . '|' . $slot])) {
                        return false;
                    }
                }
                if ($c->teacher_id && !empty($tBusy['T' . $c->teacher_id . '|' . $slot])) {
                    return false;
                }
            }
            $preferred = $effectiveRoomCode ?: $c->auditorium_code;
            if (!$preferred) {
                return null;
            }
            foreach ($roomOptionsFor($c, $preferred) as $room) {
                $roomFree = true;
                for ($i = 0; $i < $len; $i++) {
                    $rk = 'R' . $room->code . '|' . $d . '|' . ($np + $i);
                    if (!empty($rBusy[$rk]) || !empty($roomHold[$rk])) {
                        $roomFree = false;
                        break;
                    }
                }
                if ($roomFree) {
                    for ($i = 0; $i < $len; $i++) {
                        $roomHold['R' . $room->code . '|' . $d . '|' . ($np + $i)] = true;
                    }
                    return $room;
                }
            }
            return false;
        };

        $placedUnits = [];
        foreach ($pending as [$d, $parts, $blockLen]) {
            // ── 1) Yaxlit blok ──────────────────────────────────────────────
            // Kun boshidan boshlab birinchi mos joy olinadi. Oraliq bazaviy
            // slot bilan cheklanmaydi: fan bo'yicha guruhlashda blok o'z
            // shablon slotidan kechroqqa ham tushishi mumkin, aks holda unga
            // joy topilmay, eski o'rnida boshqa dars bilan to'qnashib qolardi.
            $chosen = null;
            for ($np = 1; $np + $blockLen - 1 <= $pairs; $np++) {
                $roomHold = [];
                $attempt = [];
                foreach ($parts as $part) {
                    $room = $partFits($part, $np + $part[5], $d, $roomHold);
                    if ($room === false) {
                        $attempt = null;
                        break;
                    }
                    $attempt[(int) $part[0]->id] = ['pair' => $np + $part[5], 'room' => $room];
                }
                if ($attempt !== null) {
                    $chosen = $attempt;
                    break;
                }
            }

            // ── 2) Yaxlit sig'masa — bo'laklab ──────────────────────────────
            // Fan uzilib qolishi mumkin, lekin bu birlikni eski (endi band
            // bo'lishi mumkin) o'rniga qaytarishdan afzal: u yerda guruh
            // ustma-ust ikki darsda qolib ketardi.
            if ($chosen === null) {
                $roomHold = [];
                $used = [];
                $attempt = [];
                foreach ($parts as $part) {
                    $len = $part[6];
                    $pick = null;
                    for ($np = 1; $np + $len - 1 <= $pairs; $np++) {
                        $overlap = false;
                        for ($i = 0; $i < $len; $i++) {
                            if (isset($used[$np + $i])) {
                                $overlap = true;
                                break;
                            }
                        }
                        if ($overlap) {
                            continue;
                        }
                        $room = $partFits($part, $np, $d, $roomHold);
                        if ($room === false) {
                            continue;
                        }
                        $pick = ['pair' => $np, 'room' => $room];
                        break;
                    }
                    if ($pick === null) {
                        $attempt = null;
                        break;
                    }
                    for ($i = 0; $i < $len; $i++) {
                        $used[$pick['pair'] + $i] = true;
                    }
                    $attempt[(int) $part[0]->id] = $pick;
                }
                $chosen = $attempt;
            }

            // ── 3) Hech qayerga sig'masa — eski joyida qoladi ───────────────
            if ($chosen === null) {
                $chosen = [];
                foreach ($parts as $part) {
                    $chosen[(int) $part[0]->id] = ['pair' => $part[2], 'room' => null,
                        'code' => $part[3], 'name' => $part[4]];
                }
            }

            foreach ($parts as $part) {
                $c = $part[0];
                $id = (int) $c->id;
                $chosen[$id]['code'] = $chosen[$id]['code'] ?? ($chosen[$id]['room']?->code ?: null);
                $chosen[$id]['name'] = $chosen[$id]['name'] ?? ($chosen[$id]['room']?->name ?: null);
                $mark($c, $d, $chosen[$id]['pair'], $chosen[$id]['code'], true);
            }
            $placedUnits[] = ['day' => $d, 'parts' => $parts, 'pos' => $chosen];
        }

        $this->closeWeekGaps($placedUnits, $pairs, $gBusy, $mark, $partFits);

        $moves = [];
        foreach ($placedUnits as $unit) {
            foreach ($unit['parts'] as $part) {
                $c = $part[0];
                $pick = $unit['pos'][(int) $c->id];
                // Muvaffaqiyatli blokning HAR bir kartasi vaqt+xona bilan yoziladi.
                $moves[] = [
                    'card_id' => (int) $c->id,
                    'day' => $unit['day'],
                    'pair' => $pick['pair'],
                    'auditorium_code' => $pick['code'],
                    'auditorium_name' => $pick['name'],
                ];
            }
        }

        return $moves;
    }

    /**
     * Zichlashdan keyin qolgan oynalarni yopadi (haftalik ko'chirish uchun).
     *
     * Guruh kunida [dars][oyna][dars] holati qolishi mumkin: oynadan keyingi
     * blok o'qituvchi yoki auditoriya bandligi sababli tepaga chiqa olmaydi.
     * Bunday holatda oynadan OLDINGI bloklarni pastga surish yetarli — kun
     * kechroq boshlanadi, lekin ichida bo'sh para qolmaydi.
     *
     * Surish faqat umumiy oyna soni kamayganda qabul qilinadi: oqim ma'ruzasi
     * bir necha guruhga tegishli, uni surish boshqa guruhda yangi oyna ochishi
     * mumkin.
     *
     * @param  array<int,array{day:int,parts:array,pos:array}>  $placedUnits
     * @param  array  $gBusy  guruh bandligi (havola — $mark uni yangilab boradi)
     */
    private function closeWeekGaps(array &$placedUnits, int $pairs, array &$gBusy, callable $mark, callable $partFits): void
    {
        // Birlik qaysi guruh-kunlarga tegishli va qaysi yarim-slotlarni egallaydi
        $unitKeys = [];    // birlik => ["scope|guruh", ...]
        $dayUnits = [];    // "scope|guruh|kun" => [birlik, ...]
        foreach ($placedUnits as $i => $unit) {
            $keys = [];
            foreach ($unit['parts'] as $part) {
                $scope = $this->groupScopeKey($part[0]);
                foreach ($part[0]->occupiedGroups() as $g) {
                    $keys[$scope . '|' . $g] = true;
                }
            }
            $unitKeys[$i] = array_keys($keys);
            foreach ($unitKeys[$i] as $key) {
                $dayUnits[$key . '|' . $unit['day']][] = $i;
            }
        }

        $gapsOf = function (string $key, int $d) use (&$gBusy, $pairs): int {
            $slots = [];
            for ($p = 1; $p <= $pairs; $p++) {
                if (!empty($gBusy[$key . '|' . $d . '|' . $p])) {
                    $slots[] = $p;
                }
            }
            return count($slots) < 2 ? 0 : (max($slots) - min($slots) + 1) - count($slots);
        };

        // Birlikni $k yarim-slotga pastga surish. Muvaffaqiyatsiz bo'lsa hamma
        // narsa o'z joyiga qaytariladi.
        $shift = function (int $i, int $k) use (&$placedUnits, $pairs, $mark, $partFits): bool {
            $unit = $placedUnits[$i];
            $d = $unit['day'];
            foreach ($unit['parts'] as $part) {
                $pick = $unit['pos'][(int) $part[0]->id];
                if ($pick['pair'] + $k + $part[6] - 1 > $pairs) {
                    return false;
                }
                $mark($part[0], $d, $pick['pair'], $pick['code'], false);
            }
            $roomHold = [];
            $next = [];
            $ok = true;
            foreach ($unit['parts'] as $part) {
                $pick = $unit['pos'][(int) $part[0]->id];
                $room = $partFits($part, $pick['pair'] + $k, $d, $roomHold);
                if ($room === false) {
                    $ok = false;
                    break;
                }
                $next[(int) $part[0]->id] = [
                    'pair' => $pick['pair'] + $k,
                    'room' => $room,
                    'code' => $room?->code ?: $pick['code'],
                    'name' => $room?->name ?: $pick['name'],
                ];
            }
            $use = $ok ? $next : $unit['pos'];
            foreach ($unit['parts'] as $part) {
                $pick = $use[(int) $part[0]->id];
                $mark($part[0], $d, $pick['pair'], $pick['code'], true);
            }
            if ($ok) {
                $placedUnits[$i]['pos'] = $next;
            }
            return $ok;
        };

        foreach ($dayUnits as $dayKey => $ids) {
            $parts = explode('|', $dayKey);
            $day = (int) array_pop($parts);
            $key = implode('|', $parts);

            for ($round = 0; $round < $pairs; $round++) {
                $slots = [];
                for ($p = 1; $p <= $pairs; $p++) {
                    if (!empty($gBusy[$key . '|' . $day . '|' . $p])) {
                        $slots[] = $p;
                    }
                }
                if (count($slots) < 2) {
                    break;
                }
                // Birinchi oyna
                $hole = null;
                foreach ($slots as $ix => $p) {
                    if ($ix > 0 && $p > $slots[$ix - 1] + 1) {
                        $hole = [$slots[$ix - 1] + 1, $p - $slots[$ix - 1] - 1];
                        break;
                    }
                }
                if ($hole === null) {
                    break;
                }
                [$holeStart, $holeLen] = $hole;

                // Oynadan yuqoridagi birliklar — pastga suriladi (pastdan boshlab)
                $above = [];
                foreach ($ids as $i) {
                    $maxSlot = 0;
                    foreach ($placedUnits[$i]['parts'] as $part) {
                        $pick = $placedUnits[$i]['pos'][(int) $part[0]->id];
                        $maxSlot = max($maxSlot, $pick['pair'] + $part[6] - 1);
                    }
                    if ($maxSlot < $holeStart) {
                        $above[$i] = $maxSlot;
                    }
                }
                if (!$above) {
                    break;
                }
                arsort($above);

                $affected = [];
                foreach (array_keys($above) as $i) {
                    foreach ($unitKeys[$i] as $k2) {
                        $affected[$k2] = true;
                    }
                }
                $before = 0;
                foreach (array_keys($affected) as $k2) {
                    $before += $gapsOf($k2, $day);
                }

                $done = [];
                $allShifted = true;
                foreach (array_keys($above) as $i) {
                    if ($shift($i, $holeLen)) {
                        $done[] = $i;
                    } else {
                        $allShifted = false;
                        break;
                    }
                }
                $after = 0;
                foreach (array_keys($affected) as $k2) {
                    $after += $gapsOf($k2, $day);
                }
                if (!$allShifted || $after >= $before) {
                    // Foydasi bo'lmadi — hammasini orqaga qaytaramiz
                    foreach (array_reverse($done) as $i) {
                        $shift($i, -$holeLen);
                    }
                    break;
                }
            }
        }
    }

    /** Hafta ko'chirishlarini istisno (override) sifatida saqlash. */
    private function saveWeekMoves(array $moves, int $week, array $replaceCardIds = []): void
    {
        if (empty($moves) && empty($replaceCardIds)) {
            return;
        }
        DB::transaction(function () use ($moves, $week, $replaceCardIds) {
            if (!empty($replaceCardIds)) {
                TimetableCardOverride::where('week', $week)
                    ->whereIn('card_id', $replaceCardIds)
                    ->where('cancelled', false)
                    ->delete();
            }
            foreach ($moves as $m) {
                TimetableCardOverride::updateOrCreate(
                    ['card_id' => $m['card_id'], 'week' => $week],
                    [
                        'day' => $m['day'],
                        'pair' => $m['pair'],
                        'cancelled' => false,
                        'auditorium_code' => $m['auditorium_code'] ?? null,
                        'auditorium_name' => $m['auditorium_name'] ?? null,
                    ]
                );
            }
        });
    }

    public function weekOverride(Request $request, TimetableCard $card)
    {
        $data = $request->validate([
            'week'       => 'required|integer|min:1|max:30',
            'action'     => 'required|in:move,cancel,reset',
            'day'        => 'nullable|integer|min:1|max:10',
            'pair'       => 'nullable|integer|min:1|max:10',
            'start_half' => 'nullable|integer|min:0|max:1',
        ]);
        $week = (int) $data['week'];
        $startHalf = (int) ($data['start_half'] ?? 0);

        if ($data['action'] === 'reset') {
            TimetableCardOverride::where('card_id', $card->id)->where('week', $week)->delete();
            return response()->json(['ok' => true]);
        }

        if ($data['action'] === 'cancel') {
            TimetableCardOverride::updateOrCreate(
                ['card_id' => $card->id, 'week' => $week],
                [
                    'day' => null,
                    'pair' => null,
                    'cancelled' => true,
                    'auditorium_code' => null,
                    'auditorium_name' => null,
                ]
            );
            return response()->json(['ok' => true]);
        }

        // move — tanlangan haftadagi konfliktni tekshiramiz
        $day = $data['day'] ?? null;
        $pair = $data['pair'] ?? null;
        if (!$day || !$pair) {
            return response()->json(['error' => 'Kun va para ko\'rsatilishi kerak'], 422);
        }
        $conflicts = $this->findWeekConflicts($card, $week, $day, $pair, $startHalf);
        if (!empty($conflicts)) {
            return response()->json(['error' => implode(' · ', $conflicts)], 422);
        }
        TimetableCardOverride::updateOrCreate(
            ['card_id' => $card->id, 'week' => $week],
            ['day' => $day, 'pair' => $pair, 'start_half' => $startHalf, 'cancelled' => false]
        );
        return response()->json(['ok' => true]);
    }

    /** Tanlangan haftadagi effektiv joylashuvlar bo'yicha konflikt tekshiruvi (yarim-slot oralig'i). */
    private function findWeekConflicts(TimetableCard $card, int $week, int $day, int $pair, int $startHalf = 0): array
    {
        $ovr = TimetableCardOverride::whereHas('card', fn($q) => $q->where('board_id', $card->board_id))
            ->where('week', $week)->get()->keyBy('card_id');
        $others = TimetableCard::where('board_id', $card->board_id)->where('id', '!=', $card->id)->get();

        $myRange = $this->rangeFor($card, $pair, $startHalf);
        $myGroups = $card->occupiedGroups();
        $myOverride = $ovr->get($card->id);
        $myRoomCode = $myOverride?->auditorium_code ?: $card->auditorium_code;
        $errors = [];
        foreach ($others as $o) {
            $ov = $ovr->get($o->id);
            if ($ov) {
                if ($ov->cancelled) {
                    continue;
                }
                $od = $ov->day;
                $op = $ov->pair;
                $osh = (int) ($ov->start_half ?? 0);
            } else {
                $od = $o->day;
                $op = $o->pair;
                $osh = (int) ($o->start_half ?? 0);
            }
            if (!$od || !$op || (int) $od !== $day) {
                continue;
            }
            if (!$this->halfOverlap($myRange, $this->rangeFor($o, (int) $op, $osh))) {
                continue;
            }
            if ($this->groupScopeKey($o) === $this->groupScopeKey($card)) {
                $overlap = array_intersect($myGroups, $o->occupiedGroups());
                if (!empty($overlap)) {
                    $errors[] = 'Guruh band: ' . implode(',', $overlap) . ' (' . $o->subject_name . ')';
                }
            }
            if ($card->teacher_id && $o->teacher_id && (int) $o->teacher_id === (int) $card->teacher_id) {
                $errors[] = "O'qituvchi band: " . $o->teacher_name . ' (' . $o->subject_name . ')';
            }
            $otherRoomCode = $ov?->auditorium_code ?: $o->auditorium_code;
            if ($myRoomCode && $otherRoomCode === $myRoomCode) {
                $errors[] = 'Auditoriya band: ' . ($ov?->auditorium_name ?: $o->auditorium_name) . ' (' . $o->subject_name . ')';
            }
        }
        return array_unique($errors);
    }

    /** Yo'nalish+kurs uchun panjara o'lchami (alohida sozlama yoki doska sukuti). */
    private function gridFor(TimetableBoard $board, ?string $faculty, string $specialty, int $course): array
    {
        $gs = TimetableGridSetting::where('board_id', $board->id)
            ->where('specialty_name', $specialty)->where('course', $course)
            ->when($faculty !== null, fn($q) => $q->where('faculty_name', $faculty))
            ->first();

        // Eski fakultetsiz yozuvlar yangi fakultet kesimiga o'tguncha fallback bo'lib turadi.
        if (!$gs && $faculty !== null) {
            $gs = TimetableGridSetting::where('board_id', $board->id)
                ->whereNull('faculty_name')
                ->where('specialty_name', $specialty)
                ->where('course', $course)
                ->first();
        }

        return [
            'days'  => $gs->days ?? $board->days,
            // Yarim-slot soni doska qo'ng'iroq jadvalidan (yo'nalish bo'yicha bir xil)
            'pairs' => $board->pairCount(),
        ];
    }

    /** Kartochkani joylash/ko'chirish/olib tashlash — konflikt tekshiruvi bilan. */
    public function placeCard(Request $request, TimetableCard $card)
    {
        $board = $card->board;
        $grid = $this->gridFor($board, $card->faculty_name, $card->specialty_name, (int) $card->course);
        $data = $request->validate([
            'day'        => 'nullable|integer|min:1|max:' . $grid['days'],
            'pair'       => 'nullable|integer|min:1|max:' . $grid['pairs'],
            'start_half' => 'nullable|integer|min:0|max:1',
        ]);

        $day = $data['day'] ?? null;
        $pair = $data['pair'] ?? null;
        $startHalf = (int) ($data['start_half'] ?? 0);

        if ($day && $pair) {
            $conflicts = $this->findConflicts($card, $day, $pair, $startHalf);
            if (!empty($conflicts)) {
                return response()->json(['error' => implode(' · ', $conflicts)], 422);
            }
        }

        $card->update([
            'day' => $day, 'pair' => $pair,
            'start_half' => $day && $pair ? $startHalf : 0,
        ]);
        return response()->json(['ok' => true]);
    }

    /** Ikki yarim-slot oralig'i kesishadimi: [a1,a2) va [b1,b2). */
    private function halfOverlap(array $a, array $b): bool
    {
        return $a[0] < $b[1] && $b[0] < $a[1];
    }

    /** Kartaning `pair` (yarim-slot) da yarim-slot oralig'i: [pair-1, pair-1+len_half). */
    private function rangeFor(TimetableCard $card, int $pair, int $startHalf = 0): array
    {
        $s = $pair - 1;
        return [$s, $s + $card->lenHalf()];
    }

    private function findConflicts(TimetableCard $card, int $day, int $pair, int $startHalf = 0): array
    {
        // Shu kundagi barcha joylashgan kartalar (para bo'yicha emas — oraliq kesishuvi bilan)
        $others = TimetableCard::where('board_id', $card->board_id)
            ->where('id', '!=', $card->id)
            ->where('day', $day)->whereNotNull('pair')
            ->get();

        $myRange = $this->rangeFor($card, $pair, $startHalf);
        $myGroups = $card->occupiedGroups();
        $errors = [];
        foreach ($others as $o) {
            $oRange = $o->halfRange();
            if (!$oRange || !$this->halfOverlap($myRange, $oRange)) {
                continue;
            }
            // Guruh konflikti — bir yo'nalish+kurs ichida
            if ($this->groupScopeKey($o) === $this->groupScopeKey($card)) {
                $overlap = array_intersect($myGroups, $o->occupiedGroups());
                if (!empty($overlap)) {
                    $errors[] = 'Guruh band: ' . implode(',', $overlap) . ' (' . $o->subject_name . ')';
                }
            }
            // O'qituvchi konflikti — butun doska bo'ylab
            if ($card->teacher_id && $o->teacher_id && (int) $o->teacher_id === (int) $card->teacher_id) {
                $errors[] = "O'qituvchi band: " . $o->teacher_name . ' (' . $o->subject_name . ')';
            }
            // Auditoriya konflikti — butun doska bo'ylab
            if ($card->auditorium_code && $o->auditorium_code === $card->auditorium_code) {
                $errors[] = 'Auditoriya band: ' . $o->auditorium_name . ' (' . $o->subject_name . ')';
            }
        }
        return array_unique($errors);
    }

    /** Barcha doskalarga umumiy auditoriya-o'qituvchi cheklovlari. */
    private function auditoriumTeacherMap(): array
    {
        if (!Schema::hasTable('auditorium_teacher')) {
            return [];
        }

        return AuditoriumTeacher::get(['auditorium_id', 'teacher_id', 'is_general'])
            ->mapWithKeys(fn ($assignment) => [
                (string) $assignment->auditorium_id => [
                    'teacher_id' => $assignment->teacher_id,
                    'is_general' => (bool) $assignment->is_general,
                ],
            ])->all();
    }

    private function auditoriumAllowedForCard($auditorium, TimetableCard $card, array $roomTeacherMap): bool
    {
        if (!$card->teacher_id || empty($roomTeacherMap)) {
            return true;
        }

        $assignment = $roomTeacherMap[(string) $auditorium->id] ?? null;

        return !$assignment
            || $assignment['is_general']
            || (int) $assignment['teacher_id'] === (int) $card->teacher_id;
    }

    /** Kartochka rekvizitlari: o'qituvchi / auditoriya biriktirish. */
    public function updateCard(Request $request, TimetableCard $card)
    {
        $data = $request->validate([
            'teacher_id'      => 'nullable|integer',
            'auditorium_code' => 'nullable|string|max:50',
            'len_half'        => 'nullable|integer|min:1|max:4',
            'start_half'      => 'nullable|integer|min:0|max:1',
        ]);

        if (array_key_exists('len_half', $data) && $data['len_half']) {
            $card->len_half = (int) $data['len_half'];
        }
        if (array_key_exists('start_half', $data) && $data['start_half'] !== null && $card->day && $card->pair) {
            $card->start_half = (int) $data['start_half'];
        }
        if (array_key_exists('teacher_id', $data)) {
            if ($data['teacher_id']) {
                $t = Teacher::find($data['teacher_id']);
                $card->teacher_id = $t?->id;
                $card->teacher_name = $t?->short_name ?: $t?->full_name;
            } else {
                $card->teacher_id = null;
                $card->teacher_name = null;
            }
        }
        if (array_key_exists('auditorium_code', $data)) {
            if ($data['auditorium_code']) {
                $a = Auditorium::where('code', $data['auditorium_code'])->first();
                if ($a) {
                    $roomMap = $this->auditoriumTeacherMap();
                    if (!$this->auditoriumAllowedForCard($a, $card, $roomMap)) {
                        return response()->json([
                            'error' => 'Bu auditoriya tanlangan o\'qituvchiga biriktirilmagan.',
                        ], 422);
                    }
                }
                $card->auditorium_code = $a?->code;
                $card->auditorium_name = $a?->name;
            } else {
                $card->auditorium_code = null;
                $card->auditorium_name = null;
            }
        }

        // Joylashgan bo'lsa — yangi rekvizit/uzunlik bilan konflikt tekshiramiz
        if ($card->day && $card->pair) {
            $conflicts = $this->findConflicts($card, $card->day, $card->pair, (int) ($card->start_half ?? 0));
            if (!empty($conflicts)) {
                return response()->json(['error' => implode(' · ', $conflicts)], 422);
            }
        }

        $card->save();
        return response()->json([
            'ok' => true,
            'teacher_name' => $card->teacher_name,
            'auditorium_name' => $card->auditorium_name,
            'auditorium_code' => $card->auditorium_code,
            'len_half' => $card->lenHalf(),
        ]);
    }

    /** O'qituvchilar (kafedra nomi bo'yicha filtrlash mumkin). */
    public function teachers(Request $request)
    {
        $q = Teacher::query()->whereNotNull('full_name');

        if ($this->timetableActiveRole($request) === 'kafedra_mudiri') {
            $context = $this->departmentHeadContext($request);
            $q->where('department_hemis_id', $context['department_hemis_id']);
        } elseif ($request->filled('kafedra')) {
            $q->where('department', 'like', '%' . $request->kafedra . '%');
        }

        if ($request->filled('search')) {
            $q->where('full_name', 'like', '%' . $request->search . '%');
        }

        return response()->json(
            $q->orderBy('full_name')
                ->limit(100)
                ->get(['id', 'full_name', 'short_name', 'department', 'department_hemis_id', 'lavozim'])
        );
    }

    /** O'qituvchilar kafedralari (auditoriya biriktirish filtri uchun). */
    public function teacherDepartments(Request $request)
    {
        if ($this->timetableActiveRole($request) === 'kafedra_mudiri') {
            $context = $this->departmentHeadContext($request);
            return response()->json([$context['department_name']]);
        }

        // Katalogdagi disabled kafedralar ham ko'rinsin. Teacher jadvalidagi
        // eski/nomi o'zgargan qiymatlar esa backward compatibility uchun qo'shiladi.
        $directoryDepartments = Department::query()
            ->where('name', 'like', '%kafedra%')
            ->where('structure_type_code', '!=', 11)
            ->pluck('name');

        $teacherDepartments = Teacher::whereNotNull('department')
            ->where('department', '<>', '')
            ->pluck('department');

        return response()->json(
            $directoryDepartments
                ->merge($teacherDepartments)
                ->filter(fn ($name) => trim((string) $name) !== '')
                ->map(fn ($name) => trim((string) $name))
                ->unique()
                ->sort()
                ->values()
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    //  aSc Timetables uslubidagi boshqaruv dialoglari: Fanlar, Guruhlar,
    //  Auditoriyalar, O'qituvchilar. Har biri ro'yxat + qidiruv, auditoriya
    //  esa to'liq CRUD + Excel import.
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Fanlar dialogi — doskaning o'quv yili + semestr juftligi bo'yicha
     * ishchi rejalardagi fanlar, yo'nalish+kurs kesimida (Excel "fanlar
     * royxati" varag'i uslubida). Har fan uchun ma'ruza/amaliy/laboratoriya
     * soatlari va kafedra ko'rsatiladi.
     */
    public function subjects(TimetableBoard $board)
    {
        $start = (int) substr($board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;

        $rows = DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + GREATEST(CAST(mc.level_code AS UNSIGNED) - 10, 0) - 1) = ?", [$start])
            ->groupBy('mc.specialty_name', 'mc.level_code', 's.semester', 's.subject_name')
            ->selectRaw("mc.specialty_name, mc.level_code, s.semester, s.subject_name,
                MAX(s.lecture) as lecture, MAX(s.practice) as practice,
                MAX(s.laboratory) as laboratory, MAX(s.seminar) as seminar,
                GROUP_CONCAT(DISTINCT mc.name SEPARATOR '|||') as plan_names")
            ->orderBy('mc.specialty_name')->orderBy('mc.level_code')->orderBy('s.subject_name')
            ->get();

        [$kafMap, $overrides] = $this->buildKafedraMap();
        $weeks = max(1, (int) $board->weeks);

        // Yo'nalish+kurs bo'yicha hafta soni doska sukutidan farq qilishi mumkin —
        // kartochka yaratish (assembleRows) aynan shu sozlamani ishlatadi, shuning
        // uchun haftalik yuk hisobi ham xuddi shu manbadan olinishi kerak.
        $gset = TimetableGridSetting::where('board_id', $board->id)->get()
            ->mapWithKeys(fn($g) => [
                ($g->faculty_name ?? '') . '|' . $this->specKey($g->specialty_name) . '|' . $g->course => (int) $g->weeks,
            ])->all();

        // Fakultet/reja nomi subjects() javobida bevosita manual_curricula.name
        // dan olinadi — O'quv reja to'g'riligi jadvalidagi manba bilan bir xil.

        $out = [];
        foreach ($rows as $r) {
            $course = (int) $r->level_code >= 11 ? (int) $r->level_code - 10 : (int) $r->level_code;
            $lec = (float) $r->lecture;
            $prc = (float) $r->practice + (float) $r->laboratory + (float) $r->seminar;
            $facName = collect(explode('|||', (string) ($r->plan_names ?? '')))->filter()->first();
            // Kartochka yaratishdagi bilan bir xil qidiruv: fakultet+yo'nalish+kurs,
            // so'ng fakultetsiz kalit, oxirida doska sukuti.
            $sk = $this->specKey($r->specialty_name);
            $rowWeeks = max(1, (int) ($gset[($facName ?? '') . '|' . $sk . '|' . $course]
                ?? $gset['|' . $sk . '|' . $course]
                ?? $weeks));
            $out[] = [
                'specialty_name' => $r->specialty_name,
                'course'         => $course,
                'faculty_name'   => $facName,
                'weeks'          => $rowWeeks,
                'semester'       => (int) $r->semester,
                'subject_name'   => $r->subject_name,
                'kafedra_name'   => $this->kafedraFor($overrides, $kafMap, $r->subject_name),
                'lecture'        => $lec,
                'practice'       => (float) $r->practice,
                'laboratory'     => (float) $r->laboratory,
                'seminar'        => (float) $r->seminar,
                // Haftalik para (1 para = 2 akademik soat) — eski, sodda ko'rsatkich
                'lec_pairs'      => $lec > 0 ? max(1, (int) round($lec / $rowWeeks / 2)) : 0,
                'prc_pairs'      => $prc > 0 ? max(1, (int) round($prc / $rowWeeks / 2)) : 0,
                // Haftalik yuk taqsimoti (tibbiyot uslubi): jami soat / hafta =
                // haftalik yuk; ma'ruza 2 soatdan, ma'ruzali haftada amaliy shunga
                // kamayadi, ma'ruzasiz haftada to'liq yuk amaliyga beriladi.
                'week_plan'      => $this->weeklyPlan($lec, $prc, $rowWeeks),
            ];
        }

        return response()->json(['weeks' => $weeks, 'subjects' => $out]);
    }

    /**
     * Guruhlar dialogi — doskaning tasdiqlangan oqim snapshotlaridagi
     * guruhchalar (yo'nalish+kurs+oqim+til kesimida, talaba soni bilan).
     */
    public function groups(TimetableBoard $board)
    {
        $byFaculty = $this->boardSnapshots($board);
        $out = [];
        foreach ($byFaculty as $snap) {
            foreach ($snap->data ?? [] as $bl) {
                $specName = trim(explode('|', $bl['merge_key'] ?? '')[1] ?? '') ?: ($bl['title'] ?? '');
                foreach ($bl['courses'] ?? [] as $co) {
                    $lvl = (int) ($co['level_code'] ?? 0);
                    $course = $lvl >= 11 ? $lvl - 10 : $lvl;
                    foreach ($co['oqims'] ?? [] as $oq) {
                        foreach ($oq['rows'] ?? [] as $gr) {
                            $gn = trim((string) ($gr['name'] ?? ''));
                            if ($gn === '') {
                                continue;
                            }
                            $out[] = [
                                'group_name'     => $gn,
                                'specialty_name' => $specName,
                                'course'         => $course,
                                'oqim_label'     => $oq['label'] ?? null,
                                'lang'           => $oq['lang'] ?? 'uz',
                                'students'       => (int) ($gr['count'] ?? 0),
                            ];
                        }
                    }
                }
            }
        }
        usort($out, fn($a, $b) => [$a['specialty_name'], $a['course'], $a['group_name']]
            <=> [$b['specialty_name'], $b['course'], $b['group_name']]);

        return response()->json(['groups' => $out]);
    }

    private function timetableActor(Request $request)
    {
        return $request->user()
            ?? Auth::guard('teacher')->user()
            ?? Auth::guard('web')->user();
    }

    private function timetableActiveRole(Request $request): string
    {
        $actor = $this->timetableActor($request);
        $roles = $actor && method_exists($actor, 'getRoleNames')
            ? $actor->getRoleNames()->toArray()
            : [];

        $activeRole = session('active_role', $roles[0] ?? '');
        return in_array($activeRole, $roles, true) ? $activeRole : ($roles[0] ?? '');
    }

    /**
     * Kafedra mudirining kafedrasi serverdagi Teacher/Department ma'lumotidan olinadi.
     * Client kafedrani yubormaydi, shuning uchun boshqa kafedraga xona qo'shib bo'lmaydi.
     */
    private function departmentHeadContext(Request $request): array
    {
        $actor = $this->timetableActor($request);
        if (!$actor) {
            abort(403, 'Foydalanuvchi aniqlanmadi.');
        }

        $department = null;
        $departmentHemisId = $actor->department_hemis_id ?? null;
        if ($departmentHemisId) {
            $department = Department::where('department_hemis_id', $departmentHemisId)->first();
        }

        $departmentName = trim((string) ($actor->department ?? ''));
        if (!$department && $departmentName !== '') {
            $department = Department::where('name', $departmentName)
                ->where('structure_type_code', '!=', 11)
                ->first();
        }

        if (!$department) {
            abort(422, 'Kafedra mudirining kafedrasi aniqlanmadi. Teacher profilidagi department ma\'lumotini tekshiring.');
        }

        return [
            'actor_id' => (int) $actor->id,
            'department_hemis_id' => (int) $department->department_hemis_id,
            'department_name' => $department->name,
        ];
    }

    /** Auditoriyalar ro'yxati (dialog uchun — barcha maydonlar). */
    public function auditoriums(Request $request)
    {
        $columns = ['id', 'code', 'name', 'volume', 'active', 'auditorium_type_name', 'building_name'];
        $hasOwnership = Schema::hasColumn('auditoriums', 'department_hemis_id')
            && Schema::hasColumn('auditoriums', 'department_name')
            && Schema::hasColumn('auditoriums', 'created_by_teacher_id');

        if ($hasOwnership) {
            $columns = array_merge($columns, ['department_hemis_id', 'department_name', 'created_by_teacher_id']);
        }

        $departmentId = null;
        if ($hasOwnership && $this->timetableActiveRole($request) === 'kafedra_mudiri') {
            $departmentId = $this->departmentHeadContext($request)['department_hemis_id'];
        }

        $auditoriums = Auditorium::orderBy('active', 'desc')
            ->orderBy('name')
            ->get($columns);

        return response()->json(
            $auditoriums->map(function (Auditorium $auditorium) use ($departmentId, $hasOwnership) {
                $auditorium->setAttribute(
                    'can_delete',
                    $hasOwnership
                        && $departmentId !== null
                        && (int) $auditorium->department_hemis_id === (int) $departmentId
                        && !empty($auditorium->created_by_teacher_id)
                );

                return $auditorium;
            })->values()
        );
    }

    /** Barcha doskalarga umumiy auditoriya-o'qituvchi biriktirmalari. */
    public function auditoriumTeacherAssignments(TimetableBoard $board)
    {
        $assignments = Schema::hasTable('auditorium_teacher')
            ? AuditoriumTeacher::with('teacher')->get()->keyBy('auditorium_id')
            : collect();

        $auditoriums = Auditorium::where('active', true)
            ->orderBy('building_name')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'volume', 'auditorium_type_name', 'building_name']);

        return response()->json([
            'auditoriums' => $auditoriums->map(function ($auditorium) use ($assignments) {
                $assignment = $assignments->get($auditorium->id);
                $teacher = $assignment?->teacher;

                return [
                    'id' => $auditorium->id,
                    'code' => $auditorium->code,
                    'name' => $auditorium->name,
                    'volume' => (int) $auditorium->volume,
                    'auditorium_type_name' => $auditorium->auditorium_type_name,
                    'building_name' => $auditorium->building_name,
                    'assignment_id' => $assignment?->id,
                    'teacher_id' => $assignment?->teacher_id,
                    'teacher_name' => $teacher?->short_name ?: $teacher?->full_name,
                    'is_general' => (bool) ($assignment?->is_general ?? false),
                ];
            })->values(),
        ]);
    }

    /** Auditoriyani barcha doskalar uchun o'qituvchiga yoki umumiy holatga biriktirish. */
    public function assignAuditoriumTeacher(Request $request, TimetableBoard $board)
    {
        if (!Schema::hasTable('auditorium_teacher')) {
            return response()->json(['error' => 'auditorium_teacher jadvali mavjud emas. Migratsiyani ishga tushiring.'], 503);
        }

        $data = $request->validate([
            'auditorium_id' => 'required|integer|exists:auditoriums,id',
            'teacher_id' => 'nullable|integer|exists:teachers,id',
            'is_general' => 'required|boolean',
        ]);

        $auditorium = Auditorium::where('id', $data['auditorium_id'])
            ->where('active', true)
            ->firstOrFail();

        $isGeneral = (bool) $data['is_general'];
        $teacher = !$isGeneral && !empty($data['teacher_id'])
            ? Teacher::find($data['teacher_id'])
            : null;

        if (!$isGeneral && !$teacher) {
            return response()->json(['error' => "Umumiy bo'lmagan xona uchun o'qituvchi tanlang."], 422);
        }

        if (!$isGeneral && $this->timetableActiveRole($request) === 'kafedra_mudiri') {
            $context = $this->departmentHeadContext($request);
            if ((int) $teacher->department_hemis_id !== (int) $context['department_hemis_id']) {
                return response()->json([
                    'error' => "Faqat o'z kafedrangizdagi o'qituvchini biriktira olasiz.",
                ], 422);
            }
        }

        $assignment = AuditoriumTeacher::updateOrCreate(
            ['auditorium_id' => $auditorium->id],
            [
                'teacher_id' => $teacher?->id,
                'is_general' => $isGeneral,
            ]
        );

        return response()->json([
            'ok' => true,
            'auditorium_id' => $auditorium->id,
            'assignment_id' => $assignment->id,
            'teacher_id' => $assignment->teacher_id,
            'teacher_name' => $teacher?->short_name ?: $teacher?->full_name,
            'is_general' => (bool) $assignment->is_general,
        ]);
    }

    /** Auditoriyaga berilgan umumiy biriktirishni bekor qilish. */
    public function unassignAuditoriumTeacher(TimetableBoard $board, Auditorium $auditorium)
    {
        if (!Schema::hasTable('auditorium_teacher')) {
            return response()->json(['error' => 'auditorium_teacher jadvali mavjud emas. Migratsiyani ishga tushiring.'], 503);
        }

        AuditoriumTeacher::where('auditorium_id', $auditorium->id)->delete();

        return response()->json([
            'ok' => true,
            'auditorium_id' => $auditorium->id,
            'message' => "«{$auditorium->name}» auditoriyasining biriktiruvi bekor qilindi.",
        ]);
    }

    /** Yangi auditoriya qo'shish. */
    public function storeAuditorium(Request $request)
    {
        $data = $this->validateAuditorium($request);

        if ($this->timetableActiveRole($request) === 'kafedra_mudiri') {
            if (!Schema::hasColumn('auditoriums', 'department_hemis_id')) {
                return response()->json(['error' => 'Auditoriya kafedrasi migratsiyasi bajarilmagan.'], 503);
            }

            $context = $this->departmentHeadContext($request);
            $data['department_hemis_id'] = $context['department_hemis_id'];
            $data['department_name'] = $context['department_name'];
            $data['created_by_teacher_id'] = $context['actor_id'];
            $data['active'] = true;
        }

        $auditorium = Auditorium::create($data);

        return response()->json([
            'ok' => true,
            'auditorium' => $auditorium,
            'message' => "«{$auditorium->name}» auditoriyasi saqlandi.",
        ]);
    }

    /** Auditoriyani tahrirlash. */
    public function updateAuditorium(Request $request, Auditorium $auditorium)
    {
        $data = $this->validateAuditorium($request, $auditorium->id);
        $auditorium->update($data);
        return response()->json(['ok' => true, 'auditorium' => $auditorium]);
    }

    /** Auditoriyani o'chirish (kartochkalarda ishlatilsa faqat nofaollashadi). */
    public function destroyAuditorium(Request $request, Auditorium $auditorium)
    {
        if ($this->timetableActiveRole($request) === 'kafedra_mudiri') {
            if (!Schema::hasColumn('auditoriums', 'department_hemis_id')) {
                return response()->json(['error' => 'Auditoriya kafedrasi migratsiyasi bajarilmagan.'], 503);
            }

            $context = $this->departmentHeadContext($request);
            $ownsAuditorium = (int) $auditorium->department_hemis_id === (int) $context['department_hemis_id']
                && !empty($auditorium->created_by_teacher_id);

            if (!$ownsAuditorium) {
                abort(403, 'Faqat o\'z kafedrangiz yaratgan auditoriyani o\'chira olasiz.');
            }
        }

        $used = TimetableCard::where('auditorium_code', $auditorium->code)->exists();
        if ($used) {
            $auditorium->update(['active' => false]);

            return response()->json([
                'ok' => true,
                'deactivated' => true,
                'message' => 'Auditoriya jadvalda ishlatilgani uchun nofaol qilindi.',
            ]);
        }

        $auditorium->delete();

        return response()->json([
            'ok' => true,
            'deactivated' => false,
            'message' => 'Auditoriya o\'chirildi.',
        ]);
    }

    private function validateAuditorium(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'                 => 'required|string|max:50|unique:auditoriums,code' . ($ignoreId ? ',' . $ignoreId : ''),
            'name'                 => 'required|string|max:255',
            'volume'               => 'required|integer|min:0|max:2000',
            'active'               => 'nullable|boolean',
            'building_name'        => 'nullable|string|max:255',
            'auditorium_type_name' => 'nullable|string|max:255',
        ]);
    }

    /**
     * Auditoriyalarni Excel/CSV dan import qilish. Kutilgan sarlavhalar
     * (kichik harf, bo'sh joy "_"): kod | nomi | sigim | bino | turi.
     * Mavjud kod yangilanadi, yo'q kod qo'shiladi (upsert).
     */
    public function importAuditoriums(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt']);

        $import = new \App\Imports\AuditoriumImport();
        \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

        return response()->json([
            'ok' => true,
            'imported' => $import->imported,
            'updated' => $import->updated,
            'errors' => $import->errors,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  O'qituvchi biriktirish: dars birliklari (subject × oqim/guruh) bo'yicha
    //  ommaviy biriktirish. Bir birlikning barcha (haftalik takror) kartalari
    //  bitta o'qituvchiga tegishli bo'ladi.
    // ══════════════════════════════════════════════════════════════════════

    /** Karta uchun dars birligi kaliti (ma'ruza — oqim; amaliy — guruhcha). */
    private function unitKey(TimetableCard $c): string
    {
        $scope = $c->training_type === 'lecture' ? ('L|' . $c->oqim_label) : ('P|' . $c->group_name);
        return implode('¦', [$c->specialty_name, $c->course, $c->subject_name, $c->training_type, $scope]);
    }

    /** Doskadagi dars birliklari + joriy o'qituvchi (biriktirish matritsasi uchun). */
    public function teacherUnits(TimetableBoard $board)
    {
        $cards = TimetableCard::where('board_id', $board->id)->get();
        $units = [];
        foreach ($cards as $c) {
            $k = $this->unitKey($c);
            if (!isset($units[$k])) {
                $units[$k] = [
                    'specialty_name' => $c->specialty_name, 'course' => (int) $c->course,
                    'subject_name'   => $c->subject_name, 'training_type' => $c->training_type,
                    'oqim_label'     => $c->oqim_label, 'group_name' => $c->group_name,
                    'kafedra_name'   => $c->kafedra_name, 'lang' => $c->lang,
                    'students'       => (int) $c->students, 'cards' => 0,
                    'placed'         => 0,
                    'teacher_id'     => $c->teacher_id, 'teacher_name' => $c->teacher_name,
                    'teacher_mixed'  => false,
                ];
            }
            $units[$k]['cards']++;
            if ($c->day && $c->pair) {
                $units[$k]['placed']++;
            }
            if ($units[$k]['teacher_id'] !== $c->teacher_id) {
                $units[$k]['teacher_mixed'] = true;
            }
        }
        $out = array_values($units);
        usort($out, fn($a, $b) => [$a['specialty_name'], $a['course'], $b['training_type'], $a['subject_name'], (string) $a['oqim_label'], (string) $a['group_name']]
            <=> [$b['specialty_name'], $b['course'], $a['training_type'], $b['subject_name'], (string) $b['oqim_label'], (string) $b['group_name']]);

        return response()->json(['units' => $out]);
    }

    /** Dars birligiga o'qituvchini ommaviy biriktirish (barcha kartalariga). */
    public function assignTeacher(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'specialty_name' => 'required|string|max:255',
            'course'         => 'required|integer|min:1|max:7',
            'subject_name'   => 'required|string|max:255',
            'training_type'  => 'required|in:lecture,practice',
            'oqim_label'     => 'nullable|string|max:50',
            'group_name'     => 'nullable|string|max:255',
            'teacher_id'     => 'nullable|integer|exists:teachers,id',
        ]);

        $q = TimetableCard::where('board_id', $board->id)
            ->where('specialty_name', $data['specialty_name'])
            ->where('course', $data['course'])
            ->where('subject_name', $data['subject_name'])
            ->where('training_type', $data['training_type']);
        if ($data['training_type'] === 'lecture') {
            isset($data['oqim_label']) ? $q->where('oqim_label', $data['oqim_label']) : $q->whereNull('oqim_label');
        } else {
            isset($data['group_name']) ? $q->where('group_name', $data['group_name']) : $q->whereNull('group_name');
        }

        $teacherName = null;
        if (!empty($data['teacher_id'])) {
            $t = Teacher::findOrFail($data['teacher_id']);
            if ($this->timetableActiveRole($request) === 'kafedra_mudiri') {
                $context = $this->departmentHeadContext($request);
                if ((int) $t->department_hemis_id !== (int) $context['department_hemis_id']) {
                    return response()->json([
                        'error' => "Faqat o'z kafedrangizdagi o'qituvchini biriktira olasiz.",
                    ], 422);
                }
            }
            $teacherName = $t->short_name ?: $t->full_name;
            $affected = $q->update(['teacher_id' => $t->id, 'teacher_name' => $teacherName]);
        } else {
            $affected = $q->update(['teacher_id' => null, 'teacher_name' => null]);
        }

        return response()->json(['ok' => true, 'teacher_name' => $teacherName, 'affected' => $affected]);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Umumiy sozlamalar (aSc "Установки" uslubida): muassasa nomi, kunlar,
    //  dam olish kunlari va qo'ng'iroqlar jadvali (juftliklar vaqtlari).
    // ══════════════════════════════════════════════════════════════════════

    /** Doska sozlamalarini o'qish (default qiymatlar bilan to'ldirib). */
    public function settings(TimetableBoard $board)
    {
        return response()->json([
            'institution_name' => $board->institution_name ?: $board->faculty_name,
            'academic_year'    => $board->academic_year,
            'days'             => (int) $board->days,
            'pairs_per_day'    => (int) $board->pairs_per_day,
            'weeks'            => (int) $board->weeks,
            'day_names'        => $board->day_names ?: array_slice(TimetableBoard::DEFAULT_DAY_NAMES, 0, (int) $board->days),
            'bell_schedule'    => $board->bell_schedule ?: TimetableBoard::defaultBellSchedule((int) $board->pairs_per_day),
            'settings'         => $board->settings ?: ['days_off' => ['Yakshanba'], 'allow_zero' => false, 'show_day_number' => false],
        ]);
    }

    /**
     * Sozlamalarni saqlash. Qo'ng'iroqlar jadvalidagi "pair" (juftlik) elementlar
     * soni kuniga para soni sifatida saqlanadi; panjaradan tashqarida qolgan
     * joylashuvlar bo'shatiladi.
     */
    public function saveSettings(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'institution_name'      => 'nullable|string|max:255',
            'days'                  => 'required|integer|min:1|max:7',
            'day_names'             => 'nullable|array',
            'day_names.*'           => 'nullable|string|max:40',
            'bell_schedule'         => 'required|array|min:1',
            'bell_schedule.*.type'  => 'required|in:pair,break',
            'bell_schedule.*.name'  => 'nullable|string|max:40',
            'bell_schedule.*.abbr'  => 'nullable|string|max:15',
            'bell_schedule.*.start' => 'nullable|string|max:5',
            'bell_schedule.*.end'   => 'nullable|string|max:5',
            'bell_schedule.*.print' => 'nullable|boolean',
            'settings'              => 'nullable|array',
        ]);

        // Juftliklarni qayta raqamlaymiz; para soni = "pair" elementlar soni
        $pairNo = 0;
        $schedule = array_map(function ($it) use (&$pairNo) {
            $type = $it['type'] === 'pair' ? 'pair' : 'break';
            return [
                'type'  => $type,
                'no'    => $type === 'pair' ? ++$pairNo : null,
                'name'  => trim((string) ($it['name'] ?? '')) ?: ($type === 'pair' ? $pairNo . '-para' : 'Tanaffus'),
                'abbr'  => trim((string) ($it['abbr'] ?? '')),
                'start' => trim((string) ($it['start'] ?? '')),
                'end'   => trim((string) ($it['end'] ?? '')),
                'print' => (bool) ($it['print'] ?? true),
            ];
        }, $data['bell_schedule']);

        $pairsPerDay = max(1, $pairNo);

        $board->update([
            'institution_name' => $data['institution_name'] ?? null,
            'days'             => $data['days'],
            'pairs_per_day'    => $pairsPerDay,
            'day_names'        => array_values(array_slice($data['day_names'] ?? TimetableBoard::DEFAULT_DAY_NAMES, 0, $data['days'])),
            'bell_schedule'    => $schedule,
            'settings'         => $data['settings'] ?? $board->settings,
        ]);

        // Yangi o'lchamdan tashqarida qolgan joylashuvlarni bo'shatamiz
        TimetableCard::where('board_id', $board->id)
            ->where(function ($q) use ($data, $pairsPerDay) {
                $q->where('day', '>', $data['days'])->orWhere('pair', '>', $pairsPerDay);
            })
            ->update(['day' => null, 'pair' => null]);

        return response()->json(['ok' => true, 'pairs_per_day' => $pairsPerDay]);
    }
}
