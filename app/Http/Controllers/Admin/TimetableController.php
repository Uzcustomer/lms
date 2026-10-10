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
use App\Models\TimetableCyclePlacement;
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

    private function practiceGroupSizeOverrides(): array
    {
        return DB::table('subject_kafedra_overrides')
            ->whereNotNull('practice_group_size')
            ->pluck('practice_group_size', 'norm_name')
            ->map(fn($v) => (int) $v)
            ->all();
    }

    private function defaultPracticeGroupSize(?string $subject): int
    {
        $t = $this->normSubject((string) $subject);

        foreach (['klinik', 'kasallik', 'terapiya', 'xirurgiya', 'jarrohlik', 'pediatriya', 'akusher',
                  'ginekolog', 'nevrolog', 'kardiolog', 'onkolog', 'urolog', 'endokrin', 'dermato',
                  'psixiatr', 'stomatolog', 'ftiziatr', 'reanimatsiya', 'anesteziolog', 'yuqumli'] as $kw) {
            if (str_contains($t, $kw)) {
                return 10;
            }
        }

        if (preg_match('/(\btil|xorijiy|ingliz|inglis)/u', $t)) {
            return 15;
        }

        foreach (['ijtimoiy', 'gumanitar', 'tarix', 'falsafa', 'din', 'huquq', 'iqtisod', 'pedagog',
                  'psixolog', 'jismoniy', 'sport', 'madaniyat', 'siyosat'] as $kw) {
            if (str_contains($t, $kw)) {
                return 30;
            }
        }

        return 15;
    }

    private function practiceGroupSizeFor(array $overrides, string $subject): int
    {
        $k = $this->normSubject($subject);
        return (int) ($overrides[$k] ?? $this->defaultPracticeGroupSize($subject));
    }

    private function specKey(?string $name): string
    {
        return preg_replace('/[^a-z0-9]/u', '', mb_strtolower(trim((string) $name)));
    }

    /**
     * Ba'zi yangi ishchi rejalarda fakultet nomi yo'nalish nomiga qo'shilib keladi
     * (masalan: "1-son Davolash ishi"). Snapshotda esa fakultet alohida, yo'nalish
     * "Davolash ishi" bo'lib turadi. Kartochka yaratishda ikkalasini juftlash uchun
     * shunday reja nomlaridan fakultet va sof yo'nalish aliasini ajratamiz.
     */
    private function curriculumScopeAlias(string $specialtyName): array
    {
        $name = trim($specialtyName);
        if (preg_match('/^(\d+)\s*-\s*son\s+davolash\s+ishi$/iu', $name, $m)) {
            return [$m[1] . '-son davolash', 'Davolash ishi'];
        }

        return [null, $name];
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
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + (CASE WHEN CAST(mc.level_code AS UNSIGNED) >= 11 THEN CAST(mc.level_code AS UNSIGNED) - 10 ELSE CAST(mc.level_code AS UNSIGNED) END) - 1) = ?", [$start])
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
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + (CASE WHEN CAST(mc.level_code AS UNSIGNED) >= 11 THEN CAST(mc.level_code AS UNSIGNED) - 10 ELSE CAST(mc.level_code AS UNSIGNED) END) - 1) = ?", [$start])
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
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + (CASE WHEN CAST(mc.level_code AS UNSIGNED) >= 11 THEN CAST(mc.level_code AS UNSIGNED) - 10 ELSE CAST(mc.level_code AS UNSIGNED) END) - 1) = ?", [$start])
            ->groupBy('mc.specialty_name', 'mc.level_code', 's.subject_name')
            ->selectRaw("mc.specialty_name, mc.level_code, s.subject_name,
                MAX(s.lecture) as lecture,
                MAX(s.practice) as practice, MAX(s.laboratory) as laboratory, MAX(s.seminar) as seminar")
            ->get();

        // Kafedra xaritasi
        [$kafMap, $overrides] = $this->buildKafedraMap();
        $practiceSizeOverrides = $this->practiceGroupSizeOverrides();

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
        $subjByScopeSpec = [];
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

            [$facultyAlias, $specialtyAlias] = $this->curriculumScopeAlias((string) $s->specialty_name);
            if ($facultyAlias !== null) {
                $scopedKey = $this->specKey($facultyAlias) . '|' . $this->specKey($specialtyAlias);
                $subjByScopeSpec[$scopedKey][$course][] = $s;
            }
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
                    $scopedSk = $this->specKey((string) $blockFac) . '|' . $sk;
                    $subs = $subjByScopeSpec[$scopedSk][$course] ?? $subjBySpec[$sk][$course] ?? null;
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
                            $practiceGroupSize = $this->practiceGroupSizeFor($practiceSizeOverrides, (string) $s->subject_name);
                            $pairPracticeGroups = (float) $s->seminar > 0 || $practiceGroupSize >= 30;
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
                                $practiceRowsByName = [];
                                foreach ($oq['rows'] ?? [] as $gr) {
                                    $gn = trim((string) ($gr['name'] ?? ''));
                                    if ($gn === '') {
                                        continue;
                                    }
                                    if (!isset($practiceRowsByName[$gn])) {
                                        $practiceRowsByName[$gn] = $gr;
                                    } else {
                                        $practiceRowsByName[$gn]['count'] = max(
                                            (int) ($practiceRowsByName[$gn]['count'] ?? 0),
                                            (int) ($gr['count'] ?? 0)
                                        );
                                    }
                                }

                                $practiceRows = array_values($practiceRowsByName);
                                $practiceBundles = $pairPracticeGroups
                                    ? array_chunk($practiceRows, 2)
                                    : array_map(static fn($gr) => [$gr], $practiceRows);

                                foreach ($practiceBundles as $bundle) {
                                    $names = [];
                                    $students = 0;
                                    foreach ($bundle as $gr) {
                                        $gn = trim((string) ($gr['name'] ?? ''));
                                        if ($gn === '') {
                                            continue;
                                        }
                                        $names[] = $gn;
                                        $students += (int) ($gr['count'] ?? 0);
                                    }
                                    if (!$names) {
                                        continue;
                                    }
                                    $groupLabel = implode(' + ', $names);
                                    // Bu guruhga shu fan bo'yicha kartalar allaqachon yaratilganmi?
                                    $prcKey = $sk . '|' . $course . '|' . $groupLabel . '|' . $subjKey;
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
                                            'group_name' => $groupLabel,
                                            'group_names' => count($names) > 1 ? json_encode($names, JSON_UNESCAPED_UNICODE) : null,
                                            'subject_name' => $s->subject_name, 'kafedra_name' => $kaf,
                                            'students' => $students,
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
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + (CASE WHEN CAST(mc.level_code AS UNSIGNED) >= 11 THEN CAST(mc.level_code AS UNSIGNED) - 10 ELSE CAST(mc.level_code AS UNSIGNED) END) - 1) = ?", [$start])
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
            // Kartochkalar o'chirilib qayta yaratildi — ular bilan birga
            // timetable_card_overrides dagi hafta istisnolari ham cascade
            // bo'yicha o'chib ketdi. Ularni tiklamasak, ma'ruza va uni
            // ALMASHTIRUVCHI amaliy karta bir xil (to'liq) hafta niqobini
            // oladi, bitta slotni bo'lisha olmaydi va qo'lda joylashda
            // ular bir-biriga to'qnash deb ko'rinadi. Shuning uchun taqsimotni darhol qayta hisoblaymiz.
            $this->assignCardWeeks($board);
        }

        return response()->json(['ok' => true, 'regenerated' => $weeksChanged]);
    }

    /**
     * Joylashuvlarni bo'shatish (kartochkalarni panelga qaytarish) — qamrov
     * bo'yicha: yo'nalish+kurs / kurs (barcha yo'nalishlar) / butun doska.
     * Kartochkalar o'chirilmaydi; faqat kun/para/auditoriya va haftaga xos
     * joylashuvlar tozalanadi.
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
        $scopeQuery = TimetableCard::where('board_id', $board->id);
        $this->applyScopeToQuery($scopeQuery, $facSet, $specSet, $courseSet);
        if (!empty($data['training_type'])) {
            $scopeQuery->where('training_type', $data['training_type']);
        }

        $q = (clone $scopeQuery)
            ->where(function ($w) { $w->whereNotNull('day')->orWhereNotNull('pair'); });
        $count = (clone $q)->count();

        // Haftaga xos joylashuvlar (tanlangan haftada boshqa joyga ko'chirilgan
        // kartalar) ham bo'shatiladi — aks holda o'sha haftada jadval to'la
        // qolib, kartalar panelga qaytmasdi. "Bu haftada o'tilmaydi" (cancelled)
        // yozuvlari hafta taqsimoti bo'lgani uchun saqlanadi.
        $weekMoves = null;
        if (Schema::hasTable('timetable_card_overrides')) {
            $weekMoves = TimetableCardOverride::whereIn('card_id', (clone $scopeQuery)->select('id'))
                ->where('cancelled', false);
            $count += (clone $weekMoves)->whereNotNull('day')->count();
        }

        DB::transaction(function () use ($q, $weekMoves) {
            $q->update([
                'day' => null,
                'pair' => null,
                'auditorium_code' => null,
                'auditorium_name' => null,
            ]);
            $weekMoves?->delete();
        });

        // Avtomatik joylash olib tashlangan; eski doskalarda qolgan sabab
        // yozuvlari bo'shatishda tozalanadi.
        $scopeQuery->update([
            'placement_reason_code' => null,
            'placement_reason' => null,
        ]);

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
        $hasSeason = Schema::hasColumn('timetable_subject_settings', 'season');
        $columns = ['specialty_name', 'course', 'subject_name', 'mode', 'rotation_group', 'occurrences', 'cycle_days'];
        if ($hasSeason) {
            $columns[] = 'season';
        }
        return TimetableSubjectSetting::where('board_id', $board->id)
            ->get($columns)
            ->map(fn($s) => [
                'specialty_name' => $s->specialty_name,
                'course'         => (int) $s->course,
                'subject_name'   => $s->subject_name,
                'mode'           => $s->mode,
                'season'         => $hasSeason ? ((string) ($s->season ?: $board->semester_parity)) : $board->semester_parity,
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
            'season'         => 'nullable|in:kuzgi,bahorgi',
            'rotation_group' => 'nullable|string|max:255',
            'occurrences'    => 'nullable|integer|min:1|max:60',
            'cycle_days'     => 'nullable|integer|min:1|max:120',
        ]);
        $hasSeason = Schema::hasTable('timetable_subject_settings')
            && Schema::hasColumn('timetable_subject_settings', 'season');
        $season = $data['season'] ?? $board->semester_parity;
        $seasonOverride = $hasSeason && $season !== $board->semester_parity ? $season : null;

        // Mavjud yozuvni katta-kichik harfga befarq topamiz — reja (mc) nomi
        // "Davolash ishi", karta/snapshot nomi "davolash ishi" bo'lishi mumkin;
        // dublikat yaratmaslik uchun mavjudini yangilaymiz.
        $existing = TimetableSubjectSetting::where('board_id', $board->id)
            ->where('course', (int) $data['course'])
            ->whereRaw('LOWER(TRIM(specialty_name)) = ?', [mb_strtolower(trim($data['specialty_name']))])
            ->whereRaw('LOWER(TRIM(subject_name)) = ?', [mb_strtolower(trim($data['subject_name']))])
            ->first();

        // normal — sozlama shart emas, mavjud yozuvni o'chiramiz
        if ($data['mode'] === 'normal' && $seasonOverride === null) {
            if ($existing) {
                $existing->delete();
            }
            return response()->json(['ok' => true, 'mode' => 'normal', 'season' => $board->semester_parity]);
        }

        $values = [
            'mode'           => $data['mode'],
            'rotation_group' => $data['mode'] === 'alternate' ? ($data['rotation_group'] ?? null) : null,
            'occurrences'    => $data['mode'] === 'alternate' ? ($data['occurrences'] ?? null) : null,
            'cycle_days'     => $data['mode'] === 'cycle' ? ($data['cycle_days'] ?? null) : null,
        ];
        if ($hasSeason) {
            $values['season'] = $seasonOverride;
        }
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

        return response()->json(['ok' => true, 'mode' => $data['mode'], 'season' => $season]);
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

        // Idempotentlik: allaqachon istisno yozuvi bor kartalarga tegmaymiz.
        // Bu metod endi generateCards() dan tashqari saveGrid() dan ham
        // chaqiriladi; qo'sh yozuv (card_id, week) unique indeksini buzardi,
        // qolaversa foydalanuvchi qo'lda bekor qilgan haftalar ham yo'qolardi.
        $haveOverrides = DB::table('timetable_card_overrides')
            ->whereIn('card_id', $cards->pluck('id'))
            ->distinct()->pluck('card_id')
            ->flip()->all();

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
            if (isset($haveOverrides[(int) $c->id])) {
                continue;   // yozuvi bor — qayta yozmaymiz
            }
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

    /** Guruhcha nomidan asosiy oqim kodi olinadi: d1/22-01a (rus) -> d1/22-01. */
    private function baseGroup(string $gn): string
    {
        $clean = preg_replace(
            "/\\s*\\((?:rus|ru|рус|russ|ing|eng|engl|ang|angl|англ|o['’‘]?z|oz|uz|ўз|узб)[^)]*\\)\\s*$/ui",
            '',
            trim($gn)
        );

        return preg_replace('/([0-9])\s*[a-eа-е]$/iu', '$1', trim($clean ?? $gn));
    }

    /**
     * Sikl kalendarida bitta qator = bitta OQIM (ma'ruzaga birga boradigan
     * guruhlar to'plami), guruh emas. Oqim nomi kartaning `oqim_label` ustunidan
     * olinadi — u karta generatsiyasida tasdiqlangan snapshotdan yoziladi.
     *
     * MUHIM: `oqim_label` ("1-oqim") faqat bitta fakultet+yo'nalish+kurs+til
     * ichida unikal. Joylashuvlar jadvalining kaliti esa faqat yo'nalish+kurs+
     * `group_name` — shu sabab nomga fakultet va til qo'shiladi, aks holda ikki
     * fakultetning "1-oqim"i bitta qatorga qo'shilib ketardi.
     *
     * Eski (label'siz) kartalarda label bo'sh bo'lishi mumkin: u holda avvalgi
     * xatti-harakat saqlanadi va qator asosiy guruh bo'yicha quriladi.
     */
    private function cycleFlowName(TimetableCard $card, ?string $member = null): string
    {
        $label = trim((string) ($card->oqim_label ?? ''));
        if ($label !== '') {
            $parts = [];
            $faculty = trim((string) ($card->faculty_name ?? ''));
            if ($faculty !== '') {
                $parts[] = $faculty;
            }
            $parts[] = $label;
            $lang = trim((string) ($card->lang ?? ''));
            if ($lang !== '' && mb_strtolower($lang) !== 'uz') {
                $parts[] = mb_strtolower($lang);
            }

            // Ajratgich ASCII: nom HTML data-atributi, FormData va SQL kaliti
            // sifatida aylanib yuradi — kodlanishga bog'liq belgi ishlatilmaydi.
            return implode(' / ', $parts);
        }

        $fallback = $member !== null ? $member : (string) ($card->group_name ?? '');

        return $this->baseGroup($fallback);
    }

    /** Karta ichidagi birlashtirilgan nomlarni haqiqiy a/b/c guruhlarga yoyadi. */
    private function cycleCardGroups(TimetableCard $card): array
    {
        $groups = [];
        foreach ($card->occupiedGroups() as $name) {
            foreach (preg_split('/\s+\+\s+/u', trim((string) $name)) ?: [] as $member) {
                $member = trim($member);
                if ($member !== '') {
                    $groups[$member] = true;
                }
            }
        }

        return array_keys($groups);
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
            'clear'             => 'nullable|boolean',
            'view'              => 'nullable|in:flow,group',
        ]);
        $cycleView = $data['view'] ?? 'flow';
        // O'quv bo'limi uchun sodda ko'rinish: ma'ruza/amaliy ajratilmaydi,
        // para qatorlari yo'q — faqat fan nomi, kunlar va joylash qoidalari.
        // Kafedra mudiri xuddi shu bitta kartani ko'radi, lekin uni ko'chira
        // olmaydi — blok ichida ma'ruza soatlarini belgilaydi.
        $cycleRole = $this->timetableActiveRole($request);
        $cycleMark = $cycleRole === 'kafedra_mudiri';
        // Migratsiya hali bajarilmagan bo'lsa belgilar o'qilmaydi — sahifa
        // baribir ochiladi, faqat belgilash saqlanmaydi.
        $hasLectureSlots = Schema::hasTable('timetable_cycle_placements')
            && Schema::hasColumn('timetable_cycle_placements', 'lecture_slots');
        $cycleSimple = $cycleMark || in_array($cycleRole, ['oquv_bolimi', 'oquv_bolimi_boshligi'], true);
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

        // Kalendar barcha kunlarni ko'rsatadi. Yakshanba va bayramlar blokka
        // kiritiladi, ammo sikl kuniga kirmaydi: shuning uchun blok ularni kesib
        // o'tsa, ekranda shuncha kalendar kuni uzunroq ko'rinadi.
        $D = max(1, (int) $board->days);
        $W = max(1, (int) $board->weeks);
        $dates = [];
        $workIndices = [];
        $cur = $startC->copy();
        $guard = 0;
        $targetWorkDays = $W * $D;
        while (count($workIndices) < $targetWorkDays && $guard < $targetWorkDays * 8 + count($holSet) * 2 + 60) {
            $dow = (int) $cur->dayOfWeekIso;
            $isHoliday = isset($holSet[$cur->toDateString()]);
            $isTeachingDay = $dow <= $D && $dow < 7 && !$isHoliday;
            $dates[] = $cur->copy();
            if ($isTeachingDay) {
                $workIndices[] = count($dates) - 1;
            }
            $cur->addDay();
            $guard++;
        }
        $totalDays = count($dates);
        $endIndexFor = function (int $from, int $needed) use ($workIndices): ?int {
            foreach ($workIndices as $position => $calendarIndex) {
                if ($calendarIndex >= $from) {
                    return $workIndices[$position + max(1, $needed) - 1] ?? null;
                }
            }
            return null;
        };

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

        // Oqimlar va ularning sikl fanlari kartalardan yig'iladi. Har bir qator
        // shu oqim tarkibidagi barcha amaliy guruhlarni birga qamrab oladi.
        $byFlow = [];
        if (!empty($cycleKey)) {
            foreach (TimetableCard::where('board_id', $board->id)->get() as $c) {
                if ($c->training_type !== 'practice' || !$c->group_name || !$inScope($c)) {
                    continue;
                }
                $ck = $normKey($c->specialty_name, $c->course, $c->subject_name);
                if (!isset($cycleKey[$ck])) {
                    continue;
                }
                foreach ($this->cycleCardGroups($c) as $member) {
                    // Guruh rejimida har subguruh o'z qatori; oqim rejimida oqim nomi.
                    $flow = $cycleView === 'group' ? $member : $this->cycleFlowName($c, $member);
                    $flowKey = mb_strtolower(trim((string) $c->specialty_name)) . '|' . (int) $c->course . '|' . mb_strtolower($flow);
                    if (!isset($byFlow[$flowKey])) {
                        $byFlow[$flowKey] = ['name' => $flow, 'subs' => [], 'members' => [], 'req' => [],
                            'faculty' => $c->faculty_name, 'specialty' => $c->specialty_name, 'course' => (int) $c->course];
                    }
                    $byFlow[$flowKey]['subs'][$c->subject_name] = $cycleKey[$ck];
                    $byFlow[$flowKey]['members'][$member] = true;

                    // Rekvizitlar kartalardan: Biriktirish tabi bilan bitta manba.
                    $reqKey = $c->subject_name . '|practice';
                    if (!isset($byFlow[$flowKey]['req'][$reqKey])) {
                        $byFlow[$flowKey]['req'][$reqKey] = ['teachers' => [], 'auds' => []];
                    }
                    if ($c->teacher_name) {
                        $byFlow[$flowKey]['req'][$reqKey]['teachers'][$c->teacher_name] = true;
                    }
                    if ($c->auditorium_name || $c->auditorium_code) {
                        $byFlow[$flowKey]['req'][$reqKey]['auds'][$c->auditorium_name ?: $c->auditorium_code] = true;
                    }
                }
            }
        }

        // Ma'ruza kartalarining rekvizitlari (o'qituvchi/xona) ham blokda ko'rinsin.
        if (!empty($cycleKey)) {
            foreach (TimetableCard::where('board_id', $board->id)->where('training_type', 'lecture')->get() as $c) {
                if (!$inScope($c)) {
                    continue;
                }
                $ck = $normKey($c->specialty_name, $c->course, $c->subject_name);
                if (!isset($cycleKey[$ck])) {
                    continue;
                }
                $lectureRows = $cycleView === 'group'
                    ? $this->cycleCardGroups($c)
                    : [$this->cycleFlowName($c)];
                foreach ($lectureRows as $flow) {
                    $flowKey = mb_strtolower(trim((string) $c->specialty_name)) . '|' . (int) $c->course . '|' . mb_strtolower($flow);
                    if (!isset($byFlow[$flowKey])) {
                        continue;
                    }
                    $reqKey = $c->subject_name . '|lecture';
                    if (!isset($byFlow[$flowKey]['req'][$reqKey])) {
                        $byFlow[$flowKey]['req'][$reqKey] = ['teachers' => [], 'auds' => []];
                    }
                    if ($c->teacher_name) {
                        $byFlow[$flowKey]['req'][$reqKey]['teachers'][$c->teacher_name] = true;
                    }
                    if ($c->auditorium_name || $c->auditorium_code) {
                        $byFlow[$flowKey]['req'][$reqKey]['auds'][$c->auditorium_name ?: $c->auditorium_code] = true;
                    }
                }
            }
        }

        // Fanlar global tartibi (nomi bo'yicha) — rotatsiya uchun
        $allSubs = [];
        foreach ($byFlow as $g) {
            foreach ($g['subs'] as $sn => $dd) {
                $allSubs[$sn] = $dd;
            }
        }
        ksort($allSubs);
        $subOrder = array_keys($allSubs);

        // Qatorlar yo'nalish → kurs → oqim tartibida. Oqim nomi "1-oqim" ko'rinishida
        // bo'lgani uchun raqami bo'yicha (10-oqim 2-oqimdan keyin) tartiblanadi.
        $groups = array_values($byFlow);
        usort($groups, function ($a, $b) {
            return [$a['specialty'], (int) $a['course']] <=> [$b['specialty'], (int) $b['course']]
                ?: strnatcasecmp($a['name'], $b['name']);
        });

        $pairsPerDay = max(1, (int) $board->pairs_per_day);
        // Reja soatlari: ma'ruza bloki uzunligi va kartadagi soat yozuvi uchun.
        $cycleHours = !empty($cycleKey) ? $this->cycleSubjectHoursMap($board) : [];
        $hoursFor = fn(string $spec, int $course, string $subject): ?array =>
            $cycleHours[$this->specKey($spec) . '|' . $course . '|' . $this->normSubject($subject)] ?? null;
        $fmtHours = fn(?float $v) => $v === null ? null : (fmod($v, 1.0) == 0.0 ? (int) $v : round($v, 1));
        $rowHours = $this->cycleRowHours($board);
        $rowKey = fn($g) => (string) $g['specialty'] . '|' . (int) $g['course'] . '|' . (string) $g['name'];
        $subjectKey = fn($g, $subject) => $rowKey($g) . '|' . (string) $subject;
        // Sikl joylashuvlari alohida saqlanadi: oddiy place endpointi haftalik
        // kartalarning day/pair maydonlarini buzmasligi kerak.
        $hasCyclePlacements = Schema::hasTable('timetable_cycle_placements');

        // "Bo'shatish": qamrovdagi oqimlarning sikl bloklari panelga qaytariladi.
        // Bloklar faqat qo'lda (cyclePlace) joylanadi — avtomatik joylash yo'q.
        if ($request->boolean('clear')) {
            $scopeRows = array_map(fn ($g) => [
                'specialty' => $g['specialty'],
                'course'    => $g['course'],
                'group'     => $g['name'],
            ], $groups);

            if ($hasCyclePlacements) {
                DB::transaction(function () use ($scopeRows, $board) {
                    foreach ($scopeRows as $row) {
                        TimetableCyclePlacement::where('board_id', $board->id)
                            ->where('specialty_name', $row['specialty'])
                            ->where('course', $row['course'])
                            ->where('group_name', $row['group'])
                            ->delete();
                    }
                });
            } else {
                // Eski deploylarda migration keyinroq ishlashi mumkin. Jadval paydo
                // bo'lguncha joylashuvlar doska settings JSONida saqlanadi.
                $fallback = collect($set['cycle_placements'] ?? []);
                foreach ($scopeRows as $row) {
                    $fallback = $fallback->reject(fn($item) => ($item['specialty_name'] ?? '') === $row['specialty']
                        && (int) ($item['course'] ?? 0) === (int) $row['course']
                        && ($item['group_name'] ?? '') === $row['group'])->values();
                }
                $set['cycle_placements'] = $fallback->values()->all();
                $board->update(['settings' => $set]);
            }
        }

        $saved = $hasCyclePlacements
            ? TimetableCyclePlacement::where('board_id', $board->id)->get()
            : collect($set['cycle_placements'] ?? [])->map(fn($item) => (object) $item);
        $savedByRow = [];
        foreach ($saved as $placement) {
            $key = (string) $placement->specialty_name . '|' . (int) $placement->course . '|' . (string) $placement->group_name;
            $savedByRow[$key][] = $placement;
        }

        $rows = [];
        $cycleCards = [];
        foreach ($groups as $g) {
            $rk = $rowKey($g);
            $savedBlocks = collect($savedByRow[$rk] ?? [])->sortBy('start_index');
            $blocks = [];
            foreach ($savedBlocks as $placement) {
                if (!array_key_exists($placement->subject_name, $g['subs'])) {
                    continue;
                }
                $blockType = $placement->training_type ?: 'practice';
                // Sodda rejimda ma'ruza yozuvi ko'rsatilmaydi: amaliy blok
                // butun fanni bildiradi, ikkalasi bitta karta bo'lib chiqadi.
                if ($cycleSimple && $blockType === 'lecture') {
                    continue;
                }
                $hrs = $hoursFor((string) $g['specialty'], (int) $g['course'], (string) $placement->subject_name);
                // Kun = 6 soat: ma'ruza o'z soatidan (2 soat/kun), amaliy to'liq sikl.
                $days = $cycleSimple
                    ? max(1, (int) ($g['subs'][$placement->subject_name] ?? 1))
                    : max(1, $this->cycleTypeDays(
                        max(1, (int) ($g['subs'][$placement->subject_name] ?? 1)), $hrs, $blockType
                    ));
                $from = max(0, (int) $placement->start_index);
                if ($from >= $totalDays) {
                    continue;
                }
                $to = $endIndexFor($from, $days);
                if ($to === null) {
                    continue;
                }
                $blockPair = max(1, (int) ($placement->pair ?: 1));
                // Kunlik para egallashi (6 soat modeli): amaliy blok ma'ruzali
                // davrda 2, keyin 3 para; ma'ruza 1 para. Klient shu maydonlar
                // bilan 2-3-paradagi davom segmentlarini chizadi.
                $span = $cycleSimple
                    ? ['head_span' => 1, 'tail_span' => 0, 'head_days' => max(1, (int) ($g['subs'][$placement->subject_name] ?? 1))]
                    : $this->cycleLaneSpan($blockType, $hrs, max(1, (int) ($g['subs'][$placement->subject_name] ?? 1)), $rowHours);
                $headDays = min((int) $span['head_days'], (int) $days);
                $headTo = $headDays > 0 ? $endIndexFor($from, $headDays) : null;
                $tailFrom = $headDays >= $days ? null : ($headDays > 0 ? $endIndexFor($from, $headDays + 1) : $from);
                $req = $g['req'][$placement->subject_name . '|' . $blockType] ?? ['teachers' => [], 'auds' => []];
                $teacherNames = array_keys($req['teachers']);
                $audNames = array_keys($req['auds']);

                $blocks[] = [
                    'key' => $subjectKey($g, $placement->subject_name) . '|' . $blockType,
                    'subject' => $placement->subject_name,
                    'type' => $blockType,
                    'pair' => $blockPair,
                    'from' => $from,
                    'to' => $to,
                    'days' => (int) $days,
                    'hours' => $fmtHours($hrs === null ? null
                        : (float) ($blockType === 'lecture' ? $hrs['lecture'] : $hrs['practice'])),
                    'span_head' => (int) $span['head_span'],
                    'span_tail' => (int) $span['tail_span'],
                    'head_days' => $headDays,
                    'head_to' => $headTo,
                    'tail_from' => $tailFrom,
                    'teacher_name' => $teacherNames
                        ? ($teacherNames[0] . (count($teacherNames) > 1 ? ' +' . (count($teacherNames) - 1) : ''))
                        : null,
                    'lesson_time' => $placement->lesson_time ?? null,
                    'lecture_slots' => $hasLectureSlots ? (array) ($placement->lecture_slots ?? []) : [],
                    'lecture_hours' => $hrs === null ? null : $fmtHours((float) $hrs['lecture']),
                    'auditorium_code' => null,
                    'auditorium_name' => $audNames
                        ? ($audNames[0] . (count($audNames) > 1 ? ' +' . (count($audNames) - 1) : ''))
                        : null,
                ];
            }
            $placedSubjects = collect($blocks)->keyBy(fn ($b) => $b['subject'] . '|' . $b['type']);
            foreach ($g['subs'] as $subject => $days) {
                $hrs = $hoursFor((string) $g['specialty'], (int) $g['course'], (string) $subject);
                // Sodda rejimda fanga bitta karta (ma'ruza+amaliy birga).
                $cardTypes = $cycleSimple ? ['practice'] : ['lecture', 'practice'];
                foreach ($cardTypes as $cardType) {
                    $typeDays = $cycleSimple
                        ? (int) $days
                        : $this->cycleTypeDays((int) $days, $hrs, $cardType);
                    if ($cardType === 'lecture' && $typeDays < 1) {
                        // Rejasida ma'ruza soati yo'q — ma'ruza kartasi chiqmaydi.
                        continue;
                    }
                    $block = $placedSubjects->get($subject . '|' . $cardType);
                    $cycleCards[] = [
                        'key' => $subjectKey($g, $subject) . '|' . $cardType,
                        'row_key' => $rk,
                        'group' => $g['name'],
                        'specialty' => $g['specialty'],
                        'course' => $g['course'],
                        'subject' => $subject,
                        'type' => $cardType,
                        'days' => max(1, $typeDays),
                        'hours' => $fmtHours($hrs === null ? null : (float) ($cycleSimple
                            ? ($hrs['lecture'] + $hrs['practice'])
                            : ($cardType === 'lecture' ? $hrs['lecture'] : $hrs['practice']))),
                        'placed' => (bool) $block,
                        'pair' => $block['pair'] ?? null,
                        'start_index' => $block['from'] ?? null,
                    ];
                }
            }
            $members = array_keys($g['members']);
            sort($members, SORT_NATURAL);
            $rows[] = [
                'row_key' => $rk,
                'group' => $g['name'],
                'subgroups' => $members,
                'faculty' => $g['faculty'],
                'specialty' => $g['specialty'],
                'course' => $g['course'],
                'blocks' => $blocks,
            ];
        }

        return response()->json([
            'start_date' => $startC->toDateString(),
            'holidays'   => $holidays,
            'total_days' => count($workIndices),
            'calendar_days' => $totalDays,
            'total_work_days' => count($workIndices),
            'dates'      => array_map(function ($d) use ($holSet, $D) {
                $dow = (int) $d->dayOfWeekIso;
                $holiday = isset($holSet[$d->toDateString()]);
                return [
                    'd' => $d->format('d.m'), 'iso' => $d->toDateString(), 'dow' => $dow,
                    'sunday' => $dow === 7, 'holiday' => $holiday,
                    'off' => $dow === 7 || $holiday || $dow > $D,
                ];
            }, $dates),
            'subjects'   => array_map(fn($sn) => ['name' => $sn, 'days' => $allSubs[$sn]], $subOrder),
            'rows'       => $rows,
            'pairs'      => $cycleSimple ? 1 : $pairsPerDay,
            'simple'     => $cycleSimple,
            'mark_mode'  => $cycleMark,
            'mark_ready' => $hasLectureSlots,
            'day_hours'  => 6,
            'cycle_cards' => $cycleCards,
        ]);
    }

    /**
     * Sikl bloki rekvizitlari uchun variantlar: fan kafedrasining
     * o'qituvchilari va shu sanalarda band bo'lmagan xonalar.
     *
     * O'qituvchi va xona KARTALARDA (timetable_cards) saqlanadi — Biriktirish
     * tabi bilan bitta manba; bu yerdan biriktirilgani u yerda ham ko'rinadi.
     */
    public function cycleAssignOptions(Request $request, TimetableBoard $board)
    {
        abort_unless(Schema::hasTable('timetable_cycle_placements'), 503, 'Sikl jadvali migratsiyasi hali ishga tushirilmagan.');

        $data = $request->validate([
            'specialty_name' => 'required|string|max:255',
            'course'         => 'required|integer|min:1|max:7',
            'group_name'     => 'required|string|max:255',
            'subject_name'   => 'required|string|max:255',
            'training_type'  => 'nullable|in:lecture,practice',
            'view'           => 'nullable|in:flow,group',
            'start_date'     => 'nullable|date',
            'holidays'       => 'nullable|array',
        ]);

        $placement = $this->findCyclePlacement($board, $data);
        if (!$placement) {
            return response()->json(['error' => 'Bu blok hali jadvalga joylanmagan.'], 422);
        }

        $flowCards = $this->cycleFlowCards($board, $data['specialty_name'], (int) $data['course'], $data['subject_name'], $data['group_name'], $data['training_type'] ?? null, $data['view'] ?? 'flow');
        if ($flowCards->isEmpty()) {
            return response()->json(['error' => 'Bu oqim uchun fan kartalari topilmadi.'], 422);
        }

        // Fanning kafedrasi — kartalardan.
        $kafedra = $flowCards->pluck('kafedra_name')->filter()->first();

        $teachers = collect();
        if ($kafedra) {
            $teachers = Teacher::query()
                ->whereNotNull('full_name')
                ->where('department', 'like', '%' . trim($kafedra) . '%')
                ->orderBy('full_name')
                ->limit(300)
                ->get(['id', 'full_name', 'short_name']);
        }

        $currentTeacherId = $flowCards->pluck('teacher_id')->filter()->first();
        if ($currentTeacherId && !$teachers->contains('id', $currentTeacherId)) {
            $current = Teacher::find($currentTeacherId, ['id', 'full_name', 'short_name']);
            if ($current) {
                $teachers->prepend($current);
            }
        }

        $currentAud = $flowCards->pluck('auditorium_code')->filter()->first();
        $busyRooms = $this->cycleOverlappingRoomCodes($board, $placement, $data);

        $rooms = Auditorium::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['code', 'name', 'volume'])
            ->filter(fn ($room) => !isset($busyRooms[$room->code]) || $room->code === $currentAud)
            ->values();

        return response()->json([
            'kafedra' => $kafedra,
            'teachers' => $teachers->map(fn ($teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->full_name ?: $teacher->short_name,
            ])->values(),
            'rooms' => $rooms->map(fn ($room) => [
                'code' => $room->code,
                'name' => $room->name,
                'volume' => (int) $room->volume,
            ])->values(),
            'current' => [
                'teacher_id' => $currentTeacherId,
                'lesson_time' => $placement->lesson_time,
                'auditorium_code' => $currentAud,
            ],
        ]);
    }

    /**
     * Sikl blokiga o'qituvchi/xonani KARTALARGA, dars vaqtini joylashuvga yozadi.
     * Kartalarga yozilgani uchun Biriktirish tabida ham xuddi shu ko'rinadi.
     */
    public function cycleAssignSave(Request $request, TimetableBoard $board)
    {
        abort_unless(Schema::hasTable('timetable_cycle_placements'), 503, 'Sikl jadvali migratsiyasi hali ishga tushirilmagan.');

        $data = $request->validate([
            'specialty_name'  => 'required|string|max:255',
            'course'          => 'required|integer|min:1|max:7',
            'group_name'      => 'required|string|max:255',
            'subject_name'    => 'required|string|max:255',
            'training_type'   => 'nullable|in:lecture,practice',
            'view'            => 'nullable|in:flow,group',
            'teacher_id'      => 'nullable|integer|exists:teachers,id',
            'lesson_time'     => 'nullable|string|max:50',
            'auditorium_code' => 'nullable|string|max:50',
            'start_date'      => 'nullable|date',
            'holidays'        => 'nullable|array',
        ]);

        $placement = $this->findCyclePlacement($board, $data);
        if (!$placement) {
            return response()->json(['error' => 'Bu blok hali jadvalga joylanmagan.'], 422);
        }

        $flowCards = $this->cycleFlowCards($board, $data['specialty_name'], (int) $data['course'], $data['subject_name'], $data['group_name'], $data['training_type'] ?? null, $data['view'] ?? 'flow');
        if ($flowCards->isEmpty()) {
            return response()->json(['error' => 'Bu oqim uchun fan kartalari topilmadi.'], 422);
        }

        $teacherName = null;
        if (!empty($data['teacher_id'])) {
            $teacher = Teacher::find($data['teacher_id']);
            $teacherName = $teacher?->full_name ?: $teacher?->short_name;
        }

        $roomName = null;
        if (!empty($data['auditorium_code'])) {
            $room = Auditorium::where('code', $data['auditorium_code'])->first();
            if (!$room) {
                return response()->json(['error' => 'Xona topilmadi.'], 422);
            }

            $currentAud = $flowCards->pluck('auditorium_code')->filter()->first();
            $busyRooms = $this->cycleOverlappingRoomCodes($board, $placement, $data);
            if (isset($busyRooms[$room->code]) && $room->code !== $currentAud) {
                return response()->json([
                    'error' => "«{$room->name}» xonasi bu sanalarda «{$busyRooms[$room->code]}» sikliga band.",
                ], 422);
            }

            $roomName = $room->name;
        }

        TimetableCard::whereIn('id', $flowCards->pluck('id'))->update([
            'teacher_id' => ($data['teacher_id'] ?? null) ?: null,
            'teacher_name' => $teacherName,
            'auditorium_code' => ($data['auditorium_code'] ?? null) ?: null,
            'auditorium_name' => $roomName,
        ]);

        $placement->update([
            'lesson_time' => trim((string) ($data['lesson_time'] ?? '')) ?: null,
        ]);

        return response()->json([
            'ok' => true,
            'teacher_name' => $teacherName,
            'lesson_time' => $placement->lesson_time,
            'auditorium_name' => $roomName,
        ]);
    }

    /** Joylashuvni identifikatsiya bo'yicha topadi. */
    private function findCyclePlacement(TimetableBoard $board, array $data): ?TimetableCyclePlacement
    {
        return TimetableCyclePlacement::where('board_id', $board->id)
            ->where('specialty_name', $data['specialty_name'])
            ->where('course', (int) $data['course'])
            ->where('group_name', $data['group_name'])
            ->where('subject_name', $data['subject_name'])
            ->when(!empty($data['training_type']), fn ($q) => $q
                ->where(fn ($w) => $w->where('training_type', $data['training_type'])->orWhereNull('training_type')))
            ->first();
    }

    /** Oqimga tegishli fan kartalari (ma'ruza ham, amaliyot ham). */
    private function cycleFlowCards(TimetableBoard $board, string $specialty, int $course, string $subject, string $flow, ?string $type = null, string $view = 'flow')
    {
        $subjectLower = mb_strtolower(trim($subject));
        $flowLower = mb_strtolower(trim($flow));

        return TimetableCard::where('board_id', $board->id)
            ->where('specialty_name', $specialty)
            ->where('course', $course)
            ->get()
            ->filter(function (TimetableCard $card) use ($subjectLower, $flowLower, $type, $view) {
                if ($type !== null && $card->training_type !== $type) {
                    return false;
                }
                if (mb_strtolower(trim((string) $card->subject_name)) !== $subjectLower) {
                    return false;
                }

                if ($view === 'group') {
                    foreach ($this->cycleCardGroups($card) as $member) {
                        if (mb_strtolower(trim($member)) === $flowLower) {
                            return true;
                        }
                    }

                    return false;
                }

                if ($card->training_type === 'practice') {
                    foreach ($this->cycleCardGroups($card) as $member) {
                        if (mb_strtolower($this->cycleFlowName($card, $member)) === $flowLower) {
                            return true;
                        }
                    }

                    return false;
                }

                return mb_strtolower($this->cycleFlowName($card)) === $flowLower;
            })
            ->values();
    }

    /**
     * Berilgan blok sanalari bilan kesishadigan BOSHQA sikl bloklari band
     * qilgan xonalar: [auditorium_code => fan nomi]. Har blokning xonasi o'z
     * oqim kartalaridan olinadi.
     */
    /**
     * Sikl fanlarining reja soatlari (ishchi rejadan): ma'ruza va amaliy
     * (amaliy = amaliy + laboratoriya + seminar). Ma'lumotlar tabidagi
     * "Fanlar" ro'yxati bilan bitta manba va bitta hisob.
     */
    private function cycleSubjectHoursMap(TimetableBoard $board): array
    {
        $start = (int) substr($board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;

        $rows = DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + (CASE WHEN CAST(mc.level_code AS UNSIGNED) >= 11 THEN CAST(mc.level_code AS UNSIGNED) - 10 ELSE CAST(mc.level_code AS UNSIGNED) END) - 1) = ?", [$start])
            ->groupBy('mc.specialty_name', 'mc.level_code', 's.subject_name')
            ->selectRaw('mc.specialty_name, mc.level_code, s.subject_name,
                MAX(s.lecture) as lecture, MAX(s.practice) as practice,
                MAX(s.laboratory) as laboratory, MAX(s.seminar) as seminar')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $course = (int) $r->level_code >= 11 ? (int) $r->level_code - 10 : (int) $r->level_code;
            $key = $this->specKey((string) $r->specialty_name) . '|' . $course . '|' . $this->normSubject((string) $r->subject_name);
            $lecture = (float) $r->lecture;
            $practice = (float) $r->practice + (float) $r->laboratory + (float) $r->seminar;
            if (!isset($map[$key])) {
                $map[$key] = ['lecture' => $lecture, 'practice' => $practice];
            } else {
                $map[$key]['lecture'] = max($map[$key]['lecture'], $lecture);
                $map[$key]['practice'] = max($map[$key]['practice'], $practice);
            }
        }

        return $map;
    }

    /**
     * Sikl blokining davomiyligi (o'quv kunlarida) mashg'ulot turi bo'yicha.
     * Sikl kuni 6 soat: ma'ruzali kunlarda 2 soat ma'ruza + 4 soat amaliy,
     * ma'ruza soatlari tugagach kuniga 6 soat amaliy. Shunga ko'ra ma'ruza
     * bloki ma'ruza_soati/2 kun davom etadi, amaliy blok — to'liq sikl.
     * Reja soatlari topilmasa eski xulq saqlanadi (ikkalasi to'liq sikl).
     */
    private function cycleTypeDays(int $cycleDays, ?array $hours, string $type): int
    {
        $cycleDays = max(1, $cycleDays);
        if ($type !== 'lecture' || $hours === null) {
            return $cycleDays;
        }
        $lectureDays = (int) ceil(((float) ($hours['lecture'] ?? 0)) / 2);
        return min($cycleDays, max(0, $lectureDays));
    }

    /**
     * Blok kunlik nechta para (lane) egallashini beradi: ma'ruza kuniga
     * 2 soat (to'liq bitta para), amaliy butun sikl davomida kuniga
     * 4 soat (2 para) — ma'ruza tugagandan keyin ham shu hajmda qoladi.
     * Reja soatlari topilmasa eski bir-lane xulqi saqlanadi.
     * head_days — blok boshidan nechta O'QUV kuni head rejimida ekani.
     */
    private function cycleLaneSpan(string $type, ?array $hours, int $cycleDays, int $rowHours = 2): array
    {
        $cycleDays = max(1, $cycleDays);
        $rowHours = max(1, min(2, $rowHours));
        if ($hours === null) {
            return ['head_span' => 1, 'tail_span' => 0, 'head_days' => $cycleDays];
        }
        if ($type === 'lecture') {
            // Ma'ruza kuniga 2 soat = to'liq bitta para.
            return ['head_span' => (int) ceil(2 / $rowHours), 'tail_span' => 0, 'head_days' => $cycleDays];
        }
        // Amaliy bir tekis: butun blok kuniga 4 soat (2 para).
        return [
            'head_span' => (int) ceil(4 / $rowHours),
            'tail_span' => 0,
            'head_days' => $cycleDays,
        ];
    }

    /**
     * Jadvaldagi bitta para qatori necha akademik soat ekani: qo'ng'iroq
     * jadvali yarim-paralarda (40-45 daqiqalik qatorlar, masalan 0.5-para,
     * 1-para, 1.5-para...) bo'lsa 1 soat, to'liq paralarda (80-90 daqiqa) 2.
     */
    private function cycleRowHours(TimetableBoard $board): int
    {
        $mins = [];
        foreach ((array) ($board->bell_schedule ?? []) as $it) {
            $type = is_array($it) ? ($it['type'] ?? '') : ($it->type ?? '');
            if ($type !== 'pair') {
                continue;
            }
            $startT = (string) (is_array($it) ? ($it['start'] ?? '') : ($it->start ?? ''));
            $endT = (string) (is_array($it) ? ($it['end'] ?? '') : ($it->end ?? ''));
            if (!preg_match('/^\d{1,2}:\d{2}$/', $startT) || !preg_match('/^\d{1,2}:\d{2}$/', $endT)) {
                continue;
            }
            [$sh, $sm] = array_map('intval', explode(':', $startT));
            [$eh, $em] = array_map('intval', explode(':', $endT));
            $d = ($eh * 60 + $em) - ($sh * 60 + $sm);
            if ($d > 0) {
                $mins[] = $d;
            }
        }
        if (!$mins) {
            return 2;
        }
        return (array_sum($mins) / count($mins)) >= 60 ? 2 : 1;
    }

    private function cycleOverlappingRoomCodes(TimetableBoard $board, TimetableCyclePlacement $placement, array $data): array
    {
        $set = (array) ($board->settings ?? []);
        $start = $data['start_date'] ?? ($set['semester_start'] ?? null);
        if (!$start) {
            $yearStart = (int) preg_replace('/\\D.*$/', '', (string) $board->academic_year);
            if ($yearStart < 2000) $yearStart = (int) date('Y');
            $start = $board->semester_parity === 'bahorgi'
                ? sprintf('%04d-02-01', $yearStart + 1)
                : sprintf('%04d-09-01', $yearStart);
        }
        $startC = Carbon::parse($start)->startOfDay();

        $holSet = [];
        foreach ((array) ($data['holidays'] ?? ($set['holidays'] ?? [])) as $holiday) {
            try { $holSet[Carbon::parse($holiday)->toDateString()] = true; } catch (\Exception $e) { }
        }

        $daysPerWeek = max(1, (int) $board->days);
        $maxDays = max(1, (int) $board->weeks) * $daysPerWeek;
        $workIndices = [];
        $calendarCount = 0;
        $cur = $startC->copy();
        $guard = 0;
        while (count($workIndices) < $maxDays && $guard < $maxDays * 8 + count($holSet) * 2 + 60) {
            $dow = (int) $cur->dayOfWeekIso;
            $isTeachingDay = $dow <= $daysPerWeek && $dow < 7 && !isset($holSet[$cur->toDateString()]);
            $calendarCount++;
            if ($isTeachingDay) {
                $workIndices[] = $calendarCount - 1;
            }
            $cur->addDay();
            $guard++;
        }

        $endIndexFor = function (int $from, int $needed) use ($workIndices): ?int {
            foreach ($workIndices as $position => $calendarIndex) {
                if ($calendarIndex >= $from) {
                    return $workIndices[$position + max(1, $needed) - 1] ?? null;
                }
            }
            return null;
        };

        // Fan davomiyligi (kunlarda).
        $cycleDays = [];
        foreach (TimetableSubjectSetting::where('board_id', $board->id)->where('mode', 'cycle')->get() as $setting) {
            $key = mb_strtolower(trim($setting->specialty_name)) . '|' . (int) $setting->course . '|' . mb_strtolower(trim($setting->subject_name));
            $cycleDays[$key] = max(1, (int) $setting->cycle_days);
        }
        $cycleHours = $this->cycleSubjectHoursMap($board);
        $daysOf = function ($item) use ($cycleDays, $cycleHours): int {
            $settingDays = (int) ($cycleDays[mb_strtolower(trim($item->specialty_name)) . '|' . (int) $item->course . '|' . mb_strtolower(trim($item->subject_name))] ?? 1);
            $hours = $cycleHours[$this->specKey((string) $item->specialty_name) . '|' . (int) $item->course . '|' . $this->normSubject((string) $item->subject_name)] ?? null;
            return max(1, $this->cycleTypeDays(max(1, $settingDays), $hours, (string) ($item->training_type ?: 'practice')));
        };

        // Har oqim+fan uchun xona — kartalardan (bir marta yig'iladi).
        $roomByKey = [];
        foreach (TimetableCard::where('board_id', $board->id)->whereNotNull('auditorium_code')->get() as $card) {
            $subjectLower = mb_strtolower(trim((string) $card->subject_name));
            $base = mb_strtolower(trim((string) $card->specialty_name)) . '|' . (int) $card->course . '|' . $subjectLower . '|';

            $typeSuffix = '|' . ($card->training_type === 'practice' ? 'practice' : $card->training_type);
            if ($card->training_type === 'practice') {
                foreach ($this->cycleCardGroups($card) as $member) {
                    foreach ([$this->cycleFlowName($card, $member), $member] as $rowName) {
                        $key = $base . mb_strtolower(trim($rowName)) . $typeSuffix;
                        $roomByKey[$key] = $roomByKey[$key] ?? $card->auditorium_code;
                    }
                }
            } else {
                $rowNames = array_merge([$this->cycleFlowName($card)], $this->cycleCardGroups($card));
                foreach ($rowNames as $rowName) {
                    $key = $base . mb_strtolower(trim($rowName)) . $typeSuffix;
                    $roomByKey[$key] = $roomByKey[$key] ?? $card->auditorium_code;
                }
            }
        }

        $keyOf = fn ($item) => mb_strtolower(trim((string) $item->specialty_name)) . '|' . (int) $item->course . '|'
            . mb_strtolower(trim((string) $item->subject_name)) . '|' . mb_strtolower(trim((string) $item->group_name))
            . '|' . mb_strtolower(trim((string) ($item->training_type ?? '') ?: 'practice'));

        $from = (int) $placement->start_index;
        $to = $endIndexFor($from, $daysOf($placement)) ?? $from;
        $selfKey = $keyOf($placement);

        $busy = [];
        foreach (TimetableCyclePlacement::where('board_id', $board->id)->where('id', '!=', $placement->id)->get() as $other) {
            $otherKey = $keyOf($other);
            if ($otherKey === $selfKey) {
                continue;
            }

            $room = $roomByKey[$otherKey] ?? null;
            if (!$room) {
                continue;
            }

            $otherFrom = (int) $other->start_index;
            $otherTo = $endIndexFor($otherFrom, $daysOf($other)) ?? $otherFrom;
            if ($otherFrom <= $to && $from <= $otherTo) {
                $busy[$room] = $other->subject_name;
            }
        }

        return $busy;
    }

    /**
     * Blok ichidagi ma'ruza kataklarini saqlaydi (kafedra mudiri).
     *
     * Belgilar blok boshidan hisoblangan kun siljishi bo'yicha keladi:
     * {"0": [1,2]} — blokning birinchi kunida 1 va 2 soat ma'ruza.
     * Blok surilsa belgilar u bilan birga ko'chadi.
     */
    public function cycleLectureSlots(Request $request, TimetableBoard $board)
    {
        abort_unless(Schema::hasTable('timetable_cycle_placements'), 503, 'Sikl jadvali migratsiyasi hali ishga tushirilmagan.');
        abort_unless(Schema::hasColumn('timetable_cycle_placements', 'lecture_slots'), 503, 'Ma\'ruza belgilari migratsiyasi hali ishga tushirilmagan.');

        $data = $request->validate([
            'specialty_name' => 'required|string|max:255',
            'course'         => 'required|integer|min:1|max:7',
            'group_name'     => 'required|string|max:255',
            'subject_name'   => 'required|string|max:255',
            'training_type'  => 'nullable|in:lecture,practice',
            'view'           => 'nullable|in:flow,group',
            // FormData ichma-ich obyektni yubora olmaydi, shuning uchun
            // belgilar JSON satr bo'lib keladi.
            'slots_json'     => 'nullable|string|max:20000',
        ]);

        $placement = $this->findCyclePlacement($board, $data);
        if (!$placement) {
            return response()->json(['error' => 'Sikl bloki topilmadi.'], 404);
        }

        $incoming = json_decode((string) ($data['slots_json'] ?? '{}'), true);
        if (!is_array($incoming)) {
            $incoming = [];
        }

        // Kalitlar butun son, qiymatlar takrorlanmaydigan tartiblangan soatlar.
        $slots = [];
        foreach ($incoming as $day => $hours) {
            $day = (int) $day;
            if ($day < 0) {
                continue;
            }
            $hours = collect((array) $hours)
                ->map(fn ($hour) => (int) $hour)
                ->filter(fn ($hour) => $hour >= 1 && $hour <= 12)
                ->unique()
                ->sort()
                ->values()
                ->all();
            if ($hours) {
                $slots[$day] = $hours;
            }
        }

        $placement->update(['lecture_slots' => $slots]);

        return response()->json(['ok' => true, 'slots' => $slots]);
    }

    /**
     * Bitta sikl fan blokini kalendarning o'quv kuni indeksiga joylaydi.
     * Bayramlar indekslar ro'yxatidan chiqarilgani uchun mavjud bloklar ham
     * bayramdan keyingi birinchi o'quv kuniga avtomatik siljiydi.
     */
    public function cyclePlace(Request $request, TimetableBoard $board)
    {
        $hasCyclePlacements = Schema::hasTable('timetable_cycle_placements');

        $data = $request->validate([
            'specialty_name' => 'required|string|max:255',
            'course' => 'required|integer|min:1|max:7',
            'group_name' => 'required|string|max:255',
            'subject_name' => 'required|string|max:255',
            'start_index' => 'nullable|integer|min:0',
            'training_type' => 'required|in:lecture,practice',
            'pair' => 'nullable|integer|min:1|max:24',
            'view' => 'nullable|in:flow,group',
            'action' => 'required|in:place,remove,shift',
            'direction' => 'nullable|integer|in:-1,1',
            'start_date' => 'nullable|date',
            'holidays' => 'nullable|array',
            'holidays.*' => 'nullable|date',
        ]);

        $norm = fn($value) => mb_strtolower(trim((string) $value));
        $setting = TimetableSubjectSetting::where('board_id', $board->id)
            ->where('course', (int) $data['course'])
            ->get()
            ->first(fn($s) => $norm($s->specialty_name) === $norm($data['specialty_name'])
                && $norm($s->subject_name) === $norm($data['subject_name'])
                && $s->mode === 'cycle');
        if (!$setting) {
            return response()->json(['error' => 'Bu fan sikl rejimida topilmadi.'], 422);
        }

        $reqPair = max(1, min(24, (int) ($data['pair'] ?? 1)));
        $cycleView = $data['view'] ?? 'flow';
        $cycleHours = $this->cycleSubjectHoursMap($board);
        $hoursFor = fn(string $spec, int $course, string $subject): ?array =>
            $cycleHours[$this->specKey($spec) . '|' . $course . '|' . $this->normSubject($subject)] ?? null;
        $rowHours = $this->cycleRowHours($board);
        $scopeCards = TimetableCard::where('board_id', $board->id)
            ->where('course', (int) $data['course'])
            ->where('specialty_name', $data['specialty_name'])
            ->get();
        // Qator identifikatori — OQIM (cyclePlan bilan bir xil mantiq): karta
        // `oqim_label` bo'yicha, label'siz eski kartalarda asosiy guruh bo'yicha.
        $cardFlows = function (TimetableCard $card) use ($norm, $cycleView): array {
            // Guruh rejimida qator = subguruh nomi (ma'ruza kartasi barcha
            // a'zolariga tegishli); oqim rejimida — oqim nomi.
            if ($cycleView === 'group') {
                $flows = [];
                foreach ($this->cycleCardGroups($card) as $member) {
                    $member = $norm($member);
                    if ($member !== '') {
                        $flows[$member] = true;
                    }
                }
                return array_keys($flows);
            }
            if ($card->training_type !== 'practice') {
                $flow = $norm($this->cycleFlowName($card));
                return $flow !== '' ? [$flow] : [];
            }
            $flows = [];
            foreach ($this->cycleCardGroups($card) as $member) {
                $flow = $norm($this->cycleFlowName($card, $member));
                if ($flow !== '') {
                    $flows[$flow] = true;
                }
            }
            return array_keys($flows);
        };
        $activeFlows = $scopeCards->flatMap($cardFlows)->filter()->flip()->all();
        $cards = $scopeCards
            ->filter(function ($card) use ($data, $norm, $cardFlows) {
                if ($card->training_type !== $data['training_type']) {
                    return false;
                }
                $inFlow = in_array($norm($data['group_name']), $cardFlows($card), true);
                return $inFlow
                    && $norm($card->subject_name) === $norm($data['subject_name']);
            });
        if ($cards->isEmpty()) {
            // Xabarda mavjud oqimlar sanaladi: nom mos kelmasa (mas. eski jadval
            // yoki qayta generatsiya qilingan kartalar) sabab darrov ko'rinadi.
            $known = collect(array_keys($activeFlows))->take(8)->implode(', ');

            return response()->json(['error' => 'Tanlangan oqim va fan kartasi topilmadi. So\'ralgan oqim: "'
                . $data['group_name'] . '"' . ($known !== '' ? '; mavjud oqimlar: ' . $known : '')], 422);
        }

        $set = $board->settings ?? [];
        $start = $data['start_date'] ?? ($set['semester_start'] ?? null);
        if (!$start) {
            $yearStart = (int) preg_replace('/\D.*$/', '', (string) $board->academic_year);
            if ($yearStart < 2000) $yearStart = (int) date('Y');
            $start = $board->semester_parity === 'bahorgi'
                ? sprintf('%04d-02-01', $yearStart + 1)
                : sprintf('%04d-09-01', $yearStart);
        }
        $startC = Carbon::parse($start)->startOfDay();
        $holSet = [];
        foreach ((array) ($data['holidays'] ?? ($set['holidays'] ?? [])) as $holiday) {
            try { $holSet[Carbon::parse($holiday)->toDateString()] = true; } catch (\Exception $e) { }
        }
        $dates = [];
        $workIndices = [];
        $cur = $startC->copy();
        $guard = 0;
        $requiredDays = $this->cycleTypeDays(
            max(1, (int) $setting->cycle_days),
            $hoursFor((string) $data['specialty_name'], (int) $data['course'], (string) $data['subject_name']),
            (string) $data['training_type']
        );
        if ($requiredDays < 1) {
            return response()->json(['error' => 'Bu fanning ishchi rejasida ma\'ruza soati yo\'q — ma\'ruza bloki joylanmaydi.'], 422);
        }
        // Kunlik para (lane) egallashi: vertikal kesishuv ham tekshiriladi
        // (amaliy 4 soat = 2 para, ma'ruza 2 soat = 1 para).
        $reqSpan = $this->cycleLaneSpan(
            (string) $data['training_type'],
            $hoursFor((string) $data['specialty_name'], (int) $data['course'], (string) $data['subject_name']),
            max(1, (int) $setting->cycle_days),
            $rowHours
        );
        $laneMaxSpan = fn(array $sp): int => max(1, (int) $sp['head_span'], (int) $sp['tail_span']);
        $reqLaneTo = $reqPair + $laneMaxSpan($reqSpan) - 1;
        $bellPairTotal = collect((array) ($board->bell_schedule ?? []))
            ->filter(fn($it) => (is_array($it) ? ($it['type'] ?? '') : ($it->type ?? '')) === 'pair')
            ->count();
        $pairsTotal = $bellPairTotal ?: max(1, (int) $board->pairs_per_day);
        if ($data['action'] === 'place' && $reqLaneTo > $pairsTotal) {
            return response()->json(['error' => 'Bu blok kuniga ' . $laneMaxSpan($reqSpan)
                . ' para egallaydi — ' . $reqPair . '-paradan boshlansa jadvaldagi '
                . $pairsTotal . ' paraga sig\'maydi. Yuqoriroq (kichikroq raqamli) juftlikni tanlang.'], 422);
        }
        $daysPerWeek = max(1, (int) $board->days);
        $maxDays = max(1, (int) $board->weeks) * $daysPerWeek;
        while (count($workIndices) < $maxDays && $guard < $maxDays * 8 + count($holSet) * 2 + 60) {
            $dow = (int) $cur->dayOfWeekIso;
            $isHoliday = isset($holSet[$cur->toDateString()]);
            $isTeachingDay = $dow <= $daysPerWeek && $dow < 7 && !$isHoliday;
            $dates[] = $cur->copy();
            if ($isTeachingDay) {
                $workIndices[] = count($dates) - 1;
            }
            $cur->addDay();
            $guard++;
        }
        $endIndexFor = function (int $from, int $needed) use ($workIndices): ?int {
            foreach ($workIndices as $position => $calendarIndex) {
                if ($calendarIndex >= $from) {
                    return $workIndices[$position + max(1, $needed) - 1] ?? null;
                }
            }
            return null;
        };
        $positionFor = function (int $index) use ($workIndices): ?int {
            foreach ($workIndices as $position => $calendarIndex) {
                if ($calendarIndex >= $index) {
                    return $position;
                }
            }
            return null;
        };
        $allCyclePlacements = $hasCyclePlacements
            ? TimetableCyclePlacement::where('board_id', $board->id)->get()
            : collect($set['cycle_placements'] ?? [])->map(fn($item) => (object) $item);
        $hasFlowSubjectConflict = function (string $subject, string $flow, int $from, int $days) use (
            $allCyclePlacements, $activeFlows, $data, $norm, $endIndexFor
        ): bool {
            $to = $endIndexFor($from, $days);
            if ($to === null) {
                return true;
            }
            foreach ($allCyclePlacements as $placement) {
                if ($norm(data_get($placement, 'specialty_name')) !== $norm($data['specialty_name'])
                    || (int) data_get($placement, 'course') !== (int) $data['course']
                    || $norm(data_get($placement, 'subject_name')) !== $norm($subject)
                    || $norm(data_get($placement, 'training_type') ?: 'practice') !== $norm($data['training_type'])
                    || $norm(data_get($placement, 'group_name')) === $norm($flow)
                    || !isset($activeFlows[$norm(data_get($placement, 'group_name'))])) {
                    continue;
                }
                $otherFrom = (int) data_get($placement, 'start_index');
                $otherTo = $endIndexFor($otherFrom, $days);
                if ($otherTo !== null && $from <= $otherTo && $otherFrom <= $to) {
                    return true;
                }
            }
            return false;
        };
        if ($data['action'] === 'shift') {
            $direction = (int) ($data['direction'] ?? 0);
            if (!in_array($direction, [-1, 1], true)) {
                return response()->json(['error' => 'Siljitish yo\'nalishi noto\'g\'ri.'], 422);
            }
            $currentStart = (int) ($data['start_index'] ?? -1);
            if ($currentStart < 0) {
                return response()->json(['error' => 'Sikl kartasining boshlanish kuni topilmadi.'], 422);
            }

            $shiftStart = function (int $index, int $direction) use ($workIndices): ?int {
                if ($direction > 0) {
                    foreach ($workIndices as $calendarIndex) {
                        if ($calendarIndex > $index) return $calendarIndex;
                    }
                    return null;
                }
                for ($position = count($workIndices) - 1; $position >= 0; $position--) {
                    if ($workIndices[$position] < $index) return $workIndices[$position];
                }
                return null;
            };
            $cycleSettings = TimetableSubjectSetting::where('board_id', $board->id)
                ->where('mode', 'cycle')->get()
                ->keyBy(fn($item) => $norm($item->specialty_name) . '|' . (int) $item->course . '|' . $norm($item->subject_name));
            $shiftedStart = function ($placement) use ($cycleSettings, $norm, $shiftStart, $positionFor, $workIndices, $direction, $hoursFor): ?int {
                $specialty = data_get($placement, 'specialty_name');
                $course = (int) data_get($placement, 'course');
                $subject = data_get($placement, 'subject_name');
                $setting = $cycleSettings->get($norm($specialty) . '|' . $course . '|' . $norm($subject));
                if (!$setting) return null;
                $newStart = $shiftStart((int) data_get($placement, 'start_index'), $direction);
                $newPosition = $newStart === null ? null : $positionFor($newStart);
                $days = max(1, $this->cycleTypeDays(
                    max(1, (int) $setting->cycle_days),
                    $hoursFor((string) $specialty, $course, (string) $subject),
                    (string) (data_get($placement, 'training_type') ?: 'practice')
                ));
                if ($newPosition === null || $newPosition + $days > count($workIndices)) return null;
                return $newStart;
            };
            $placementConflicts = function ($placement, int $newStart) use ($cycleSettings, $norm, $hasFlowSubjectConflict, $data, $hoursFor): bool {
                $setting = $cycleSettings->get(
                    $norm(data_get($placement, 'specialty_name')) . '|' . (int) data_get($placement, 'course') . '|' . $norm(data_get($placement, 'subject_name'))
                );
                $days = max(1, $this->cycleTypeDays(
                    max(1, (int) ($setting->cycle_days ?? 1)),
                    $hoursFor((string) data_get($placement, 'specialty_name'), (int) data_get($placement, 'course'), (string) data_get($placement, 'subject_name')),
                    (string) (data_get($placement, 'training_type') ?: 'practice')
                ));
                return $hasFlowSubjectConflict((string) data_get($placement, 'subject_name'), $data['group_name'], $newStart, $days);
            };
            $isTarget = function ($placement) use ($data, $norm, $currentStart): bool {
                return $norm(data_get($placement, 'specialty_name')) === $norm($data['specialty_name'])
                    && (int) data_get($placement, 'course') === (int) $data['course']
                    && $norm(data_get($placement, 'group_name')) === $norm($data['group_name'])
                    && $norm(data_get($placement, 'subject_name')) === $norm($data['subject_name'])
                    && $norm(data_get($placement, 'training_type') ?: 'practice') === $norm($data['training_type'])
                    && (int) data_get($placement, 'start_index') === $currentStart;
            };
            $isRow = function ($placement) use ($data, $norm): bool {
                return $norm(data_get($placement, 'specialty_name')) === $norm($data['specialty_name'])
                    && (int) data_get($placement, 'course') === (int) $data['course']
                    && $norm(data_get($placement, 'group_name')) === $norm($data['group_name']);
            };
            $shifted = 0;
            if ($hasCyclePlacements) {
                // Kaskad faqat lane (para) oralig'i kesishgan bloklarda —
                // boshqa paralardagi bloklar joyida qoladi.
                $placements = TimetableCyclePlacement::where('board_id', $board->id)
                    ->where('specialty_name', $data['specialty_name'])
                    ->where('course', (int) $data['course'])
                    ->where('group_name', $data['group_name'])
                    ->orderBy('start_index')->get()
                    ->filter(function ($p) use ($data, $norm, $cycleSettings, $hoursFor, $laneMaxSpan, $reqPair, $reqLaneTo, $rowHours) {
                        $pSetting = $cycleSettings->get(
                            $norm($data['specialty_name']) . '|' . (int) $data['course'] . '|' . $norm($p->subject_name)
                        );
                        $span = $this->cycleLaneSpan(
                            (string) ($p->training_type ?: 'practice'),
                            $hoursFor((string) $data['specialty_name'], (int) $data['course'], (string) $p->subject_name),
                            max(1, (int) ($pSetting->cycle_days ?? 1)),
                            $rowHours
                        );
                        $pairNo = max(1, (int) ($p->pair ?? 1));
                        return $pairNo + $laneMaxSpan($span) - 1 >= $reqPair && $pairNo <= $reqLaneTo;
                    })->values();
                $toShift = [];
                $targetFound = false;
                foreach ($placements as $placement) {
                    if (!$targetFound) {
                        if (!$isTarget($placement)) continue;
                        $targetFound = true;
                    }
                    $newStart = $shiftedStart($placement);
                    if ($newStart === null) {
                        return response()->json(['error' => 'Sikl kartasini bu tomonga siljitib bo\'lmaydi.'], 422);
                    }
                    if ($placementConflicts($placement, $newStart)) {
                        return response()->json(['error' => 'Bu fan shu sanalarda boshqa oqimga joylangan.'], 422);
                    }
                    $toShift[] = [$placement, $newStart];
                }
                if (!$targetFound) {
                    return response()->json(['error' => 'Siljitiladigan sikl kartasi topilmadi.'], 422);
                }
                DB::transaction(function () use ($toShift, &$shifted) {
                    foreach ($toShift as [$placement, $newStart]) {
                        $placement->update(['start_index' => $newStart]);
                        $shifted++;
                    }
                });
            } else {
                $fallback = collect($set['cycle_placements'] ?? []);
                if (!$fallback->contains($isTarget)) {
                    return response()->json(['error' => 'Siljitiladigan sikl kartasi topilmadi.'], 422);
                }
                foreach ($fallback as $placement) {
                    if (!$isRow($placement) || (int) data_get($placement, 'start_index') < $currentStart) continue;
                    $newStart = $shiftedStart($placement);
                    if ($newStart === null) {
                        return response()->json(['error' => 'Sikl kartasini bu tomonga siljitib bo\'lmaydi.'], 422);
                    }
                    if ($placementConflicts($placement, $newStart)) {
                        return response()->json(['error' => 'Bu fan shu sanalarda boshqa oqimga joylangan.'], 422);
                    }
                }
                $fallback = $fallback->map(function ($placement) use ($shiftedStart, $isRow, $currentStart, &$shifted) {
                    if (!$isRow($placement) || (int) data_get($placement, 'start_index') < $currentStart) return $placement;
                    $placement['start_index'] = $shiftedStart($placement);
                    $shifted++;
                    return $placement;
                });
                $set['cycle_placements'] = $fallback->values()->all();
                $board->update(['settings' => $set]);
            }
            return response()->json(['ok' => true, 'shifted' => $shifted, 'direction' => $direction]);
        }

        if ($data['action'] === 'remove') {
            if ($hasCyclePlacements) {
                TimetableCyclePlacement::where('board_id', $board->id)
                    ->where('specialty_name', $data['specialty_name'])
                    ->where('course', (int) $data['course'])
                    ->where('group_name', $data['group_name'])
                    ->where('subject_name', $data['subject_name'])
                    ->where(fn ($q) => $q->where('training_type', $data['training_type'])->orWhereNull('training_type'))
                    ->delete();
            } else {
                $set['cycle_placements'] = collect($set['cycle_placements'] ?? [])
                    ->reject(fn($item) => ($item['specialty_name'] ?? '') === $data['specialty_name']
                        && (int) ($item['course'] ?? 0) === (int) $data['course']
                        && ($item['group_name'] ?? '') === $data['group_name']
                        && ($item['subject_name'] ?? '') === $data['subject_name'])
                    ->values()->all();
                $board->update(['settings' => $set]);
            }
            return response()->json(['ok' => true, 'removed' => true]);
        }

        $from = (int) ($data['start_index'] ?? -1);
        if ($from < 0 || $positionFor($from) === null) {
            return response()->json(['error' => 'Fan bloki semestr kalendariga sig‘maydi.'], 422);
        }

        $settings = TimetableSubjectSetting::where('board_id', $board->id)
            ->where('course', (int) $data['course'])->get()
            ->keyBy(fn($s) => $norm($s->specialty_name) . '|' . $norm($s->subject_name));
        $others = $hasCyclePlacements
            ? TimetableCyclePlacement::where('board_id', $board->id)
                ->where('specialty_name', $data['specialty_name'])
                ->where('course', (int) $data['course'])
                ->where('group_name', $data['group_name'])
                ->get()
            : collect($set['cycle_placements'] ?? [])
                ->filter(fn($item) => ($item['specialty_name'] ?? '') === $data['specialty_name']
                    && (int) ($item['course'] ?? 0) === (int) $data['course']
                    && ($item['group_name'] ?? '') === $data['group_name'])
                ->map(fn($item) => (object) $item);
        // Qo'lda joylashda blok AYNAN bosilgan kunga tushadi — hech qayerga
        // surilmaydi. Qo'shni sikl bilan to'qnashsa yoki oraliq yetmasa, xato
        // qaytariladi va foydalanuvchi boshqa kun tanlaydi.
        $startPosition = (int) $positionFor($from);
        $cycleGapDays = 2;
        $to = $endIndexFor($from, $requiredDays);
        if ($to === null) {
            return response()->json(['error' => 'Fan bloki semestr kalendariga sig\'maydi.'], 422);
        }
        $endPosition = $startPosition + $requiredDays - 1;
        foreach ($others->sortBy('start_index') as $other) {
            // Lane (para) oralig'i kesishmagan blok xalaqit bermaydi.
            $otherSpanSetting = $settings->get($norm($data['specialty_name']) . '|' . $norm($other->subject_name));
            $otherSpan = $this->cycleLaneSpan(
                (string) ($other->training_type ?: 'practice'),
                $hoursFor((string) $data['specialty_name'], (int) $data['course'], (string) $other->subject_name),
                max(1, (int) ($otherSpanSetting->cycle_days ?? 1)),
                $rowHours
            );
            $otherPairNo = max(1, (int) ($other->pair ?? 1));
            if ($otherPairNo + $laneMaxSpan($otherSpan) - 1 < $reqPair || $otherPairNo > $reqLaneTo) continue;
            if ($norm($other->subject_name) === $norm($data['subject_name'])
                && $norm($other->training_type ?? 'practice') === $norm($data['training_type'])) continue;
            $otherStartPosition = $positionFor((int) $other->start_index);
            if ($otherStartPosition === null) continue;
            $otherSetting = $settings->get($norm($data['specialty_name']) . '|' . $norm($other->subject_name));
            $otherDays = max(1, $this->cycleTypeDays(
                max(1, (int) ($otherSetting->cycle_days ?? 1)),
                $hoursFor((string) $data['specialty_name'], (int) $data['course'], (string) $other->subject_name),
                (string) ($other->training_type ?: 'practice')
            ));
            $otherEndPosition = $otherStartPosition + $otherDays - 1;

            if ($startPosition <= $otherEndPosition && $otherStartPosition <= $endPosition) {
                return response()->json(['error' => 'Bu sana oralig‘ida "' . $other->subject_name
                    . '" sikli bor. Boshqa kunni tanlang.'], 422);
            }
            // Sikllar orasida kamida 2 o'quv kuni bo'sh qolishi kerak.
            $gapBefore = $otherStartPosition - $endPosition - 1;
            $gapAfter = $startPosition - $otherEndPosition - 1;
            $gap = $startPosition > $otherEndPosition ? $gapAfter : $gapBefore;
            if ($gap < $cycleGapDays) {
                return response()->json(['error' => '"' . $other->subject_name . '" sikli bilan orasida kamida '
                    . $cycleGapDays . ' o‘quv kuni bo‘sh qolishi kerak (hozir ' . max(0, $gap) . ').'], 422);
            }
        }

        if ($hasFlowSubjectConflict($data['subject_name'], $data['group_name'], $from, $requiredDays)) {
            return response()->json(['error' => 'Bu fan shu sanalarda boshqa oqimga joylangan.'], 422);
        }
        if ($hasCyclePlacements) {
            TimetableCyclePlacement::updateOrCreate([
                'board_id' => $board->id,
                'specialty_name' => $data['specialty_name'],
                'course' => (int) $data['course'],
                'group_name' => $data['group_name'],
                'subject_name' => $data['subject_name'],
                'training_type' => $data['training_type'],
            ], ['start_index' => $from, 'pair' => $reqPair]);
        } else {
            $fallback = collect($set['cycle_placements'] ?? [])
                ->reject(fn($item) => ($item['specialty_name'] ?? '') === $data['specialty_name']
                    && (int) ($item['course'] ?? 0) === (int) $data['course']
                    && ($item['group_name'] ?? '') === $data['group_name']
                    && ($item['subject_name'] ?? '') === $data['subject_name'])
                ->values();
            $fallback->push([
                'specialty_name' => $data['specialty_name'],
                'course' => (int) $data['course'],
                'group_name' => $data['group_name'],
                'subject_name' => $data['subject_name'],
                'start_index' => $from,
            ]);
            $set['cycle_placements'] = $fallback->all();
            $board->update(['settings' => $set]);
        }

        return response()->json(['ok' => true, 'start_index' => $from, 'days' => $requiredDays]);
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

    /** Joylanmagan kartalar ro'yxatini XLSX ga chiqaradi. */
    public function unplacedExport(Request $request, TimetableBoard $board)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $toArray = static function ($value): array {
            if ($value === null || $value === '') {
                return [];
            }
            return is_array($value) ? $value : [$value];
        };
        $facultyNames = array_values(array_filter(array_map('strval', $toArray($request->input('faculty_names'))), static fn($v) => $v !== ''));
        $specialtyNames = array_values(array_filter(array_map('strval', $toArray($request->input('specialty_names'))), static fn($v) => $v !== ''));
        $courses = array_values(array_filter(array_map('intval', $toArray($request->input('courses'))), static fn($v) => $v > 0));
        $type = (string) $request->input('type', 'all');
        $week = max(0, (int) $request->input('week', 0));

        $query = TimetableCard::where('board_id', $board->id)
            ->orderBy('faculty_name')
            ->orderBy('specialty_name')
            ->orderBy('course')
            ->orderBy('subject_name');

        if ($facultyNames) {
            $query->whereIn('faculty_name', $facultyNames);
        }
        if ($specialtyNames) {
            $query->whereIn('specialty_name', $specialtyNames);
        }
        if ($courses) {
            $query->whereIn('course', $courses);
        }
        if (in_array($type, ['lecture', 'practice'], true)) {
            $query->where('training_type', $type);
        }

        if (!$week) {
            $query->where(function ($q) {
                $q->whereNull('day')->orWhereNull('pair');
            });
            $cards = $query->get();
        } else {
            $cards = $query->get();
            $overrides = collect();
            if (Schema::hasTable('timetable_card_overrides') && $cards->isNotEmpty()) {
                $overrides = TimetableCardOverride::whereIn('card_id', $cards->pluck('id'))
                    ->where('week', $week)
                    ->get()
                    ->keyBy('card_id');
            }
            $cards = $cards->filter(function (TimetableCard $card) use ($overrides): bool {
                $override = $overrides->get($card->id);
                if ($override && $override->cancelled) {
                    return false;
                }
                if ($override && $override->day && $override->pair) {
                    return false;
                }
                return !$card->day || !$card->pair;
            })->values();
        }

        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Joylanmagan kartalar');

            $headers = [
                'ID', 'Fakultet', 'Yo\'nalish', 'Kurs', 'Oqim', 'Guruh', 'Fan',
                'Turi', 'Hafta', 'Talabalar', 'O\'qituvchi', 'Auditoriya',
            ];
            $sheet->fromArray([$headers], null, 'A1');
            $sheet->mergeCells('A1:L1');
            $sheet->setCellValue('A1', 'Joylanmagan kartalar - ' . $board->name);
            $sheet->getStyle('A1:L1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 13],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1D4ED8']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(24);

            $sheet->fromArray([$headers], null, 'A2');
            $sheet->getStyle('A2:L2')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '475569']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
            ]);

            $rows = [];
            foreach ($cards as $card) {
                $rows[] = [
                    $card->id,
                    $card->faculty_name ?: '-',
                    $card->specialty_name,
                    (int) $card->course,
                    $card->oqim_label ?: '-',
                    $card->group_name ?: (is_array($card->group_names) ? implode(', ', $card->group_names) : '-'),
                    $card->subject_name,
                    $card->training_type === 'lecture' ? 'Ma\'ruza' : 'Amaliy',
                    $card->weeks !== null ? (int) $card->weeks : $board->weeks,
                    (int) $card->students,
                    $card->teacher_name ?: 'Biriktirilmagan',
                    $card->auditorium_name ?: 'Biriktirilmagan',
                ];
            }
            if ($rows) {
                $sheet->fromArray($rows, null, 'A3');
            }

            $lastRow = max(2, 2 + count($rows));
            $sheet->getStyle('A2:L' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
            $sheet->getStyle('A3:L' . $lastRow)->getAlignment()->setVertical('top')->setWrapText(true);
            $sheet->freezePane('A3');
            $widths = ['A'=>9,'B'=>22,'C'=>25,'D'=>8,'E'=>14,'F'=>20,'G'=>34,'H'=>12,'I'=>9,'J'=>11,'K'=>24,'L'=>22];
            foreach ($widths as $col => $width) $sheet->getColumnDimension($col)->setWidth($width);
            for ($r = 3; $r <= $lastRow; $r++) $sheet->getRowDimension($r)->setRowHeight(-1);

            $sheet->getAutoFilter()->setRange('A2:L' . $lastRow);
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filename = 'joylanmagan-kartalar-diagnostikasi-' . $board->id . '.xlsx';
            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Timetable unplaced-export xatosi: ' . $e->getMessage());
            return response()->json(['error' => 'Diagnostika Excelini yaratib bo\'lmadi: ' . $e->getMessage()], 500);
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
        $rowHeights = []; // Katak ichidagi o'ralgan matnga mos qator balandligi
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

                    // Excel birlashtirilgan kataklarni avtomatik balandlashtirmaydi.
                    // Matnning haqiqiy qatorlari va ustun kengligida o'ralishini taxminan
                    // hisoblab, balandlikni rowspan bo'ylab teng taqsimlaymiz.
                    $visualLines = 0;
                    // 8 pt shrift va 16 birlikli ustun uchun o'ralgan matn
                    // balandligini hisoblaymiz; eksport ixcham, lekin o'qiladigan qoladi.
                    $charsPerLine = max(8, 12 * $cs);
                    foreach (preg_split('/\R/u', $text) ?: [$text] as $line) {
                        $length = function_exists('mb_strlen') ? mb_strlen($line, 'UTF-8') : strlen($line);
                        $visualLines += max(1, (int) ceil($length / $charsPerLine));
                    }
                    $heightPerRow = min(300, max(15, (($visualLines * 9.5) + 5) / $rs));
                    for ($heightRow = 0; $heightRow < $rs; $heightRow++) {
                        $targetRow = $excelRow + $heightRow;
                        $rowHeights[$targetRow] = max($rowHeights[$targetRow] ?? 15, $heightPerRow);
                    }
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
        $sheet->getStyle($dim)->getFont()->setSize(8);

        // Ikkinchi va keyingi fanlar yashirinib qolmasligi uchun qatorlar
        // katak ichidagi barcha matnga mos balandlikda ochiladi.
        foreach ($rowHeights as $rowNumber => $height) {
            $sheet->getRowDimension($rowNumber)->setRowHeight($height);
        }

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
            'placement_reason_code' => null,
            'placement_reason' => null,
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
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + (CASE WHEN CAST(mc.level_code AS UNSIGNED) >= 11 THEN CAST(mc.level_code AS UNSIGNED) - 10 ELSE CAST(mc.level_code AS UNSIGNED) END) - 1) = ?", [$start])
            ->groupBy('mc.specialty_name', 'mc.level_code', 's.semester', 's.subject_name')
            ->selectRaw("mc.specialty_name, mc.level_code, s.semester, s.subject_name,
                MAX(s.lecture) as lecture, MAX(s.practice) as practice,
                MAX(s.laboratory) as laboratory, MAX(s.seminar) as seminar,
                GROUP_CONCAT(DISTINCT mc.name SEPARATOR '|||') as plan_names")
            ->orderBy('mc.specialty_name')->orderBy('mc.level_code')->orderBy('s.subject_name')
            ->get();

        [$kafMap, $overrides] = $this->buildKafedraMap();
        $weeks = max(1, (int) $board->weeks);
        $seasonLookup = $this->subjectSeasonLookup($board);

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
                'season'         => $this->subjectEffectiveSeason(
                    $board,
                    $seasonLookup,
                    (string) $r->specialty_name,
                    $course,
                    (string) $r->subject_name
                ),
                'semester_label' => (int) $r->semester . '-semestr',
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

    private function subjectSettingLookupKey(string $specialtyName, int $course, string $subjectName): string
    {
        return $this->specKey($specialtyName) . '|' . (int) $course . '|' . $this->normSubject($subjectName);
    }

    private function subjectSeasonLookup(TimetableBoard $board): array
    {
        if (!Schema::hasTable('timetable_subject_settings')
            || !Schema::hasColumn('timetable_subject_settings', 'season')) {
            return [];
        }

        return TimetableSubjectSetting::where('board_id', $board->id)
            ->whereNotNull('season')
            ->get(['specialty_name', 'course', 'subject_name', 'season'])
            ->mapWithKeys(fn($s) => [
                $this->subjectSettingLookupKey(
                    (string) $s->specialty_name,
                    (int) $s->course,
                    (string) $s->subject_name
                ) => (string) $s->season,
            ])->all();
    }

    private function subjectEffectiveSeason(
        TimetableBoard $board,
        array $seasonLookup,
        string $specialtyName,
        int $course,
        string $subjectName
    ): string {
        return $seasonLookup[$this->subjectSettingLookupKey($specialtyName, $course, $subjectName)]
            ?? $board->semester_parity;
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
        $scope = ($c->faculty_name ?? '') . '|F|' . $scope;
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
                    'faculty_name'   => $c->faculty_name,
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
        usort($out, fn($a, $b) => [(string) ($a['faculty_name'] ?? ''), $a['specialty_name'], $a['course'], $b['training_type'], $a['subject_name'], (string) $a['oqim_label'], (string) $a['group_name']]
            <=> [(string) ($b['faculty_name'] ?? ''), $b['specialty_name'], $b['course'], $a['training_type'], $b['subject_name'], (string) $b['oqim_label'], (string) $b['group_name']]);

        return response()->json(['units' => $out]);
    }

    /** Dars birligiga o'qituvchini ommaviy biriktirish (barcha kartalariga). */
    public function assignTeacher(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'faculty_name'   => 'nullable|string|max:255',
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
        array_key_exists('faculty_name', $data) && $data['faculty_name'] !== null && $data['faculty_name'] !== ''
            ? $q->where('faculty_name', $data['faculty_name'])
            : $q->whereNull('faculty_name');
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
    /**
     * Fan ranglarini saqlaydi (fan nomi => #rrggbb). Bo'sh qiymat — avtomatik
     * (golden-angle) rangga qaytarish.
     */
    public function saveSubjectColors(Request $request, TimetableBoard $board)
    {
        $data = $request->validate(['subject_colors' => 'nullable|string']);
        $input = json_decode((string) ($data['subject_colors'] ?? '{}'), true);
        if (!is_array($input)) {
            return response()->json(['error' => 'Ranglar ro\'yxati noto\'g\'ri.'], 422);
        }

        $colors = [];
        foreach ($input as $subject => $hex) {
            $subject = trim((string) $subject);
            $hex = strtolower(trim((string) $hex));
            if ($subject === '' || !preg_match('/^#[0-9a-f]{6}$/', $hex)) {
                continue;
            }
            $colors[$subject] = $hex;
        }

        $set = $board->settings ?? [];
        $set['subject_colors'] = $colors;
        $board->update(['settings' => $set]);

        return response()->json(['ok' => true, 'subject_colors' => $colors]);
    }

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
