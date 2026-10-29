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
use App\Models\TimetableUnitAssignment;
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
    use Concerns\TimetableHemisSync;
    use Concerns\TimetableProgramCalendars;
    use Concerns\TimetableBlockTickets;
    use Concerns\TimetableBoardSnapshotsActions;
    use Concerns\TimetableWeekSuggestions;

    public function index(Request $request)
    {
        $isBoardAdmin = $this->isBoardAdmin($request);
        $boards = TimetableBoard::withCount('cards')
            ->when(!$isBoardAdmin && Schema::hasColumn('timetable_boards', 'admin_only'), fn ($q) => $q->where('admin_only', false))
            ->orderByDesc('id')->get();

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

        $teacherId = $this->timetableTeacherId($request);
        $timetableTeacherId = $teacherId > 0 ? $teacherId : null;

        return view('admin.timetable.index', compact('boards', 'years', 'faculties', 'isBoardAdmin', 'timetableTeacherId'));
    }

    /** Sinov (faqat admin) doskalarini ko'ra oladigan rollar. */
    private function isBoardAdmin(Request $request): bool
    {
        return in_array($this->timetableActiveRole($request), ['superadmin', 'admin'], true);
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
            'subject_source'  => 'nullable|in:ishchi,hemis',
            'admin_only'      => 'nullable|boolean',
        ]);
        // Sinov doskasi va HEMIS manbai — faqat admin
        $isBoardAdmin = $this->isBoardAdmin($request);
        $source = $isBoardAdmin && ($data['subject_source'] ?? '') === 'hemis' && $data['kind'] === 'real' ? 'hemis' : 'ishchi';
        $adminOnly = $isBoardAdmin && !empty($data['admin_only']);

        $facName = $data['faculty_id']
            ? optional(\App\Models\Department::find($data['faculty_id']))->name
            : null;

        $board = TimetableBoard::create([
            'name'            => $data['academic_year'] . ' · ' . ($data['semester_parity'] === 'kuzgi' ? 'Kuzgi' : 'Bahorgi')
                                 . ' · ' . ($data['kind'] === 'plan' ? 'Reja' : 'Real')
                                 . ($facName ? ' · ' . $facName : ' · Barcha fakultetlar')
                                 . ($source === 'hemis' ? ' · HEMIS (KTR)' : '')
                                 . ($adminOnly ? ' · sinov' : ''),
            'academic_year'   => $data['academic_year'],
            'semester_parity' => $data['semester_parity'],
            'kind'            => $data['kind'],
            'subject_source'  => $source,
            'admin_only'      => $adminOnly,
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

    /** UTF-8 harflari bo'yicha masofa; 2 tadan ko'p farq uchun 3 qaytaradi. */
    private function subjectTypoDistance(string $a, string $b): int
    {
        $left = mb_str_split($a);
        $right = mb_str_split($b);
        $n = count($right);
        if (abs(count($left) - $n) > 2) {
            return 3;
        }
        $previous = range(0, $n);
        foreach ($left as $i => $letter) {
            $row = [$i + 1];
            foreach ($right as $j => $other) {
                $row[$j + 1] = min($row[$j] + 1, $previous[$j + 1] + 1,
                    $previous[$j] + ($letter === $other ? 0 : 1));
            }
            if (min($row) > 2) {
                return 3;
            }
            $previous = $row;
        }

        return min(3, $previous[$n]);
    }

    /** Yo'nalish+kurs+fan bo'yicha joriy yilgi HEMIS kafedrasi (buildKafedraMap to'ldiradi). */
    private array $scopedKafMap = [];

    /**
     * Fan kafedrasi: 1) qo'lda belgilangan; 2) shu yo'nalish+kursning joriy yilgi
     * HEMIS rejasidagi kafedrasi (bir xil fan turli yo'nalishda turli kafedraniki
     * bo'lishi mumkin, masalan "Mutaxassislikka kirish"); 3) fan nomi bo'yicha umumiy.
     */
    private function kafedraFor(array $overrides, array $kafMap, string $subject, ?string $specialty = null, ?int $course = null): ?string
    {
        $k = $this->normSubject($subject);
        if (!empty($overrides[$k])) {
            return $overrides[$k];
        }
        if ($specialty !== null && $course) {
            $scoped = $this->scopedKafMap[$this->scopeSpecKey($specialty) . '|' . $course] ?? [];
            if (isset($scoped[$k])) {
                return $scoped[$k];
            }
            // Nomi qisqaroq/uzunroq yozilgan bo'lsa ("Hamshiralik ishi" ~ "Hamshiralik ishi asoslari") —
            // faqat shu yo'nalish+kurs ichida va bitta mos fan bo'lsa
            $hits = [];
            foreach ($scoped as $name => $kaf) {
                if ($k !== '' && (str_starts_with($name, $k . ' ') || str_starts_with($k, $name . ' '))) {
                    $hits[$kaf] = true;
                }
            }
            if (count($hits) === 1) {
                return array_key_first($hits);
            }
        }

        return $kafMap[$k] ?? null;
    }

    /** Yo'nalish kaliti (HEMIS va ishchi reja nomlarini juftlash): qavs ichi va "1-son" olib tashlanadi. */
    private function scopeSpecKey(string $name): string
    {
        $name = preg_replace('/\(.*?\)/u', '', $name);
        $name = preg_replace('/^\s*\d+\s*-?\s*son\s+/iu', '', (string) $name);

        return $this->specKey($name);
    }

    /**
     * Fan → kafedra xaritasi: [$kafMap, $overrides] (assembleRows va subjects uchun umumiy).
     * Avval joriy o'quv yilida o'tiladigan fanlarning kafedrasi olinadi (kafedra bo'linsa/
     * qayta nomlansa eski rejalar ko'pchilik bo'lib yutib ketmasin), joriy yilda
     * uchramagan fanlar uchun — barcha rejalardagi eng ko'p uchragan kafedra.
     */
    private function buildKafedraMap(): array
    {
        $kafMap = $this->kafedraCounts(DB::table('curriculum_subjects'));

        $year = DB::table('semesters')->where('current', true)->max('education_year');
        if ($year) {
            $current = $this->kafedraCounts(DB::table('curriculum_subjects as cs')
                ->join('semesters as s', function ($j) {
                    $j->on('s.curriculum_hemis_id', '=', 'cs.curricula_hemis_id')
                        ->on('s.code', '=', 'cs.semester_code');
                })
                ->where('s.education_year', $year)
                ->where('cs.is_active', true), 'cs.');
            $kafMap = $current + $kafMap;

            // Yo'nalish+kurs bo'yicha (joriy yil)
            $this->scopedKafMap = [];
            $rows = DB::table('curriculum_subjects as cs')
                ->join('semesters as s', function ($j) {
                    $j->on('s.curriculum_hemis_id', '=', 'cs.curricula_hemis_id')
                        ->on('s.code', '=', 'cs.semester_code');
                })
                ->join('curricula as c', 'c.curricula_hemis_id', '=', 'cs.curricula_hemis_id')
                ->join('specialties as sp', 'sp.specialty_hemis_id', '=', 'c.specialty_hemis_id')
                ->where('s.education_year', $year)
                ->where('cs.is_active', true)
                ->whereNotNull('cs.department_name')->where('cs.department_name', '!=', '')
                ->selectRaw('sp.name as specialty_name, s.level_code, cs.subject_name, cs.department_name, COUNT(*) as c')
                ->groupBy('sp.name', 's.level_code', 'cs.subject_name', 'cs.department_name')
                ->get();
            $acc = [];
            foreach ($rows as $r) {
                $course = (int) $r->level_code >= 11 ? (int) $r->level_code - 10 : (int) $r->level_code;
                $k = $this->normSubject((string) $r->subject_name);
                if ($course < 1 || $k === '') {
                    continue;
                }
                $sc = $this->scopeSpecKey((string) $r->specialty_name) . '|' . $course;
                $acc[$sc][$k][$r->department_name] = ($acc[$sc][$k][$r->department_name] ?? 0) + (int) $r->c;
            }
            foreach ($acc as $sc => $subjects) {
                foreach ($subjects as $k => $deps) {
                    arsort($deps);
                    $this->scopedKafMap[$sc][$k] = array_key_first($deps);
                }
            }
        }

        $overrides = DB::table('subject_kafedra_overrides')
            ->where('kafedra_name', '!=', '')
            ->pluck('kafedra_name', 'norm_name')->all();

        return [$kafMap, $overrides];
    }

    /** Normallashtirilgan fan nomi → eng ko'p uchragan kafedra nomi. */
    private function kafedraCounts($query, string $p = ''): array
    {
        $kafRows = $query
            ->whereNotNull($p . 'department_name')->where($p . 'department_name', '!=', '')
            ->selectRaw("{$p}subject_name as subject_name, {$p}department_name as department_name, COUNT(*) as c")
            ->groupBy($p . 'subject_name', $p . 'department_name')->get();
        $acc = [];
        $totals = [];
        foreach ($kafRows as $r) {
            $k = $this->normSubject($r->subject_name);
            if ($k !== '') {
                $acc[$k][$r->department_name] = ($acc[$k][$r->department_name] ?? 0) + (int) $r->c;
                $totals[$r->department_name] = ($totals[$r->department_name] ?? 0) + (int) $r->c;
            }
        }
        $kafMap = [];
        foreach ($acc as $k => $deps) {
            // Teng bo'lsa — umumiy fanlari ko'proq kafedra (bo'lingan eski kafedra yutmasin)
            uksort($deps, fn ($a, $b) => [$deps[$b], $totals[$b] ?? 0, $a] <=> [$deps[$a], $totals[$a] ?? 0, $b]);
            $kafMap[$k] = array_key_first($deps);
        }

        return $kafMap;
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

        // Qolgan barcha fanlar (gumanitar fanlar ham) — guruhlar birlashtirilmaydi,
        // har biriga alohida o'qituvchi va xona
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

    private function isHemisBoard(TimetableBoard $board): bool
    {
        return method_exists($board, 'usesHemisSubjects') && $board->usesHemisSubjects();
    }

    /**
     * HEMIS o'quv rejasi fanlari (KTR sahifasidagi bilan bir xil manba) — ishchi reja
     * so'rovi bilan bir xil ko'rinishdagi qatorlar: specialty_name, level_code,
     * subject_name, lecture, practice, laboratory, seminar, semester, plan_names,
     * hemis_kafedra. Doska o'quv yili va semestri (kuzgi = toq) bo'yicha, faqat faol fanlar.
     *   - soatlar subject_details dan: ma'ruza (11), amaliy (12), laboratoriya (13), seminar (14);
     *     boshqa turlar (amaliyot, mustaqil ta'lim...) olinmaydi — soati yo'q fan chiqmaydi;
     *   - (a)/(b)/(c) variantlari bo'lsa — asosiy (harfsiz) nom olinmaydi, har variant alohida;
     *   - "1-son"/"2-son" fakultet davolash ishi — "N-son Davolash ishi" (ishchi reja kabi).
     */
    private function hemisSubjectRows(TimetableBoard $board)
    {
        static $cache = [];
        if (isset($cache[$board->id])) {
            return $cache[$board->id];
        }

        $start = (int) substr((string) $board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;
        $rows = DB::table('curriculum_subjects as cs')
            ->join('semesters as s', function ($j) {
                $j->on('s.curriculum_hemis_id', '=', 'cs.curricula_hemis_id')->on('s.code', '=', 'cs.semester_code');
            })
            ->join('curricula as c', 'c.curricula_hemis_id', '=', 'cs.curricula_hemis_id')
            ->join('specialties as sp', 'sp.specialty_hemis_id', '=', 'c.specialty_hemis_id')
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->where('cs.is_active', true)
            ->where('s.education_year', (string) $start)
            ->whereRaw('MOD(CAST(cs.semester_code AS UNSIGNED) - 10, 2) = ?', [$parityRem])
            ->get(['cs.subject_name', 'cs.subject_details', 'cs.department_name', 'cs.semester_code', 'cs.updated_at', 'cs.curricula_hemis_id',
                'c.name as plan_name', 'sp.name as specialty_name', 'f.name as faculty_name', 's.level_code']);

        // Mashg'ulot turi: ma'ruza / laboratoriya / seminar / amaliy (klinik amaliy ham).
        // Mustaqil ta'lim, amaliyot (malakaviy, ishlab chiqarish), kurs ishi olinmaydi.
        $typeOf = function (array $d): ?string {
            $code = (string) ($d['trainingType']['code'] ?? '');
            $name = mb_strtolower((string) ($d['trainingType']['name'] ?? ''));
            foreach (['mustaqil', 'amaliyot', 'kurs ishi', 'самостоят', 'практика', 'курсов'] as $skip) {
                if (str_contains($name, $skip)) return null;
            }
            if (preg_match("/ma['’ʻ`]?ruza|лекц/u", $name)) return 'lecture';
            if (str_contains($name, 'laborator') || str_contains($name, 'лаборатор')) return 'laboratory';
            if (str_contains($name, 'seminar') || str_contains($name, 'семинар')) return 'seminar';
            if (str_contains($name, 'amaliy') || str_contains($name, 'klinik')
                || str_contains($name, 'практическ') || str_contains($name, 'клиническ')) return 'practice';
            if ($name === '') {
                return ['11' => 'lecture', '12' => 'practice', '13' => 'practice', '14' => 'seminar'][$code] ?? null;
            }
            return null;
        };

        $acc = [];
        foreach ($rows as $r) {
            $details = is_string($r->subject_details) ? json_decode($r->subject_details, true) : $r->subject_details;
            $h = ['lecture' => 0.0, 'practice' => 0.0, 'laboratory' => 0.0, 'seminar' => 0.0];
            foreach ((array) $details as $d) {
                if (is_array($d) && ($t = $typeOf($d))) {
                    $h[$t] += (float) ($d['academic_load'] ?? 0);
                }
            }
            if (array_sum($h) <= 0) {
                continue;
            }
            $spec = trim((string) preg_replace('/\(.*?\)/u', '', (string) $r->specialty_name));
            if (preg_match('/(\d+)\s*-\s*son/u', (string) $r->faculty_name, $m) && preg_match('/^davolash\s+ishi$/iu', $spec)) {
                $spec = $m[1] . '-son Davolash ishi';
            }
            $level = (int) $r->level_code;
            $key = $spec . '|' . $level . '|' . $r->subject_name . '|' . $r->curricula_hemis_id;
            if (!isset($acc[$key])) {
                $acc[$key] = (object) [
                    'specialty_name' => $spec, 'level_code' => $level, 'subject_name' => (string) $r->subject_name,
                    'lecture' => 0, 'practice' => 0, 'laboratory' => 0, 'seminar' => 0,
                    'faculty_name' => $r->faculty_name,
                    'semester' => (int) $r->semester_code - 10, 'plans' => [], 'kafedras' => [], 'updated_at' => $r->updated_at,
                    'curricula' => [], 'curriculum_hemis_id' => (int) $r->curricula_hemis_id,
                ];
            }
            $o = $acc[$key];
            $o->curricula[(int) $r->curricula_hemis_id] = true;
            foreach ($h as $t => $v) {
                $o->$t = max((float) $o->$t, $v);
            }
            $o->plans[$r->plan_name] = true;
            if ($r->department_name) {
                $o->kafedras[$r->department_name] = ($o->kafedras[$r->department_name] ?? 0) + 1;
            }
        }

        // (a)/(b)/(c) variantlari bo'lsa — asosiy nom olinmaydi
        $variantBases = [];
        foreach ($acc as $o) {
            if (preg_match('/^(.*)\s*\([a-z]\)$/u', $o->subject_name, $m)) {
                $variantBases[$o->specialty_name . '|' . $o->level_code . '|' . ($o->curriculum_hemis_id ?? 0) . '|' . trim($m[1])] = true;
            }
        }
        $out = [];
        foreach ($acc as $o) {
            if (isset($variantBases[$o->specialty_name . '|' . $o->level_code . '|' . ($o->curriculum_hemis_id ?? 0) . '|' . $o->subject_name])) {
                continue;
            }
            arsort($o->kafedras);
            $o->hemis_kafedra = array_key_first($o->kafedras);
            $o->plan_names = implode('|||', array_keys($o->plans));
            unset($o->plans, $o->kafedras);
            $out[] = $o;
        }

        return $cache[$board->id] = collect($out);
    }

    /** Guruh nomi kaliti: apostrof turlari, katta-kichik harf va bo'shliqlar farqi hisobga olinmaydi. */
    private function groupNameKey(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(['‘', '’', 'ʻ', 'ʼ', '`', '´', '"'], "'", $name);

        return preg_replace('/\s+/u', '', $name);
    }

    /** Asos guruh kaliti: "d1/d24-02a (o'z)" -> "d1/d24-02" (pastki guruh harfi va til qo'shimchasisiz). */
    private function baseGroupKey(string $name): string
    {
        $n = $this->groupNameKey((string) preg_replace('/\s*\(.*$/u', '', $name));

        return (string) preg_replace('/(\d)[a-zа-яё]$/u', '$1', $n);
    }

    /** Variant harfi: "... (a)" / "(А)" -> a, "(b)" / "(Б)" -> b, "(c)" / "(С)" -> c. */
    private function variantLetter(string $text, bool $groupName = false): ?string
    {
        $re = $groupName
            ? '/\d\s*([a-zа-яё])\s*(?:\(|$)/iu'   // guruh: "d1/d23-07a (o'z)" -> a
            : '/\(\s*([a-zа-яё])\s*\)\s*$/iu';   // fan: "Umumiy xirurgiya (a)" -> a
        if (!preg_match($re, trim($text), $m)) {
            return null;
        }
        $l = mb_strtolower($m[1]);

        return ['а' => 'a', 'б' => 'b', 'в' => 'c', 'с' => 'c', 'ц' => 'c'][$l] ?? $l;
    }

    /**
     * HEMIS doska: oqim guruhlaridan shu fanni o'qiydiganlari — guruhning o'quv rejasida
     * shu fan bo'lsa va (variant fan bo'lsa) guruh harfi variant harfiga mos bo'lsa.
     * Rejasi yoki harfi aniqlanmagan guruh chiqarib tashlanmaydi.
     */
    private function hemisGroupsFor($subject, array $rows, array $groupCurricula, bool $letterRule = true): array
    {
        $variant = $letterRule ? $this->variantLetter((string) $subject->subject_name) : null;
        $curricula = $subject->curricula ?? [];

        return array_values(array_filter($rows, function ($r) use ($variant, $curricula, $groupCurricula) {
            $name = trim((string) ($r['name'] ?? ''));
            if ($name === '') {
                return false;
            }
            $cur = $groupCurricula[$this->groupNameKey($name)]
                ?? $groupCurricula['base:' . $this->groupNameKey((string) preg_replace('/\s*\(.*$/u', '', $name))]
                ?? null;
            if ($curricula && $cur && !isset($curricula[(int) $cur])) {
                return false;
            }
            if ($variant !== null) {
                $gl = $this->variantLetter(preg_replace('/\s*\(.*$/u', '', $name), true);
                if ($gl !== null && $gl !== $variant) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Bo'lakli fan ("Fan (a)"): HEMIS'da shu fanga biriktirilgan (o'qiyotgan) talabalar soni —
     * [o'quv reja => [asosiy guruh kaliti => soni]]. Guruh nomi fakultetlararo takrorlanishi
     * mumkin, shuning uchun biriktiruv o'z rejasi (curriculum_subjects) bilan olinadi; faqat
     * doskaning o'quv yili — yili yozilmagan qator olinmaydi: oldindan rejalashtirilayotgan
     * yilda bitta shunday qator oqimni "biriktirilgan" qilib, qolgan bo'laklarni yo'qotardi.
     * Ma'lumot student_subjects (import:student-subjects) dan; bo'sh bo'lsa — [].
     */
    private function variantStudentCounts(string $subjectName, int $yearStart = 0): array
    {
        static $cache = [];
        $key = mb_strtolower(trim($subjectName));
        $cacheKey = $key . '|' . $yearStart;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }
        if (!Schema::hasTable('student_subjects')) {
            return $cache[$cacheKey] = [];
        }
        $query = DB::table('student_subjects as ss')
            ->join('students as st', 'st.hemis_id', '=', 'ss.student_hemis_id')
            ->leftJoin('curriculum_subjects as cs', 'cs.curriculum_subject_hemis_id', '=', 'ss.curriculum_subject_hemis_id')
            ->whereRaw('LOWER(TRIM(ss.subject_name)) = ?', [$key])
            ->where('st.student_status_code', 11);
        if ($yearStart > 0 && Schema::hasColumn('student_subjects', 'education_year')) {
            // "2026" yoki "2026-2027" — HEMIS qanday yozgan bo'lsa.
            $query->where('ss.education_year', 'like', $yearStart . '%');
        }
        $counts = [];
        foreach ($query->get(['st.hemis_id', 'st.group_name', 'cs.curricula_hemis_id']) as $r) {
            $g = $this->groupNameKey((string) preg_replace('/\s*\(.*$/u', '', (string) $r->group_name));
            if ($g === '') {
                continue;
            }
            $counts[(int) $r->curricula_hemis_id][$g][(string) $r->hemis_id] = true;
        }

        return $cache[$cacheKey] = array_map(fn ($byGroup) => array_map('count', $byGroup), $counts);
    }

    /**
     * Guruhning o'quv rejasi (groups jadvalidan). Guruh nomi fakultetlararo takrorlanishi mumkin,
     * shuning uchun avval qatorning fakulteti (groups.department_name — kartadagi faculty_name ham
     * shundan) bo'yicha; nom yagona bo'lsa — o'sha; aks holda oxirgi faol guruhniki (eski xatti).
     */
    /** groupCurriculum keshi — so'rov (controller) bo'yicha; funksiya ichidagi static butun jarayonda qolardi. */
    private ?array $groupCurriculumMap = null;

    /** Guruh nomi → o'quv reja (HEMIS doskada fanlar guruhning o'z rejasidan olinadi). */
    private function hemisGroupCurricula(): array
    {
        $map = [];
        // Faollari oxirida — bir xil nomli eski (nofaol) guruhni ustidan yozadi; ikkalasi faol bo'lsa —
        // kalendar bilan bir xil qaror (faol talabalar ko'pchiligi o'qiydigan reja).
        // Ikki kalit: to'liq nom va qavssiz nom (oqimda "d1/d26-01a (o'z)", bazada "d1/d26-01a").
        $groups = $this->preferStudentBackedGroups(DB::table('groups')->whereNotNull('curriculum_hemis_id')
            ->orderBy('active')->orderBy('id')->get(['name', 'department_name', 'curriculum_hemis_id', 'active', 'group_hemis_id']));
        foreach ($groups as $g) {
            $map[$this->groupNameKey((string) $g->name)] = (int) $g->curriculum_hemis_id;
        }
        foreach ($groups as $g) {
            $base = $this->groupNameKey((string) preg_replace('/\s*\(.*$/u', '', (string) $g->name));
            $map['base:' . $base] = (int) $g->curriculum_hemis_id;
        }

        return $map;
    }

    private function groupCurriculum(string $groupName, ?string $faculty = null): ?int
    {
        $map = &$this->groupCurriculumMap;
        if ($map === null) {
            $map = [];
            // Faollari oxirida — bir xil nomli eski (nofaol) guruhni ustidan yozadi; ikkalasi faol bo'lsa —
            // kalendar bilan bir xil qaror (faol talabalar ko'pchiligi o'qiydigan reja).
            foreach ($this->preferStudentBackedGroups(DB::table('groups')->whereNotNull('curriculum_hemis_id')->orderBy('active')->orderBy('id')
                ->get(['name', 'department_name', 'curriculum_hemis_id', 'active', 'group_hemis_id'])) as $g) {
                $k = $this->groupNameKey((string) preg_replace('/\s*\(.*$/u', '', (string) $g->name));
                $map[$k]['fac'][mb_strtolower(trim((string) $g->department_name))] = (int) $g->curriculum_hemis_id;
                $map[$k]['last'] = (int) $g->curriculum_hemis_id;
            }
        }
        $entry = $map[$this->groupNameKey((string) preg_replace('/\s*\(.*$/u', '', $groupName))] ?? null;
        if ($entry === null) {
            return null;
        }
        $fac = mb_strtolower(trim((string) $faculty));
        if ($fac !== '' && isset($entry['fac'][$fac])) {
            return $entry['fac'][$fac];
        }
        $distinct = array_values(array_unique($entry['fac']));

        return count($distinct) === 1 ? $distinct[0] : $entry['last'];
    }

    /** Doskadagi barcha guruh(cha)lar uchun faol talabalar soni: "fakultet|guruh nomi" => son. */
    private function boardGroupStudentCounts($cards): array
    {
        $out = [];
        try {
            foreach ($cards as $c) {
                $names = $c['group_name'] ? [$c['group_name']] : (array) ($c['group_names'] ?? []);
                foreach ($names as $g) {
                    $key = ($c['faculty_name'] ?? '') . '|' . $g;
                    if ($g !== '' && !isset($out[$key])) {
                        $out[$key] = $this->activeStudentCountFor((string) $g, $c['faculty_name'] ?? null);
                    }
                }
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $out;
    }

    /** Talabalar soni keshi bitta controller so'rovi doirasida saqlanadi. */
    private ?array $activeStudentCounts = null;

    /**
     * Guruhdagi faol talabalar soni (students, status 11). Guruh nomi fakultetlararo takrorlanishi
     * mumkin, shuning uchun guruhning o'z o'quv rejasi bo'yicha; reja mos kelmasa — nom bo'yicha jami.
     */
    private function activeStudentCountFor(string $groupName, ?string $faculty = null): int
    {
        $counts = &$this->activeStudentCounts;
        if ($counts === null) {
            $counts = [];
            foreach (DB::table('students')->where('student_status_code', 11)
                ->selectRaw('group_name, curriculum_id, COUNT(*) as n')->groupBy('group_name', 'curriculum_id')->get() as $r) {
                $k = $this->groupNameKey((string) preg_replace('/\s*\(.*$/u', '', (string) $r->group_name));
                if ($k !== '') {
                    $counts[$k][(int) $r->curriculum_id] = ($counts[$k][(int) $r->curriculum_id] ?? 0) + (int) $r->n;
                }
            }
        }
        $k = $this->groupNameKey((string) preg_replace('/\s*\(.*$/u', '', $groupName));
        $cur = $this->groupCurriculum($groupName, $faculty);

        return (int) (($cur !== null ? ($counts[$k][$cur] ?? null) : null) ?? array_sum($counts[$k] ?? []));
    }

    /**
     * Bo'lak fanlariga (HEMIS student_subjects) biriktirilgan faol talabalar — barcha fanlar uchun
     * BITTA so'rov (cyclePlan har ochilishda chaqiriladi; har qatorga alohida so'rov jadvalni
     * qayta-qayta to'liq o'qirdi). [kichik harfli fan nomi => [['id', 'name', 'group', 'cur'], ...]]
     */
    private function variantStudentRows(array $subjectNames, int $yearStart): array
    {
        $names = array_values(array_unique(array_filter(array_map(fn ($n) => mb_strtolower(trim((string) $n)), $subjectNames))));
        if (!$names || !Schema::hasTable('student_subjects')) {
            return [];
        }
        $query = DB::table('student_subjects as ss')
            ->join('students as st', 'st.hemis_id', '=', 'ss.student_hemis_id')
            ->leftJoin('curriculum_subjects as cs', 'cs.curriculum_subject_hemis_id', '=', 'ss.curriculum_subject_hemis_id')
            ->whereRaw('LOWER(TRIM(ss.subject_name)) IN (' . implode(',', array_fill(0, count($names), '?')) . ')', $names)
            ->where('st.student_status_code', 11);
        if ($yearStart > 0 && Schema::hasColumn('student_subjects', 'education_year')) {
            $query->where('ss.education_year', 'like', $yearStart . '%');
        }
        $out = [];
        foreach ($query->get(['ss.subject_name', 'st.hemis_id', 'st.full_name', 'st.group_name', 'cs.curricula_hemis_id']) as $r) {
            $out[mb_strtolower(trim((string) $r->subject_name))][] = [
                'id' => (string) $r->hemis_id, 'name' => trim((string) $r->full_name),
                'group' => trim((string) preg_replace('/\s*\(.*$/u', '', (string) $r->group_name)),
                'cur' => (int) $r->curricula_hemis_id,
            ];
        }

        return $out;
    }

    /**
     * Bo'lak qatori talabalari: shu qator guruhlaridan, har guruh o'z o'quv rejasi bo'yicha
     * (guruh nomi fakultetlararo takrorlanishi mumkin — variantStudentCounts dagi kabi).
     * [['name' => .., 'group' => ..], ...]
     */
    private function variantStudentList(array $rowsBySubject, string $subjectName, array $groupNames, ?string $faculty = null): array
    {
        $want = [];
        foreach ($groupNames as $g) {
            $gk = $this->groupNameKey((string) preg_replace('/\s*\(.*$/u', '', (string) $g));
            $want[$gk] = $this->groupCurriculum((string) $g, $faculty);
        }
        $out = [];
        foreach ($rowsBySubject[mb_strtolower(trim($subjectName))] ?? [] as $r) {
            $gk = $this->groupNameKey($r['group']);
            if (!array_key_exists($gk, $want) || ($want[$gk] !== null && $r['cur'] !== $want[$gk])) {
                continue;
            }
            $out[$r['id']] = ['name' => $r['name'], 'group' => $r['group']];
        }
        $out = array_values($out);
        usort($out, fn ($a, $b) => strnatcasecmp($a['group'], $b['group']) ?: strcasecmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * Kartadagi guruhdan bo'lakka biriktirilgan talabalar soni (variantStudentCounts natijasidan) —
     * guruhning o'z o'quv rejasi bo'yicha: guruh nomi fakultetlararo takrorlanadi. Rejasi noma'lum — jami.
     */
    private function groupAssignedCount(array $byCurriculum, string $group, ?string $faculty): int
    {
        $gk = $this->groupNameKey((string) preg_replace('/\s*\(.*$/u', '', $group));
        $cur = $this->groupCurriculum($group, $faculty);

        return $cur ? (int) ($byCurriculum[$cur][$gk] ?? 0) : (int) array_sum(array_column($byCurriculum, $gk));
    }

    /**
     * Guruh qatoridan shu bo'lakka biriktirilgan talabalar soni: guruhning o'z o'quv rejasi
     * bo'yicha (hemisGroupsFor dagi kabi); rejasi noma'lum bo'lsa — fanning rejalari bo'yicha.
     */
    private function variantAssignedCount(array $byCurriculum, $subject, array $row, array $groupCurricula): int
    {
        $name = trim((string) ($row['name'] ?? ''));
        $gk = $this->groupNameKey((string) preg_replace('/\s*\(.*$/u', '', $name));
        $cur = $groupCurricula[$this->groupNameKey($name)] ?? $groupCurricula['base:' . $gk] ?? null;
        if ($cur) {
            return (int) ($byCurriculum[(int) $cur][$gk] ?? 0);
        }
        $curricula = array_keys($subject->curricula ?? []) ?: array_keys($byCurriculum);
        $sum = 0;
        foreach ($curricula as $c) {
            $sum += (int) ($byCurriculum[(int) $c][$gk] ?? 0);
        }

        return $sum;
    }

    /**
     * Kartaning kafedrasi. HEMIS doskada — faqat fanning HEMIS dagi o'z kafedrasi (KTR kabi,
     * "O'quv reja to'g'riligi"dagi qo'lda belgilanganlar ishlatilmaydi). Ishchi reja doskada —
     * qo'lda belgilangan, so'ng xarita.
     */
    private function rowKafedra(array $overrides, array $kafMap, $row, ?string $specialty = null, ?int $course = null): ?string
    {
        if (!empty($row->hemis_kafedra)) {
            return $row->hemis_kafedra;
        }

        return $this->kafedraFor($overrides, $kafMap, (string) $row->subject_name, $specialty, $course);
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
        $rows = ($this->isHemisBoard($board) ? $this->hemisSubjectRows($board) : DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + (CASE WHEN CAST(mc.level_code AS UNSIGNED) >= 11 THEN CAST(mc.level_code AS UNSIGNED) - 10 ELSE CAST(mc.level_code AS UNSIGNED) END) - 1) = ?", [$start])
            ->groupBy('mc.specialty_name', 'mc.level_code', 's.subject_name')
            ->selectRaw('mc.specialty_name, mc.level_code, s.subject_name, MAX(s.lecture) as lecture')
            ->get());
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
        $curr = ($this->isHemisBoard($board) ? $this->hemisSubjectRows($board) : DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + (CASE WHEN CAST(mc.level_code AS UNSIGNED) >= 11 THEN CAST(mc.level_code AS UNSIGNED) - 10 ELSE CAST(mc.level_code AS UNSIGNED) END) - 1) = ?", [$start])
            ->groupBy('mc.specialty_name', 'mc.level_code')
            ->selectRaw('mc.specialty_name, mc.level_code')
            ->get());
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
        $cycleKeys = $this->cycleSubjectKeys($board);
        $separatePractice = array_flip((array) (($board->settings ?? [])['separate_practice'] ?? []));
        // KTR (har hafta soati) bo'lsa — kartalar soati KTR bo'yicha (ishchi reja soati emas).
        $ktrPlans = $this->cycleKtrDayPlans($board);

        // Fanlar: o'quv yili + semestr juft/toqligi bo'yicha
        $start = (int) substr($board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;
        $subjects = ($this->isHemisBoard($board) ? $this->hemisSubjectRows($board) : DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + (CASE WHEN CAST(mc.level_code AS UNSIGNED) >= 11 THEN CAST(mc.level_code AS UNSIGNED) - 10 ELSE CAST(mc.level_code AS UNSIGNED) END) - 1) = ?", [$start])
            ->groupBy('mc.specialty_name', 'mc.level_code', 's.subject_name')
            ->selectRaw("mc.specialty_name, mc.level_code, s.subject_name,
                MAX(s.lecture) as lecture,
                MAX(s.practice) as practice, MAX(s.laboratory) as laboratory, MAX(s.seminar) as seminar")
            ->get());

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
            $key = $sk . '|' . $course . '|' . $this->normSubject((string) $s->subject_name) . '|' . ($s->curriculum_hemis_id ?? 0);

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
        $gset = TimetableGridSetting::where('board_id', $board->id)->get()->filter(fn ($g) => !(int) ($g->curriculum_hemis_id ?? 0))
            ->mapWithKeys(fn($g) => [
                ($g->faculty_name ?? '') . '|' . $this->specKey($g->specialty_name) . '|' . $g->course => (int) $g->weeks,
            ])
            ->all();

        // Fakultet id → nomi (snapshot fakultet kontekstidan kartaga yozish uchun)
        $facMap = \App\Models\Department::where('structure_type_code', 11)
            ->pluck('name', 'id')->all();

        // HEMIS doska: guruh nomi → o'quv reja (fanlar guruhning o'z rejasidan)
        $hemisBoard = $this->isHemisBoard($board);
        $groupCurricula = $hemisBoard ? $this->hemisGroupCurricula() : [];

        $now = now();
        $rows = [];
        // Takrorlanishdan himoya: bir guruhga bir fan bo'yicha karta to'plami FAQAT
        // BIR MARTA yaratiladi. Snapshotda bir guruh bir necha blok/oqimda uchrashi
        // mumkin (turli fakultet konteksti, bir yo'nalishning bir necha bloki) —
        // aks holda o'sha fanning kartalari necha marta uchrasa shuncha ko'payib,
        // haftalik yuk bir necha barobar oshib ketardi.
        // Kalitda haqiqiy fakultet ($blockFac) ham bor: 1-kurs guruh nomlari ("1K-01a (o'z)") ikki davolash
        // fakultetida takrorlanadi — aks holda ikkinchisining amaliy kartalari yaratilmay qolardi.
        $madePractice = [];   // "fakultet|spec|kurs|guruh|fan" => true
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
                    foreach ($this->programOqims($co['oqims'] ?? [], $blockFac) as $oq) {
                        $programId = (int) ($oq['curriculum_hemis_id'] ?? 0);
                        $weeks = (int) ($this->calendarSetting($board, $blockFac, $specName, $course, $programId)?->weeks ?? $board->weeks);
                        $groupNames = array_values(array_filter(array_map(
                            fn($r) => trim((string) ($r['name'] ?? '')), $oq['rows'] ?? []
                        )));
                        if (empty($groupNames)) {
                            continue;
                        }
                        $oqTotal = (int) ($oq['total'] ?? 0);
                        foreach ($subs as $s) {
                            if ($hemisBoard && $programId && isset($s->curriculum_hemis_id) && (int) $s->curriculum_hemis_id !== $programId) continue;
                            // HEMIS doska: shu fanni o'qiydigan guruhlargina (o'z rejasi + variant harfi)
                            $oqRows = $oq['rows'] ?? [];
                            $sGroupNames = $groupNames;
                            $sOqTotal = $oqTotal;
                            // Bo'lakli fan: qaysi guruhdan nechta talaba o'qishi HEMIS biriktiruvidan
                            // (student_subjects). Guruhning katta qismi (≥ yarmi) — o'z amaliy guruhi;
                            // kichik qismlar ("02a dan 5 + 02b dan 5") bitta aralash guruhga yig'iladi.
                            $variantUnits = null;
                            $vCandidates = [];
                            $vCounts = [];
                            $useAssignments = false;
                            if ($hemisBoard && $this->variantLetter((string) $s->subject_name) !== null) {
                                $vCandidates = $this->hemisGroupsFor($s, $oqRows, $groupCurricula, false);
                                $vYear = (int) substr((string) $board->academic_year, 0, 4);
                                $vCounts = $this->variantStudentCounts((string) $s->subject_name, $vYear);
                                // Biriktiruv HEMIS'ga hali hamma oqim uchun kiritilmagan bo'lishi mumkin:
                                // shu oqim guruhlari fanning birorta bo'lagiga ham biriktirilmagan bo'lsa —
                                // ma'lumot yo'q, eski harf qoidasi (oqim kartasiz qolib ketmasin).
                                $vBase = mb_strtolower((string) $this->cycleSubjectVariantBase((string) $s->subject_name));
                                $flowAssigned = false;
                                foreach ($subs as $sibling) {
                                    if ($vBase === '' || mb_strtolower((string) $this->cycleSubjectVariantBase((string) $sibling->subject_name)) !== $vBase) {
                                        continue;
                                    }
                                    $siblingCounts = $this->variantStudentCounts((string) $sibling->subject_name, $vYear);
                                    foreach ($vCandidates as $r) {
                                        if ($this->variantAssignedCount($siblingCounts, $sibling, $r, $groupCurricula) > 0) {
                                            $flowAssigned = true;
                                            break 2;
                                        }
                                    }
                                }
                                $useAssignments = $flowAssigned;
                            }
                            if ($useAssignments) {
                                $vRows = [];
                                foreach ($vCandidates as $r) {
                                    $assigned = $this->variantAssignedCount($vCounts, $s, $r, $groupCurricula);
                                    if ($assigned < 1) {
                                        continue;
                                    }
                                    $groupTotal = max(1, (int) ($r['count'] ?? 0));
                                    $r['partial'] = $assigned * 2 < $groupTotal;
                                    $r['count'] = $assigned;
                                    $vRows[] = $r;
                                }
                                if (!$vRows) {
                                    continue;   // bu oqimda bu bo'lakni hech kim o'qimaydi
                                }
                                $oqRows = $vRows;
                                $variantUnits = true;
                                $sGroupNames = array_values(array_unique(array_map(fn ($r) => trim((string) $r['name']), $oqRows)));
                                $sOqTotal = array_sum(array_map(fn ($r) => (int) ($r['count'] ?? 0), $oqRows));
                            } elseif ($hemisBoard) {
                                $oqRows = $this->hemisGroupsFor($s, $oqRows, $groupCurricula);
                                if (!$oqRows) {
                                    continue;
                                }
                                $sGroupNames = array_values(array_unique(array_map(fn ($r) => trim((string) $r['name']), $oqRows)));
                                $sOqTotal = array_sum(array_map(fn ($r) => (int) ($r['count'] ?? 0), $oqRows));
                            }
                            $kaf = $this->rowKafedra($overrides, $kafMap, $s, (string) $specName, (int) $course);
                            $prcHours = (float) $s->practice + (float) $s->laboratory + (float) $s->seminar;
                            $practiceGroupSize = $this->practiceGroupSizeFor($practiceSizeOverrides, (string) $s->subject_name);
                            // Istisno: shu yo'nalish+kurs+fanda guruhlar alohida o'tiladi (doska sozlamasi) — barcha
                            // fakultetlar uchun ('' fakultet) yoki faqat shu blok fakulteti uchun.
                            $separateHere = isset($separatePractice[$this->separatePracticeKey('', (string) $specName, (int) $course, (string) $s->subject_name)])
                                || isset($separatePractice[$this->separatePracticeKey((string) $blockFac, (string) $specName, (int) $course, (string) $s->subject_name)]);
                            $pairPracticeGroups = ((float) $s->seminar > 0 || $practiceGroupSize >= 30) && !$separateHere;
                            // Haftalik yuk taqsimoti: jami soat / hafta = haftalik yuk.
                            // Ma'ruza 2 soat egallagani uchun ma'ruzali haftada amaliy
                            // kamayadi — shuning uchun "qo'shimcha" amaliy kartalar faqat
                            // ma'ruzasiz haftalarda o'tiladi (kartada weeks bilan belgilanadi).
                            $wp = $this->weeklyPlan((float) $s->lecture, $prcHours, $weeks);
                            // Sikl fanlari KTR'i kunlik (haftalik emas) — ularga qo'llanmaydi.
                            $cycleRowKey = $this->specKey((string) $specName) . '|' . (int) $course . '|';
                            $cycleBase = $this->cycleSubjectVariantBase((string) $s->subject_name);
                            $isCycleRow = isset($cycleKeys[$cycleRowKey . $this->normSubject((string) $s->subject_name)])
                                || ($cycleBase !== null && isset($cycleKeys[$cycleRowKey . $this->normSubject($cycleBase)]));
                            $ktrPlan = $isCycleRow ? null
                                : $this->ktrPlanForRow($ktrPlans, $blockFac, (string) $specName, (int) $course, (string) $s->subject_name, $programId);
                            if ($ktrPlan !== null) {
                                // 2 soatlik ma'ruza necha marta — KTR'ning jami ma'ruza soatidan (ishchi rejadagi weeklyPlan kabi):
                                // odatda ma'ruzali haftalar soni; haftada 4 soat bo'lsa kamayib, toq (1 soat) bo'lsa oshib ketmaydi.
                                $ktrLecHours = array_sum(array_map(fn ($d) => max(0, (int) ($d['lec'] ?? 0)), $ktrPlan));
                                $ktrLecWeeks = min((int) round($ktrLecHours / 2), max(1, (int) $weeks));
                                if ($ktrLecWeeks > 0) {
                                    $wp['lecture_weeks'] = $ktrLecWeeks;
                                }
                            }

                            // Ma'ruza — bitta oqimga BITTA karta; necha hafta o'tilishi
                            // ma'ruza soatidan (1 para = 2 soat = 1 hafta).
                            $subjKey = $this->normSubject((string) $s->subject_name);
                            // Bo'lakli fan ("Fan (a)/(b)/(c)") ma'ruzasi bo'laklarga ajralmaydi — oqim birga
                            // eshitadi: asosiy nom bilan bitta karta, barcha bo'laklarning guruhlari bilan.
                            $lecSubject = $this->mergesVariantLecture($cycleKeys, (string) $specName, (int) $course, (string) $s->subject_name)
                                ? (string) $this->cycleSubjectVariantBase((string) $s->subject_name) : (string) $s->subject_name;
                            $lecSubjKey = $this->normSubject($lecSubject);
                            // Bir xil ko'rinadigan oqim bir nechta snapshot blokida kelishi mumkin.
                            // Ma'ruza bunday bo'laklarga ajralmasin: fakultet+yo'nalish+kurs+
                            // oqim+til+fan bo'yicha bitta karta, guruhlar esa birlashtiriladi.
                            $flowLabel = trim((string) ($oq['label'] ?? ''));
                            // UI ham oqimlarni til yoki snapshot bo'lagi bo'yicha ajratmaydi.
                            // Shu sababli ular ma'ruza kalitida ham alohida bo'lmasligi kerak.
                            $lecKey = ($blockFac ?? '') . '|' . $sk . '|' . $course . '|'
                                . $flowLabel . '|' . $lecSubjKey . '|' . $programId;
                            if ((float) $s->lecture > 0) {
                                foreach ($oqRows as $lectureGroup) {
                                    $lectureGroupName = trim((string) ($lectureGroup['name'] ?? ''));
                                    if ($lectureGroupName === '') {
                                        continue;
                                    }
                                    // Bo'laklar bir guruhning turli talabalari — guruh+bo'lak bo'yicha yig'iladi
                                    // (HEMIS biriktiruvi: qatorda shu bo'lak talabalari). Biriktiruvsiz (harf qoidasi,
                                    // ishchi reja) qatorda guruhning jami talabasi — har bo'lakda qayta sanalmaydi.
                                    $slot = $lectureGroupName . ($variantUnits ? "\0" . $s->subject_name : '');
                                    $lectureGroupStudents[$lecKey][$slot] = max(
                                        (int) ($lectureGroupStudents[$lecKey][$slot] ?? 0),
                                        (int) ($lectureGroup['count'] ?? 0)
                                    );
                                }
                                $mergedLectureGroups = array_values(array_unique(array_map(
                                    fn ($k) => explode("\0", (string) $k)[0], array_keys($lectureGroupStudents[$lecKey] ?? [])
                                )));
                                $mergedLectureStudents = array_sum($lectureGroupStudents[$lecKey] ?? []);

                                if (!isset($madeLecture[$lecKey])) {
                                    $madeLecture[$lecKey] = count($rows);
                                    $rows[] = [
                                        'board_id' => $board->id,
                                        'specialty_name' => $specName, 'course' => $course, 'faculty_name' => $blockFac,
                                        'oqim_label' => $oq['label'] ?? null, 'lang' => $oq['lang'] ?? 'uz',
                                        'training_type' => 'lecture',
                                        'group_name' => null, 'group_names' => json_encode($mergedLectureGroups ?: $sGroupNames),
                                        'subject_name' => $lecSubject, 'kafedra_name' => $kaf,
                                        'students' => $mergedLectureStudents > 0 ? $mergedLectureStudents : $sOqTotal,
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
                                        : max((int) $rows[$lectureRowIndex]['students'], $sOqTotal);
                                }
                            }

                            // Amaliy darslar SOAT bo'yicha taqsimlanadi (len_half = soat):
                            //  - har hafta o'tiladigan soat        -> weeks ta haftada
                            //  - ma'ruzasiz haftalardagi qo'shimcha -> plain_weeks ta haftada
                            //  - qoldiq +1 soat                     -> remainder_weeks ta haftada
                            // Shu bilan reja soati qoldiqsiz to'ldiriladi.
                            $cardPlan = [];   // [len_half, necha hafta]
                            $ktrCards = $ktrPlan !== null ? $this->ktrPracticeCardPlan($ktrPlan, (int) $weeks) : null;
                            if ($ktrCards !== null) {
                                $cardPlan = $ktrCards;
                            } else foreach ($this->splitPracticeHours((int) $wp['practice_hours_base']) as $lh) {
                                $cardPlan[] = [$lh, $weeks];
                            }
                            if ($ktrCards === null && (int) $wp['practice_extra_weeks'] > 0) {
                                foreach ($this->splitPracticeHours((int) $wp['practice_hours_extra']) as $lh) {
                                    $cardPlan[] = [$lh, (int) $wp['practice_extra_weeks']];
                                }
                            }
                            if ($ktrCards === null && (int) $wp['practice_remainder_weeks'] > 0) {
                                $cardPlan[] = [1, (int) $wp['practice_remainder_weeks']];
                            }

                            if ($cardPlan) {
                                $practiceRowsByName = [];
                                foreach ($oqRows as $gr) {
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
                                $practiceBundles = $this->practiceBundles($practiceRows, (bool) $variantUnits, $pairPracticeGroups, $separateHere);

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
                                    $prcKey = ($blockFac ?? '') . '|' . $sk . '|' . $course . '|' . $groupLabel . '|' . $subjKey;
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
                 'specialty_name' => $info['name'], 'course' => $info['course']]
                    + (Schema::hasColumn('timetable_grid_settings', 'curriculum_hemis_id') ? ['curriculum_hemis_id' => 0] : []),
                ['days' => $board->days, 'pairs_per_day' => $board->pairs_per_day, 'weeks' => $board->weeks]
            );
        }
    }

    /**
     * Nusxadan tiklashda joylashuv tekshiruvi — qo'lda joylashtirishdagi kabi (guruh, o'qituvchi, xona) va panjara
     * chegarasi (validateTimeProposal dagidek): ichki shablon, tashqi jadval va haftalik ko'chirish uchun.
     * To'qnashuvlar matnini qaytaradi.
     */
    public function restoreConflictChecker(): \Closure
    {
        // Doska bandligi birinchi tekshiruvda bir marta yuklanadi (har tekshiruvda butun doskani o'qimaslik uchun) va
        // har qabul qilingan joylashuv bilan yangilanadi: tiklash joylashuvni tekshiruvdan keyin, to'qnashmasa, yozadi.
        $state = null;
        $board = null;
        $grids = [];

        return function (TimetableCard $card, string $what, ?int $week, int $day, int $pair, int $startHalf, array $room = []) use (&$state, &$board, &$grids): array {
            $board ??= $card->board;
            $grid = $grids[$card->faculty_name . '|' . $card->specialty_name . '|' . $card->course . '|' . $this->cardCurriculumId($card)]
                ??= $this->cardGrid($board, $card);
            if ($day < 1 || $day > $grid['days'] || $pair < 1 || $pair + $card->lenHalf() - 1 > $grid['pairs']
                || ($week !== null && ($week < 1 || $week > $grid['weeks']))) {
                return ['Jadval chegarasidan tashqarida'];
            }
            if ($what === 'external') {
                return $this->findExternalConflicts($card, $day, $pair);
            }
            $state ??= $this->occupancyState((int) $card->board_id);
            // Kartaning hozirgi holati (tiklash o'qituvchi/xonani avval qo'yadi).
            $this->stateSetCard($state, clone $card);
            if ($what === 'week') {
                // Yoziladigan haftalik yozuv (xonasi bilan) bandlikka vaqtincha qo'yiladi; to'qnashsa — avvalgisi qaytadi.
                $prev = $state['ov'][(int) $card->id][$week] ?? null;
                $pending = new TimetableCardOverride();
                $pending->forceFill(['card_id' => $card->id, 'week' => $week, 'day' => $day, 'pair' => $pair, 'start_half' => $startHalf,
                    'cancelled' => false, 'auditorium_code' => $room['auditorium_code'] ?? null, 'auditorium_name' => $room['auditorium_name'] ?? null]);
                $this->stateSetOverride($state, $pending);
                $errors = $this->findWeekConflicts($card, $week, $day, $pair, $startHalf, $state);
                if ($errors) {
                    $this->stateSetOverride($state, $prev, (int) $card->id, $week);
                }

                return $errors;
            }
            $errors = $this->findConflicts($card, $day, $pair, $startHalf, $state);
            if (!$errors) {
                $placed = clone $card;
                $placed->forceFill(['day' => $day, 'pair' => $pair, 'start_half' => $startHalf]);
                $this->stateSetCard($state, $placed);
            }

            return $errors;
        };
    }

    /**
     * Xavfli (ko'p kartaga tegadigan) amaldan oldin doska nusxasi — versiyalar tarixida qoladi; sinov (dry_run) uchun emas.
     * Ruxsat va kiritilgan ma'lumot tekshirilgandan keyin chaqiriladi: rad etilgan so'rov nusxa qoldirmaydi.
     */
    private function snapshotBefore(Request $request, TimetableBoard $board, string $reason): void
    {
        if ($request->boolean('dry_run') || !\App\Services\Timetable\BoardSnapshots::ready()) {
            return;
        }
        // Nusxa butun doskani (8000+ karta, 40000+ haftalik yozuv) xotiraga oladi — 128M yetmaydi.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);
        $actor = $this->timetableActor($request);
        app(\App\Services\Timetable\BoardSnapshots::class)->take($board, $reason, $actor?->full_name ?? $actor?->name);
    }

    /**
     * Kartochkalarni yaratish: tasdiqlangan oqim + ishchi reja fanlari.
     * Mavjud kartochkalar o'chirilib qaytadan yaratiladi (joylashuvlar yo'qoladi).
     */
    public function generateCards(Request $request, TimetableBoard $board)
    {
        // Sinxronlash: nusxa + oldingi/keyingi holatni solishtirish + KTR rejalari — butun doska xotirada.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);
        $specsFound = [];
        $rows = $this->assembleRows($board, null, null, $specsFound);
        if ($rows === null) {
            return response()->json(['error' => "Tasdiqlangan oqim topilmadi. Avval Oqim sahifasida "
                . ($board->kind === 'plan' ? "kelasi yil (reja) oqimini" : "joriy oqimni") . " tasdiqlang."], 422);
        }
        $this->snapshotBefore($request, $board, 'before_generate');
        $rows = $this->stripUnsupportedColumns($rows);

        // Sinxronlash (sukut): mavjud kartalar joyida qoladi, faqat endi kerak bo'lmaganlari
        // o'chiriladi va yangilari qo'shiladi. "full" — hammasini qaytadan yaratish (eski xatti-harakat).
        if ($request->input('mode', 'sync') !== 'full') {
            $before = app(\App\Services\Timetable\BoardSnapshots::class)->collect($board);
            $res = $this->syncCards($board, $rows, $specsFound);
            // Soati yoki hafta soni o'zgargani uchun qayta yaratilgan kartalar eski kartaning joyini oladi (fan + guruh
            // bo'yicha) — joylashgan dars "joylanmaganlar"ga tushib qolmasin. Faqat haqiqatan yangi fan/guruh joylanmagan qoladi.
            if (!empty($res['created']) && $before) {
                $kept = app(\App\Services\Timetable\BoardSnapshots::class)->keepPlacementOfRecreated($board, $before, $this->restoreConflictChecker());
                $res['kept_placement'] = $kept['cards'];
                $res['kept_placement_conflicts'] = $kept['conflicts'];
            }

            return response()->json($res);
        }

        // O'qituvchi/xona biriktirishlari dars birligi bo'yicha saqlab qolinadi
        // (kafedra mudirlari biriktirgani qayta yaratishda yo'qolmasin): kartalardagi
        // joriy holat avval timetable_unit_assignments jadvaliga yoziladi.
        $kept = $this->assignmentsByUnit($board);
        $this->storeUnitAssignments($board, $kept);

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

        // Fakultet nomi to'ldirilgandan keyin — birlik kaliti unga bog'liq
        $restored = $this->restoreAssignments($board, $this->storedUnitAssignments($board) + $kept);

        return response()->json(['ok' => true, 'created' => count($rows)] + $restored);
    }

    /**
     * Oqimga keyin qo'shilgan guruhlar uchun kartochkalarni QO'SHADI — mavjud kartalarga (joylashuv,
     * birlashtirilgan/bo'lingan kartalar, haftalar) tegmaydi:
     *  - amaliy: guruh(lar)i hammasi yangi bo'lsa — yangi karta;
     *  - ma'ruza: shu oqimning shu fan ma'ruzasi bor bo'lsa — yangi guruhlar unga qo'shiladi
     *    (joyi saqlanadi), yo'q bo'lsa — yangi karta.
     * Aralash (eski + yangi guruhli) amaliy qatorlar va mos ma'ruzasi topilmagan aralash ma'ruza
     * qatorlari qo'shilmaydi, ro'yxatda ko'rsatiladi.
     */
    public function addMissingGroupCards(Request $request, TimetableBoard $board)
    {
        if (!in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true)) {
            return response()->json(['error' => 'Bu amal faqat admin uchun.'], 403);
        }
        $this->snapshotBefore($request, $board, 'before_add_groups');
        $dry = $request->boolean('dry_run');
        $specsFound = [];
        $rows = $this->assembleRows($board, null, null, $specsFound);
        if ($rows === null) {
            return response()->json(['error' => 'Tasdiqlangan oqim topilmadi.'], 422);
        }
        $rows = $this->stripUnsupportedColumns($rows);
        [$insert, $grow, $mixed, $newGroups, $groupsOfRow] = $this->planMissingGroupCards($rows, TimetableCard::where('board_id', $board->id)->get());
        $result = ['ok' => true, 'dry_run' => $dry, 'new_groups' => array_keys($newGroups), 'insert' => count($insert),
            'lectures_extended' => count($grow), 'mixed_skipped' => array_slice($mixed, 0, 100),
            'insert_list' => array_slice(array_map(fn ($r) => ($r['course'] ?? '') . 'k ' . ($r['specialty_name'] ?? '') . ' · '
                . ($r['subject_name'] ?? '') . ' · ' . ($r['training_type'] ?? '') . ' · ' . implode('+', $groupsOfRow($r)), $insert), 0, 300)];
        if ($dry) {
            return response()->json($result);
        }
        DB::transaction(function () use ($insert, $grow, $board, $specsFound) {
            foreach ($grow as $id => [$groups, $add, $total]) {
                $c = TimetableCard::find($id);
                $c->update(['group_names' => array_values($groups), 'students' => $total ?: (int) $c->students + (int) round($add)]);
            }
            foreach (array_chunk($insert, 500) as $chunk) {
                TimetableCard::insert($chunk);
            }
            $this->ensureGridSettings($board, $specsFound);
        });
        $this->assignCardWeeks($board);
        $this->backfillFacultyNames($board);

        return response()->json($result);
    }

    /**
     * addMissingGroupCards rejasi: [qo'shiladigan qatorlar, kengaytiriladigan ma'ruzalar
     * (card_id => [guruhlar, qo'shiladigan talaba taxmini, aniq jami|null]), aralash, yangi guruhlar, guruh o'quvchi].
     */
    private function planMissingGroupCards(array $rows, $cards): array
    {
        $norm = fn ($g) => mb_strtolower(trim((string) $g));
        $groupsOfRow = function (array $r): array {
            $v = $r['group_names'] ?? null;
            $list = is_string($v) ? (json_decode($v, true) ?: []) : (array) ($v ?: []);
            if (!$list && !empty($r['group_name'])) {
                $list = array_map('trim', explode('+', (string) $r['group_name']));
            }

            return array_values(array_filter(array_map(fn ($g) => trim((string) $g), $list), fn ($g) => $g !== ''));
        };
        // Guruh nomi fakultetlararo takrorlanadi ("1K-01a" 1-son va 2-son davolashda) — kalit panjaradagi
        // kabi fakultet+yo'nalish+kurs+nom. Fakultetsiz eski kartalar fakultetsiz kalit bilan ham tekshiriladi.
        $key = fn ($fac, $spec, $course, $g) => mb_strtolower(trim((string) $fac)) . '|' . $this->specKey((string) $spec) . '|' . (int) $course . '|' . $norm($g);
        $have = [];
        foreach ($cards as $c) {
            foreach ($c->occupiedGroups() as $g) {
                $have[$key($c->faculty_name, $c->specialty_name, $c->course, $g)] = true;
            }
        }
        $exists = fn (array $r, $g) => isset($have[$key($r['faculty_name'] ?? '', $r['specialty_name'] ?? '', $r['course'] ?? 0, $g)])
            || isset($have[$key('', $r['specialty_name'] ?? '', $r['course'] ?? 0, $g)]);
        $insert = [];
        $grow = [];      // card_id => [groups, students]
        $mixed = [];
        $newGroups = [];
        foreach ($rows as $r) {
            $gs = $groupsOfRow($r);
            if (!$gs) {
                continue;
            }
            $new = array_values(array_filter($gs, fn ($g) => !$exists($r, $g)));
            if (!$new) {
                continue;
            }
            foreach ($new as $g) {
                $newGroups[$g] = true;
            }
            if (count($new) === count($gs)) {
                $insert[] = $r;
                continue;
            }
            if (($r['training_type'] ?? '') !== 'lecture') {
                $mixed[] = ($r['subject_name'] ?? '') . ' · ' . implode(' + ', $gs);
                continue;
            }
            $subj = $this->normSubject((string) ($r['subject_name'] ?? ''));
            $base = $this->cycleSubjectVariantBase((string) ($r['subject_name'] ?? ''));
            $baseN = $base !== null ? $this->normSubject($base) : null;
            $old = array_map($norm, array_diff($gs, $new));
            $target = $cards->first(function (TimetableCard $c) use ($r, $subj, $baseN, $old, $norm) {
                if ($c->training_type !== 'lecture' || (int) $c->course !== (int) ($r['course'] ?? 0)
                    || ((string) $c->faculty_name !== '' && mb_strtolower(trim((string) $c->faculty_name)) !== mb_strtolower(trim((string) ($r['faculty_name'] ?? ''))))
                    || $this->specKey((string) $c->specialty_name) !== $this->specKey((string) ($r['specialty_name'] ?? ''))
                    || mb_strtolower((string) $c->oqim_label) !== mb_strtolower((string) ($r['oqim_label'] ?? ''))) {
                    return false;
                }
                $cs = $this->normSubject((string) $c->subject_name);
                if ($cs !== $subj && ($baseN === null || $cs !== $baseN)) {
                    return false;
                }

                return (bool) array_intersect($old, array_map($norm, $c->occupiedGroups()));
            });
            if (!$target) {
                // Eski guruhlarning ma'ruzasi boshqa nom/oqim bilan turibdi — yangi karta ularga ikkinchi ma'ruza bo'lardi.
                $mixed[] = ($r['subject_name'] ?? '') . ' (ma\'ruza) · ' . implode(' + ', $gs);
                continue;
            }
            $share = (int) round(((int) ($r['students'] ?? 0)) * count($new) / max(1, count($gs)));
            $cur = $grow[$target->id] ?? [(array) ($target->group_names ?: [$target->group_name]), 0, null];
            foreach ($new as $g) {
                if (!in_array($g, $cur[0], true)) {
                    $cur[0][] = $g;
                    $cur[1] += $share / max(1, count($new));
                }
            }
            // Karta guruhlari endi aynan shu qatorniki — qatordagi jami (guruhlar haqiqiy soni bo'yicha) olinadi;
            // aks holda ulush taxmini (guruhlar teng deb), chunki qatorda guruhma-guruh son yo'q.
            $sameGroups = fn (array $a, array $b) => !array_diff(array_map($norm, $a), array_map($norm, $b))
                && !array_diff(array_map($norm, $b), array_map($norm, $a));
            $cur[2] = $sameGroups($cur[0], $gs) ? (int) ($r['students'] ?? 0) : null;
            $grow[$target->id] = $cur;
        }

        return [$insert, $grow, $mixed, $newGroups, $groupsOfRow];
    }

    /**
     * Kartalarni tasdiqlangan oqim/rejaga moslab SINXRONLAYDI: har karta kalit bo'yicha
     * (yo'nalish, kurs, oqim, til, tur, guruh(lar), fan, soat, hafta) solishtiriladi.
     *  - mos kelganlari saqlanadi (joylashuv, hafta istisnolari, o'qituvchi/xona bilan);
     *    faqat talabalar soni va kafedra yangilanadi;
     *  - endi rejada yo'qlari o'chiriladi;
     *  - yangilari joylashtirilmagan holda qo'shiladi.
     */
    private function syncCards(TimetableBoard $board, array $rows, array $specsFound): array
    {
        $norm = fn ($v) => mb_strtolower(trim((string) $v));
        $groupsOf = function ($v): string {
            if (is_string($v)) {
                $v = json_decode($v, true);
            }
            $list = array_map(fn ($g) => trim((string) $g), (array) ($v ?: []));
            sort($list, SORT_NATURAL | SORT_FLAG_CASE);

            return implode('+', $list);
        };
        // Fakultet kalitga kirmaydi: yangi qatorlarda u keyin (backfill) to'ldiriladi,
        // guruh nomlari esa fakultetlararo takrorlanmaydi.
        $keyOf = fn ($c) => implode('¦', [
            $norm($c['specialty_name'] ?? ''), (int) ($c['course'] ?? 0),
            $norm($c['oqim_label'] ?? ''), $norm($c['lang'] ?? ''), (string) ($c['training_type'] ?? ''),
            $norm($c['group_name'] ?? ''), $norm($groupsOf($c['group_names'] ?? null)),
            $norm($c['subject_name'] ?? ''), (int) ($c['len_half'] ?? 0), (int) ($c['weeks'] ?? 0),
        ]);

        $existing = [];
        foreach (TimetableCard::where('board_id', $board->id)->get() as $card) {
            $existing[$keyOf($card->getAttributes() + ['group_names' => $card->getRawOriginal('group_names')])][] = $card;
        }
        // Joylashgan / biriktirilgan (o'qituvchi yoki xona) kartalar birinchi saqlansin — reja
        // bunday kartalar sonini kamaytirsa, bo'sh karta qolib, xonasi bori o'chib ketmasin.
        foreach ($existing as &$bucket) {
            usort($bucket, fn ($a, $b) => [(int) !($a->day && $a->pair), (int) !$a->teacher_id, (int) !$a->auditorium_code, $a->id]
                <=> [(int) !($b->day && $b->pair), (int) !$b->teacher_id, (int) !$b->auditorium_code, $b->id]);
        }
        unset($bucket);

        $kept = [];
        $toInsert = [];
        $updates = [];
        $renames = [];       // yo'nalish¦kurs¦eski nom => [yo'nalish, kurs, eski, yangi]
        $renamedCards = [];  // karta id => [eski, yangi]
        foreach ($rows as $row) {
            $k = $keyOf($row);
            if (!empty($existing[$k])) {
                $card = array_shift($existing[$k]);
                $kept[] = $card->id;
                $changes = [];
                foreach (['students', 'kafedra_name'] as $col) {
                    if (array_key_exists($col, $row) && (string) $card->{$col} !== (string) $row[$col]) {
                        $changes[$col] = $row[$col];
                    }
                }
                // Karta normallashtirilgan nom bilan topiladi — HEMIS'da nom tuzatilsa ("Fan " → "Fan"), karta
                // o'chirilmay (joylashuvi bilan) yangi nomni oladi.
                if (array_key_exists('subject_name', $row) && (string) $card->subject_name !== (string) $row['subject_name']) {
                    $changes['subject_name'] = (string) $row['subject_name'];
                    $renames[$card->specialty_name . '¦' . (int) $card->course . '¦' . $card->subject_name]
                        = [(string) $card->specialty_name, (int) $card->course, (string) $card->subject_name, (string) $row['subject_name']];
                    $renamedCards[(int) $card->id] = [(string) $card->subject_name, (string) $row['subject_name']];
                }
                if ($changes) {
                    $updates[$card->id] = $changes;
                }
                continue;
            }
            $toInsert[] = $row;
        }
        $removeIds = [];
        foreach ($existing as $bucket) {
            foreach ($bucket as $card) {
                $removeIds[] = $card->id;
            }
        }
        $removedPlaced = TimetableCard::whereIn('id', $removeIds ?: [0])
            ->whereNotNull('day')->whereNotNull('pair')->count();

        // O'chiriladigan kartalarning o'qituvchi/xonasi dars birligi bo'yicha saqlanadi —
        // yangi kartaga (masalan guruh tarkibi o'zgargan) qaytarish uchun.
        $assignments = $this->assignmentsByUnit($board);
        $this->storeUnitAssignments($board, $assignments);

        DB::transaction(function () use ($board, $removeIds, $toInsert, $updates, $specsFound, $renames, $renamedCards) {
            foreach (array_chunk($removeIds, 500) as $chunk) {
                if (Schema::hasTable('timetable_card_overrides')) {
                    DB::table('timetable_card_overrides')->whereIn('card_id', $chunk)->delete();
                }
                TimetableCard::whereIn('id', $chunk)->delete();
            }
            foreach ($updates as $id => $changes) {
                TimetableCard::where('id', $id)->update($changes);
            }
            if ($renames) {
                $this->renameSubjectRefs($board, $renames, $renamedCards);
            }
            foreach (array_chunk($toInsert, 500) as $chunk) {
                TimetableCard::insert($chunk);
            }
            $this->ensureGridSettings($board, $specsFound);
        });

        // Yangi kartalar uchun hafta istisnolari (mavjudlariga tegmaydi), fakultet va biriktirishlar.
        $this->assignCardWeeks($board);
        $this->backfillFacultyNames($board);
        $restored = $toInsert
            ? $this->restoreAssignments($board, $this->storedUnitAssignments($board) + $assignments, $kept)
            : ['restored_units' => 0, 'restored_cards' => 0];

        return ['ok' => true, 'mode' => 'sync', 'created' => count($toInsert), 'kept' => count($kept),
            'removed' => count($removeIds), 'removed_placed' => $removedPlaced, 'updated' => count($updates),
            'renamed' => count(array_unique(array_map(fn ($r) => $r[2] . "\0" . $r[3], $renames)))] + $restored;
    }

    /**
     * HEMIS'da fan nomi tuzatilganda kartalar bilan birga shu doskadagi bog'liq yozuvlar ham yangi nomga o'tadi — aks holda
     * sikl joylashuvi va fan sozlamalari eski nomda qolib, joylashda nom mos kelmaydi. Moslik: yo'nalish + kurs + aniq nom.
     * Baza solishtiruvi oxiridagi bo'sh joy va registrni farqlamaydi, shuning uchun aniq tekshiruv PHP'da.
     */
    private function renameSubjectRefs(TimetableBoard $board, array $renames, array $renamedCards): void
    {
        $sameSpec = fn (?string $a, ?string $b) => $this->specKey((string) $a) === $this->specKey((string) $b);
        foreach ($renames as [$spec, $course, $old, $new]) {
            foreach (['timetable_subject_settings', 'timetable_cycle_placements', 'timetable_unit_assignments'] as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }
                $rows = DB::table($table)->where('board_id', $board->id)->where('course', $course)->where('subject_name', $old)
                    ->get(['id', 'specialty_name', 'subject_name'])
                    ->filter(fn ($r) => $r->subject_name === $old && $sameSpec($r->specialty_name, $spec));
                foreach ($rows as $r) {
                    // Fan sozlamasining kaliti (doska, yo'nalish, kurs, fan) noyob: yangi nomli yozuv bor bo'lsa — u qoladi.
                    if ($table === 'timetable_subject_settings' && DB::table($table)->where('board_id', $board->id)->where('course', $course)
                        ->where('subject_name', $new)->where('id', '!=', $r->id)->get(['specialty_name', 'subject_name'])
                        ->contains(fn ($x) => $x->subject_name === $new && $sameSpec($x->specialty_name, $spec))) {
                        DB::table($table)->where('id', $r->id)->delete();
                        continue;
                    }
                    DB::table($table)->where('id', $r->id)->update(['subject_name' => $new]);
                }
            }
        }
        // Muammo xabarlari kartaga bog'langan.
        if (Schema::hasTable('timetable_block_tickets')) {
            foreach ($renamedCards as $cardId => [$old, $new]) {
                $ids = DB::table('timetable_block_tickets')->where('card_id', $cardId)->where('subject_name', $old)->get(['id', 'subject_name'])
                    ->filter(fn ($t) => $t->subject_name === $old)->pluck('id')->all();
                if ($ids) {
                    DB::table('timetable_block_tickets')->whereIn('id', $ids)->update(['subject_name' => $new]);
                }
            }
        }
        // Kutilayotgan so'rov tasdiqlanganda payload qayta ijro etiladi (cycle-place va b. fan nomini aynan
        // solishtiradi): payload'dagi yo'nalish/kurs doirasida eski nom yangisiga almashadi. O'qituvchi taklifining
        // imzosi kartaning fan nomini ham o'z ichiga oladi — faqat nom o'zgargan bo'lsa, yangi nom bilan qayta imzolanadi.
        if (Schema::hasTable('timetable_change_requests')) {
            $approval = app(\App\Services\Timetable\TimetableChangeApproval::class);
            foreach (\App\Models\TimetableChangeRequest::where('board_id', $board->id)
                ->where('status', \App\Models\TimetableChangeRequest::PENDING)->get() as $req) {
                $payload = $req->payload;
                foreach ($renames as [$spec, $course, $old, $new]) {
                    if (is_array($payload) && ($payload['subject_name'] ?? null) === $old && (int) ($payload['course'] ?? 0) === $course
                        && $sameSpec($payload['specialty_name'] ?? '', $spec)) {
                        $payload['subject_name'] = $new;
                        $req->payload = $payload;
                        if ($req->subject_name === $old) {
                            $req->subject_name = $new;
                        }
                        break;
                    }
                }
                $details = $req->details;
                $cardId = (int) ($req->params['card'] ?? 0);
                if (isset($renamedCards[$cardId], $details['original_signature']) && is_array($payload) && isset($payload['week'])
                    && ($card = TimetableCard::find($cardId))) {
                    $before = (clone $card)->setAttribute('subject_name', $renamedCards[$cardId][0]);
                    if ($approval->proposalSignature($before, (int) $payload['week']) === $details['original_signature']) {
                        $details['original_signature'] = $approval->proposalSignature($card, (int) $payload['week']);
                        $req->details = $details;
                    }
                }
                if ($req->isDirty()) {
                    $req->save();
                }
            }
        }
        // O'zgartirish so'rovlarida yo'nalish/kurs yo'q — doskada eski nomli karta umuman qolmagan bo'lsagina.
        if (Schema::hasTable('timetable_change_requests')) {
            foreach (array_unique(array_map(fn ($r) => $r[2] . "\0" . $r[3], $renames)) as $pair) {
                [$old, $new] = explode("\0", $pair);
                if (TimetableCard::where('board_id', $board->id)->where('subject_name', $old)->pluck('subject_name')->contains(fn ($n) => $n === $old)) {
                    continue;
                }
                $ids = DB::table('timetable_change_requests')->where('board_id', $board->id)->where('subject_name', $old)->get(['id', 'subject_name'])
                    ->filter(fn ($q) => $q->subject_name === $old)->pluck('id')->all();
                if ($ids) {
                    DB::table('timetable_change_requests')->whereIn('id', $ids)->update(['subject_name' => $new]);
                }
            }
        }
    }

    /** Biriktirishni saqlash kaliti: fakultet + yo'nalish + kurs + fan (normallashtirilgan) + tur + oqim/guruh. */
    private function assignmentKey(TimetableCard $c): string
    {
        $scope = $c->training_type === 'lecture' ? ('L|' . $c->oqim_label) : ('P|' . $c->group_name);

        return implode('¦', [
            $c->faculty_name ?? '', $this->specKey((string) $c->specialty_name), (int) $c->course,
            $this->normSubject((string) $c->subject_name), $c->training_type, $scope,
        ]);
    }

    /** Birlik kaliti jadval uchun (sha1). */
    private function unitKeyHash(TimetableCard $c): string
    {
        return sha1($this->assignmentKey($c));
    }

    /** Birlikning o'qilishi uchun ma'lumotlari (jadvalga yoziladi). */
    private function unitMeta(TimetableCard $c): array
    {
        return [
            'faculty_name' => $c->faculty_name ?: null,
            'specialty_name' => (string) $c->specialty_name,
            'course' => (int) $c->course,
            'subject_name' => (string) $c->subject_name,
            'training_type' => (string) $c->training_type,
            'scope_label' => $c->training_type === 'lecture' ? $c->oqim_label : $c->group_name,
        ];
    }

    /** Doskadagi joriy o'qituvchi/xona biriktirishlari: sha1 kalit => [meta, teacher_id, teacher_name, auditorium_code, auditorium_name]. */
    private function assignmentsByUnit(TimetableBoard $board): array
    {
        $kept = [];
        $cards = TimetableCard::where('board_id', $board->id)
            ->where(fn ($q) => $q->whereNotNull('teacher_id')->orWhere(fn ($q) => $q->whereNotNull('auditorium_code')->where('auditorium_code', '!=', '')))
            ->get();
        foreach ($cards as $c) {
            $k = $this->unitKeyHash($c);
            $kept[$k] ??= ['meta' => $this->unitMeta($c), 'teacher_id' => null, 'teacher_name' => null, 'auditorium_code' => null, 'auditorium_name' => null];
            if ($c->teacher_id && !$kept[$k]['teacher_id']) {
                $kept[$k]['teacher_id'] = $c->teacher_id;
                $kept[$k]['teacher_name'] = $c->teacher_name;
            }
            if ($c->auditorium_code && !$kept[$k]['auditorium_code']) {
                $kept[$k]['auditorium_code'] = $c->auditorium_code;
                $kept[$k]['auditorium_name'] = $c->auditorium_name;
            }
        }

        return $kept;
    }

    /** Saqlangan biriktirishlarni yangi kartalarga qaytaradi. */
    private function restoreAssignments(TimetableBoard $board, array $kept, array $skipIds = []): array
    {
        if ($kept === []) {
            return ['restored_units' => 0, 'restored_cards' => 0];
        }
        // $skipIds — sinxronlashda saqlangan kartalar: ularning o'z o'qituvchi/xonasi
        // (birlik ichida har xil bo'lishi ham mumkin) qayta yozilmaydi, faqat yangilariga.
        $skip = array_flip($skipIds);
        $byKey = [];
        $present = [];
        foreach (TimetableCard::where('board_id', $board->id)->get(['id', 'faculty_name', 'specialty_name', 'course', 'subject_name', 'training_type', 'oqim_label', 'group_name']) as $c) {
            $k = $this->unitKeyHash($c);
            if (isset($kept[$k])) {
                $present[$k] = true;
                if (!isset($skip[$c->id])) {
                    $byKey[$k][] = $c->id;
                }
            }
        }
        $cardsUpdated = 0;
        DB::transaction(function () use ($byKey, $kept, &$cardsUpdated) {
            foreach ($byKey as $k => $ids) {
                $set = array_filter([
                    'teacher_id' => $kept[$k]['teacher_id'], 'teacher_name' => $kept[$k]['teacher_name'],
                    'auditorium_code' => $kept[$k]['auditorium_code'], 'auditorium_name' => $kept[$k]['auditorium_name'],
                ], fn ($v) => $v !== null && $v !== '');
                if ($set) {
                    $cardsUpdated += TimetableCard::whereIn('id', $ids)->update($set);
                }
            }
        });

        return ['restored_units' => count($byKey), 'restored_cards' => $cardsUpdated, 'lost_units' => count($kept) - count($present)];
    }

    /**
     * Bir doskadagi o'qituvchi/xona biriktirishlarini boshqa doskaga ko'chirish
     * (artisan timetable:copy-assignments). Dars birligi bo'yicha moslanadi:
     * fakultet + yo'nalish + kurs + fan (normallashtirilgan) + tur + oqim/guruh.
     * Topilmasa — variant harfisiz fan nomi bo'yicha ("Umumiy xirurgiya (a)" ← "Umumiy xirurgiya").
     * Manba: doska kartalari + timetable_unit_assignments jadvali.
     * $overwrite = false — maqsad doskada allaqachon bor biriktirish o'zgartirilmaydi.
     */
    public function copyUnitAssignments(TimetableBoard $from, TimetableBoard $to, bool $overwrite = false, bool $dryRun = false, ?string $kafedra = null): array
    {
        $baseName = fn (string $name) => $this->normSubject((string) preg_replace('/\s*\(\s*[a-zа-яё]\s*\)\s*$/iu', '', $name));
        $keyOf = function (TimetableCard $c, ?string $subjectNorm = null) {
            $scope = $c->training_type === 'lecture' ? ('L|' . $c->oqim_label) : ('P|' . $c->group_name);

            return implode('¦', [
                $c->faculty_name ?? '', $this->specKey((string) $c->specialty_name), (int) $c->course,
                $subjectNorm ?? $this->normSubject((string) $c->subject_name), $c->training_type, $scope,
            ]);
        };

        // Manba biriktirishlari: to'liq kalit va variant harfisiz kalit bo'yicha
        $src = [];
        $srcBase = [];
        $merge = function (array &$map, string $k, $teacherId, $teacherName, $roomCode, $roomName): void {
            $map[$k] ??= ['teacher_id' => null, 'teacher_name' => null, 'auditorium_code' => null, 'auditorium_name' => null];
            if ($teacherId && !$map[$k]['teacher_id']) {
                $map[$k]['teacher_id'] = $teacherId;
                $map[$k]['teacher_name'] = $teacherName;
            }
            if ($roomCode && !$map[$k]['auditorium_code']) {
                $map[$k]['auditorium_code'] = $roomCode;
                $map[$k]['auditorium_name'] = $roomName;
            }
        };
        $put = function (TimetableCard $c, $teacherId, $teacherName, $roomCode, $roomName) use (&$src, &$srcBase, $keyOf, $baseName, $merge) {
            $merge($src, $keyOf($c), $teacherId, $teacherName, $roomCode, $roomName);
            $merge($srcBase, $keyOf($c, $baseName((string) $c->subject_name)), $teacherId, $teacherName, $roomCode, $roomName);
        };
        foreach (TimetableCard::where('board_id', $from->id)
            ->where(fn ($q) => $q->whereNotNull('teacher_id')->orWhere(fn ($q) => $q->whereNotNull('auditorium_code')->where('auditorium_code', '!=', '')))
            ->get() as $c) {
            $put($c, $c->teacher_id, $c->teacher_name, $c->auditorium_code, $c->auditorium_name);
        }
        if ($this->unitAssignmentsEnabled()) {
            foreach (TimetableUnitAssignment::where('board_id', $from->id)->get() as $a) {
                $c = new TimetableCard([
                    'faculty_name' => $a->faculty_name, 'specialty_name' => $a->specialty_name, 'course' => $a->course,
                    'subject_name' => $a->subject_name, 'training_type' => $a->training_type,
                    'oqim_label' => $a->training_type === 'lecture' ? $a->scope_label : null,
                    'group_name' => $a->training_type === 'lecture' ? null : $a->scope_label,
                ]);
                $put($c, $a->teacher_id, $a->teacher_name, $a->auditorium_code, $a->auditorium_name);
            }
        }

        // Maqsad doska kartalari birlik bo'yicha
        $units = [];
        foreach (TimetableCard::where('board_id', $to->id)->get() as $c) {
            $units[$keyOf($c)][] = $c;
        }

        $stats = ['source_units' => count($src), 'target_units' => count($units), 'matched' => 0, 'by_variant' => 0,
            'teacher_set' => 0, 'room_set' => 0, 'skipped_existing' => 0, 'cards_updated' => 0];
        $kafedra = trim((string) $kafedra);
        DB::transaction(function () use ($units, $src, $srcBase, $keyOf, $baseName, $overwrite, $dryRun, $kafedra, &$stats) {
            foreach ($units as $k => $cards) {
                $first = $cards[0];
                // Faqat shu kafedra (maqsad doskadagi kafedra nomi bo'yicha)
                if ($kafedra !== '' && mb_stripos((string) $first->kafedra_name, $kafedra) === false) {
                    continue;
                }
                $a = $src[$k] ?? null;
                if (!$a) {
                    $a = $srcBase[$keyOf($first, $baseName((string) $first->subject_name))] ?? null;
                    if ($a) {
                        $stats['by_variant']++;
                    }
                }
                if (!$a) {
                    continue;
                }
                $stats['matched']++;
                $hasTeacher = collect($cards)->contains(fn ($c) => $c->teacher_id);
                $hasRoom = collect($cards)->contains(fn ($c) => $c->auditorium_code);
                $set = [];
                if ($a['teacher_id'] && ($overwrite || !$hasTeacher)) {
                    $set['teacher_id'] = $a['teacher_id'];
                    $set['teacher_name'] = $a['teacher_name'];
                    $stats['teacher_set']++;
                }
                if ($a['auditorium_code'] && ($overwrite || !$hasRoom)) {
                    $set['auditorium_code'] = $a['auditorium_code'];
                    $set['auditorium_name'] = $a['auditorium_name'];
                    $stats['room_set']++;
                }
                if (!$set) {
                    $stats['skipped_existing']++;
                    continue;
                }
                if (!$dryRun) {
                    $stats['cards_updated'] += TimetableCard::whereIn('id', collect($cards)->pluck('id'))->update($set);
                    $this->recordUnitAssignment($first->fresh());
                }
            }
        });

        return $stats;
    }

    /** Doskadagi joriy biriktirishlarni jadvalga zaxiralash (artisan timetable:backup-assignments). */
    public function backupUnitAssignments(TimetableBoard $board): int
    {
        $kept = $this->assignmentsByUnit($board);
        $this->storeUnitAssignments($board, $kept);

        return count($kept);
    }

    private function unitAssignmentsEnabled(): bool
    {
        static $enabled = null;

        return $enabled ??= Schema::hasTable('timetable_unit_assignments');
    }

    /** Kartalardan olingan biriktirishlarni jadvalga yozadi (mavjudini yangilaydi). */
    private function storeUnitAssignments(TimetableBoard $board, array $kept): void
    {
        if (!$this->unitAssignmentsEnabled() || $kept === []) {
            return;
        }
        $userId = auth()->id();
        foreach ($kept as $k => $a) {
            TimetableUnitAssignment::updateOrCreate(
                ['board_id' => $board->id, 'unit_key' => $k],
                ($a['meta'] ?? []) + [
                    'teacher_id' => $a['teacher_id'], 'teacher_name' => $a['teacher_name'],
                    'auditorium_code' => $a['auditorium_code'], 'auditorium_name' => $a['auditorium_name'],
                    'updated_by' => $userId,
                ]
            );
        }
    }

    /** Jadvaldagi biriktirishlar: sha1 kalit => [teacher_id, teacher_name, auditorium_code, auditorium_name]. */
    private function storedUnitAssignments(TimetableBoard $board): array
    {
        if (!$this->unitAssignmentsEnabled()) {
            return [];
        }

        return TimetableUnitAssignment::where('board_id', $board->id)->get()
            ->mapWithKeys(fn ($a) => [$a->unit_key => [
                'teacher_id' => $a->teacher_id, 'teacher_name' => $a->teacher_name,
                'auditorium_code' => $a->auditorium_code, 'auditorium_name' => $a->auditorium_name,
            ]])->all();
    }

    /**
     * Dars birligining hozirgi o'qituvchi/xonasini jadvalga yozadi (biriktirish
     * o'zgargan har safar). Ikkalasi ham bo'sh bo'lsa — yozuv o'chiriladi.
     */
    private function recordUnitAssignment(?TimetableCard $sample): void
    {
        if (!$sample || !$this->unitAssignmentsEnabled()) {
            return;
        }
        $q = TimetableCard::where('board_id', $sample->board_id)
            ->where('specialty_name', $sample->specialty_name)
            ->where('course', $sample->course)
            ->where('subject_name', $sample->subject_name)
            ->where('training_type', $sample->training_type);
        $sample->faculty_name ? $q->where('faculty_name', $sample->faculty_name) : $q->whereNull('faculty_name');
        if ($sample->training_type === 'lecture') {
            $sample->oqim_label !== null ? $q->where('oqim_label', $sample->oqim_label) : $q->whereNull('oqim_label');
        } else {
            $sample->group_name !== null ? $q->where('group_name', $sample->group_name) : $q->whereNull('group_name');
        }
        $cards = $q->get(['teacher_id', 'teacher_name', 'auditorium_code', 'auditorium_name']);
        $teacher = $cards->first(fn ($c) => $c->teacher_id);
        $room = $cards->first(fn ($c) => $c->auditorium_code);

        $where = ['board_id' => $sample->board_id, 'unit_key' => $this->unitKeyHash($sample)];
        if (!$teacher && !$room) {
            TimetableUnitAssignment::where($where)->delete();
            return;
        }
        TimetableUnitAssignment::updateOrCreate($where, $this->unitMeta($sample) + [
            'teacher_id' => $teacher?->teacher_id, 'teacher_name' => $teacher?->teacher_name,
            'auditorium_code' => $room?->auditorium_code, 'auditorium_name' => $room?->auditorium_name,
            'updated_by' => auth()->id(),
        ]);
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
        $subjects = ($this->isHemisBoard($board) ? $this->hemisSubjectRows($board) : DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + (CASE WHEN CAST(mc.level_code AS UNSIGNED) >= 11 THEN CAST(mc.level_code AS UNSIGNED) - 10 ELSE CAST(mc.level_code AS UNSIGNED) END) - 1) = ?", [$start])
            ->orderByDesc('s.updated_at')
            ->orderByDesc('s.id')
            ->get(['mc.specialty_name', 'mc.level_code', 's.subject_name']));

        // Kalit: specKey|kurs|normFan => joriy ko'rinadigan nom (birinchi = eng yangi)
        $map = [];
        // "1-son Davolash ishi" kabi reja nomlari kartochkada fakultet ("1-son davolash")
        // va sof yo'nalish ("Davolash ishi") bo'lib turadi — kalitni alias bo'yicha quramiz,
        // aks holda bunday yo'nalishlardagi kartalar hech qachon yangilanmaydi.
        // Fakultetli kalit ustuvor (1-son va 2-son nomlari bir-birini bosib ketmasin).
        // Alias rejalarining fakultetsiz kaliti faqat zaxira: sof "Davolash ishi"
        // rejasi (mas. Xalqaro ta'lim) kartalari 1-son/2-son nomini olmasin.
        $aliasFallback = [];
        foreach ($subjects as $s) {
            $course = (int) $s->level_code >= 11 ? (int) $s->level_code - 10 : (int) $s->level_code;
            [$facAlias, $specAlias] = $this->curriculumScopeAlias((string) $s->specialty_name);
            $key = $this->specKey($specAlias) . '|' . $course . '|' . $this->normSubject((string) $s->subject_name);
            if ($facAlias === null) {
                $map[$key] ??= $s->subject_name;
            } else {
                $map[$this->specKey($facAlias) . '|' . $key] ??= $s->subject_name;
                $aliasFallback[$key] ??= $s->subject_name;
            }
        }
        $map += $aliasFallback;
        // Bo'lakli fanlarning asosiy nomi ("Fan (a)" → "Fan") ham — birlashtirilgan umumiy ma'ruza kartasi uchun
        foreach ($map as $k => $name) {
            $base = $this->cycleSubjectVariantBase((string) $name);
            if ($base !== null) {
                $map[substr($k, 0, strrpos($k, '|') + 1) . $this->normSubject($base)] ??= $base;
            }
        }
        // Imlo xatosi tuzatilgan nomlar uchun (masalan HEMIS da "stamatologiya" → "stomatologiya"):
        // aniq mos kelmasa — shu yo'nalish+kursdagi 1–2 harf farqli YAGONA nom (bo'lak harfi bir xil).
        $byPrefix = [];
        foreach ($map as $k => $name) {
            $cut = strrpos($k, '|');
            $byPrefix[substr($k, 0, $cut + 1)][substr($k, $cut + 1)] = $name;
        }
        $variantLetter = fn (string $n) => preg_match('/\(\s*(\p{L})\s*\)\s*$/u', trim($n), $m) ? mb_strtolower($m[1]) : '';
        $fuzzy = function (string $prefix, string $norm, string $name) use ($byPrefix, $variantLetter): ?string {
            if (mb_strlen($norm) < 12 || strlen($norm) > 255 || empty($byPrefix[$prefix])) {
                return null;
            }
            $best = null;
            $bestD = 3;
            $ties = 0;
            foreach ($byPrefix[$prefix] as $cand => $candName) {
                if (strlen($cand) > 255 || $variantLetter((string) $candName) !== $variantLetter($name)) {
                    continue;
                }
                $d = $this->subjectTypoDistance($norm, $cand);
                if ($d < $bestD) {
                    [$best, $bestD, $ties] = [$candName, $d, 0];
                } elseif ($d === $bestD) {
                    $ties++;
                }
            }

            return $best !== null && $ties === 0 && $bestD > 0 ? $best : null;
        };

        // Kafedra ham qayta hisoblanadi (barcha kartalarda — nom o'zgarmagan bo'lsa ham:
        // HEMIS da kafedra o'zgargan / bo'lingan bo'lsa joylashuvlarni yo'qotmasdan to'g'rilansin)
        [$kafMap, $overrides] = $this->buildKafedraMap();

        // HEMIS doska: kafedra faqat HEMIS fanining o'zidan (qo'lda belgilanganlar ishlatilmaydi)
        $hemisKaf = [];
        if ($this->isHemisBoard($board)) {
            foreach ($subjects as $s) {
                if (empty($s->hemis_kafedra)) {
                    continue;
                }
                $course = (int) $s->level_code >= 11 ? (int) $s->level_code - 10 : (int) $s->level_code;
                [$facAlias, $specAlias] = $this->curriculumScopeAlias((string) $s->specialty_name);
                $k = $this->specKey($specAlias) . '|' . $course . '|' . $this->normSubject((string) $s->subject_name);
                $hemisKaf[($facAlias !== null ? $this->specKey($facAlias) . '|' : '') . $k] ??= $s->hemis_kafedra;
                $hemisKaf[$k] ??= $s->hemis_kafedra;
            }
        }

        $updated = 0;
        $kafUpdated = 0;
        $touched = [];
        foreach (TimetableCard::where('board_id', $board->id)->get() as $c) {
            $key = $this->specKey($c->specialty_name) . '|' . (int) $c->course . '|' . $this->normSubject((string) $c->subject_name);
            $new = $map[$this->specKey((string) $c->faculty_name) . '|' . $key] ?? $map[$key] ?? null;
            if ($new === null) {
                $norm = $this->normSubject((string) $c->subject_name);
                $p = $this->specKey($c->specialty_name) . '|' . (int) $c->course . '|';
                $facultyPrefix = $this->specKey((string) $c->faculty_name) . '|' . $p;
                // Fakultet rejasida noaniq yoki yo'q moslikni boshqa fakultetdan olmang.
                $prefix = isset($byPrefix[$facultyPrefix]) ? $facultyPrefix : $p;
                $new = $fuzzy($prefix, $norm, (string) $c->subject_name);
            }
            $changed = false;
            if ($new !== null && $new !== $c->subject_name) {
                $c->subject_name = $new;
                $updated++;
                $changed = true;
            }
            $hk = $this->specKey($c->specialty_name) . '|' . (int) $c->course . '|' . $this->normSubject((string) $c->subject_name);
            $kaf = $hemisKaf[$this->specKey((string) $c->faculty_name) . '|' . $hk] ?? $hemisKaf[$hk]
                ?? ($hemisKaf ? null : $this->kafedraFor($overrides, $kafMap, (string) $c->subject_name, (string) $c->specialty_name, (int) $c->course));
            if ($kaf && $kaf !== $c->kafedra_name) {
                $c->kafedra_name = $kaf;
                $kafUpdated++;
                $changed = true;
            }
            if ($changed) {
                $touched[] = $c;
            }
        }

        DB::transaction(function () use ($touched) {
            foreach ($touched as $c) {
                $c->save();
            }
        });

        return response()->json(['ok' => true, 'updated' => $updated, 'kafedra_updated' => $kafUpdated]);
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
            'faculty_name' => 'nullable|string|max:255',
            'course' => 'required|integer|min:1|max:7',
            'curriculum_hemis_id' => 'nullable|integer|min:0',
            'days' => 'required|integer|min:1|max:7',
            'pairs_per_day' => 'required|integer|min:1|max:24',
            'weeks' => 'required|integer|min:1|max:30',
            'start_date' => 'nullable|date',
            'weekly_breaks' => 'nullable|array|max:10',
            'weekly_breaks.*.from' => 'required|date',
            'weekly_breaks.*.to' => 'required|date|after_or_equal:weekly_breaks.*.from',
            'regenerate' => 'nullable|boolean',
        ]);
        if ($request->boolean('regenerate')) {
            return response()->json(['error' => "Kalendarni saqlash kartochkalarni qayta yaratmaydi. Kartochkalar amalidan alohida foydalaning."], 422);
        }
        $program = (int) ($data['curriculum_hemis_id'] ?? 0);
        $hasPrograms = Schema::hasColumn('timetable_grid_settings', 'curriculum_hemis_id');
        if ($program && !$hasPrograms) return response()->json(['error' => 'Avval php artisan migrate buyrug‘ini bajaring.'], 503);
        if ($program) {
            $cards = TimetableCard::where('board_id', $board->id)->where('faculty_name', $data['faculty_name'] ?? null)
                ->where('specialty_name', $data['specialty_name'])->where('course', $data['course'])->get();
            if (!$cards->contains(fn ($c) => $this->cardCurriculumId($c) === $program)) {
                return response()->json(['error' => "Bu o‘quv reja tanlangan fakultet, yo‘nalish va kursda topilmadi."], 422);
            }
        }
        $identity = ['board_id' => $board->id, 'faculty_name' => $data['faculty_name'] ?? null,
            'specialty_name' => $data['specialty_name'], 'course' => $data['course']];
        if ($hasPrograms) $identity['curriculum_hemis_id'] = $program;
        $values = ['days' => $data['days'], 'pairs_per_day' => $data['pairs_per_day'], 'weeks' => $data['weeks']];
        if (Schema::hasColumn('timetable_grid_settings', 'start_date') && $request->has('start_date')) $values['start_date'] = $data['start_date'] ?: null;
        if ($hasPrograms && $request->has('weekly_breaks')) $values['weekly_breaks'] = $data['weekly_breaks'];
        TimetableGridSetting::updateOrCreate($identity, $values);
        $this->calendarGridCache = [];
        $this->calendarWeekCache = [];
        // Saving a calendar never deletes cards, placements, assignments or week overrides.
        return response()->json(['ok' => true, 'regenerated' => false]);
    }

    /**
     * Bo'lakli fanning ma'ruzalari ("Fan (a)", "Fan (b)", "Fan (c)") bitta oqimda — bitta umumiy ma'ruza:
     * asosiy nom bilan bitta karta qoladi (barcha guruhlar, jami talaba), qolganlari o'chiriladi.
     * Qolgan kartaning joylashuvi va haftalari saqlanadi.
     */
    public function mergeVariantLectures(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'dry_run'           => 'nullable|boolean',
        ]);
        if (!in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true)) {
            return response()->json(['error' => 'Bu amal faqat admin uchun.'], 403);
        }
        $this->snapshotBefore($request, $board, 'before_merge_lectures');
        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $q = TimetableCard::where('board_id', $board->id)->where('training_type', 'lecture');
        $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
        $groups = [];
        $cycleKeys = $this->cycleSubjectKeys($board);
        foreach ($q->orderBy('id')->get() as $card) {
            if (!$this->mergesVariantLecture($cycleKeys, (string) $card->specialty_name, (int) $card->course, (string) $card->subject_name)) {
                continue;
            }
            $base = (string) $this->cycleSubjectVariantBase((string) $card->subject_name);
            $groups[implode('|', [$card->faculty_name, $card->specialty_name, $card->course, mb_strtolower((string) $card->oqim_label), $this->normSubject($base)])][] = [$card, $base];
        }
        // Avval birlashtirilgan (asosiy nomli) ma'ruza shu oqimda qolgan bo'lsa — keyin qo'shilgan
        // bo'lak ma'ruzalari ham unga qo'shiladi (u qoldiriladigan karta bo'ladi).
        if ($groups) {
            $q2 = TimetableCard::where('board_id', $board->id)->where('training_type', 'lecture');
            $this->applyScopeToQuery($q2, $facSet, $specSet, $courseSet);
            foreach ($q2->orderBy('id')->get() as $card) {
                if ($this->cycleSubjectVariantBase((string) $card->subject_name) !== null) {
                    continue;
                }
                $k = implode('|', [$card->faculty_name, $card->specialty_name, $card->course, mb_strtolower((string) $card->oqim_label), $this->normSubject((string) $card->subject_name)]);
                if (isset($groups[$k])) {
                    array_unshift($groups[$k], [$card, $groups[$k][0][1]]);
                }
            }
        }
        $dry = $request->boolean('dry_run');
        $hasOv = Schema::hasTable('timetable_card_overrides');
        // Joylashuv (ikkala qatlam) va hafta yozuvlari: bo'laklarniki farq qilsa, qoldiriladigan karta
        // boshqa bo'lak guruhlarini tekshiruvsiz o'z joyiga (bandligi noma'lum) va o'z haftalariga olib
        // o'tardi — o'sha bo'laklarning qo'shimcha amaliysi esa eski ma'ruza haftalariga moslangan.
        $placementSig = function (TimetableCard $c) use ($hasOv): string {
            $ovs = $hasOv
                ? TimetableCardOverride::where('card_id', $c->id)->orderBy('week')->get()
                    ->map(fn ($o) => [(int) $o->week, (int) $o->day, (int) $o->pair, (int) $o->start_half, (bool) $o->cancelled])->all()
                : [];

            return json_encode([(int) $c->day, (int) $c->pair, (int) $c->start_half, (int) $c->ext_day, (int) $c->ext_pair, $ovs]);
        };
        // HEMIS biriktiruvi bo'yicha yaratilgan bo'lak ma'ruzasi (assembleRows dagi $variantUnits) — har biri
        // o'z bo'lagining ulushi, guruhlari bir xil bo'lsa ham (bitta guruh talabalari bo'laklarga bo'lingan).
        $hemis = $this->isHemisBoard($board);
        $year = (int) substr((string) $board->academic_year, 0, 4);
        $assignmentBacked = function (array $items) use ($hemis, $year): bool {
            if (!$hemis) {
                return false;
            }
            foreach ($items as [$c]) {
                $counts = $this->variantStudentCounts((string) $c->subject_name, $year);
                foreach ((array) $c->group_names as $g) {
                    if ($this->groupAssignedCount($counts, (string) $g, $c->faculty_name) > 0) {
                        return true;
                    }
                }
            }

            return false;
        };
        $list = [];
        $skipped = [];
        $removed = 0;
        DB::transaction(function () use ($groups, $dry, $hasOv, $placementSig, $assignmentBacked, &$list, &$skipped, &$removed) {
            foreach ($groups as $items) {
                [$keep, $base] = $items[0];
                $label = $base . ' · ' . $keep->oqim_label;
                if (count(array_unique(array_map(fn ($it) => $placementSig($it[0]), $items))) > 1) {
                    $skipped[] = $label . " — bo'laklar ma'ruzasi turli vaqtda yoki turli haftalarda";
                    continue;
                }
                $names = [];
                $sets = [];
                $students = [];
                foreach ($items as [$c]) {
                    $gs = (array) $c->group_names;
                    foreach ($gs as $g) {
                        $names[$g] = true;
                    }
                    sort($gs);
                    $sets[json_encode($gs)] = true;
                    $students[] = (int) $c->students;
                }
                // Biriktiruvsiz (ishchi reja, harf qoidasi) va guruhlari bir xil bo'laklar — har biri butun oqim
                // sonini olgan, qo'shilsa ikki-uch barobar bo'lardi; aks holda har bo'lak o'z ulushi.
                $total = count($sets) === 1 && !$assignmentBacked($items) ? max($students) : array_sum($students);
                $list[] = $label . ' · ' . count($items) . " bo'lak → 1";
                if ($dry) {
                    continue;
                }
                $keep->update(['subject_name' => $base, 'group_names' => array_keys($names), 'students' => max(1, $total)]);
                foreach (array_slice($items, 1) as [$c]) {
                    if ($hasOv) {
                        TimetableCardOverride::where('card_id', $c->id)->delete();
                    }
                    $c->delete();
                    $removed++;
                }
            }
        });

        return response()->json(['ok' => true, 'dry_run' => $dry, 'lectures' => count($list), 'removed' => $removed,
            'list' => array_slice($list, 0, 200), 'skipped' => array_slice($skipped, 0, 200)]);
    }

    /**
     * Amaliy kartalarning guruh to'plamlari. Bo'lakli fan (HEMIS biriktiruvi): to'liq qismlar alohida, kichik qismlar
     * asos guruh bo'yicha bitta aralash guruh; boshqa fan: seminar / katta guruhda juftlab, aks holda alohida.
     * "Alohida amaliy" istisnosida har guruh (bo'lakning kichik qismi ham) alohida — ajratilgan kartalar qayta
     * yaratishda yana birlashmaydi.
     */
    private function practiceBundles(array $practiceRows, bool $variantUnits, bool $pairPracticeGroups, bool $separate): array
    {
        if ($separate || (!$variantUnits && !$pairPracticeGroups)) {
            return array_map(static fn ($gr) => [$gr], $practiceRows);
        }
        if (!$variantUnits) {
            return array_chunk($practiceRows, 2);
        }
        $bundles = array_map(static fn ($gr) => [$gr], array_values(array_filter($practiceRows, fn ($gr) => empty($gr['partial']))));
        // Kichik qismlar asos guruh bo'yicha: "02a dan 5 + 02b dan 5" — bitta (c) guruhi; butun oqimning qoldiqlari
        // bitta katta guruhga qo'shilmaydi.
        $partByBase = [];
        foreach ($practiceRows as $gr) {
            if (!empty($gr['partial'])) {
                $partByBase[$this->baseGroupKey((string) ($gr['name'] ?? ''))][] = $gr;
            }
        }

        return array_merge($bundles, array_values($partByBase));
    }

    /** Istisno kaliti: fakultet ('' — barcha fakultetlar) + yo'nalish + kurs + fan (amaliy guruhlari juftlanmaydi). */
    private function separatePracticeKey(string $faculty, string $specialty, int $course, string $subject): string
    {
        return mb_strtolower(trim($faculty)) . '|' . $this->specKey($specialty) . '|' . $course . '|' . $this->normSubject($subject);
    }

    /**
     * Istisno: seminar (yoki katta guruh) sababli juftlab yaratilgan amaliy kartalarini ("01a + 01b") har guruhchaga
     * alohida kartaga bo'ladi. Haftalik ko'chirishlar nusxalarga ham o'tadi; birinchi guruhda o'qituvchi/xona qoladi,
     * qolganlariga keyin alohida biriktiriladi. Istisno doska sozlamasida saqlanadi — kartalar qayta
     * yaratilganda ham bu fan juftlanmaydi.
     */
    public function separatePracticeGroups(Request $request, TimetableBoard $board)
    {
        if (!in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true)) {
            abort(403);
        }
        $data = $request->validate([
            'faculty_name'   => 'nullable|string|max:255',
            'specialty_name' => 'required|string|max:255',
            'course'         => 'required|integer|min:1|max:7',
            'subject_name'   => 'required|string|max:255',
            'dry_run'        => 'nullable|boolean',
        ]);
        $this->snapshotBefore($request, $board, 'before_separate_practice');
        $dry = $request->boolean('dry_run');
        $subjKey = $this->normSubject($data['subject_name']);
        $cards = TimetableCard::where('board_id', $board->id)->where('training_type', 'practice')->where('course', (int) $data['course'])
            ->get()
            ->filter(fn (TimetableCard $c) => $this->specKey($c->specialty_name) === $this->specKey($data['specialty_name'])
                && $this->normSubject((string) $c->subject_name) === $subjKey
                && (empty($data['faculty_name']) || mb_strtolower(trim((string) $c->faculty_name)) === mb_strtolower(trim($data['faculty_name'])))
                && count($c->occupiedGroups()) > 1)
            ->values();

        $plan = $cards->map(fn (TimetableCard $c) => '#' . $c->id . ' ' . (string) $c->group_name . ' → ' . implode(' | ', $c->occupiedGroups()))->all();
        $hasOv = Schema::hasTable('timetable_card_overrides');
        $ovCols = $hasOv ? array_values(array_intersect(['week', 'day', 'pair', 'start_half', 'cancelled', 'auditorium_code', 'auditorium_name'],
            Schema::getColumnListing('timetable_card_overrides'))) : [];
        $created = 0;
        $hemisBoard = $this->isHemisBoard($board);
        $year = (int) substr((string) $board->academic_year, 0, 4);
        if (!$dry) {
            DB::transaction(function () use ($cards, $hasOv, $ovCols, $hemisBoard, $year, &$created) {
                foreach ($cards as $card) {
                    $names = array_values($card->occupiedGroups());
                    // Bo'lakli fan (HEMIS biriktiruvi): har guruhdan shu bo'lakka biriktirilgan talabalar — assembleRows va
                    // splitVariantMixed dagidek; biriktiruvi yo'q bo'lsa — guruhning faol talabalari.
                    $counts = [];
                    if ($hemisBoard && $this->variantLetter((string) $card->subject_name) !== null) {
                        $vCounts = $this->variantStudentCounts((string) $card->subject_name, $year);
                        $counts = array_map(fn ($n) => $this->groupAssignedCount($vCounts, (string) $n, $card->faculty_name), $names);
                    }
                    if (array_sum($counts) < 1) {
                        $counts = array_map(fn ($n) => $this->activeStudentCountFor((string) $n, $card->faculty_name), $names);
                    }
                    $fallback = (int) max(1, round($card->students / max(1, count($names))));
                    $ovs = $hasOv ? DB::table('timetable_card_overrides')->where('card_id', $card->id)->get() : collect();
                    foreach ($names as $i => $name) {
                        $attrs = ['group_name' => $name, 'group_names' => null, 'students' => max(1, $counts[$i] ?: $fallback)];
                        if ($i === 0) {
                            $card->update($attrs);
                            continue;
                        }
                        $copy = $card->replicate();
                        $copy->fill($attrs);
                        $copy->teacher_id = null;
                        $copy->teacher_name = null;
                        $copy->auditorium_code = null;
                        $copy->auditorium_name = null;
                        $copy->save();
                        $created++;
                        foreach ($ovs as $o) {
                            $row = ['card_id' => $copy->id, 'created_at' => now(), 'updated_at' => now()];
                            foreach ($ovCols as $col) {
                                $row[$col] = in_array($col, ['auditorium_code', 'auditorium_name'], true) ? null : $o->{$col};
                            }
                            DB::table('timetable_card_overrides')->insert($row);
                        }
                    }
                }
            });
            $set = (array) ($board->settings ?? []);
            $list = (array) ($set['separate_practice'] ?? []);
            // Fakultet ko'rsatilgan bo'lsa — istisno faqat o'sha fakultetga (boshqa fakultetdagi shu yo'nalish juftligicha qoladi).
            $list[] = $this->separatePracticeKey((string) ($data['faculty_name'] ?? ''), $data['specialty_name'], (int) $data['course'], $data['subject_name']);
            $set['separate_practice'] = array_values(array_unique($list));
            $board->update(['settings' => $set]);
        }

        return response()->json(['ok' => true, 'dry_run' => $dry, 'cards' => $cards->count(), 'created' => $created, 'plan' => $plan]);
    }

    /**
     * 2 soatdan uzun amaliy kartalarini (3 yoki 4 soat) 2 + 1 / 2 + 2 soatlik kartalarga bo'ladi.
     * Asl karta birinchi qism (2 soat) bo'lib qoladi; qolgan qismlar uning nusxasi — xuddi shu joyda
     * davomi sifatida (tashqi va ichki joylashuv, haftalar, o'qituvchi, xona bilan).
     */
    public function splitLongPractice(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'dry_run'           => 'nullable|boolean',
        ]);
        if (!in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true)) {
            return response()->json(['error' => 'Bu amal faqat admin uchun.'], 403);
        }
        $this->snapshotBefore($request, $board, 'before_split_practice');
        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $q = TimetableCard::where('board_id', $board->id)->where('training_type', 'practice')->where('len_half', '>', 2);
        $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
        $dry = $request->boolean('dry_run');
        $hasOv = Schema::hasTable('timetable_card_overrides');
        $cards = $q->get();
        $created = 0;
        if (!$dry) {
            DB::transaction(function () use ($cards, $hasOv, &$created) {
                foreach ($cards as $card) {
                    $parts = $this->splitPracticeHours((int) $card->len_half);
                    $ovs = $hasOv ? TimetableCardOverride::where('card_id', $card->id)->get() : collect();
                    $offset = (int) $parts[0];
                    $card->update(['len_half' => $parts[0]]);
                    foreach (array_slice($parts, 1) as $len) {
                        $copy = $card->replicate();
                        $copy->len_half = $len;
                        $copy->pair = $card->pair ? (int) $card->pair + $offset : null;
                        $copy->ext_pair = $card->ext_pair ? (int) $card->ext_pair + $offset : null;
                        $copy->save();
                        $created++;
                        foreach ($ovs as $o) {
                            TimetableCardOverride::create([
                                'card_id' => $copy->id, 'week' => $o->week, 'day' => $o->day,
                                'pair' => $o->pair ? (int) $o->pair + $offset : null,
                                'start_half' => $o->start_half, 'cancelled' => $o->cancelled,
                                'auditorium_code' => $o->auditorium_code, 'auditorium_name' => $o->auditorium_name,
                            ]);
                        }
                        $offset += (int) $len;
                    }
                }
            });
        }

        return response()->json(['ok' => true, 'dry_run' => $dry, 'cards' => $cards->count(), 'created' => $created,
            'list' => $cards->take(100)->map(fn ($c) => $c->subject_name . ' · ' . ($c->group_name ?: $c->oqim_label) . ' · ' . $c->len_half . ' soat')->values()]);
    }

    /**
     * Bo'lakli fanning aralash (c) amaliy kartasi bir nechta asos guruhni qamrab olgan bo'lsa
     * ("02a+02b+03a+03b+…"), uni asos guruhlar bo'yicha bo'ladi: har asos guruhga ("02a + 02b")
     * alohida karta. Asl karta birinchi asos guruhda qoladi; qolganlari uning nusxasi —
     * tashqi/ichki joylashuvi va haftalari (bekor qilingan haftalar, ko'chirishlar) bilan.
     * O'qituvchi va xona nusxalarga ko'chirilmaydi (kafedra o'zi biriktiradi).
     */
    public function splitVariantMixed(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'dry_run'           => 'nullable|boolean',
        ]);
        if (!in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true)) {
            return response()->json(['error' => 'Bu amal faqat admin uchun.'], 403);
        }
        // Aralash karta faqat HEMIS doskada (biriktiruv bo'yicha) yig'iladi; ishchi reja doskasida
        // ko'p guruhli amaliy karta — ataylab juftlangan guruhlar.
        if (!$this->isHemisBoard($board)) {
            return response()->json(['error' => 'Bu amal faqat HEMIS fanlari doskasi uchun.'], 422);
        }
        $this->snapshotBefore($request, $board, 'before_split_variant');
        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $q = TimetableCard::where('board_id', $board->id)->where('training_type', 'practice')->whereNotNull('group_names');
        $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
        $year = (int) substr((string) $board->academic_year, 0, 4);
        $dry = $request->boolean('dry_run');
        $split = [];
        $created = 0;
        $hasOv = Schema::hasTable('timetable_card_overrides');

        DB::transaction(function () use ($q, $year, $dry, $hasOv, &$split, &$created) {
            foreach ($q->get() as $card) {
                if ($this->variantLetter((string) $card->subject_name) === null) {
                    continue;
                }
                $byBase = [];
                foreach ((array) $card->group_names as $g) {
                    $byBase[$this->baseGroupKey((string) $g)][] = $g;
                }
                if (count($byBase) < 2) {
                    continue;
                }
                // Har guruhdan shu bo'lakka HEMIS'da biriktirilgan talabalar — guruhning o'z o'quv
                // rejasi bo'yicha (variantAssignedCount kabi): guruh nomi fakultetlararo takrorlanadi.
                $counts = $this->variantStudentCounts((string) $card->subject_name, $year);
                $studentsOf = function (array $names) use ($counts, $card) {
                    $sum = 0;
                    foreach ($names as $n) {
                        $sum += $this->groupAssignedCount($counts, (string) $n, $card->faculty_name);
                    }
                    return $sum;
                };
                // Biriktiruv bo'yicha yig'ilgan aralash kartagina bo'linadi. Biriktiruvi yo'q karta
                // harf qoidasi bilan ataylab juftlangan ("01a + 02a": seminar / katta amaliy guruh).
                if ($studentsOf((array) $card->group_names) < 1) {
                    continue;
                }
                $bundles = array_values($byBase);
                $split[] = $card->subject_name . ' · ' . $card->oqim_label . ' · ' . count($bundles) . ' guruh';
                if ($dry) {
                    continue;
                }
                $ovs = $hasOv ? TimetableCardOverride::where('card_id', $card->id)->get() : collect();
                // Biriktiruvi yo'q bo'lak uchun ulush — asl (bo'linmagan) kartadan: birinchi bo'lak
                // $card->students ni yangilab yuboradi.
                $fallback = (int) round($card->students / count($bundles));
                foreach ($bundles as $i => $names) {
                    $attrs = [
                        'group_name' => implode(' + ', $names),
                        'group_names' => count($names) > 1 ? $names : null,
                        'students' => max(1, $studentsOf($names) ?: $fallback),
                    ];
                    if ($i === 0) {
                        $card->update($attrs);
                        continue;
                    }
                    $copy = $card->replicate();
                    $copy->fill($attrs);
                    $copy->teacher_id = null;
                    $copy->teacher_name = null;
                    $copy->auditorium_code = null;
                    $copy->auditorium_name = null;
                    $copy->save();
                    $created++;
                    foreach ($ovs as $o) {
                        TimetableCardOverride::create([
                            'card_id' => $copy->id, 'week' => $o->week, 'day' => $o->day, 'pair' => $o->pair,
                            'start_half' => $o->start_half, 'cancelled' => $o->cancelled,
                        ]);
                    }
                }
            }
        });

        return response()->json(['ok' => true, 'dry_run' => $dry, 'cards' => count($split), 'created' => $created, 'list' => array_slice($split, 0, 200)]);
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
            'layer'          => 'nullable|in:external,internal',
        ]);

        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $scopeQuery = TimetableCard::where('board_id', $board->id);
        $this->applyScopeToQuery($scopeQuery, $facSet, $specSet, $courseSet);
        if (!empty($data['training_type'])) {
            $scopeQuery->where('training_type', $data['training_type']);
        }

        $layer = $this->weeklyLayer($request, $data['layer'] ?? null);
        if ($denied = $this->weeklyLayerDenied($request, $layer)) {
            return $denied;
        }
        $this->snapshotBefore($request, $board, 'before_unplace');
        if ($layer === 'external') {
            $q = (clone $scopeQuery)->whereNotNull('ext_day');
            $count = (clone $q)->count();
            $q->update(['ext_day' => null, 'ext_pair' => null]);

            return response()->json(['ok' => true, 'unplaced' => $count, 'layer' => 'external']);
        }
        // Kafedra mudiri faqat o'z kafedrasi kartalarini bo'shatadi (kafedra aniqlanmasa
        // weeklyLayerDenied allaqachon rad etgan — bu yerda $own bo'sh bo'lmaydi).
        if ($this->timetableActiveRole($request) === 'kafedra_mudiri') {
            $own = $this->departmentHeadKafedraSafe($request) ?? [];
            $ids = (clone $scopeQuery)->get(['id', 'kafedra_name'])
                ->filter(fn ($c) => $own && $this->sameKafedra($c->kafedra_name, $own))->pluck('id')->all();
            $scopeQuery->whereIn('id', $ids ?: [0]);
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
            ...$this->calendarProgram($this->cardCurriculumId($c)),
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
            // Tashqi qatlam (o'quv bo'limi) joylashuvi; day/pair — ichki (kafedra) jadval.
            'ext_day' => $c->ext_day ?? null,
            'ext_pair' => $c->ext_pair ?? null,
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
            'term_weeks' => $this->cardGrid($board, $c)['weeks'],
            'weeks' => $c->weeks !== null
                ? (int) $c->weeks
                : ($c->training_type === 'lecture'
                    ? $this->lectureWeeks(
                        $lecHours[$this->specKey($c->specialty_name) . '|' . $c->course . '|' . $this->normSubject((string) $c->subject_name)] ?? 0
                    )
                    : null),
        ]);

        $grids = TimetableGridSetting::where('board_id', $board->id)
            ->get(array_merge(['faculty_name', 'specialty_name', 'course', 'days', 'pairs_per_day', 'weeks'],
                Schema::hasColumn('timetable_grid_settings', 'start_date') ? ['start_date'] : [],
                Schema::hasColumn('timetable_grid_settings', 'curriculum_hemis_id') ? ['curriculum_hemis_id', 'weekly_breaks'] : []));

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
            // Kafedra mudiri: "Kafedraniki" filtri uchun o'z kafedrasi nomlari.
            'my_kafedras' => $this->departmentHeadKafedraSafe(request()),
            // Fan mas'uli: "Mas'ul fanlarim" filtri uchun shu doskadagi mas'ul fanlari kartalari.
            'my_subject_card_ids' => $this->responsibleCardIds(request(), $cards),
            // Guruh(cha) sarlavhasida faol talabalar soni (sikl jadvalidagi kabi): "fakultet|guruh" => son.
            'group_students' => $this->boardGroupStudentCounts($cards),
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

        $totalFor = fn ($c): int => $this->cardGrid($board, $c)['weeks'];
        // Bo'lakli fan: umumiy ma'ruza asosiy nom bilan ("Fan"), amaliylar bo'lak nomi bilan ("Fan (a)") —
        // qo'shimcha amaliy ma'ruzasiz haftalarga tushishi uchun ular bitta fan kaliti bilan bog'lanadi.
        $subjectKey = fn($c) => ($c->faculty_name ?? '') . '|' . $this->cardCurriculumId($c) . '|' . $this->specKey($c->specialty_name) . '|' . (int) $c->course
            . '|' . $this->normSubject($this->cycleSubjectVariantBase((string) $c->subject_name) ?? (string) $c->subject_name);

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
            ->get(['id', 'faculty_name', 'specialty_name', 'course', 'subject_name', 'training_type', 'weeks', 'group_name', 'group_names']);
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
     * Haftalik (1-3 kurs) fanlar: qaysi kartochka qaysi haftada o'tilishini KTR'dan belgilaydi.
     * KTR'da har hafta uchun ma'ruza va amaliy (amaliy+laboratoriya+seminar) soatlari bor;
     * shu haftaning soati dars birligi (fan × guruhcha / ma'ruzada oqim) kartochkalaridan
     * yig'iladi, qolgan kartochkalar o'sha haftada "o'tilmaydi" (override cancelled).
     * Ko'chirilgan hafta (day/pair) yozuvlari faol hafta bo'lsa saqlanadi.
     */
    public function ktrWeeks(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'dry_run'           => 'nullable|boolean',
        ]);
        if (!Schema::hasTable('timetable_card_overrides')) {
            return response()->json(['error' => 'Haftalar jadvali (timetable_card_overrides) yo\'q.'], 422);
        }

        return response()->json($this->syncCardWeeksFromKtr($board, $data, $request->boolean('dry_run')));
    }

    /**
     * Haftaning KTR soatiga teng bo'ladigan kartochkalar to'plami: soatdan oshmaydigan eng katta
     * yig'indi (aniq teng afzal), ichida — kamroq karta, so'ng asosiy (ko'p haftali) kartalar.
     * $cards shu ustuvorlik tartibida saralangan. Kartalar soni cheklanmaydi: yig'indi bo'yicha
     * dinamik dasturlash (soat × karta). [card_id => true]
     */
    private function ktrPickCards(array $cards, int $need): array
    {
        if ($need <= 0 || !$cards) {
            return [];
        }
        $cards = array_values($cards);
        // $best[yig'indi] = tanlangan indekslar (o'sish tartibida). Oxirgi kartadan boshlab
        // yig'iladi: PHP massivlarni avval soni, so'ng elementlari bo'yicha solishtiradi —
        // kamroq karta, teng bo'lsa oldingi (asosiy) kartasi bor to'plam "kichik" (yaxshi).
        $best = [0 => []];
        for ($i = count($cards) - 1; $i >= 0; $i--) {
            $len = $cards[$i]->lenHalf();
            $next = $best;
            foreach ($best as $sum => $set) {
                $total = $sum + $len;
                if ($total > $need) {
                    continue;
                }
                $candidate = array_merge([$i], $set);
                if (!isset($next[$total]) || $candidate < $next[$total]) {
                    $next[$total] = $candidate;
                }
            }
            $best = $next;
        }
        unset($best[0]);
        if (!$best) {
            return [];
        }
        $out = [];
        foreach ($best[max(array_keys($best))] as $i) {
            $out[$cards[$i]->id] = true;
        }

        return $out;
    }

    private function syncCardWeeksFromKtr(TimetableBoard $board, array $scope = [], bool $dryRun = false): array
    {
        // Qamrov boshqa amallar (bo'shatish) bilan bir xil: yuborilmagan — cheklovsiz,
        // bo'sh ro'yxat — hech narsa, '' — fakultetsiz kartalar.
        [$facSet, $specSet, $courseSet] = $this->scopeSets($scope);

        // Sikl fanlari bu yerda emas (ularning kunlari sikl kalendarida).
        $cycleSubjects = [];
        foreach ($this->subjectSettingsFor($board) as $st) {
            if (($st['mode'] ?? '') === 'cycle') {
                $cycleSubjects[$this->specKey($st['specialty_name']) . '|' . (int) $st['course'] . '|' . $this->normSubject((string) $st['subject_name'])] = true;
            }
        }
        $isCycle = function (TimetableCard $c) use ($cycleSubjects): bool {
            $k = $this->specKey($c->specialty_name) . '|' . (int) $c->course . '|';
            $base = $this->cycleSubjectVariantBase((string) $c->subject_name);

            return isset($cycleSubjects[$k . $this->normSubject((string) $c->subject_name)])
                || ($base !== null && isset($cycleSubjects[$k . $this->normSubject($base)]));
        };

        $totalFor = fn ($c): int => $this->cardGrid($board, $c)['weeks'];

        $ktr = $this->cycleKtrDayPlans($board);
        $ktrFor = function (TimetableCard $c) use ($ktr): ?array {
            $fac = trim((string) $c->faculty_name);
            $find = function (string $subject) use ($ktr, $c, $fac): ?array {
                $key = $this->cycleKtrKey((string) $c->specialty_name, (int) $c->course, $subject);

                $id = $this->cardCurriculumId($c);
                return $id ? ($ktr['curriculum:' . $id . '|' . $key] ?? null)
                    : (($fac !== '' ? ($ktr[$this->specKey($fac) . '|' . $key] ?? null) : null) ?? $ktr[$key] ?? null);
            };
            $plan = $find((string) $c->subject_name);
            // Bo'lakli fanning umumiy ma'ruzasi asosiy nom bilan — KTR esa bo'lak nomlari bilan kiritilgan.
            if ($plan === null && $c->training_type === 'lecture' && $this->variantLetter((string) $c->subject_name) === null) {
                foreach (['a', 'b', 'c', 'а', 'б', 'с'] as $l) {
                    if ($plan = $find($c->subject_name . ' (' . $l . ')')) {
                        break;
                    }
                }
            }

            return $plan;
        };

        $cardQuery = TimetableCard::where('board_id', $board->id);
        $this->applyScopeToQuery($cardQuery, $facSet, $specSet, $courseSet);
        $cards = $cardQuery->get()->reject($isCycle);

        // Dars birligi: ma'ruza — oqim (+til), amaliy — guruhcha.
        $units = [];
        foreach ($cards as $c) {
            $who = $c->training_type === 'lecture'
                ? 'L|' . mb_strtolower((string) $c->oqim_label) . '|' . $c->lang . '|' . implode(',', (array) $c->group_names)
                : 'P|' . mb_strtolower(trim((string) $c->group_name));
            $units[implode('|', [mb_strtolower((string) $c->faculty_name), $this->specKey($c->specialty_name), (int) $c->course,
                $this->normSubject((string) $c->subject_name), $who])][] = $c;
        }

        $existing = DB::table('timetable_card_overrides')->whereIn('card_id', $cards->pluck('id'))->get()
            ->groupBy('card_id');
        $now = now();
        $insert = [];
        $cancelIds = [];
        $deleteIds = [];
        $weekCounts = [];
        $noKtr = [];
        $mismatch = [];
        $synced = 0;

        foreach ($units as $unitCards) {
            $first = $unitCards[0];
            $plan = $ktrFor($first);
            if ($plan === null) {
                $noKtr[$first->specialty_name . ' ' . $first->course . '-kurs · ' . $first->subject_name] = true;
                continue;
            }
            $total = $totalFor($first);
            $typeKey = $first->training_type === 'lecture' ? 'lec' : 'prc';
            // Asosiy (ko'p haftali, uzun) kartochka birinchi tanlanadi.
            usort($unitCards, fn ($a, $b) => [(int) $b->weeks, $b->lenHalf(), $a->id] <=> [(int) $a->weeks, $a->lenHalf(), $b->id]);
            $active = [];
            for ($w = 1; $w <= $total; $w++) {
                $need = (int) ($plan[$w - 1][$typeKey] ?? 0);
                $chosen = $this->ktrPickCards($unitCards, $need);
                $need -= array_sum(array_map(fn ($c) => isset($chosen[$c->id]) ? $c->lenHalf() : 0, $unitCards));
                if ($need > 0) {
                    $mismatch[] = $first->subject_name . ' · ' . ($first->group_name ?: $first->oqim_label) . ' · ' . $w . '-hafta: KTR '
                        . (int) ($plan[$w - 1][$typeKey] ?? 0) . ' soat, kartochkalar yetmaydi';
                }
                foreach ($unitCards as $c) {
                    $active[$c->id][$w] = isset($chosen[$c->id]);
                }
            }
            foreach ($unitCards as $c) {
                $rows = ($existing[$c->id] ?? collect())->keyBy('week');
                $count = 0;
                for ($w = 1; $w <= $total; $w++) {
                    $row = $rows[$w] ?? null;
                    if ($active[$c->id][$w]) {
                        $count++;
                        if ($row && $row->cancelled) {
                            $deleteIds[] = $row->id;
                        }
                    } elseif (!$row) {
                        $insert[] = ['card_id' => $c->id, 'week' => $w, 'day' => null, 'pair' => null, 'cancelled' => true,
                            'created_at' => $now, 'updated_at' => $now];
                    } elseif (!$row->cancelled) {
                        $cancelIds[] = $row->id;
                    }
                }
                $weekCounts[$c->id] = $count;
                $synced++;
            }
        }

        $result = [
            'ok' => true,
            'dry_run' => $dryRun,
            'cards' => $synced,
            'units' => count($units),
            'weeks_on' => count($deleteIds),
            'weeks_off' => count($insert) + count($cancelIds),
            'no_ktr' => array_keys($noKtr),
            'mismatch' => array_slice($mismatch, 0, 50),
            'mismatch_count' => count($mismatch),
        ];
        if ($dryRun) {
            return $result;
        }

        DB::transaction(function () use ($insert, $cancelIds, $deleteIds, $weekCounts, $now) {
            foreach (array_chunk($deleteIds, 1000) as $chunk) {
                DB::table('timetable_card_overrides')->whereIn('id', $chunk)->delete();
            }
            foreach (array_chunk($cancelIds, 1000) as $chunk) {
                DB::table('timetable_card_overrides')->whereIn('id', $chunk)
                    ->update(['cancelled' => true, 'day' => null, 'pair' => null, 'updated_at' => $now]);
            }
            foreach (array_chunk($insert, 1000) as $chunk) {
                DB::table('timetable_card_overrides')->insert($chunk);
            }
            foreach ($weekCounts as $id => $count) {
                DB::table('timetable_cards')->where('id', $id)->update(['weeks' => max(0, $count)]);
            }
        });

        return $result;
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
        // Dars 1 yoki 2 soatlik (yarim yoki bitta para) bo'ladi: toq soat alohida 1 soatlik karta —
        // 3 soatlik bitta karta qilinmaydi, uni kerak bo'lsa keyingi paraga alohida surish mumkin.
        if ($hours === 1) {
            $out[] = 1;
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
            'layer'             => 'nullable|in:external,internal',
            'mine'              => 'nullable|boolean',
            'kafedra'           => 'nullable|boolean',
            'subjects'          => 'nullable|boolean',
        ]);
        $cycleView = $data['view'] ?? 'flow';
        // "Faqat meniki": o'qituvchi faqat o'zi biriktirilgan fan bloklarini ko'radi.
        $mineTeacherId = !empty($data['mine']) ? $this->timetableTeacherId($request) : null;
        // "Kafedraniki" (kafedra mudiri): faqat o'z kafedrasi fanlari bloklari.
        $scopeKafedra = null;
        if ($mineTeacherId === null && !empty($data['kafedra']) && $this->timetableActiveRole($request) === 'kafedra_mudiri') {
            $scopeKafedra = $this->departmentHeadKafedraSafe($request) ?: null;
        }
        // "Mas'ul fanlarim" (fan mas'uli): faqat o'zi mas'ul bo'lgan fanlar bloklari.
        $scopeSubjects = null;
        if ($mineTeacherId === null && $scopeKafedra === null && !empty($data['subjects'])) {
            $scopeSubjects = $this->responsibleSubjectKeys($request);
        }
        $scoped = $mineTeacherId !== null || $scopeKafedra !== null || $scopeSubjects !== null;
        $inMyScope = fn ($c) => $mineTeacherId !== null
            ? (int) $c->teacher_id === $mineTeacherId
            : ($scopeSubjects !== null
                ? $this->responsibleMatch($scopeSubjects, $c)
                : ($scopeKafedra !== null && $this->sameKafedra($c->kafedra_name, $scopeKafedra)));
        $mineFlowSubjects = [];   // flowKey => [subject => [training_type => true]]
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
        // Tashqi (o'quv bo'limi, tavsiyaviy) / ichki (kafedra, KTR bo'yicha) jadval.
        // Tashqi: fanga bitta blok, ma'ruza/amaliy ajratilmaydi (sodda ko'rinish).
        // Ichki: ma'ruza/amaliy alohida; kafedra mudiri uchun belgilash rejimi.
        $cycleLayer = $data['layer'] ?? $this->cycleDefaultLayer($cycleRole);
        if ($cycleLayer === 'external') {
            $cycleSimple = true;
            $cycleMark = false;
        } else {
            // Ichki jadval: fanga bitta blok, ichida kun × soat to'ri KTR bo'yicha
            // (ma'ruza/amaliy soatlari har kun uchun). Barcha rollar uchun bir xil
            // ko'rinish; tahrirlash (kunni surish) — faqat ichki qatlam egasi.
            $cycleSimple = true;
            $cycleMark = true;
        }
        // Ichki jadval har guruhcha (d1/d22-01a, -01b ...) uchun alohida qator: o'qituvchi
        // va xona guruhchaga biriktiriladi. Joylashuv (sana) esa oqimnikidan olinadi.
        $cycleSubRows = $cycleLayer === 'internal';
        if ($cycleSubRows) {
            $cycleView = 'group';
        }
        $cycleCanEdit = $this->cycleLayerEditable($cycleRole, $cycleLayer);
        if ($request->boolean('clear') && !$cycleCanEdit) {
            return $this->cycleLayerDenied($cycleLayer);
        }
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

        // Semestr sanasi va bayramlarni sozlamaga saqlaymiz (keyingi safar eslab qolish uchun).
        // Faqat sikl jadvalini tahrirlay oladigan rollar: o'qituvchi (faqat ko'rish) va
        // boshqalar butun doska kalendarini o'zgartirib yubormasin.
        $canSaveCalendar = $this->cycleLayerEditable($cycleRole, 'external') || $this->cycleLayerEditable($cycleRole, 'internal');
        if ($canSaveCalendar
            && (($set['semester_start'] ?? null) !== $startC->toDateString() || ($set['holidays'] ?? []) !== $holidays)) {
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
        $targetWorkDays = $this->cycleCalendarWorkDays($board, $startC, $holSet);
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
        $variantFirst = [];   // norm(spec|kurs|asosiy nom) => birinchi bo'lak nomi (soatlar uchun)
        if (!empty($cycleKey)) {
            foreach (TimetableCard::where('board_id', $board->id)->get() as $c) {
                if ($c->training_type !== 'practice' || !$c->group_name || !$inScope($c)
                    || ($scopeSubjects !== null && !$inMyScope($c))) {
                    continue;
                }
                $ck = $normKey($c->specialty_name, $c->course, $c->subject_name);
                if (!isset($cycleKey[$ck])) {
                    continue;
                }
                // Bo'lakli fan ("Fan (a)/(b)/(c)" — HEMIS'da guruhcha hajmi uchun sun'iy bo'lingan):
                // tashqi jadvalda asosiy nomi bilan bitta fan; ichki jadvalda har bo'lak alohida
                // qator (u — haqiqiy o'quv guruhchasi: o'qituvchi/xona shunga biriktiriladi).
                $variantBase = $this->cycleSubjectVariantBase((string) $c->subject_name);
                $rowSubject = ($variantBase !== null && !$cycleSubRows) ? $variantBase : (string) $c->subject_name;
                if ($variantBase !== null && !$cycleSubRows) {
                    $variantFirst[$normKey($c->specialty_name, $c->course, $variantBase)] ??= (string) $c->subject_name;
                }
                $cardMembers = $this->cycleCardGroups($c);
                // Ichki jadval: bo'lakning har bir o'quv guruhi (karta guruhlari to'plami) alohida
                // qator — "Fan (a)" 02a va 03a da alohida o'qitilsa, ikkita qator (o'qituvchilari
                // har xil); 02a + 03a dan aralash bitta guruh bo'lsa — bitta qator.
                $unitMembers = $cardMembers;
                sort($unitMembers, SORT_NATURAL | SORT_FLAG_CASE);
                $rowTargets = ($variantBase !== null && $cycleSubRows && $cardMembers)
                    ? [[$this->cycleFlowName($c, $cardMembers[0]) . ' · ' . $c->subject_name . ' · ' . implode(' + ', $unitMembers), $cardMembers[0], true]]
                    : array_map(fn ($m) => [null, $m, false], $cardMembers);
                foreach ($rowTargets as [$variantRow, $member, $isVariantRow]) {
                    // Guruh rejimida har subguruh o'z qatori; oqim rejimida oqim nomi.
                    $flow = $variantRow ?? ($cycleView === 'group' ? $member : $this->cycleFlowName($c, $member));
                    $flowKey = mb_strtolower(trim((string) $c->specialty_name)) . '|' . (int) $c->course . '|' . mb_strtolower($flow);
                    if (!isset($byFlow[$flowKey])) {
                        $byFlow[$flowKey] = ['name' => $flow, 'subs' => [], 'members' => [], 'req' => [],
                            // Guruhcha qatori uchun: joylashuv saqlangan oqim nomi.
                            'parent' => $this->cycleFlowName($c, $member),
                            'variant' => $isVariantRow ? (string) $c->subject_name : null,
                            // Bo'lak guruhi API'ga o'z a'zolari to'plami bilan murojaat qiladi ("02a" yoki
                            // "02a + 02b"): o'qituvchi/xona va kun soatlari (starts_g) faqat shu guruhga —
                            // oqim nomi bo'lsa, shu oqimdagi bo'lakning boshqa guruhlariga ham tegardi.
                            'unit' => $isVariantRow ? implode(' + ', $unitMembers) : null,
                            'faculty' => $c->faculty_name, 'specialty' => $c->specialty_name, 'course' => (int) $c->course];
                    }
                    // Bo'laklar bitta blok: uzunligi eng uzun bo'lakniki (bo'laklar to'qnashuvda
                    // o'z kunlari bilan hisoblanadi — blok ularning hammasini qoplasin).
                    $byFlow[$flowKey]['subs'][$rowSubject] = max($byFlow[$flowKey]['subs'][$rowSubject] ?? 0, $cycleKey[$ck]);
                    if ($variantBase !== null && !$cycleSubRows) {
                        // Shu qatorda o'qitiladigan bo'laklar: karta faqat hammasi joylansa "joylangan".
                        $byFlow[$flowKey]['vsubs'][$rowSubject][mb_strtolower(trim((string) $c->subject_name))] = true;
                    }
                    if ($isVariantRow) {
                        foreach ($cardMembers as $vm) {
                            $byFlow[$flowKey]['members'][$vm] = true;
                        }
                    } else {
                        $byFlow[$flowKey]['members'][$member] = true;
                    }

                    // Rekvizitlar kartalardan: Biriktirish tabi bilan bitta manba.
                    $reqKey = $rowSubject . '|practice';
                    if (!isset($byFlow[$flowKey]['req'][$reqKey])) {
                        $byFlow[$flowKey]['req'][$reqKey] = ['teachers' => [], 'auds' => []];
                    }
                    if ($c->teacher_name) {
                        $byFlow[$flowKey]['req'][$reqKey]['teachers'][$c->teacher_name] = true;
                    }
                    if ($c->auditorium_name || $c->auditorium_code) {
                        $byFlow[$flowKey]['req'][$reqKey]['auds'][$c->auditorium_name ?: $c->auditorium_code] = true;
                    }
                    if ($scoped && $inMyScope($c)) {
                        $mineFlowSubjects[$flowKey][$rowSubject][(string) $c->training_type] = true;
                    }
                }
            }
        }

        // Ma'ruza kartalarining rekvizitlari (o'qituvchi/xona) ham blokda ko'rinsin.
        if (!empty($cycleKey)) {
            foreach (TimetableCard::where('board_id', $board->id)->where('training_type', 'lecture')->get() as $c) {
                if (!$inScope($c) || ($scopeSubjects !== null && !$inMyScope($c))) {
                    continue;
                }
                $ck = $normKey($c->specialty_name, $c->course, $c->subject_name);
                if (!isset($cycleKey[$ck])) {
                    continue;
                }
                $lecVariantBase = $this->cycleSubjectVariantBase((string) $c->subject_name);
                $lecSubject = ($lecVariantBase !== null && !$cycleSubRows) ? $lecVariantBase : (string) $c->subject_name;
                $lecMembers = $this->cycleCardGroups($c);
                $lectureRows = ($lecVariantBase !== null && $cycleSubRows && $lecMembers)
                    // Bo'lak ma'ruzasi: shu oqimdagi shu bo'lakning barcha guruh qatorlari.
                    ? array_values(array_map(fn ($g) => (string) $g['name'], array_filter($byFlow, fn ($g) =>
                        ($g['variant'] ?? null) === (string) $c->subject_name
                        && mb_strtolower((string) $g['parent']) === mb_strtolower($this->cycleFlowName($c, $lecMembers[0]))
                        && mb_strtolower((string) $g['specialty']) === mb_strtolower((string) $c->specialty_name)
                        && (int) $g['course'] === (int) $c->course)))
                    : ($cycleView === 'group' ? $lecMembers : [$this->cycleFlowName($c)]);
                foreach ($lectureRows as $flow) {
                    $flowKey = mb_strtolower(trim((string) $c->specialty_name)) . '|' . (int) $c->course . '|' . mb_strtolower($flow);
                    if (!isset($byFlow[$flowKey])) {
                        continue;
                    }
                    $reqKey = $lecSubject . '|lecture';
                    if (!isset($byFlow[$flowKey]['req'][$reqKey])) {
                        $byFlow[$flowKey]['req'][$reqKey] = ['teachers' => [], 'auds' => []];
                    }
                    if ($scoped && $inMyScope($c)) {
                        $mineFlowSubjects[$flowKey][$lecSubject][(string) $c->training_type] = true;
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

        // "Faqat meniki": o'qituvchi dars bermaydigan fanlar va oqimlar olib tashlanadi.
        if ($scoped) {
            foreach ($byFlow as $flowKey => $g) {
                $keep = $mineFlowSubjects[$flowKey] ?? [];
                $byFlow[$flowKey]['subs'] = array_intersect_key($g['subs'], $keep);
                // Batafsil ko'rinishda mashg'ulot turi ham filtrlanadi (mas. faqat ma'ruza o'qituvchisi).
                $byFlow[$flowKey]['mine'] = $keep;
                if (!$byFlow[$flowKey]['subs']) {
                    unset($byFlow[$flowKey]);
                }
            }
        }

        // Bo'lak qatorlari talabalari — barcha bo'lak fanlari uchun bitta so'rov.
        $variantStudentRows = $cycleSubRows
            ? $this->variantStudentRows(array_map(fn ($g) => $g['variant'] ?? null, $byFlow), (int) substr((string) $board->academic_year, 0, 4))
            : [];

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
        $hoursFor = fn(string $spec, int $course, string $subject, ?string $faculty = null): ?array =>
            $this->cycleHoursLookup($cycleHours, $faculty, $spec, $course, $subject)
            // Bo'lakli fanning asosiy nomi: soatlar birinchi bo'lakdan (har bo'lak to'liq soat o'qiydi).
            ?? (isset($variantFirst[$normKey($spec, $course, $subject)])
                ? $this->cycleHoursLookup($cycleHours, $faculty, $spec, $course, $variantFirst[$normKey($spec, $course, $subject)])
                : null);
        $fmtHours = fn(?float $v) => $v === null ? null : (fmod($v, 1.0) == 0.0 ? (int) $v : round($v, 1));
        $rowHours = $this->cycleRowHours($board);
        // Ichki jadval to'ri butun kunni qamraydi: qo'ng'iroq jadvalidagi paralar × para soati
        // (to'liq parada 1 para = 2 akademik soat → 6 para = 12 qator, 08:30–17:50).
        $bellPairCount = collect((array) ($board->bell_schedule ?? []))
            ->filter(fn($it) => (is_array($it) ? ($it['type'] ?? '') : ($it->type ?? '')) === 'pair')->count();
        $cycleGridHours = max(6, ($bellPairCount ?: max(1, (int) $board->pairs_per_day)) * $rowHours);
        $rowKey = fn($g) => (string) $g['specialty'] . '|' . (int) $g['course'] . '|' . (string) $g['name'];
        // Ichki jadval: KTR'dagi kunlik ma'ruza/amaliy soatlari (sikl fanida KTR "hafta" = sikl kuni).
        $ktrDayPlans = $cycleMark ? $this->cycleKtrDayPlans($board) : [];
        $subjectKey = fn($g, $subject) => $rowKey($g) . '|' . (string) $subject;
        // Sikl joylashuvlari alohida saqlanadi: oddiy place endpointi haftalik
        // kartalarning day/pair maydonlarini buzmasligi kerak.
        $hasCyclePlacements = Schema::hasTable('timetable_cycle_placements');

        // "Bo'shatish": qamrovdagi oqimlarning sikl bloklari panelga qaytariladi.
        // Bloklar faqat qo'lda (cyclePlace) joylanadi — avtomatik joylash yo'q.
        // "Faqat meniki" ko'rinishida bo'shatish yo'q — qamrov to'liq emas.
        if ($request->boolean('clear') && !$scoped) {
            $scopeRows = array_map(fn ($g) => [
                'specialty' => $g['specialty'],
                'course'    => $g['course'],
                'group'     => $g['name'],
            ], $groups);
            // Kafedra mudiri faqat o'z kafedrasi fanlarini bo'shatadi.
            $ownFor = fn ($row) => $this->ownKafedraCycleSubjects($request, $board, (string) $row['specialty'], (int) $row['course']);

            if ($hasCyclePlacements) {
                DB::transaction(function () use ($scopeRows, $board, $ownFor) {
                    foreach ($scopeRows as $row) {
                        $own = $ownFor($row);
                        $q = TimetableCyclePlacement::where('board_id', $board->id)
                            ->where('specialty_name', $row['specialty'])
                            ->where('course', $row['course'])
                            ->where('group_name', $row['group']);
                        if ($own !== null) {
                            $q->whereIn('subject_name', array_values($own) ?: ['__none__']);
                        }
                        $q->delete();
                    }
                });
            } else {
                // Eski deploylarda migration keyinroq ishlashi mumkin. Jadval paydo
                // bo'lguncha joylashuvlar doska settings JSONida saqlanadi.
                $fallback = collect($set['cycle_placements'] ?? []);
                foreach ($scopeRows as $row) {
                    $own = $ownFor($row);
                    $fallback = $fallback->reject(fn($item) => ($item['specialty_name'] ?? '') === $row['specialty']
                        && (int) ($item['course'] ?? 0) === (int) $row['course']
                        && ($item['group_name'] ?? '') === $row['group']
                        && ($own === null || isset($own[mb_strtolower(trim((string) ($item['subject_name'] ?? '')))])))->values();
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
            // Ichki jadvalda guruhcha qatori oqimning joylashuvidan foydalanadi. Guruhchaning
            // o'z joylashuvi bo'lsa (tashqi jadval "Guruh bo'yicha" ko'rinishida joylangan) —
            // fan+tur bo'yicha u ustuvor; aks holda bunday bloklar ichki jadvalda yo'qolardi.
            $placeGroup = $cycleSubRows ? (string) ($g['parent'] ?? $g['name']) : (string) $g['name'];
            $placeKey = (string) $g['specialty'] . '|' . (int) $g['course'] . '|' . $placeGroup;
            $rowPlacements = [];
            $placementGroupOf = [];   // fan|tur => joylashuv saqlangan qator nomi
            $variantPlaced = [];      // asosiy nom => [bo'lak => true] (amaliy yozuvlar)
            // Bo'lak joylashuvi sozlama (o'quv reja) nomi bilan yoziladi — karta nomidan harf
            // registri/bo'shliq bilan farq qilishi mumkin, shuning uchun normallashgan moslash.
            $subsByNorm = [];
            foreach (array_keys($g['subs']) as $subsName) {
                $subsByNorm[mb_strtolower(trim((string) $subsName))] = $subsName;
            }
            foreach ([$placeKey => $placeGroup, $rk => (string) $g['name']] as $savedKey => $savedGroup) {
                foreach ($savedByRow[$savedKey] ?? [] as $placement) {
                    // Asosiy nomga yig'ishdan oldin faqat mas'ul bo'lak joylashuvlarini qoldiramiz.
                    if ($scopeSubjects !== null && !$this->responsibleMatch($scopeSubjects, [
                        'faculty_name' => $g['faculty'], 'specialty_name' => $g['specialty'],
                        'course' => $g['course'], 'subject_name' => $placement->subject_name,
                    ])) {
                        continue;
                    }
                    // Tashqi jadval: bo'lak joylashuvi asosiy nomli bitta blok bo'lib ko'rinadi.
                    $placementBase = !$cycleSubRows ? $this->cycleSubjectVariantBase((string) $placement->subject_name) : null;
                    $baseSubject = $placementBase !== null ? ($subsByNorm[mb_strtolower($placementBase)] ?? null) : null;
                    if ($baseSubject !== null) {
                        if (($placement->training_type ?: 'practice') === 'practice') {
                            $variantPlaced[$baseSubject][mb_strtolower(trim((string) $placement->subject_name))] = true;
                        }
                        $placement = clone $placement;
                        $placement->subject_name = $baseSubject;
                    }
                    $typeKey = $placement->subject_name . '|' . ($placement->training_type ?: 'practice');
                    $rowPlacements[$typeKey] = $placement;
                    $placementGroupOf[$typeKey] = $savedGroup;
                }
            }
            $savedBlocks = collect(array_values($rowPlacements))->sortBy('start_index');
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
                // "Faqat meniki": batafsil ko'rinishda faqat o'ziga biriktirilgan turdagi blok
                // (sodda ko'rinishda amaliy blok butun fanni bildiradi — fan bo'yicha yetarli).
                if (!$cycleSimple && isset($g['mine']) && !isset($g['mine'][$placement->subject_name][$blockType])) {
                    continue;
                }
                $hrs = $hoursFor((string) $g['specialty'], (int) $g['course'], (string) $placement->subject_name, $g['faculty'] ?? null);
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
                // Sodda rejimda bitta qator bor: tashqi jadvalda amaliy yozuv 2-parada
                // saqlansa ham, blok 1-qatorda ko'rinsin (aks holda klient uni tashlab ketadi).
                $blockPair = $cycleSimple ? 1 : max(1, (int) ($placement->pair ?: 1));
                // Kunlik para egallashi (6 soat modeli): amaliy blok ma'ruzali
                // davrda 2, keyin 3 para; ma'ruza 1 para. Klient shu maydonlar
                // bilan 2-3-paradagi davom segmentlarini chizadi.
                $span = $cycleSimple
                    ? ['head_span' => 1, 'tail_span' => 0, 'head_days' => max(1, (int) ($g['subs'][$placement->subject_name] ?? 1))]
                    : $this->cycleLaneSpan($blockType, $hrs, max(1, (int) ($g['subs'][$placement->subject_name] ?? 1)), $rowHours);
                $headDays = min((int) $span['head_days'], (int) $days);
                $headTo = $headDays > 0 ? $endIndexFor($from, $headDays) : null;
                $tailFrom = $headDays >= $days ? null : ($headDays > 0 ? $endIndexFor($from, $headDays + 1) : $from);
                // Tashqi jadval tavsiyaviy: o'qituvchi va xona ko'rsatilmaydi (ular ichki jadvalda).
                $req = $cycleLayer === 'external'
                    ? ['teachers' => [], 'auds' => []]
                    : ($g['req'][$placement->subject_name . '|' . $blockType] ?? ['teachers' => [], 'auds' => []]);
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
                        : (float) ($cycleSimple
                            ? ($hrs['lecture'] + $hrs['practice'])
                            : ($blockType === 'lecture' ? $hrs['lecture'] : $hrs['practice']))),
                    'span_head' => (int) $span['head_span'],
                    'span_tail' => (int) $span['tail_span'],
                    'tail_offset' => (int) ($span['tail_offset'] ?? 0),
                    'head_days' => $headDays,
                    'head_to' => $headTo,
                    'tail_from' => $tailFrom,
                    'teacher_name' => $teacherNames
                        ? ($teacherNames[0] . (count($teacherNames) > 1 ? ' +' . (count($teacherNames) - 1) : ''))
                        : null,
                    'lesson_time' => $placement->lesson_time ?? null,
                    'lecture_slots' => $hasLectureSlots ? (array) ($placement->lecture_slots ?? []) : [],
                    'lecture_hours' => $hrs === null ? null : $fmtHours((float) $hrs['lecture']),
                    'practice_hours' => $hrs === null ? null : $fmtHours((float) $hrs['practice']),
                    'day_plan' => $cycleMark
                        ? $this->cycleDayPlan(
                            $ktrDayPlans[($g['faculty'] ?? '') !== '' ? $this->specKey((string) $g['faculty']) . '|' . $this->cycleKtrKey((string) $g['specialty'], (int) $g['course'], (string) $placement->subject_name) : '']
                                ?? $ktrDayPlans[$this->cycleKtrKey((string) $g['specialty'], (int) $g['course'], (string) $placement->subject_name)]
                                // Bo'lakli fan KTR'i asosiy nom bilan kiritilgan bo'lishi mumkin.
                                ?? (($ktrBase = $this->cycleSubjectVariantBase((string) $placement->subject_name)) !== null
                                    ? ($ktrDayPlans[($g['faculty'] ?? '') !== '' ? $this->specKey((string) $g['faculty']) . '|' . $this->cycleKtrKey((string) $g['specialty'], (int) $g['course'], $ktrBase) : '']
                                        ?? $ktrDayPlans[$this->cycleKtrKey((string) $g['specialty'], (int) $g['course'], $ktrBase)] ?? null)
                                    : null),
                            $hrs, (int) $days, $from, $workIndices,
                            // Guruhcha qiymatlari oqimnikiga kun bo'yicha ustma-ust qo'yiladi
                            // (bitta kun surilsa, qolgan kunlar oqimdan meros qoladi).
                            $hasLectureSlots ? array_replace(
                                (array) (($placement->lecture_slots ?? [])['starts'] ?? []),
                                $cycleSubRows ? (array) ((($placement->lecture_slots ?? [])['starts_g'] ?? [])[(string) ($g['unit'] ?? $g['name'])] ?? []) : []
                            ) : [],
                            6,
                            $cycleGridHours
                        )
                        : null,
                    'auditorium_code' => null,
                    'auditorium_name' => $audNames
                        ? ($audNames[0] . (count($audNames) > 1 ? ' +' . (count($audNames) - 1) : ''))
                        : null,
                ];
            }
            $placedSubjects = collect($blocks)->keyBy(fn ($b) => $b['subject'] . '|' . $b['type']);
            foreach ($g['subs'] as $subject => $days) {
                $hrs = $hoursFor((string) $g['specialty'], (int) $g['course'], (string) $subject, $g['faculty'] ?? null);
                // Sodda rejimda fanga bitta karta (ma'ruza+amaliy birga).
                $cardTypes = $cycleSimple ? ['practice'] : ['lecture', 'practice'];
                foreach ($cardTypes as $cardType) {
                    if (!$cycleSimple && isset($g['mine']) && !isset($g['mine'][$subject][$cardType])) {
                        continue;
                    }
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
                        // Bo'lak qatori API'ga oqim nomi bilan murojaat qiladi (joylashuv oqimda).
                        'group' => !empty($g['variant']) ? (string) ($g['unit'] ?? $g['parent']) : $g['name'],
                        'placement_group' => $placementGroupOf[$subject . '|' . $cardType] ?? $placeGroup,
                        'specialty' => $g['specialty'],
                        'course' => $g['course'],
                        'subject' => $subject,
                        'type' => $cardType,
                        'days' => max(1, $typeDays),
                        'hours' => $fmtHours($hrs === null ? null : (float) ($cycleSimple
                            ? ($hrs['lecture'] + $hrs['practice'])
                            : ($cardType === 'lecture' ? $hrs['lecture'] : $hrs['practice']))),
                        // Bo'lakli fan: blok bor, lekin ba'zi bo'laklar joylanmagan bo'lsa — karta
                        // "joylanmagan" bo'lib qoladi; uni qayta qo'yish qolgan bo'laklarni ham joylaydi.
                        'placed' => (bool) $block && !array_diff_key($g['vsubs'][$subject] ?? [], $variantPlaced[$subject] ?? []),
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
                // Ichki jadval: bo'lakli fan qatori ("Fan (c)": 02a dan 5 + 02b dan 5 talaba).
                'variant' => $g['variant'] ?? null,
                'flow' => !empty($g['variant']) ? (string) $g['parent'] : null,
                // Bo'lak qatori: shu bo'lakka HEMIS'da biriktirilgan talabalar (chap ustunda ro'yxat).
                'students' => !empty($g['variant'])
                    ? $this->variantStudentList($variantStudentRows, (string) $g['variant'], $members, $g['faculty'] ?? null)
                    : null,
                'subgroups' => $members,
                // Oddiy (bo'lak bo'lmagan) qator: har guruhchadagi faol talabalar soni.
                'member_students' => empty($g['variant'])
                    ? (function () use ($members, $g) {
                        $out = [];
                        foreach ($members as $m) {
                            foreach (preg_split('/\s+\+\s+/u', (string) $m) as $part) {
                                $out[$part] = $this->activeStudentCountFor($part, $g['faculty'] ?? null);
                            }
                        }
                        return $out;
                    })()
                    : null,
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
            'layer'      => $cycleLayer,
            'view'       => $cycleView,
            'sub_rows'   => $cycleSubRows,
            'can_edit'   => $cycleCanEdit,
            'mark_mode'  => $cycleMark,
            'day_edit'   => $cycleMark && $cycleCanEdit,
            'mark_ready' => $hasLectureSlots,
            'day_hours'  => $cycleMark ? $cycleGridHours : 6,
            'day_load'   => 6,
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
            'placement_group' => 'nullable|string|max:255',
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
                ->where(function ($w) use ($kafedra) {
                    $w->where('department', 'like', '%' . trim($kafedra) . '%');
                    // Shu kafedradagi o'rindosh/soatbay o'qituvchilar ham
                    if (Schema::hasTable('teacher_employments')) {
                        $w->orWhereIn('id', $this->employmentTeacherIdsByDepartmentName($kafedra));
                    }
                })
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
        $busyRooms = $this->cycleOverlappingRoomCodes($board, $placement, $data)
            + $this->cycleSiblingRoomCodes($board, $placement, $data, $flowCards);

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
            // Guruhcha qatorida "A + B" kabi umumiy karta: biriktirish boshqa guruhchalarga ham tegadi.
            'shared_with' => ($data['view'] ?? 'flow') === 'group'
                ? $flowCards->flatMap(fn ($card) => $this->cycleCardGroups($card))
                    ->reject(fn ($member) => mb_strtolower(trim($member)) === mb_strtolower(trim($data['group_name'])))
                    ->unique()->values()
                : [],
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
            'placement_group' => 'nullable|string|max:255',
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
        // Kafedra mudiri faqat o'z kafedrasi fanlariga o'qituvchi/xona biriktiradi.
        if ($denied = $this->denyForeignKafedra($request, $flowCards)) {
            return $denied;
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
            $busyRooms = $this->cycleOverlappingRoomCodes($board, $placement, $data)
                + $this->cycleSiblingRoomCodes($board, $placement, $data, $flowCards);
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

    /**
     * Ichki jadval: guruhcha oqim joylashuvini meros qilsa, shu joylashuvni bo'lishadigan
     * boshqa guruhcha kartalari band qilgan xonalar [auditorium_code => yorliq]. Ular bir
     * sanalarda bo'ladi, lekin cycleOverlappingRoomCodes joylashuvning o'zini hisobga olmaydi.
     */
    private function cycleSiblingRoomCodes(TimetableBoard $board, TimetableCyclePlacement $placement, array $data, $flowCards): array
    {
        if (mb_strtolower(trim((string) $placement->group_name)) === mb_strtolower(trim((string) $data['group_name']))) {
            return [];
        }
        $ownIds = $flowCards->pluck('id')->all();
        $busy = [];
        $siblings = $this->cycleFlowCards($board, $data['specialty_name'], (int) $data['course'], $data['subject_name'],
            (string) $placement->group_name, $data['training_type'] ?? null, 'flow');
        foreach ($siblings as $card) {
            if (in_array($card->id, $ownIds, true) || !$card->auditorium_code) {
                continue;
            }
            $busy[$card->auditorium_code] ??= $card->subject_name . ' (' . implode(' + ', $this->cycleCardGroups($card)) . ')';
        }

        return $busy;
    }

    /** Joylashuvni identifikatsiya bo'yicha topadi. */
    private function findCyclePlacement(TimetableBoard $board, array $data): ?TimetableCyclePlacement
    {
        // Ichki jadvalda guruhcha qatori oqim joylashuvidan foydalanadi (placement_group).
        $placementGroup = trim((string) ($data['placement_group'] ?? '')) ?: $data['group_name'];
        $query = fn () => TimetableCyclePlacement::where('board_id', $board->id)
            ->where('specialty_name', $data['specialty_name'])
            ->where('course', (int) $data['course'])
            ->where('group_name', $placementGroup)
            ->when(!empty($data['training_type']), fn ($q) => $q
                ->where(fn ($w) => $w->where('training_type', $data['training_type'])->orWhereNull('training_type')));

        // Tashqi jadvalda bo'lakli fan asosiy nomi bilan keladi — bo'laklardan birining joylashuvi.
        return $query()->where('subject_name', $data['subject_name'])->first()
            ?? $query()->where('subject_name', 'like', addcslashes(trim((string) $data['subject_name']), '%_\\') . ' (_)')
                ->orderBy('subject_name')->first();
    }

    /**
     * Tasdiq so'rovi uchun: o'zgarish tegishli bo'lgan kartalar (guruhlar, o'qituvchilar —
     * xabar yuborish uchun). TimetableChangeApproval chaqiradi.
     */
    public function changeRequestCards(TimetableBoard $board, string $route, array $p)
    {
        $spec = (string) ($p['specialty_name'] ?? '');
        $course = (int) ($p['course'] ?? 0);
        $subject = (string) ($p['subject_name'] ?? '');
        if ($spec === '' || !$course || $subject === '') {
            return collect();
        }
        $type = $p['training_type'] ?? null;
        if (str_starts_with($route, 'admin.timetable.cycle-')) {
            $flow = (string) ($p['sub_group'] ?? '') ?: (string) ($p['group_name'] ?? '');
            $cards = $this->cycleFlowCards($board, $spec, $course, $subject, $flow, $type, $p['view'] ?? 'flow');
            if ($cards->isEmpty() && !empty($p['placement_group'])) {
                $cards = $this->cycleFlowCards($board, $spec, $course, $subject, (string) $p['placement_group'], $type, 'flow');
            }

            return $cards->values();
        }

        $subjectLower = mb_strtolower(trim($subject));
        $group = mb_strtolower(trim((string) ($p['group_name'] ?? '')));
        $oqim = mb_strtolower(trim((string) ($p['oqim_label'] ?? '')));
        $faculty = mb_strtolower(trim((string) ($p['faculty_name'] ?? '')));

        return TimetableCard::where('board_id', $board->id)
            ->where('specialty_name', $spec)->where('course', $course)
            ->when($type, fn ($q) => $q->where('training_type', $type))
            ->get()
            ->filter(function (TimetableCard $c) use ($subjectLower, $group, $oqim, $faculty) {
                if (mb_strtolower(trim((string) $c->subject_name)) !== $subjectLower) {
                    return false;
                }
                if ($faculty !== '' && $c->faculty_name && mb_strtolower(trim((string) $c->faculty_name)) !== $faculty) {
                    return false;
                }
                if ($group !== '') {
                    return mb_strtolower(trim((string) $c->group_name)) === $group;
                }

                return $oqim === '' || mb_strtolower(trim((string) $c->oqim_label)) === $oqim;
            })->values();
    }

    /** Kartaning guruhlari (amaliy — o'z guruhi, ma'ruza — oqim guruhlari). */
    public function changeRequestCardGroups(TimetableCard $card): array
    {
        return $this->cycleCardGroups($card);
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
                if (mb_strtolower(trim((string) $card->subject_name)) !== $subjectLower
                    // Asosiy nom bo'lakli fanning barcha bo'laklarini qamraydi.
                    && mb_strtolower((string) $this->cycleSubjectVariantBase((string) $card->subject_name)) !== $subjectLower) {
                    return false;
                }

                if ($view === 'group') {
                    $members = $this->cycleCardGroups($card);
                    foreach ($members as $member) {
                        if (mb_strtolower(trim($member)) === $flowLower) {
                            return true;
                        }
                    }
                    // Ichki jadvalda bo'lakli fan ("Fan (a)") guruhi a'zolari to'plami bilan keladi
                    // ("02a + 02b", cyclePlan dagi 'unit' bilan bir xil tartibda).
                    sort($members, SORT_NATURAL | SORT_FLAG_CASE);

                    return mb_strtolower(implode(' + ', $members)) === $flowLower;
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

    /** Kartochka qatori uchun KTR haftalik rejasi (syncCardWeeksFromKtr bilan bir xil qidiruv). */
    private function ktrPlanForRow(array $ktr, ?string $faculty, string $specialty, int $course, string $subject, int $curriculum = 0): ?array
    {
        $fac = trim((string) $faculty);
        $key = $this->cycleKtrKey($specialty, $course, $subject);
        $plan = $curriculum > 0 ? ($ktr['curriculum:' . $curriculum . '|' . $key] ?? null)
            : (($fac !== '' ? ($ktr[$this->specKey($fac) . '|' . $key] ?? null) : null) ?? $ktr[$key] ?? null);

        return is_array($plan) && $plan ? $plan : null;
    }

    /**
     * KTR'ning har hafta amaliy soatidan kartochkalar: [len_half, necha hafta]. Har "qatlam" 2 soatdan
     * (amaliy karta 2 soatdan oshmaydi): 1-qatlam — amaliy bor haftalar, 2-qatlam — 3+ soatli haftalar va h.k.;
     * toq soat — 1 soatlik karta. Masalan 4,4,4,2 soat → [2 soat × 4 hafta] + [2 soat × 3 hafta] = 14 soat.
     * Qaysi karta qaysi haftada o'tilishini keyin "KTR bo'yicha haftalar" belgilaydi.
     */
    private function ktrPracticeCardPlan(array $plan, int $weeks): ?array
    {
        $h = [];
        foreach (array_slice($plan, 0, max(1, $weeks)) as $d) {
            $h[] = max(0, (int) ($d['prc'] ?? 0));
        }
        $max = $h ? max($h) : 0;
        if ($max <= 0) {
            return null;
        }
        $out = [];
        for ($j = 1; 2 * $j - 1 <= $max; $j++) {
            $n2 = count(array_filter($h, fn ($x) => $x >= 2 * $j));
            $n1 = count(array_filter($h, fn ($x) => $x === 2 * $j - 1));
            if ($n2 > 0) {
                $out[] = [2, $n2];
            }
            if ($n1 > 0) {
                $out[] = [1, $n1];
            }
        }

        return $out ?: null;
    }

    /** KTR kunlik rejasi kaliti (fakultetsiz): specKey(sof yo'nalish)|kurs|normFan. */
    private function cycleKtrKey(string $specialty, int $course, string $subject): string
    {
        return $this->specKey($specialty) . '|' . $course . '|' . $this->normSubject($subject);
    }

    /**
     * KTR (ktr_plans) dan sikl fanlari uchun kunlik soatlar:
     * [kalit => [['lec' => soat, 'prc' => soat, 'tl' => ma'ruza mavzusi, 'tp' => amaliy mavzusi], ...]].
     * Kalit — cycleKtrKey; 1-son/2-son kabi fakultetli rejalar uchun "specKey(fakultet)|" bilan ham.
     * KTR "hafta" qatorlari sikl fanida sikl kunlarini bildiradi (1-qator = 1-kun).
     */

    /**
     * The weekly board length is a minimum viewport, not a cycle placement limit.
     * Include longer program calendars and the complete final cycle block.
     */
    private function cycleCalendarWorkDays(TimetableBoard $board, Carbon $start, array $holidays, ?int $requestedIndex = null, int $requestedDays = 0): int
    {
        $days = max(1, min(6, (int) $board->days));
        $weeks = max(1, (int) $board->weeks);
        if (Schema::hasTable('timetable_grid_settings')) {
            $weeks = max($weeks, (int) TimetableGridSetting::where('board_id', $board->id)->max('weeks'));
        }
        // Support two calendar years, including cross-year semesters, with bounded work.
        abort_if($requestedIndex !== null && ($requestedIndex < 0 || $requestedIndex > 730), 422,
            'Sikl boshlanishi kalendar boshidan 730 kundan oshmasligi kerak.');
        $target = min(104, $weeks) * $days;
        $settings = Schema::hasTable('timetable_subject_settings')
            ? TimetableSubjectSetting::where('board_id', $board->id)->where('mode', 'cycle')->get()
                ->keyBy(fn ($s) => mb_strtolower(trim($s->specialty_name)).'|'.(int) $s->course.'|'.mb_strtolower(trim($s->subject_name)))
            : collect();
        $placements = Schema::hasTable('timetable_cycle_placements')
            ? TimetableCyclePlacement::where('board_id', $board->id)->get()
            : collect(($board->settings ?? [])['cycle_placements'] ?? []);
        $spans = [];
        foreach ($placements as $p) {
            $key = mb_strtolower(trim((string) data_get($p, 'specialty_name'))).'|'.(int) data_get($p, 'course').'|'.mb_strtolower(trim((string) data_get($p, 'subject_name')));
            $setting = $settings->get($key);
            $index = (int) data_get($p, 'start_index', -1);
            if ($setting && $index >= 0 && $index <= 730) {
                $spans[] = [$index, max(1, min(120, (int) $setting->cycle_days))];
            }
        }
        if ($requestedIndex !== null) $spans[] = [$requestedIndex, max(1, min(120, $requestedDays))];
        $maxIndex = $spans ? max(array_column($spans, 0)) : 0;
        $teachingBefore = [0];
        $date = $start->copy();
        for ($i = 0; $i < $maxIndex; $i++, $date->addDay()) {
            $teachingBefore[$i + 1] = $teachingBefore[$i]
                + (int) ($date->dayOfWeekIso <= $days && !isset($holidays[$date->toDateString()]));
        }
        foreach ($spans as [$index, $length]) {
            // Keep one further teaching week available for placing the next block.
            $target = max($target, $teachingBefore[$index] + $length + $days);
        }
        return $target;
    }

    private function cycleKtrDayPlans(TimetableBoard $board): array
    {
        static $cache = [];
        if (isset($cache[$board->id])) {
            return $cache[$board->id];
        }
        if (!Schema::hasTable('ktr_plans')) {
            return $cache[$board->id] = [];
        }
        $start = (int) substr((string) $board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;
        $rows = DB::table('ktr_plans as kp')
            ->join('curriculum_subjects as cs', 'cs.id', '=', 'kp.curriculum_subject_id')
            ->join('semesters as s', function ($j) {
                $j->on('s.curriculum_hemis_id', '=', 'cs.curricula_hemis_id')->on('s.code', '=', 'cs.semester_code');
            })
            ->join('curricula as c', 'c.curricula_hemis_id', '=', 'cs.curricula_hemis_id')
            ->join('specialties as sp', 'sp.specialty_hemis_id', '=', 'c.specialty_hemis_id')
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->where('s.education_year', (string) $start)
            ->whereRaw('MOD(CAST(cs.semester_code AS UNSIGNED) - 10, 2) = ?', [$parityRem])
            ->orderByDesc('kp.updated_at')
            ->get(['cs.curricula_hemis_id', 'kp.plan_data', 'kp.week_count', 'cs.subject_name', 'cs.subject_details',
                'sp.name as specialty_name', 'f.name as faculty_name', 's.level_code']);

        $typeOf = function (string $name): ?string {
            $name = mb_strtolower($name);
            foreach (['mustaqil', 'amaliyot', 'kurs ishi', 'самостоят', 'практика', 'курсов'] as $skip) {
                if (str_contains($name, $skip)) return null;
            }
            if (preg_match("/ma['’ʻ`]?ruza|лекц/u", $name)) return 'lec';
            return 'prc'; // amaliy, klinik amaliy, laboratoriya, seminar — auditoriya amaliy soati
        };

        $map = [];
        $aliasFallback = [];
        foreach ($rows as $r) {
            $plan = is_string($r->plan_data) ? json_decode($r->plan_data, true) : $r->plan_data;
            if (!is_array($plan)) continue;
            $hours = $plan['hours'] ?? $plan;
            $topics = $plan['topics'] ?? [];
            $details = is_string($r->subject_details) ? json_decode($r->subject_details, true) : $r->subject_details;
            $codeType = [];
            foreach ((array) $details as $d) {
                if (!is_array($d)) continue;
                $code = (string) ($d['trainingType']['code'] ?? '');
                $t = $typeOf((string) ($d['trainingType']['name'] ?? ''));
                if ($code !== '' && $t !== null) $codeType[$code] = $t;
            }
            if (!$codeType) continue;
            $weeks = max(0, (int) $r->week_count);
            $days = [];
            for ($w = 1; $w <= $weeks; $w++) {
                $day = ['lec' => 0, 'prc' => 0, 'tl' => '', 'tp' => ''];
                foreach ((array) ($hours[$w] ?? $hours[(string) $w] ?? []) as $code => $h) {
                    $t = $codeType[(string) $code] ?? null;
                    if ($t === null) continue;
                    $day[$t] += (int) $h;
                    $topic = trim((string) ($topics[$w][$code] ?? $topics[(string) $w][(string) $code] ?? ''));
                    if ($topic !== '' && (int) $h > 0) {
                        $tk = $t === 'lec' ? 'tl' : 'tp';
                        $day[$tk] = $day[$tk] === '' ? $topic : $day[$tk] . '; ' . $topic;
                    }
                }
                $days[] = $day;
            }
            if (!$days) continue;

            // Yo'nalish nomi hemisSubjectRows bilan bir xil normallashtiriladi.
            $spec = trim((string) preg_replace('/\(.*?\)/u', '', (string) $r->specialty_name));
            if (preg_match('/(\d+)\s*-\s*son/u', (string) $r->faculty_name, $m) && preg_match('/^davolash\s+ishi$/iu', $spec)) {
                $spec = $m[1] . '-son Davolash ishi';
            }
            [$facAlias, $specAlias] = $this->curriculumScopeAlias($spec);
            $level = (int) $r->level_code;
            $course = $level >= 11 ? $level - 10 : $level;
            $key = $this->cycleKtrKey($specAlias, $course, (string) $r->subject_name);
            $map['curriculum:' . $r->curricula_hemis_id . '|' . $key] ??= $days;
            // Eng so'nggi tahrirlangan KTR ustuvor (orderByDesc updated_at). Alias rejalarining
            // fakultetsiz kaliti faqat zaxira (cycleSubjectHoursMap bilan bir xil): sof
            // "Davolash ishi" rejasi (mas. Xalqaro ta'lim) 1-son/2-son KTR'ini olmasin.
            if ($facAlias === null) {
                $map[$key] ??= $days;
            } else {
                $map[$this->specKey($facAlias) . '|' . $key] ??= $days;
                $aliasFallback[$key] ??= $days;
            }
        }

        return $cache[$board->id] = $map + $aliasFallback;
    }

    /**
     * Ichki jadval uchun blokning kunlik rejasi: kalendar siljishi (blok boshidan) =>
     * ['k' => sikl kuni (1..), 'lec' => soat, 'prc' => soat, 'start' => boshlanish soati,
     *  'tl' / 'tp' => mavzular, 'ktr' => KTR'dan olinganmi].
     * KTR bo'lmasa: ma'ruzali kunlar 2+4, keyin 6 soat amaliy (reja soatidan).
     * $starts — kafedra surgan kunlar: [sikl kuni indeksi (0..) => boshlanish soati].
     */
    private function cycleDayPlan(?array $ktrDays, ?array $hrs, int $days, int $from, array $workIndices, array $starts, int $dayHours, ?int $gridHours = null): array
    {
        // $dayHours — sikl kunining o'quv yuklamasi (6 soat, KTR bo'lmasa taxmin uchun);
        // $gridHours — kun to'ridagi qatorlar soni (butun kun, masalan 6 para × 2 = 12):
        // darsni shu oraliqda surish mumkin.
        $gridHours = max($dayHours, (int) ($gridHours ?? $dayHours));
        $pos = array_search($from, $workIndices, true);
        if ($pos === false) {
            return [];
        }
        $lecLeft = (int) round((float) ($hrs['lecture'] ?? 0));
        $prcLeft = (int) round((float) ($hrs['practice'] ?? 0));
        $out = [];
        for ($k = 0; $k < $days; $k++) {
            $ci = $workIndices[$pos + $k] ?? null;
            if ($ci === null) break;
            if ($ktrDays !== null) {
                $d = $ktrDays[$k] ?? ['lec' => 0, 'prc' => 0, 'tl' => '', 'tp' => ''];
                $lec = (int) $d['lec'];
                $prc = (int) $d['prc'];
            } else {
                $lec = min(2, max(0, $lecLeft));
                $prc = min($dayHours - $lec, max(0, $prcLeft));
                $lecLeft -= $lec;
                $prcLeft -= $prc;
                $d = ['tl' => '', 'tp' => ''];
            }
            $total = $lec + $prc;
            $start = (int) ($starts[$k] ?? $starts[(string) $k] ?? 1);
            $start = max(1, min($start, max(1, $gridHours - $total + 1)));
            $out[$ci - $from] = [
                'k' => $k + 1, 'lec' => $lec, 'prc' => $prc, 'start' => $start,
                'tl' => (string) ($d['tl'] ?? ''), 'tp' => (string) ($d['tp'] ?? ''),
                'ktr' => $ktrDays !== null,
            ];
        }

        return $out;
    }

    /** Sikl soatlari: avval fakultet bo'yicha, topilmasa yo'nalish bo'yicha. */
    private function cycleHoursLookup(array $map, ?string $faculty, string $spec, int $course, string $subject): ?array
    {
        $key = $this->specKey($spec) . '|' . $course . '|' . $this->normSubject($subject);
        if ($faculty !== null && $faculty !== '' && isset($map[$this->specKey($faculty) . '|' . $key])) {
            return $map[$this->specKey($faculty) . '|' . $key];
        }
        return $map[$key] ?? null;
    }

    /** Sikl qatori nomidan ("1-son davolash / 1-oqim / rus") fakultetni ajratadi. */
    private function cycleRowFaculty(string $groupName, $cards = null): ?string
    {
        if (str_contains($groupName, ' / ')) {
            return trim(explode(' / ', $groupName)[0]);
        }
        if ($cards) {
            $card = collect($cards)->first(fn($c) => in_array($groupName, $this->cycleCardRowNames($c), true));
            if ($card) {
                return (string) $card->faculty_name;
            }
        }
        return null;
    }

    /**
     * Karta qaysi sikl qatorlarida ko'rinadi: guruh rejimida subguruh ("A + B"
     * bo'laklari ham), oqim rejimida oqim nomi (oqim_label'siz — asosiy guruh).
     */
    private function cycleCardRowNames(TimetableCard $card): array
    {
        $names = [];
        foreach ($this->cycleCardGroups($card) as $member) {
            $names[] = $member;
            $names[] = $this->cycleFlowName($card, $member);
        }
        return array_values(array_unique($names));
    }

    /**
     * Sikl fanlarining reja soatlari (ishchi rejadan): ma'ruza va amaliy
     * (amaliy = amaliy + laboratoriya + seminar). Ma'lumotlar tabidagi
     * "Fanlar" ro'yxati bilan bitta manba va bitta hisob.
     */
    private function cycleSubjectHoursMap(TimetableBoard $board): array
    {
        $start = (int) substr($board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;

        $rows = ($this->isHemisBoard($board) ? $this->hemisSubjectRows($board) : DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + (CASE WHEN CAST(mc.level_code AS UNSIGNED) >= 11 THEN CAST(mc.level_code AS UNSIGNED) - 10 ELSE CAST(mc.level_code AS UNSIGNED) END) - 1) = ?", [$start])
            ->groupBy('mc.specialty_name', 'mc.level_code', 's.subject_name')
            ->selectRaw('mc.specialty_name, mc.level_code, s.subject_name,
                MAX(s.lecture) as lecture, MAX(s.practice) as practice,
                MAX(s.laboratory) as laboratory, MAX(s.seminar) as seminar')
            ->get());

        $map = [];
        // Alias rejalarining fakultetsiz kaliti — faqat zaxira: sof "Davolash ishi"
        // rejasi (mas. Xalqaro ta'lim) o'z soatini 1-son/2-son soatlari bilan
        // max qilinmasdan olishi kerak.
        $aliasFallback = [];
        $put = function (array &$target, string $key, float $lecture, float $practice) {
            if (!isset($target[$key])) {
                $target[$key] = ['lecture' => $lecture, 'practice' => $practice];
            } else {
                $target[$key]['lecture'] = max($target[$key]['lecture'], $lecture);
                $target[$key]['practice'] = max($target[$key]['practice'], $practice);
            }
        };
        foreach ($rows as $r) {
            $course = (int) $r->level_code >= 11 ? (int) $r->level_code - 10 : (int) $r->level_code;
            $lecture = (float) $r->lecture;
            $practice = (float) $r->practice + (float) $r->laboratory + (float) $r->seminar;
            // "1-son Davolash ishi" kabi reja nomlari kartochkada fakultet + sof
            // yo'nalish bo'lib turadi — kalitni alias bo'yicha quramiz. Fakultetli
            // kalit ham saqlanadi: 1-son, 2-son va boshqa fakultetlarda bir xil
            // nomli fanning soatlari farq qilsa, aynan o'z fakulteti olinadi.
            [$facAlias, $specAlias] = $this->curriculumScopeAlias((string) $r->specialty_name);
            $key = $this->specKey($specAlias) . '|' . $course . '|' . $this->normSubject((string) $r->subject_name);
            if ($facAlias === null) {
                $put($map, $key, $lecture, $practice);
            } else {
                $put($map, $this->specKey($facAlias) . '|' . $key, $lecture, $practice);
                $put($aliasFallback, $key, $lecture, $practice);
            }
        }

        return $map + $aliasFallback;
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
     * Blok kunlik nechta para (lane) egallashini beradi. Sikl kuni 6 soat:
     *  - ma'ruza kuniga 2 soat (1 para, ma'ruza soati / 2 kun);
     *  - amaliy ma'ruzali kunlarda 4 soat (2 para, ma'ruzadan keyingi paralarda),
     *    ma'ruza tugagach 6 soat (3 para) — kunning boshidan.
     *    Masalan Pediatriya (6 s ma'ruza, 24 s amaliy, 5 kun): 1–3-kunlar 2+4,
     *    4–5-kunlar 6 soat amaliy; 3×4 + 2×6 = 24.
     * head_days — blok boshidan nechta O'QUV kuni head rejimida ekani;
     * tail_offset — dum bo'lagi blok parasiga nisbatan nechta para yuqoridan
     * boshlanishi (amaliy uchun ma'ruza egallagan paralar soni, manfiy).
     * Reja soatlari topilmasa eski bir-lane xulqi saqlanadi.
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
        $lectureHours = (float) ($hours['lecture'] ?? 0);
        if ($lectureHours <= 0) {
            // Ma'ruzasiz fan: amaliy butun sikl kuniga 6 soat.
            return ['head_span' => (int) ceil(6 / $rowHours), 'tail_span' => 0, 'head_days' => $cycleDays, 'tail_offset' => 0];
        }
        $lectureDays = min($cycleDays, (int) ceil($lectureHours / 2));
        return [
            'head_span' => (int) ceil(4 / $rowHours),
            'tail_span' => $lectureDays < $cycleDays ? (int) ceil(6 / $rowHours) : 0,
            'head_days' => $lectureDays,
            'tail_offset' => -(int) ceil(2 / $rowHours),
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

    /**
     * Berilgan blok sanalari bilan kesishadigan BOSHQA sikl bloklari band
     * qilgan xonalar: [auditorium_code => fan nomi]. Har blokning xonasi o'z
     * oqim kartalaridan olinadi.
     */
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
        $maxDays = $this->cycleCalendarWorkDays($board, $startC, $holSet);
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
        // Guruh rejimida (yoki oqim_label'siz eski kartalarda) qator nomi oddiy
        // subguruh — fakultet nomda yo'q, shuning uchun kartalardan olinadi.
        $facultyByRow = [];
        foreach (TimetableCard::where('board_id', $board->id)->where('training_type', 'practice')->get() as $card) {
            if (!$card->faculty_name) {
                continue;
            }
            $base = mb_strtolower(trim((string) $card->specialty_name)) . '|' . (int) $card->course . '|';
            foreach ($this->cycleCardRowNames($card) as $rowName) {
                $facultyByRow[$base . mb_strtolower(trim($rowName))] ??= (string) $card->faculty_name;
            }
        }
        $daysOf = function ($item) use ($cycleDays, $cycleHours, $facultyByRow): int {
            $settingDays = (int) ($cycleDays[mb_strtolower(trim($item->specialty_name)) . '|' . (int) $item->course . '|' . mb_strtolower(trim($item->subject_name))] ?? 1);
            $faculty = $this->cycleRowFaculty((string) ($item->group_name ?? ''))
                ?? ($facultyByRow[mb_strtolower(trim((string) $item->specialty_name)) . '|' . (int) $item->course . '|'
                    . mb_strtolower(trim((string) $item->group_name))] ?? null);
            $hours = $this->cycleHoursLookup($cycleHours, $faculty,
                (string) $item->specialty_name, (int) $item->course, (string) $item->subject_name);
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
            'placement_group' => 'nullable|string|max:255',
            'subject_name'   => 'required|string|max:255',
            'training_type'  => 'nullable|in:lecture,practice',
            'view'           => 'nullable|in:flow,group',
            // FormData ichma-ich obyektni yubora olmaydi, shuning uchun
            // belgilar JSON satr bo'lib keladi.
            'slots_json'     => 'nullable|string|max:20000',
            // Ichki jadval: bitta sikl kunining darslari boshlanadigan soat (1..12).
            'day_index'      => 'nullable|integer|min:0|max:200',
            'start_hour'     => 'nullable|integer|min:1|max:24',
            // Ichki jadval: guruhcha (d1/d22-01a ...) — har biri o'z vaqtiga ega bo'lishi mumkin.
            'sub_group'      => 'nullable|string|max:255',
        ]);

        // Ma'ruza kataklari — ichki jadval: faqat kafedra mudiri (o'z kafedrasi fani) yoki admin.
        $slotRole = $this->timetableActiveRole($request);
        if (!$this->cycleLayerEditable($slotRole, 'internal')) {
            return $this->cycleLayerDenied('internal');
        }
        $ownSubjects = $this->ownKafedraCycleSubjects($request, $board, (string) $data['specialty_name'], (int) $data['course']);
        if ($ownSubjects !== null && !isset($ownSubjects[mb_strtolower(trim((string) $data['subject_name']))])) {
            return response()->json(['error' => 'Bu fan sizning kafedrangizga tegishli emas.'], 403);
        }

        $placement = $this->findCyclePlacement($board, $data);
        if (!$placement) {
            return response()->json(['error' => 'Sikl bloki topilmadi.'], 404);
        }

        if (array_key_exists('day_index', $data) && $data['day_index'] !== null) {
            // Yangi format: {"starts": {kunIndeksi: boshlanishSoati}} — eski belgilar bilan aralashmaydi.
            $current = (array) ($placement->lecture_slots ?? []);
            $sub = trim((string) ($data['sub_group'] ?? ''));
            $byGroup = (array) ($current['starts_g'] ?? []);
            $starts = $sub !== '' ? (array) ($byGroup[$sub] ?? []) : (array) ($current['starts'] ?? []);
            $k = (string) (int) $data['day_index'];
            $hour = (int) ($data['start_hour'] ?? 1);
            // Meros qilinadigan qiymat: oqimda — 1-soat, guruhchada — oqimning shu kundagi soati.
            // Teng bo'lsa yozuv o'chiriladi (meros), aks holda aniq saqlanadi — guruhcha oqim
            // 3-soatdan boshlagan kunni 1-soatga qaytarsa ham, bu qiymat saqlanib qoladi.
            $parentStarts = (array) ($current['starts'] ?? []);
            $inherited = $sub !== '' ? max(1, (int) ($parentStarts[$k] ?? 1)) : 1;
            if ($hour === $inherited) {
                unset($starts[$k]);
            } else {
                $starts[$k] = $hour;
            }
            if ($sub !== '') {
                if ($starts) {
                    $byGroup[$sub] = $starts;
                } else {
                    unset($byGroup[$sub]);
                }
                $current['starts_g'] = $byGroup;
            } else {
                $current['starts'] = $starts;
            }
            $placement->update(['lecture_slots' => [
                'starts' => (array) ($current['starts'] ?? []),
                'starts_g' => (array) ($current['starts_g'] ?? []),
            ]]);

            return response()->json(['ok' => true, 'starts' => $starts]);
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
        // Tashqi jadvalda bo'lakli fan ("Fan (a)/(b)/(c)") bitta karta bo'lib, asosiy nomi
        // bilan keladi: shu oqimdagi barcha bo'laklar bitta tranzaksiyada joylanadi/ko'chadi.
        $variants = $this->cycleRequestVariants($request, $board);
        if (!$variants) {
            return $this->cyclePlaceOne($request, $board);
        }
        $baseName = (string) $request->input('subject_name');
        $request->attributes->set('cycle_generated_variant', true);
        $last = null;
        $failed = null;
        try {
            DB::transaction(function () use ($variants, $request, $board, &$last, &$failed) {
                foreach ($variants as $variant) {
                    $request->merge(['subject_name' => $variant]);
                    $response = $this->cyclePlaceOne($request, $board);
                    $payload = $response->getData(true);
                    if ($response->getStatusCode() >= 400) {
                        if (($payload['code'] ?? null) === 'no_card') {
                            continue;   // bu bo'lak shu oqimda o'qitilmaydi
                        }
                        $failed = response()->json(array_merge($payload, [
                            'error' => $variant . ': ' . ($payload['error'] ?? $payload['message'] ?? 'xatolik'),
                        ]), $response->getStatusCode());
                        throw new \RuntimeException('cycle-variant-failed');
                    }
                    $last = $response;
                    // Siljitish kaskad: nishon bilan bir kunda boshlangan qardosh bo'laklar ham
                    // birga suriladi. Ularni qayta siljitish eski kunida topolmay xato beradi.
                    if ($request->input('action') === 'shift') {
                        break;
                    }
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'cycle-variant-failed') {
                throw $e;
            }
        } finally {
            $request->merge(['subject_name' => $baseName]);
            $request->attributes->remove('cycle_generated_variant');
        }
        if ($failed) {
            return $failed;
        }

        return $last ?? response()->json(['error' => 'Tanlangan oqimda bu fanning bo\'laklari topilmadi.'], 422);
    }

    /** Fan nomi "Asosiy nom (a)" ko'rinishida bo'lsa — asosiy nomi, aks holda null. */
    private function cycleSubjectVariantBase(string $subject): ?string
    {
        return preg_match('/^(.*\S)\s*\(\p{L}\)\s*$/u', trim($subject), $m) ? trim($m[1]) : null;
    }

    /** Sikl rejimidagi fanlar: "specKey|kurs|normSubject" => true. */
    private function cycleSubjectKeys(TimetableBoard $board): array
    {
        $keys = [];
        foreach ($this->subjectSettingsFor($board) as $st) {
            if (($st['mode'] ?? '') === 'cycle') {
                $keys[$this->specKey((string) $st['specialty_name']) . '|' . (int) $st['course'] . '|' . $this->normSubject((string) $st['subject_name'])] = true;
            }
        }

        return $keys;
    }

    /**
     * Bo'lakli fanning ma'ruzalari umumiy kartaga ("Fan") birlashtiriladimi. Sikl fani — yo'q: sikl
     * kalendari ma'ruzani bo'lak nomi bilan qidiradi (cycleRequestVariants → cycleFlowCards "Fan (a)").
     */
    private function mergesVariantLecture(array $cycleKeys, string $specialty, int $course, string $subject): bool
    {
        $base = $this->cycleSubjectVariantBase($subject);
        if ($base === null) {
            return false;
        }
        $k = $this->specKey($specialty) . '|' . $course . '|';

        return !isset($cycleKeys[$k . $this->normSubject($subject)]) && !isset($cycleKeys[$k . $this->normSubject($base)]);
    }

    /**
     * So'rovdagi fan nomi bo'lakli fanning asosiy nomi bo'lsa (o'zining sikl sozlamasi yo'q,
     * "Nom (a)", "Nom (b)"... sozlamalari bor) — shu bo'laklar nomlari; aks holda bo'sh.
     */
    private function cycleRequestVariants(Request $request, TimetableBoard $board): array
    {
        $subject = mb_strtolower(trim((string) $request->input('subject_name')));
        $specialty = mb_strtolower(trim((string) $request->input('specialty_name')));
        $course = (int) $request->input('course');
        if ($subject === '' || $specialty === '' || $course < 1 || !Schema::hasTable('timetable_subject_settings')) {
            return [];
        }
        $variants = [];
        foreach (TimetableSubjectSetting::where('board_id', $board->id)->where('course', $course)->where('mode', 'cycle')->get() as $s) {
            if (mb_strtolower(trim((string) $s->specialty_name)) !== $specialty) {
                continue;
            }
            $name = trim((string) $s->subject_name);
            if (mb_strtolower($name) === $subject) {
                return [];   // asosiy nomning o'z sozlamasi bor — oddiy fan
            }
            $base = $this->cycleSubjectVariantBase($name);
            if ($base !== null && mb_strtolower($base) === $subject) {
                $variants[] = $name;
            }
        }
        sort($variants, SORT_NATURAL);

        return $variants;
    }

    private function cyclePlaceOne(Request $request, TimetableBoard $board)
    {
        $hasCyclePlacements = Schema::hasTable('timetable_cycle_placements');

        $data = $request->validate([
            'specialty_name' => 'required|string|max:255',
            'course' => 'required|integer|min:1|max:7',
            'group_name' => 'required|string|max:255',
            'subject_name' => 'required|string|max:255',
            'start_index' => 'nullable|integer|min:0|max:730',
            'training_type' => 'required|in:lecture,practice',
            'pair' => 'nullable|integer|min:1|max:24',
            'view' => 'nullable|in:flow,group',
            'action' => 'required|in:place,remove,shift',
            'direction' => 'nullable|integer|in:-1,1',
            'start_date' => 'nullable|date',
            'holidays' => 'nullable|array',
            'holidays.*' => 'nullable|date',
            'allow_overlap' => 'nullable|boolean',
            'overlap_days' => 'nullable|integer|min:0|max:60',
            'ignore_gap' => 'nullable|boolean',
            'layer' => 'nullable|in:external,internal',
        ]);
        $placeRole = $this->timetableActiveRole($request);
        $placeLayer = $data['layer'] ?? $this->cycleDefaultLayer($placeRole);
        if (!$this->cycleLayerEditable($placeRole, $placeLayer)) {
            return $this->cycleLayerDenied($placeLayer);
        }
        // Tashqi jadvalda fan bitta blok: amaliy blok ko'chsa yoki olinsa,
        // ma'ruza yozuvi ham u bilan birga yuradi (ichki jadval shu asosda quriladi).
        $syncLecture = $placeLayer === 'external' && $data['training_type'] === 'practice';
        // Kafedra mudiri ichki jadvalda faqat o'z kafedrasi fanlarini o'zgartiradi.
        $ownCycleSubjects = $this->ownKafedraCycleSubjects($request, $board, (string) $data['specialty_name'], (int) $data['course']);
        if ($ownCycleSubjects !== null && !isset($ownCycleSubjects[mb_strtolower(trim((string) $data['subject_name']))])) {
            return response()->json(['error' => 'Bu fan sizning kafedrangizga tegishli emas.'], 403);
        }
        // Bir fanning turli oqimlardagi sikllari kesishishiga ruxsat (sig'masa):
        // ruxsat berilsa, kesishish overlap_days o'quv kunidan oshmasligi kerak.
        $allowedOverlapDays = !empty($data['allow_overlap']) ? (int) ($data['overlap_days'] ?? 0) : 0;

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
        $placeFaculty = $this->cycleRowFaculty(
            (string) $data['group_name'],
            TimetableCard::where('board_id', $board->id)->where('course', (int) $data['course'])
                ->where('specialty_name', $data['specialty_name'])
                ->get(['group_name', 'group_names', 'faculty_name', 'oqim_label', 'lang'])
        );
        $hoursFor = fn(string $spec, int $course, string $subject, ?string $faculty = null): ?array =>
            $this->cycleHoursLookup($cycleHours, $faculty ?? $placeFaculty, $spec, $course, $subject);
        $rowHours = $this->cycleRowHours($board);
        if ($syncLecture) {
            // Tashqi jadvalda amaliy blok ma'ruzadan keyingi paralarda turadi
            // (ma'ruza — 1-para), shunda ichki jadvalga o'tganda ular ustma-ust tushmaydi.
            $hasLectureDays = $this->cycleTypeDays(
                max(1, (int) $setting->cycle_days),
                $hoursFor((string) $data['specialty_name'], (int) $data['course'], (string) $data['subject_name']),
                'lecture'
            ) >= 1;
            $reqPair = $hasLectureDays ? 2 : 1;
        }
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
                . $data['group_name'] . '"' . ($known !== '' ? '; mavjud oqimlar: ' . $known : ''), 'code' => 'no_card'], 422);
        }

        // Nomni yashirincha trim/normallashtirib joylamaymiz. HEMIS fan nomidagi ortiqcha
        // bo'shliq TrimStrings'dan o'tgan so'rovda yo'qolsa, yozuv bazaga tushib, keyin qat'iy
        // nom bo'yicha jadvalda ko'rinmay qoladi. Farqni ko'rinadigan ␠ belgisi bilan qaytaramiz.
        $exactName = (string) $data['subject_name'];
        $actualNames = $cards->pluck('subject_name')->map(fn ($name) => (string) $name)->unique()->values();
        if ($request->attributes->get('cycle_generated_variant') && $actualNames->isNotEmpty()) {
            // Tashqi jadvaldagi asosiy fan uchun (a)/(b) nomlarini server sozlamadan hosil qiladi.
            // Bunday ichki nom karta bilan registr/bo'shliq bo'yicha farq qilsa, kartaning aynan
            // o'z nomini ishlatamiz; bu foydalanuvchi yuborgan nomni yashirincha tuzatish emas.
            $data['subject_name'] = $actualNames->first();
        } elseif (!$actualNames->contains(fn ($name) => $name === $exactName)) {
            $visible = fn (string $name): string => str_replace(["\t", " "], ['⇥', '␠'], $name);
            $expected = $actualNames->map(fn ($name) => '"' . $visible($name) . '"')->implode(', ');

            return response()->json([
                'error' => 'Fan nomi HEMIS/KTR kartasidagi nom bilan aynan mos emas. '
                    . 'Kartada: ' . $expected . '; so\'rovda: "' . $visible($exactName) . '". '
                    . 'HEMISdagi fan nomini to\'g\'rilab, KTRdan qayta sinxronlang.',
                'code' => 'subject_name_mismatch',
            ], 422);
        }

        $sample = $cards->first();
        $activeFlows = $scopeCards->filter(fn ($c) => $this->sameCycleCapacityPool($c, $sample))->flatMap($cardFlows)->filter()->flip()->all();

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
        // Blok egallaydigan paralar oralig'i [lo, hi]: amaliy dum bo'lagi ma'ruza
        // parasidan boshlanadi (tail_offset), shuning uchun pastki chegara ham hisoblanadi.
        $laneRange = function (int $pair, array $sp): array {
            $headHi = $pair + max(1, (int) $sp['head_span']) - 1;
            if ((int) $sp['tail_span'] <= 0) {
                return [$pair, $headHi];
            }
            // Klient bilan bir xil: dum bo'lagi 1-paradan yuqoriga chiqmaydi.
            $tailLo = max(1, $pair + (int) ($sp['tail_offset'] ?? 0));
            return [min($pair, $tailLo), max($headHi, $tailLo + (int) $sp['tail_span'] - 1)];
        };
        // Blokning kun × para bo'laklari: [[pozFrom, pozTo, paraLo, paraHi], ...] (o'quv kuni
        // pozitsiyalarida). Amaliy: bosh (ma'ruzali kunlar) va dum (ma'ruza tugagach).
        $laneSegments = function (int $startPos, int $days, int $pair, array $sp): array {
            $headDays = min($days, max(0, (int) ($sp['head_days'] ?? $days)));
            $headLanes = [$pair, $pair + max(1, (int) $sp['head_span']) - 1];
            if ((int) $sp['tail_span'] <= 0 || $headDays >= $days) {
                return [[$startPos, $startPos + $days - 1, ...$headLanes]];
            }
            $tailLo = max(1, $pair + (int) ($sp['tail_offset'] ?? 0));
            $segments = $headDays > 0 ? [[$startPos, $startPos + $headDays - 1, ...$headLanes]] : [];
            $segments[] = [$startPos + $headDays, $startPos + $days - 1, $tailLo, $tailLo + (int) $sp['tail_span'] - 1];
            return $segments;
        };
        $segmentsCollide = function (array $a, array $b): bool {
            foreach ($a as [$aFrom, $aTo, $aLo, $aHi]) {
                foreach ($b as [$bFrom, $bTo, $bLo, $bHi]) {
                    if ($aFrom <= $bTo && $bFrom <= $aTo && $aLo <= $bHi && $bLo <= $aHi) {
                        return true;
                    }
                }
            }
            return false;
        };
        $pairOverlapError = fn(string $subject) => response()->json(['error' => '"' . $subject
            . '" fanining ma\'ruza va amaliy bloklari bir kunning bir parasida ustma-ust tushadi. '
            . 'Ma\'ruzani amaliy bilan bir kunda boshlang yoki ikkalasini birga siljiting.'], 422);
        [$reqLaneFrom, $reqLaneTo] = $laneRange($reqPair, $reqSpan);
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
        $maxDays = $this->cycleCalendarWorkDays($board, $startC, $holSet,
            isset($data['start_index']) ? (int) $data['start_index'] : null, $requiredDays);
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
            $allCyclePlacements, $activeFlows, $data, $norm, $endIndexFor, $workIndices, $allowedOverlapDays
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
                    if ($allowedOverlapDays <= 0) {
                        return true;
                    }
                    // Kesishgan o'quv kunlari soni (bayram/yakshanbalar hisobga olinmaydi)
                    $lo = max($from, $otherFrom);
                    $hi = min($to, $otherTo);
                    $overlap = count(array_filter($workIndices, fn($i) => $i >= $lo && $i <= $hi));
                    if ($overlap > $allowedOverlapDays) {
                        return true;
                    }
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
                if ($newStart !== null && ($newStart < 0 || $newStart > 730)) return null;
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
                $rowPlacements = TimetableCyclePlacement::where('board_id', $board->id)
                    ->where('specialty_name', $data['specialty_name'])
                    ->where('course', (int) $data['course'])
                    ->where('group_name', $data['group_name'])
                    ->orderBy('start_index')->get();
                $placements = $rowPlacements
                    ->filter(function ($p) use ($data, $norm, $cycleSettings, $hoursFor, $laneRange, $reqLaneFrom, $reqLaneTo, $rowHours) {
                        $pSetting = $cycleSettings->get(
                            $norm($data['specialty_name']) . '|' . (int) $data['course'] . '|' . $norm($p->subject_name)
                        );
                        $span = $this->cycleLaneSpan(
                            (string) ($p->training_type ?: 'practice'),
                            $hoursFor((string) $data['specialty_name'], (int) $data['course'], (string) $p->subject_name),
                            max(1, (int) ($pSetting->cycle_days ?? 1)),
                            $rowHours
                        );
                        [$lo, $hi] = $laneRange(max(1, (int) ($p->pair ?? 1)), $span);
                        return $hi >= $reqLaneFrom && $lo <= $reqLaneTo;
                    })->values();
                $toShift = [];
                $targetFound = $placements->contains($isTarget);
                foreach ($placements as $placement) {
                    // Nishon va undan keyingi bloklar; nishon bilan bir kunda boshlanganlar ham
                    // (mas. bir fanning ma'ruza + amaliy juftligi) — ular ajralib qolmasin.
                    if (!$targetFound || (int) $placement->start_index < $currentStart) continue;
                    $newStart = $shiftedStart($placement);
                    if ($newStart === null) {
                        return response()->json(['error' => 'Sikl kartasini bu tomonga siljitib bo\'lmaydi.'], 422);
                    }
                    if ($placementConflicts($placement, $newStart)) {
                        return response()->json(['error' => 'Bu fan shu sanalarda boshqa oqimga joylangan.'], 422);
                    }
                    if ($ownCycleSubjects !== null
                        && !isset($ownCycleSubjects[mb_strtolower(trim((string) $placement->subject_name))])) {
                        return response()->json(['error' => 'Siljitish boshqa kafedraning "' . $placement->subject_name
                            . '" sikliga ham ta\'sir qiladi. Uni o\'quv bo\'limi yoki o\'sha kafedra siljitsin.'], 422);
                    }
                    $toShift[] = [$placement, $newStart];
                }
                if (!$targetFound) {
                    return response()->json(['error' => 'Siljitiladigan sikl kartasi topilmadi.'], 422);
                }
                // Siljigandan keyin bir fanning ma'ruza va amaliy bloklari ustma-ust tushmasin
                // (biri zanjirda, ikkinchisi joyida qolgan bo'lishi mumkin). Tashqi jadvalda
                // ma'ruza amaliy bilan birga ko'chadi — tekshirish shart emas.
                if (!$syncLecture) {
                    $newStarts = [];
                    foreach ($toShift as [$placement, $newStart]) {
                        $newStarts[$placement->id] = $newStart;
                    }
                    $segmentsOf = function ($p) use ($newStarts, $cycleSettings, $norm, $data, $hoursFor, $positionFor, $rowHours, $laneSegments): ?array {
                        $pSetting = $cycleSettings->get(
                            $norm($data['specialty_name']) . '|' . (int) $data['course'] . '|' . $norm($p->subject_name)
                        );
                        $cycleDays = max(1, (int) ($pSetting->cycle_days ?? 1));
                        $hrs = $hoursFor((string) $data['specialty_name'], (int) $data['course'], (string) $p->subject_name);
                        $type = (string) ($p->training_type ?: 'practice');
                        $startPos = $positionFor($newStarts[$p->id] ?? (int) $p->start_index);
                        if ($startPos === null) {
                            return null;
                        }
                        return $laneSegments($startPos, max(1, $this->cycleTypeDays($cycleDays, $hrs, $type)),
                            max(1, (int) ($p->pair ?? 1)), $this->cycleLaneSpan($type, $hrs, $cycleDays, $rowHours));
                    };
                    foreach ($toShift as [$placement]) {
                        foreach ($rowPlacements as $partner) {
                            if ($partner->id === $placement->id
                                || $norm($partner->subject_name) !== $norm($placement->subject_name)
                                || $norm($partner->training_type ?: 'practice') === $norm($placement->training_type ?: 'practice')) {
                                continue;
                            }
                            $a = $segmentsOf($placement);
                            $b = $segmentsOf($partner);
                            if ($a !== null && $b !== null && $segmentsCollide($a, $b)) {
                                return $pairOverlapError((string) $placement->subject_name);
                            }
                        }
                    }
                }
                DB::transaction(function () use ($toShift, &$shifted, $syncLecture, $board, $data) {
                    $movedIds = [];
                    foreach ($toShift as [$placement, $newStart]) {
                        $placement->update(['start_index' => $newStart]);
                        $movedIds[] = $placement->id;
                        $shifted++;
                    }
                    if (!$syncLecture) {
                        return;
                    }
                    // Tashqi jadval: siljigan amaliy bloklarning ma'ruza yozuvi ham birga siljiydi.
                    foreach ($toShift as [$placement, $newStart]) {
                        if (($placement->training_type ?: 'practice') !== 'practice') continue;
                        TimetableCyclePlacement::where('board_id', $board->id)
                            ->where('specialty_name', $placement->specialty_name)
                            ->where('course', (int) $placement->course)
                            ->where('group_name', $placement->group_name)
                            ->where('subject_name', $placement->subject_name)
                            ->where('training_type', 'lecture')
                            ->whereNotIn('id', $movedIds)
                            ->update(['start_index' => $newStart]);
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
                    ->where(fn ($q) => $syncLecture
                        ? $q->whereIn('training_type', ['practice', 'lecture'])->orWhereNull('training_type')
                        : $q->where('training_type', $data['training_type'])->orWhereNull('training_type'))
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
        // Sikllar orasidagi majburiy bo'sh kunlar; "Oraliqsiz" belgilansa (rasmiy
        // jadvalda sikllar ketma-ket bo'lsa) talab qilinmaydi.
        $cycleGapDays = !empty($data['ignore_gap']) ? 0 : 2;
        // Bir fanning guruhcha bo'laklari — "Fan (a)", "Fan (b)"... HEMIS'da alohida fan,
        // aslida bitta sikl: aynan bir kundan boshlansa, bitta blok sifatida qabul qilinadi.
        $variantBase = fn($value) => preg_replace('/\s*\(\p{L}\)\s*$/u', '', $norm($value));
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
            [$otherLo, $otherHi] = $laneRange(max(1, (int) ($other->pair ?? 1)), $otherSpan);
            if ($otherHi < $reqLaneFrom || $otherLo > $reqLaneTo) continue;
            $sameSubject = $norm($other->subject_name) === $norm($data['subject_name']);
            // Shu blokning o'zi (qayta joylanmoqda) yoki tashqi jadvalda amaliy bilan
            // birga ko'chadigan ma'ruza yozuvi — tekshirilmaydi.
            if ($sameSubject && ($syncLecture
                || $norm($other->training_type ?: 'practice') === $norm($data['training_type']))) continue;
            $otherStartPosition = $positionFor((int) $other->start_index);
            if ($otherStartPosition === null) continue;
            $otherSetting = $settings->get($norm($data['specialty_name']) . '|' . $norm($other->subject_name));
            $otherDays = max(1, $this->cycleTypeDays(
                max(1, (int) ($otherSetting->cycle_days ?? 1)),
                $hoursFor((string) $data['specialty_name'], (int) $data['course'], (string) $other->subject_name),
                (string) ($other->training_type ?: 'practice')
            ));
            $otherEndPosition = $otherStartPosition + $otherDays - 1;
            // Bir fanning bo'laklari ("Fan (a)", "Fan (b)"...) — bitta sikl: tashqi jadvalda ular
            // birga joylanadi va birga ko'chadi (cyclePlace hammasini ketma-ket yozadi), shuning
            // uchun bir-biriga to'qnashuv sifatida qaralmaydi.
            if (!$sameSubject && $variantBase($other->subject_name) === $variantBase($data['subject_name'])
                && $this->cycleSubjectVariantBase((string) $other->subject_name) !== null) continue;
            if ($sameSubject) {
                // Bir fanning ma'ruza va amaliy bloklari: oraliq talab qilinmaydi, faqat
                // kun × para bo'yicha ustma-ust tushmasin (amaliy dumi ma'ruza tugagach
                // I-paradan boshlanadi — ma'ruza kechroq boshlansa, ular to'qnashadi).
                if ($segmentsCollide(
                    $laneSegments($startPosition, $requiredDays, $reqPair, $reqSpan),
                    $laneSegments($otherStartPosition, $otherDays, max(1, (int) ($other->pair ?? 1)), $otherSpan)
                )) {
                    return $pairOverlapError((string) $data['subject_name']);
                }
                continue;
            }

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
            if ($syncLecture) {
                $lecHours = $hoursFor((string) $data['specialty_name'], (int) $data['course'], (string) $data['subject_name']);
                if ($this->cycleTypeDays(max(1, (int) $setting->cycle_days), $lecHours, 'lecture') >= 1) {
                    TimetableCyclePlacement::updateOrCreate([
                        'board_id' => $board->id,
                        'specialty_name' => $data['specialty_name'],
                        'course' => (int) $data['course'],
                        'group_name' => $data['group_name'],
                        'subject_name' => $data['subject_name'],
                        'training_type' => 'lecture',
                    ], ['start_index' => $from, 'pair' => 1]);
                }
            }
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
        // Haftalik qatlam: tashqida joylashuv ext_day/ext_pair, o'qituvchi/xona bu qatlamda yo'q.
        $layerInput = (string) $request->input('layer', '');
        $external = $this->weeklyLayer($request, in_array($layerInput, ['external', 'internal'], true) ? $layerInput : null) === 'external';
        [$dayCol, $pairCol] = $external ? ['ext_day', 'ext_pair'] : ['day', 'pair'];

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
            $query->where(function ($q) use ($dayCol, $pairCol) {
                $q->whereNull($dayCol)->orWhereNull($pairCol);
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
            $cards = $cards->filter(function (TimetableCard $card) use ($overrides, $external, $dayCol, $pairCol): bool {
                $override = $overrides->get($card->id);
                if ($override && $override->cancelled) {
                    return false;
                }
                // Haftaga xos ko'chirishlar — ichki jadvalniki (blade'dagi effPlace kabi).
                if (!$external && $override && $override->day && $override->pair) {
                    return false;
                }
                return !$card->{$dayCol} || !$card->{$pairCol};
            })->values();
        }

        // Sikl fanlari (4–6 kurs) haftalik panjarada emas — ularning kunlari sikl kalendarida turadi
        // (takliflar va panjaradagi panel kabi) — joylanmaganlar ro'yxatiga kirmaydi.
        $cycleKeys = $this->cycleSubjectKeys($board);
        if ($cycleKeys) {
            $cards = $cards->reject(function (TimetableCard $c) use ($cycleKeys): bool {
                $k = $this->specKey($c->specialty_name) . '|' . (int) $c->course . '|';
                $base = $this->cycleSubjectVariantBase((string) $c->subject_name);

                return isset($cycleKeys[$k . $this->normSubject((string) $c->subject_name)])
                    || ($base !== null && isset($cycleKeys[$k . $this->normSubject($base)]));
            })->values();
        }

        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Joylanmagan kartalar');

            $headers = [
                'ID', 'Fakultet', 'Yo\'nalish', 'Kurs', 'Oqim', 'Guruh', 'Fan',
                'Turi', 'Hafta', 'Talabalar', 'O\'qituvchi', 'Auditoriya', 'Tekshirilgan vaqt', 'Sabab',
            ];
            $sheet->fromArray([$headers], null, 'A1');
            $sheet->mergeCells('A1:N1');
            $sheet->setCellValue('A1', 'Joylanmagan kartalar' . ($external ? ' (tashqi jadval)' : '') . ' - ' . $board->name);
            $sheet->getStyle('A1:N1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 13],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1D4ED8']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(24);

            $sheet->fromArray([$headers], null, 'A2');
            $sheet->getStyle('A2:N2')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '475569']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
            ]);

            $reasonFor = $this->unplacedReasonFn($board, $external, $week);
            $rows = [];
            foreach ($cards as $card) {
                [$slotText, $reason] = $reasonFor($card);
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
                    $external ? '—' : ($card->teacher_name ?: 'Biriktirilmagan'),
                    $external ? '—' : ($card->auditorium_name ?: 'Biriktirilmagan'),
                    $slotText,
                    $reason,
                ];
            }
            if ($rows) {
                $sheet->fromArray($rows, null, 'A3');
            }

            $lastRow = max(2, 2 + count($rows));
            $sheet->getStyle('A2:N' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
            $sheet->getStyle('A3:N' . $lastRow)->getAlignment()->setVertical('top')->setWrapText(true);
            $sheet->freezePane('A3');
            $widths = ['A'=>9,'B'=>22,'C'=>25,'D'=>8,'E'=>14,'F'=>20,'G'=>34,'H'=>12,'I'=>9,'J'=>11,'K'=>24,'L'=>22,'M'=>20,'N'=>70];
            foreach ($widths as $col => $width) $sheet->getColumnDimension($col)->setWidth($width);
            for ($r = 3; $r <= $lastRow; $r++) $sheet->getRowDimension($r)->setRowHeight(-1);

            $sheet->getAutoFilter()->setRange('A2:N' . $lastRow);
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

    /**
     * Joylanmagan karta sababi (Excel uchun): nomzod vaqt tekshiriladi — o'sha vaqtda kim band (guruh / o'qituvchi /
     * xona) va bitta para pastda ham. Nomzod: ichki jadval uchun — odatiy ichki joyi (haftalik ko'chirishlarining eng
     * ko'pi), bo'lmasa tashqi jadvaldagi joyi; tashqi jadval uchun — ichki jadvaldagi joyi, bo'lmasa odatiy ichki joyi.
     * Joyi umuman bo'lmasa — shuni aytadi. Chegara — yo'nalish panjarasi (gridFor). Qaytaradi: [tekshirilgan vaqt, sabab].
     */
    private function unplacedReasonFn(TimetableBoard $board, bool $external, int $week): \Closure
    {
        $state = $this->occupancyState($board->id);
        $grids = [];
        // Kun nomlari — doska sozlamasidan (jadvaldagi kabi), bo'lmasa sukutdagilar.
        $dayNames = $board->day_names ?: TimetableBoard::DEFAULT_DAY_NAMES;
        $paraText = fn (int $pair, int $len) => $len >= 2 && $pair % 2 === 1
            ? intdiv($pair + 1, 2) . '-para'
            : intdiv($pair + 1, 2) . '-para (' . ($pair % 2 === 1 ? '1' : '2') . '-yarmi)';
        $usual = function (TimetableCard $card) use ($state): ?array {
            $cnt = [];
            foreach ($state['ov'][$card->id] ?? [] as $o) {
                if (!$o->cancelled && $o->day && $o->pair) {
                    $k = (int) $o->day . '/' . (int) $o->pair;
                    $cnt[$k] = ($cnt[$k] ?? 0) + 1;
                }
            }
            if (!$cnt) {
                return null;
            }
            arsort($cnt);

            return array_map('intval', explode('/', (string) array_key_first($cnt)));
        };

        return function (TimetableCard $card) use ($board, $state, $external, $week, $dayNames, $paraText, $usual, &$grids): array {
            $cands = $external
                ? [[$card->day && $card->pair ? [(int) $card->day, (int) $card->pair] : null, 'ichki jadval'], [$usual($card), 'odatiy ichki joyi']]
                : [[$usual($card), 'odatiy joyi'], [$card->ext_day && $card->ext_pair ? [(int) $card->ext_day, (int) $card->ext_pair] : null, 'tashqi jadval']];
            $slot = null;
            $src = '';
            foreach ($cands as [$cand, $label]) {
                if ($cand) {
                    [$slot, $src] = [$cand, $label];
                    break;
                }
            }
            $noTeacher = $card->teacher_id ? '' : "O'qituvchi biriktirilmagan. ";
            if (!$slot) {
                return ['—', $noTeacher . "Tashqi jadvalda ham, ichkida ham joyi yo'q — qaysi vaqtga qo'yish noma'lum."];
            }
            [$day, $pair] = $slot;
            $len = $card->lenHalf();
            $grid = $grids[$card->faculty_name . '|' . $card->specialty_name . '|' . $card->course . '|' . $this->cardCurriculumId($card)]
                ??= $this->cardGrid($board, $card);
            $check = function (int $p) use ($card, $external, $week, $day, $state, $len, $grid): array {
                if ($day < 1 || $day > $grid['days'] || $p < 1 || $p + $len - 1 > $grid['pairs']) {
                    return ['Jadval chegarasidan tashqarida'];
                }
                try {
                    $errs = $external ? $this->findExternalConflicts($card, $day, $p)
                        : ($week ? $this->findWeekConflicts($card, $week, $day, $p, 0, $state)
                            : $this->findConflicts($card, $day, $p, 0, $state));
                } catch (\Throwable $e) {
                    return ['Tekshirib bo\'lmadi: ' . $e->getMessage()];
                }

                return array_values(array_unique($errs));
            };
            $slotText = ($dayNames[$day - 1] ?? $day . '-kun') . ', ' . $paraText($pair, $len) . ' (' . $src . ')';
            $e1 = $check($pair);
            if (!$e1) {
                return [$slotText, $noTeacher . "Bu vaqt bo'sh — joylash mumkin."];
            }
            $e2 = $check($pair + 2);
            $reason = $noTeacher . implode('; ', $e1)
                . ($e2 ? ' || 1 para pastda ham: ' . implode('; ', $e2) : " || 1 para pastda bo'sh — o'sha yerga qo'yish mumkin.");

            return [$slotText, $reason];
        };
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
        // Varaq nomi (Excel cheklovi: 31 belgi, []:*?/\ taqiqlangan).
        $sheetTitle = trim(preg_replace('/[\[\]:*?\/\\\\]+/u', ' ', (string) ($payload['sheet_title'] ?? '')));
        $sheet->setTitle($sheetTitle !== '' ? mb_substr($sheetTitle, 0, 31) : 'Dars jadvali');
        $leftCells = [];  // chapga tekislanadigan kataklar (umumiy markazlashdan keyin qo'llanadi)

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
                $fc = $this->argbColor($cell['fc'] ?? null);
                if ($bg !== null || $bold || $fc !== null) {
                    $style = $sheet->getStyle($cs > 1 || $rs > 1 ? $from . ':' . $to : $from);
                    if ($bg !== null) {
                        $style->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB($bg);
                    }
                    if ($bold) {
                        $style->getFont()->setBold(true);
                    }
                    if ($fc !== null) {
                        $style->getFont()->getColor()->setARGB($fc);
                    }
                }
                if (($cell['al'] ?? '') === 'l') {
                    $leftCells[] = $cs > 1 || $rs > 1 ? $from . ':' . $to : $from;
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
        $fontSize = (float) ($payload['font_size'] ?? 8);
        $sheet->getStyle($dim)->getFont()->setSize($fontSize >= 6 && $fontSize <= 14 ? $fontSize : 8);
        foreach ($leftCells as $range) {
            $sheet->getStyle($range)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                ->setIndent(1);
        }

        // Klient qator balandligini aniq bergan bo'lsa (masalan, sikl jadvalining
        // tor sana ustunlari) — taxminiy hisob o'rniga o'shani ishlatamiz.
        foreach ((array) ($payload['row_heights'] ?? []) as $r => $height) {
            if (is_numeric($r) && is_numeric($height)) {
                $rowHeights[$firstRow + (int) $r] = max(6, min(400, (float) $height));
            }
        }
        // Ikkinchi va keyingi fanlar yashirinib qolmasligi uchun qatorlar
        // katak ichidagi barcha matnga mos balandlikda ochiladi.
        foreach ($rowHeights as $rowNumber => $height) {
            $sheet->getRowDimension($rowNumber)->setRowHeight($height);
        }

        $colWidths = array_values((array) ($payload['col_widths'] ?? []));
        for ($i = 1; $i <= $maxCol; $i++) {
            $w = $colWidths[$i - 1] ?? null;
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))
                ->setWidth(is_numeric($w) ? max(2, min(80, (float) $w)) : ($i <= 2 ? 7 : 16));
        }

        if (!empty($payload['landscape'])) {
            $sheet->getPageSetup()
                ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3)
                ->setFitToWidth(1)
                ->setFitToHeight(0);
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

    /** Validate a proposal without writing any schedule data. Repeated on final approval. */
    public function validateTimeProposal(TimetableCard $card, array $data, array $ignorePendingIds = []): void
    {
        $grid = $this->cardGrid($card->board, $card);
        if ((int) $data['week'] > $grid['weeks'] || (int) $data['day'] > $grid['days']
            || (int) $data['pair'] + $card->lenHalf() - 1 > $grid['pairs']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['day' => 'Tanlangan vaqt jadval chegarasidan tashqarida.']);
        }
        if (TimetableCardOverride::where('card_id', $card->id)->where('week', $data['week'])->where('cancelled', true)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['week' => 'Bu haftada ushbu dars o‘tilmaydi.']);
        }
        $conflicts = $this->findWeekConflicts($card, (int) $data['week'], (int) $data['day'], (int) $data['pair']);
        $conflicts = array_merge($conflicts, $this->pendingTimeConflicts($card, $data, $ignorePendingIds));
        if ($conflicts) {
            throw \Illuminate\Validation\ValidationException::withMessages(['day' => implode(' · ', $conflicts)]);
        }
    }

    /** Other pending weekly changes also respect teacher reservations. */
    public function validatePendingTimeProposal(TimetableCard $card, array $payload, string $route, array $ignoreIds): void
    {
        if (($payload['layer'] ?? 'internal') !== 'internal' || empty($payload['day']) || empty($payload['pair'])) return;
        if (!in_array($route, ['admin.timetable.cards.week-override', 'admin.timetable.cards.place'], true)) return;
        if ($route === 'admin.timetable.cards.week-override' && ($payload['action'] ?? '') !== 'move') return;
        $weeks = $route === 'admin.timetable.cards.week-override' ? [(int) $payload['week']] : range(1, (int) $card->board->weeks);
        foreach ($weeks as $week) {
            $conflicts = $this->pendingTimeConflicts($card, array_merge($payload, ['week' => $week]), $ignoreIds);
            if ($conflicts) {
                throw \Illuminate\Validation\ValidationException::withMessages(['day' => implode(' · ', $conflicts)]);
            }
        }
    }

    /** Pending internal moves reserve the same group, teacher and room until released. */
    private function pendingTimeConflicts(TimetableCard $card, array $data, array $ignoreIds): array
    {
        if (!\App\Services\Timetable\TimetableChangeApproval::tableReady()) return [];
        $pending = \App\Models\TimetableChangeRequest::where('board_id', $card->board_id)
            ->where('status', \App\Models\TimetableChangeRequest::PENDING)
            ->whereIn('route_name', ['admin.timetable.cards.week-override', 'admin.timetable.cards.place'])
            ->when($ignoreIds, fn ($q) => $q->whereNotIn('id', $ignoreIds))->get();
        $week = (int) $data['week'];
        $sourceRoom = TimetableCardOverride::where('card_id', $card->id)->where('week', $week)->first();
        $room = $sourceRoom?->auditorium_code ?: $card->auditorium_code;
        $errors = [];
        foreach ($pending as $req) {
            $p = $req->payload ?? [];
            if (($p['layer'] ?? 'internal') === 'external' || empty($p['day']) || empty($p['pair'])) continue;
            if ($req->route_name === 'admin.timetable.cards.week-override'
                && (($p['action'] ?? '') !== 'move' || (int) ($p['week'] ?? 0) !== $week)) continue;
            $other = TimetableCard::find($req->params['card'] ?? 0);
            if (!$other) continue;
            // Competing proposals for one lesson cannot both be pending for this week.
            $sameCard = (int) $other->id === (int) $card->id;
            if (!$sameCard && ((int) $p['day'] !== (int) $data['day']
                || !$this->halfOverlap($this->rangeFor($card, (int) $data['pair'], 0),
                    $this->rangeFor($other, (int) $p['pair'], (int) ($p['start_half'] ?? 0))))) continue;
            $resources = [];
            if ($sameCard) $resources[] = 'shu dars';
            if ($this->groupScopeKey($card) === $this->groupScopeKey($other) && !$this->bolakSiblings($card, $other)) {
                $groups = array_intersect($card->occupiedGroups(), $other->occupiedGroups());
                if ($groups) $resources[] = 'guruh: ' . implode(', ', $groups);
            }
            if ($card->teacher_id && (int) $card->teacher_id === (int) $other->teacher_id) $resources[] = 'o‘qituvchi';
            $ov = TimetableCardOverride::where('card_id', $other->id)->where('week', $week)->first();
            if ($room && $room === ($ov?->auditorium_code ?: $other->auditorium_code)
                && !$this->isSharedRoom($ov?->auditorium_name ?: $other->auditorium_name)) $resources[] = 'auditoriya: ' . $room;
            if (!$resources) continue;
            $dayName = ($card->board->day_names ?: TimetableBoard::DEFAULT_DAY_NAMES)[(int) $p['day'] - 1] ?? $p['day'] . '-kun';
            $schedule = array_values(array_filter($card->board->bell_schedule ?: [], fn ($slot) => ($slot['type'] ?? 'pair') === 'pair'));
            $time = $schedule[(int) $p['pair'] - 1]['start'] ?? ($p['pair'] . '-katak');
            $errors[] = $dayName . ', ' . $time . ' — ' . ($other->teacher_name ?: $req->requester_name)
                . ' «' . $other->subject_name . '» darsi uchun bron qilgan (' . implode('; ', $resources)
                . '). Tasdiqlash jarayonida. Bron bekor qilinmaguncha bu vaqtni tanlab bo‘lmaydi.';
        }
        return $errors;
    }

    /** Match the card's department using the same aliases used for head editing rights. */
    public function proposalDepartmentMatches(TimetableCard $card, int $departmentId): bool
    {
        $names = DB::table('curriculum_subjects')->where('department_id', $departmentId)
            ->whereNotNull('department_name')->distinct()->pluck('department_name')->all();
        $names[] = Department::where('department_hemis_id', $departmentId)->value('name');

        return $this->sameKafedra($card->kafedra_name, array_values(array_filter($names)));
    }

    public function weekOverride(Request $request, TimetableCard $card)
    {
        $data = $request->validate([
            'week'       => 'required|integer|min:1|max:30',
            'action'     => 'required|in:move,cancel,reset',
            'day'        => 'nullable|integer|min:1|max:10',
            // Yarim para katakli qo'ng'iroq jadvalida kuniga 12+ katak bo'ladi.
            'pair'       => 'nullable|integer|min:1|max:24',
            'start_half' => 'nullable|integer|min:0|max:1',
            // Faqat shu hafta uchun boshqa xona (takliflardan "xonani almashtirish").
            'auditorium_code' => 'nullable|string|max:50',
        ]);
        // Haftaga xos o'zgarish — faqat ichki (kafedra) jadvalda.
        if ($denied = $this->weeklyLayerDenied($request, 'internal', $card)) {
            return $denied;
        }
        $week = (int) $data['week'];
        if ($week > $this->cardGrid($card->board, $card)['weeks']) return response()->json(['error' => 'Bu dasturda ushbu o‘quv haftasi yo‘q.'], 422);
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
        $room = null;
        if (!empty($data['auditorium_code'])) {
            $room = Auditorium::where('code', $data['auditorium_code'])->first();
            if (!$room) {
                return response()->json(['error' => 'Auditoriya topilmadi'], 422);
            }
            if (!$this->auditoriumAllowedForCard($room, $card, $this->auditoriumTeacherMap())) {
                return response()->json(['error' => 'Bu auditoriya tanlangan o\'qituvchiga biriktirilmagan.'], 422);
            }
        }
        // Berilgan xona bilan tekshiriladi (haftalik yozuvdagi eski xona emas); rad etilsa hech narsa o'zgarmaydi.
        $conflicts = $this->findWeekConflicts($card, $week, $day, $pair, $startHalf, null, $room?->code);
        if (!empty($conflicts)) {
            return response()->json(['error' => implode(' · ', $conflicts)], 422);
        }
        TimetableCardOverride::updateOrCreate(
            ['card_id' => $card->id, 'week' => $week],
            ['day' => $day, 'pair' => $pair, 'start_half' => $startHalf, 'cancelled' => false]
                + ($room ? ['auditorium_code' => $room->code, 'auditorium_name' => $room->name] : [])
        );
        $ovRoom = TimetableCardOverride::where('card_id', $card->id)->where('week', $week)->value('auditorium_code');
        $warnings = $this->weeklyCycleWarnings($card, $week, (int) $day, (int) $pair, $ovRoom);
        return response()->json(['ok' => true] + ($warnings ? ['warnings' => $warnings] : []));
    }

    /** Tanlangan haftadagi effektiv joylashuvlar bo'yicha konflikt tekshiruvi (yarim-slot oralig'i). */
    /** $roomCode — shu hafta uchun beriladigan xona (berilmasa — haftalik yozuvdagi yoki kartaning xonasi). */
    private function findWeekConflicts(TimetableCard $card, int $week, int $day, int $pair, int $startHalf = 0, ?array $state = null, ?string $roomCode = null): array
    {
        $board = $card->board;
        if ($week > $this->cardGrid($board, $card)['weeks']) return [];
        $state ??= $this->calendarConflictState($card, $roomCode);
        $date = $this->weekStartDate($board, $card, $week)->addDays($day - 1)->toDateString();
        $myRange = $this->rangeFor($card, $pair, $startHalf);
        $myOverride = $state['ov'][$card->id][$week] ?? null;
        $myRoomCode = $roomCode ?: ($myOverride?->auditorium_code ?: $card->auditorium_code);
        $errors = [];
        foreach ($state['cards'] as $id => $o) {
            if ((int) $id === (int) $card->id) continue;
            // Week numbers are program-local. Compare actual dates, including program holidays.
            $otherWeek = $this->calendarWeekAt($board, $o, $date);
            if ($otherWeek === null) continue;
            $ov = $state['ov'][$id][$otherWeek] ?? null;
            if ($ov?->cancelled) continue;
            $od = $ov ? $ov->day : $o->day;
            $op = $ov ? $ov->pair : $o->pair;
            $osh = (int) ($ov ? ($ov->start_half ?? 0) : ($o->start_half ?? 0));
            if (!$od || !$op || (int) $od !== $day || !$this->halfOverlap($myRange, $this->rangeFor($o, (int) $op, $osh))) continue;
            array_push($errors, ...$this->clashWith($card, $myRoomCode, $o,
                $ov?->auditorium_code ?: $o->auditorium_code, $ov?->auditorium_name ?: $o->auditorium_name));
        }
        return array_values(array_unique($errors));
    }

    /** Yo'nalish+kurs uchun panjara o'lchami (alohida sozlama yoki doska sukuti). */
    private function gridFor(TimetableBoard $board, ?string $faculty, string $specialty, int $course, int $curriculum = 0): array
    {
        $gs = $this->calendarSetting($board, $faculty, $specialty, $course, $curriculum);

        return [
            'days'  => $gs->days ?? $board->days,
            'start_date' => $gs->start_date ?? null,
            'weekly_breaks' => $gs->weekly_breaks ?? null,
            // Yarim-slot soni doska qo'ng'iroq jadvalidan (yo'nalish bo'yicha bir xil)
            'pairs' => $board->pairCount(),
            'weeks' => max(1, (int) ($gs->weeks ?? $board->weeks ?? 15)),
        ];
    }

    /**
     * Haftalik jadvalni ikki qatlamga ajratish (admin): qamrovdagi haftalik kartalarning hozirgi
     * joylashuvi tashqi jadvalga ko'chiriladi (ext_day/ext_pair), ichki jadval (day/pair) esa
     * xohlansa bo'shatiladi — kafedra mudiri o'z kartalarini bo'sh holatdan joylaydi.
     */
    public function weeklyLayerCopy(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'clear_internal'    => 'nullable|boolean',
            'dry_run'           => 'nullable|boolean',
        ]);
        if (!Schema::hasColumn('timetable_cards', 'ext_day')) {
            return response()->json(['error' => 'Migratsiya hali ishga tushirilmagan (php artisan migrate).'], 503);
        }
        if (!in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true)) {
            return response()->json(['error' => 'Bu amal faqat admin uchun.'], 403);
        }
        $this->snapshotBefore($request, $board, 'before_layer_copy');
        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $scopeQ = TimetableCard::where('board_id', $board->id);
        $this->applyScopeToQuery($scopeQ, $facSet, $specSet, $courseSet);
        $cards = (clone $scopeQ)->whereNotNull('day')->whereNotNull('pair')->get(['id', 'day', 'pair']);
        $result = ['ok' => true, 'copied' => $cards->count(), 'cleared' => $request->boolean('clear_internal') ? $cards->count() : 0];
        if ($request->boolean('dry_run')) {
            return response()->json($result + ['dry_run' => true]);
        }
        DB::transaction(function () use ($cards, $request, $scopeQ) {
            // Qamrovdagi tashqi qatlam hozirgi joylashuvning nusxasi bo'ladi: ichki jadvaldan
            // olib tashlangan kartaning eski tashqi joyi qolib ketmasin.
            (clone $scopeQ)->whereNotNull('ext_day')->update(['ext_day' => null, 'ext_pair' => null]);
            foreach ($cards as $c) {
                $upd = ['ext_day' => $c->day, 'ext_pair' => $c->pair];
                if ($request->boolean('clear_internal')) {
                    $upd += ['day' => null, 'pair' => null, 'start_half' => 0];
                }
                DB::table('timetable_cards')->where('id', $c->id)->update($upd);
            }
            if ($request->boolean('clear_internal') && Schema::hasTable('timetable_card_overrides')) {
                // Haftaga xos ko'chirishlar ichki jadvalga tegishli — tozalanadi (bekor qilingan haftalar qoladi).
                DB::table('timetable_card_overrides')->whereIn('card_id', $cards->pluck('id'))
                    ->where('cancelled', false)->delete();
            }
        });

        return response()->json($result);
    }

    /**
     * Haftalik jadval qatlami: so'rovda berilgan, aks holda rol bo'yicha (o'quv bo'limi — tashqi,
     * qolganlar — ichki). Ustunlar hali yo'q bo'lsa (migratsiyadan oldin) doim ichki.
     */
    private function weeklyLayer(Request $request, ?string $requested): string
    {
        if (!Schema::hasColumn('timetable_cards', 'ext_day')) {
            return 'internal';
        }

        return $requested ?: ($this->isOquvBolimiRole($this->timetableActiveRole($request)) ? 'external' : 'internal');
    }

    /**
     * Haftalik qatlamni kim tahrirlaydi (sikl kabi): tashqi — o'quv bo'limi, ichki — kafedra
     * mudiri (faqat o'z kafedrasi fanlari); admin/superadmin/registrator ofisi — ikkalasini.
     */
    private function weeklyLayerDenied(Request $request, string $layer, ?TimetableCard $card = null)
    {
        if (!$this->timetableActor($request)) {
            return null;   // ichki chaqiruv (konsol/test) — HTTP so'rovlar middleware'dan o'tadi
        }
        $role = $this->timetableActiveRole($request);
        if (in_array($role, ['admin', 'superadmin', 'registrator_ofisi', 'kichik_admin'], true)) {
            return null;
        }
        if ($layer === 'external') {
            return $this->isOquvBolimiRole($role) ? null
                : response()->json(['error' => 'Tashqi dars jadvalini faqat o‘quv bo‘limi tahrirlaydi.'], 403);
        }
        if ($role !== 'kafedra_mudiri') {
            return response()->json(['error' => 'Ichki dars jadvalini faqat kafedra mudiri tahrirlaydi.'], 403);
        }
        // Kafedra aniqlanmasa — cheklovsiz ruxsat emas, rad etiladi.
        $own = $this->departmentHeadKafedraSafe($request);
        if (!$own) {
            return response()->json(['error' => 'Kafedrangiz aniqlanmadi — profilingizdagi kafedra ma\'lumotini tekshiring.'], 403);
        }
        if ($card && !$this->sameKafedra($card->kafedra_name, $own)) {
            return response()->json(['error' => 'Bu fan sizning kafedrangizga tegishli emas.'], 403);
        }

        return null;
    }

    /**
     * Bitta fanning turli bo'laklari ("Fan (a)" va "Fan (b)"): guruhning turli talabalari —
     * bir vaqtda parallel o'tadi, guruh bandligi bo'yicha bir-biriga to'sqinlik qilmaydi
     * (o'qituvchi va xona bandligi baribir tekshiriladi).
     */
    private function bolakSiblings(TimetableCard $a, TimetableCard $b): bool
    {
        $rx = '/^(.*?)\s*\((\p{L})\)\s*$/u';
        if (!preg_match($rx, trim((string) $a->subject_name), $ma) || !preg_match($rx, trim((string) $b->subject_name), $mb)) {
            return false;
        }

        return mb_strtolower(trim($ma[1])) === mb_strtolower(trim($mb[1])) && mb_strtolower($ma[2]) !== mb_strtolower($mb[2]);
    }

    /**
     * Sikl (4–6-kurs) darslari sana bo'yicha: [Y-m-d => [[from_hour, to_hour, teacher_id, room_code, matn], ...]].
     * Haftalik darsni qo'yganda o'qituvchi/xona sikl darsida bandligini ogohlantirish uchun.
     * Soat — yarim-para indeksi (1..12). Kunlik vaqt: ichki jadval boshlanish soati (lecture_slots.starts)
     * yoki blok parasi; davomiyligi KTR kunlik soatidan (topilmasa ma'ruza 2, amaliy 6 soat).
     */
    private function cycleBusyByDate(TimetableBoard $board): array
    {
        if (!Schema::hasTable('timetable_cycle_placements')) {
            return [];
        }
        $startC = $this->weeklySemesterStart($board);
        $set = (array) ($board->settings ?? []);
        $holSet = [];
        foreach ((array) ($set['holidays'] ?? []) as $holiday) {
            try { $holSet[Carbon::parse($holiday)->toDateString()] = true; } catch (\Exception $e) { }
        }
        $daysPerWeek = max(1, (int) $board->days);
        $maxDays = $this->cycleCalendarWorkDays($board, $startC, $holSet);
        $work = [];   // o'quv kuni tartib raqami => kalendar indeksi
        $cur = $startC->copy();
        for ($i = 0, $guard = 0; count($work) < $maxDays && $guard < $maxDays * 8 + 60; $i++, $guard++) {
            $dow = (int) $cur->dayOfWeekIso;
            if ($dow <= $daysPerWeek && $dow < 7 && !isset($holSet[$cur->toDateString()])) {
                $work[] = $i;
            }
            $cur->addDay();
        }
        $posOf = function (int $calIndex) use ($work): ?int {
            foreach ($work as $pos => $ci) {
                if ($ci >= $calIndex) {
                    return $pos;
                }
            }
            return null;
        };
        $cycleDays = [];
        foreach (TimetableSubjectSetting::where('board_id', $board->id)->where('mode', 'cycle')->get() as $st) {
            $cycleDays[mb_strtolower(trim($st->specialty_name)) . '|' . (int) $st->course . '|' . mb_strtolower(trim($st->subject_name))] = max(1, (int) $st->cycle_days);
        }
        $cycleHours = $this->cycleSubjectHoursMap($board);
        $ktr = $this->cycleKtrDayPlans($board);
        // Sikl kartalari: qator (guruh/oqim) + fan + tur => o'qituvchi, xona
        $req = [];
        $facultyByRow = [];
        foreach (TimetableCard::where('board_id', $board->id)->where('course', '>=', 4)->get() as $card) {
            $base = mb_strtolower(trim((string) $card->specialty_name)) . '|' . (int) $card->course . '|';
            $type = $card->training_type === 'lecture' ? 'lecture' : 'practice';
            $rows = $type === 'practice'
                ? array_merge(...array_map(fn ($m) => [$this->cycleFlowName($card, $m), $m], $this->cycleCardGroups($card) ?: ['']))
                : array_merge([$this->cycleFlowName($card)], $this->cycleCardGroups($card));
            foreach ($rows as $rowName) {
                $k = $base . mb_strtolower(trim((string) $card->subject_name)) . '|' . mb_strtolower(trim((string) $rowName)) . '|' . $type;
                $req[$k][$card->id] = [$card->teacher_id, $card->teacher_name, $card->auditorium_code, $card->auditorium_name];
                if ($card->faculty_name) {
                    $facultyByRow[$base . mb_strtolower(trim((string) $rowName))] ??= (string) $card->faculty_name;
                }
            }
        }
        $out = [];
        foreach (TimetableCyclePlacement::where('board_id', $board->id)->get() as $pl) {
            $type = ($pl->training_type ?: 'practice') === 'lecture' ? 'lecture' : 'practice';
            $spec = (string) $pl->specialty_name;
            $course = (int) $pl->course;
            $subject = (string) $pl->subject_name;
            $rowKey = mb_strtolower(trim($spec)) . '|' . $course . '|';
            $resources = $req[$rowKey . mb_strtolower(trim($subject)) . '|' . mb_strtolower(trim((string) $pl->group_name)) . '|' . $type] ?? [];
            if (!$resources) {
                continue;
            }
            $faculty = $this->cycleRowFaculty((string) $pl->group_name) ?? ($facultyByRow[$rowKey . mb_strtolower(trim((string) $pl->group_name))] ?? null);
            $hours = $this->cycleHoursLookup($cycleHours, $faculty, $spec, $course, $subject);
            $days = max(1, $this->cycleTypeDays($cycleDays[$rowKey . mb_strtolower(trim($subject))] ?? 1, $hours, $type));
            $kk = $this->cycleKtrKey($spec, $course, $subject);
            $plan = ($faculty ? ($ktr[$this->specKey($faculty) . '|' . $kk] ?? null) : null) ?? $ktr[$kk] ?? null;
            $starts = (array) (($pl->lecture_slots ?? [])['starts'] ?? []);
            $defaultStart = $pl->pair ? ((int) $pl->pair - 1) * 2 + 1 : 1;
            $pos = $posOf((int) $pl->start_index);
            if ($pos === null) {
                continue;
            }
            $dayPlan = ($plan !== null || $hours !== null)
                ? $this->cycleDayPlan($plan, $hours, $days, $work[$pos], $work, $starts, 6, max(12, (int) $board->pairs_per_day))
                : [];
            for ($k = 0; $k < $days; $k++) {
                $ci = $work[$pos + $k] ?? null;
                if ($ci === null) {
                    break;
                }
                $daily = $dayPlan[$ci - $work[$pos]] ?? null;
                $dayHours = $daily !== null ? (int) $daily[$type === 'lecture' ? 'lec' : 'prc'] : ($type === 'lecture' ? 2 : 6);
                if ($dayHours <= 0) {
                    continue;
                }
                $from = $daily !== null
                    ? (int) $daily['start'] + ($type === 'practice' ? (int) $daily['lec'] : 0)
                    : max(1, (int) ($starts[(string) $k] ?? $defaultStart));
                $date = $startC->copy()->addDays($ci)->toDateString();
                foreach ($resources as [$tid, $tname, $room, $roomName]) {
                    $out[$date][] = [$from, $from + $dayHours, $tid, $room, $tname, $roomName,
                        $course . '-kurs · ' . $pl->group_name . ' · ' . $subject . ' (' . ($type === 'lecture' ? "ma'ruza" : 'amaliy') . ', sikl)'];
                }
            }
        }

        return $out;
    }

    /**
     * Yo'nalish+kurs kalendari bo'yicha W-haftaning dushanbasi: panjara boshlanishi (bo'lmasa doska
     * semestr boshi) dan sanab, doska sozlamasidagi ta'til oraliqlariga (settings.weekly_breaks) tushgan
     * haftalar o'tkazib yuboriladi (masalan qishki ta'til 28.12–09.01 — o'quv haftasi hisoblanmaydi).
     */
    private function weekStartDate(TimetableBoard $board, $card, int $week): Carbon
    {
        $grid = $this->cardGrid($board, $card);
        $start = $grid['start_date'] ? Carbon::parse($grid['start_date'])->startOfDay() : $this->weeklySemesterStart($board);
        $mondays = $this->weekMondays($board, $start, $week, $grid['weekly_breaks']);

        return ($mondays[max(1, $week) - 1] ?? end($mondays))->copy();
    }

    /**
     * $start haftasidan boshlab birinchi $count ta o'quv haftasining dushanbalari: doska sozlamasidagi
     * ta'til oraliqlariga (settings.weekly_breaks) tushgan haftalar o'tkazib yuboriladi. Bazaga murojaat qilmaydi.
     */
    private function weekMondays(TimetableBoard $board, Carbon $start, int $count, ?array $calendarBreaks = null): array
    {
        $d = $start->copy()->startOfWeek();
        $breaks = [];
        foreach ($calendarBreaks ?? (array) (($board->settings ?? [])['weekly_breaks'] ?? []) as $b) {
            if (!empty($b['from']) && !empty($b['to'])) {
                $breaks[] = [Carbon::parse($b['from'])->startOfDay(), Carbon::parse($b['to'])->endOfDay()];
            }
        }
        $out = [];
        for ($guard = 0; $guard < 200 && count($out) < max(1, $count); $guard++, $d->addWeek()) {
            $inBreak = false;
            foreach ($breaks as [$f, $t]) {
                if ($d->between($f, $t)) {
                    $inBreak = true;
                    break;
                }
            }
            if (!$inBreak) {
                $out[] = $d->copy();
            }
        }

        return $out;
    }

    private function weeklySemesterStart(TimetableBoard $board): Carbon
    {
        $set = (array) ($board->settings ?? []);
        $start = $set['semester_start'] ?? null;
        if (!$start) {
            $y = (int) preg_replace('/\D.*$/', '', (string) $board->academic_year);
            if ($y < 2000) $y = (int) date('Y');
            $start = $board->semester_parity === 'bahorgi' ? sprintf('%04d-02-01', $y + 1) : sprintf('%04d-09-01', $y);
        }

        return Carbon::parse($start)->startOfDay();
    }

    /** Haftalik dars (hafta, kun, yarim-para) sikl darsi bilan o'qituvchi/xona bo'yicha ustma-ust tushsa — ogohlantirishlar. */
    private function weeklyCycleWarnings(TimetableCard $card, int $week, int $day, int $pair, ?string $roomCode = null): array
    {
        if ((int) $card->course >= 4) {
            return [];
        }
        $roomCode = $roomCode ?: $card->auditorium_code;
        if (!$card->teacher_id && !$roomCode) {
            return [];
        }
        $board = $card->board;
        $date = $this->weekStartDate($board, $card, $week)->addDays($day - 1);
        $busy = $this->cycleBusyByDate($board)[$date->toDateString()] ?? [];
        $from = $pair;
        $to = $pair + $card->lenHalf();
        $out = [];
        foreach ($busy as [$bf, $bt, $tid, $room, $tname, $roomName, $label]) {
            if ($bf >= $to || $from >= $bt) {
                continue;
            }
            if ($card->teacher_id && $tid && (int) $tid === (int) $card->teacher_id) {
                $out[] = "O'qituvchi sikl darsida band (" . $date->format('d.m.Y') . '): ' . $tname . ' — ' . $label;
            }
            if ($roomCode && $room && $room === $roomCode && !$this->isSharedRoom($roomName)) {
                $out[] = 'Auditoriya sikl darsida band (' . $date->format('d.m.Y') . '): ' . $roomName . ' — ' . $label;
            }
        }

        return array_values(array_unique($out));
    }

    /** Band qilgan dars haqida batafsil: "2-son davolash, 3-kurs · d2/d24-05a · Patologik anatomiya (amaliy)". */
    private function describeCard(TimetableCard $o): string
    {
        $groups = array_map(fn ($g) => trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', (string) $g)), $o->occupiedGroups());
        $gText = count($groups) > 4 ? implode(', ', array_slice($groups, 0, 4)) . ' +' . (count($groups) - 4) : implode(', ', $groups);
        $who = trim((string) $o->faculty_name) !== '' ? trim((string) $o->faculty_name) . ', ' : '';

        return $who . (int) $o->course . '-kurs'
            . ($o->training_type === 'lecture' && $o->oqim_label ? ' · ' . $o->oqim_label : '')
            . ($gText !== '' ? ' · ' . $gText : '')
            . ' · ' . $o->subject_name . ' (' . ($o->training_type === 'lecture' ? "ma'ruza" : 'amaliy') . ')';
    }

    /** Tashqi qatlamdagi guruh bandligi (ext_day/ext_pair bo'yicha). */
    private function findExternalConflicts(TimetableCard $card, int $day, int $pair): array
    {
        $others = TimetableCard::where('board_id', $card->board_id)
            ->where('id', '!=', $card->id)
            ->where('ext_day', $day)->whereNotNull('ext_pair')
            ->get();
        $my = [$pair - 1, $pair - 1 + $card->lenHalf()];
        $myGroups = $card->occupiedGroups();
        // Tashqi jadval — umumiy shablon: bir katakni hech qachon bir haftaga tushmaydigan
        // kartalar (toq/juft hafta ma'ruzalari, "BKP 7 / Umumiy xirurgiya 8" kabi navbatlashuvchi
        // fanlar) bo'lisha oladi. Shuning uchun guruh bandligi faqat umumiy faol hafta bo'lsa konflikt.
        // Haftalar — yo'nalish+kurs panjarasidan (hafta istisnolari shu chegaragacha yoziladi);
        // guruh konflikti faqat bir panjara (groupScopeKey) ichida bo'ladi.
        $totalWeeks = $this->cardGrid($card->board, $card)['weeks'];
        $cancelled = [];
        $activeWeeks = function (int $cardId) use (&$cancelled, $totalWeeks): array {
            if (!array_key_exists($cardId, $cancelled)) {
                $cancelled[$cardId] = Schema::hasTable('timetable_card_overrides')
                    ? TimetableCardOverride::where('card_id', $cardId)->where('cancelled', true)->pluck('week')->map(fn ($w) => (int) $w)->all()
                    : [];
            }
            return array_values(array_diff(range(1, $totalWeeks), $cancelled[$cardId]));
        };
        $errors = [];
        foreach ($others as $o) {
            $range = [(int) $o->ext_pair - 1, (int) $o->ext_pair - 1 + $o->lenHalf()];
            if (!$this->halfOverlap($my, $range) || $this->groupScopeKey($o) !== $this->groupScopeKey($card)) {
                continue;
            }
            $common = array_intersect($myGroups, $o->occupiedGroups());
            if ($common && ($this->bolakSiblings($card, $o) || !array_intersect($activeWeeks((int) $card->id), $activeWeeks((int) $o->id)))) {
                continue;
            }
            if ($common) {
                $errors[] = 'Guruh band: ' . implode(', ', $common) . ' — ' . $this->describeCard($o);
            }
        }

        return array_values(array_unique($errors));
    }

    /** Kartochkani joylash/ko'chirish/olib tashlash — konflikt tekshiruvi bilan. */
    public function placeCard(Request $request, TimetableCard $card)
    {
        $board = $card->board;
        $grid = $this->cardGrid($board, $card);
        $data = $request->validate([
            'day'        => 'nullable|integer|min:1|max:' . $grid['days'],
            'pair'       => 'nullable|integer|min:1|max:' . $grid['pairs'],
            'start_half' => 'nullable|integer|min:0|max:1',
            'layer'      => 'nullable|in:external,internal',
        ]);

        $day = $data['day'] ?? null;
        $pair = $data['pair'] ?? null;
        $startHalf = (int) ($data['start_half'] ?? 0);
        $layer = $this->weeklyLayer($request, $data['layer'] ?? null);
        if ($denied = $this->weeklyLayerDenied($request, $layer, $card)) {
            return $denied;
        }

        if ($layer === 'external') {
            // Tashqi jadval: faqat guruh bandligi (o'qituvchi/xona bu qatlamda yo'q).
            if ($day && $pair) {
                $conflicts = $this->findExternalConflicts($card, $day, $pair);
                if (!empty($conflicts)) {
                    return response()->json(['error' => implode(' · ', $conflicts)], 422);
                }
            }
            $card->update(['ext_day' => $day && $pair ? $day : null, 'ext_pair' => $day && $pair ? $pair : null]);

            return response()->json(['ok' => true, 'layer' => 'external']);
        }

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

    /**
     * Umumiy (raqamsiz) xona nomi — masalan "amaliy mashg'ulot xonasi": bu aniq bitta xona emas,
     * kafedraning amaliy xonalari umumiy belgisi. Bir vaqtda bir nechta guruhga berilishi mumkin,
     * shuning uchun xona bandligi tekshirilmaydi (o'qituvchi va guruh bandligi tekshiriladi).
     * Faqat raqamsiz VA "mashg'ulot"/"umumiy" so'zli nom: "Katta majlislar zali" kabi raqamsiz,
     * lekin aniq bitta zal bandligi tekshirilaveradi (blade'dagi isSharedRoomName bilan bir xil).
     */
    private function isSharedRoom(?string $name): bool
    {
        $name = trim((string) $name);

        return $name !== '' && !preg_match('/\d/u', $name) && preg_match('/mashg|umumiy/iu', $name) === 1;
    }

    /**
     * Shablon joyi to'qnashuvlari: shu kunda shablonda turgan boshqa kartalar (oraliq kesishuvi bilan) va haftalik
     * ko'chirish bilan shu vaqtga kelgan kartalar — karta o'sha haftada shablon joyida bo'lsa (o'sha hafta bekor
     * qilinmagan va boshqa joyga ko'chirilmagan). $state — tiklashda bir marta yuklangan doska bandligi.
     */
    private function findConflicts(TimetableCard $card, int $day, int $pair, int $startHalf = 0, ?array $state = null): array
    {
        $state ??= $this->calendarConflictState($card);
        $weeksOf = [];   // to'qnashuv matni => haftalar
        $checked = 0;
        foreach (range(1, $this->cardGrid($card->board, $card)['weeks']) as $week) {
            $own = $state['ov'][$card->id][$week] ?? null;
            if ($own && ($own->cancelled || $own->day)) continue;
            $checked++;
            foreach ($this->findWeekConflicts($card, $week, $day, $pair, $startHalf, $state) as $error) {
                $weeksOf[$error][] = $week;
            }
        }
        // Karta shablon joyida turadigan barcha haftalarda bo'lgan to'qnashuv — haftasiz; faqat ayrim haftalarda bo'lsa
        // qaysi haftalar ekani yoziladi ("3–5, 9-hafta: ...") — shablon ko'rinishida bu joy bo'sh ko'rinadi.
        $errors = [];
        foreach ($weeksOf as $error => $weeks) {
            $errors[] = count($weeks) === $checked ? $error : $this->weekRangesText($weeks) . '-hafta: ' . $error;
        }

        return $errors;
    }

    /** Haftalar ro'yxati qisqa ko'rinishda: [3, 4, 5, 9] → "3–5, 9". */
    private function weekRangesText(array $weeks): string
    {
        sort($weeks);
        $parts = [];
        $from = $prev = null;
        foreach ($weeks as $w) {
            if ($prev !== null && $w === $prev + 1) {
                $prev = $w;
                continue;
            }
            if ($from !== null) {
                $parts[] = $from === $prev ? (string) $from : $from . '–' . $prev;
            }
            $from = $prev = $w;
        }
        if ($from !== null) {
            $parts[] = $from === $prev ? (string) $from : $from . '–' . $prev;
        }

        return implode(', ', $parts);
    }

    /** Ikki dars bir vaqtda: guruh (bir yo'nalish+kurs ichida, bo'lak qo'shnilaridan tashqari), o'qituvchi va xona bandligi. */
    private function clashWith(TimetableCard $card, ?string $myRoom, TimetableCard $o, ?string $oRoom, ?string $oRoomName): array
    {
        $errors = [];
        // Guruh konflikti — bir yo'nalish+kurs ichida
        if ($this->groupScopeKey($o) === $this->groupScopeKey($card) && !$this->bolakSiblings($card, $o)) {
            $overlap = array_intersect($card->occupiedGroups(), $o->occupiedGroups());
            if (!empty($overlap)) {
                $errors[] = 'Guruh band: ' . implode(',', $overlap) . ' — ' . $this->describeCard($o);
            }
        }
        // O'qituvchi konflikti — butun doska bo'ylab
        if ($card->teacher_id && $o->teacher_id && (int) $o->teacher_id === (int) $card->teacher_id) {
            $errors[] = "O'qituvchi band: " . $o->teacher_name . ' — ' . $this->describeCard($o);
        }
        // Auditoriya konflikti — butun doska bo'ylab
        if ($myRoom && $oRoom === $myRoom && !$this->isSharedRoom($oRoomName)) {
            $errors[] = 'Auditoriya band: ' . $oRoomName . ' — ' . $this->describeCard($o);
        }

        return $errors;
    }

    /**
     * Bir kunning bandligi (to'qnashuv tekshiruvi uchun): ['cards' => [id => karta], 'tpl' => shablonda shu kundagi
     * kartalar id'lari, 'moved' => [[id, hafta, yozuv]] — haftalik ko'chirish bilan shu kunga kelganlar, 'ov' => [id =>
     * [hafta => yozuv]]]. $state berilsa (doska bir marta yuklangan) — undan; aks holda bazadan, faqat shu kun
     * ($week berilsa — faqat shu hafta).
     */
    private function daySlice(TimetableCard $card, int $day, ?int $week, ?array $state = null): array
    {
        if ($state !== null) {
            return ['cards' => $state['cards'], 'tpl' => array_values($state['tplByDay'][$day] ?? []), 'ov' => $state['ov'],
                'moved' => array_values(array_filter($state['movedByDay'][$day] ?? [], fn ($m) => $week === null || $m[1] === $week))];
        }
        $cards = TimetableCard::where('board_id', $card->board_id)->where('day', $day)->whereNotNull('pair')->get()->keyBy('id')->all();
        $out = ['cards' => $cards, 'tpl' => array_keys($cards), 'moved' => [], 'ov' => []];
        if (!Schema::hasTable('timetable_card_overrides')) {
            return $out;
        }
        // Shablon kartalari (shu haftadagi joyi uchun — faqat hafta tekshiruvida) va kartaning o'zi; shu kunga ko'chirilganlar.
        $ownIds = $week !== null ? array_merge(array_keys($cards), [$card->id]) : [$card->id];
        $rows = TimetableCardOverride::whereHas('card', fn ($q) => $q->where('board_id', $card->board_id))
            ->when($week !== null, fn ($q) => $q->where('week', $week))
            ->where(fn ($q) => $q->whereIn('card_id', $ownIds)->orWhere(fn ($w) => $w->where('day', $day)->where('cancelled', false)))
            ->get();
        $missing = array_diff($rows->pluck('card_id')->map(fn ($id) => (int) $id)->unique()->all(), array_keys($cards), [(int) $card->id]);
        if ($missing) {
            $out['cards'] += TimetableCard::whereIn('id', $missing)->get()->keyBy('id')->all();
        }
        foreach ($rows as $o) {
            $out['ov'][(int) $o->card_id][(int) $o->week] = $o;
            if (!$o->cancelled && (int) $o->day === $day && $o->pair) {
                $out['moved'][] = [(int) $o->card_id, (int) $o->week, $o];
            }
        }

        return $out;
    }

    /** Butun doska bandligi bir marta (tiklash uchun) — daySlice shundan olinadi; yozilganlar stateSet* bilan qo'shiladi. */
    private function occupancyState(int $boardId): array
    {
        $state = ['cards' => [], 'tplByDay' => [], 'tplDayOf' => [], 'ov' => [], 'movedByDay' => []];
        foreach (TimetableCard::where('board_id', $boardId)->get() as $c) {
            $this->stateSetCard($state, $c);
        }
        if (Schema::hasTable('timetable_card_overrides')) {
            foreach (TimetableCardOverride::whereHas('card', fn ($q) => $q->where('board_id', $boardId))->get() as $o) {
                $this->stateSetOverride($state, $o);
            }
        }

        return $state;
    }

    private function stateSetCard(array &$state, TimetableCard $c): void
    {
        $id = (int) $c->id;
        if (isset($state['tplDayOf'][$id])) {
            unset($state['tplByDay'][$state['tplDayOf'][$id]][$id], $state['tplDayOf'][$id]);
        }
        $state['cards'][$id] = $c;
        if ($c->day && $c->pair) {
            $state['tplByDay'][(int) $c->day][$id] = $id;
            $state['tplDayOf'][$id] = (int) $c->day;
        }
    }

    private function stateSetOverride(array &$state, ?TimetableCardOverride $o, ?int $cardId = null, ?int $week = null): void
    {
        $cardId ??= (int) $o->card_id;
        $week ??= (int) $o->week;
        $old = $state['ov'][$cardId][$week] ?? null;
        if ($old && $old->day) {
            unset($state['movedByDay'][(int) $old->day][$cardId . '|' . $week]);
        }
        if (!$o) {
            unset($state['ov'][$cardId][$week]);

            return;
        }
        $state['ov'][$cardId][$week] = $o;
        if (!$o->cancelled && $o->day && $o->pair) {
            $state['movedByDay'][(int) $o->day][$cardId . '|' . $week] = [$cardId, $week, $o];
        }
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
        if (array_key_exists('teacher_id', $data) || array_key_exists('auditorium_code', $data)) {
            $this->recordUnitAssignment($card);
        }
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
            $this->whereDepartmentTeacher($q, $context);
        } elseif ($request->filled('kafedra')) {
            // HEMIS dagi kafedrasi + shu kafedraga mudir qilib tayinlanganlar (HEMIS da boshqa bo'limda bo'lsa ham)
            $headDeptIds = Schema::hasColumn('teachers', 'head_department_hemis_id')
                ? Department::where('name', 'like', '%' . $request->kafedra . '%')->pluck('department_hemis_id')->all()
                : [];
            $q->where(function ($w) use ($request, $headDeptIds) {
                $w->where('department', 'like', '%' . $request->kafedra . '%');
                if ($headDeptIds) {
                    $w->orWhereIn('head_department_hemis_id', $headDeptIds);
                }
                if (Schema::hasTable('teacher_employments')) {
                    $w->orWhereIn('id', $this->employmentTeacherIdsByDepartmentName($request->kafedra));
                }
            });
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

    /**
     * Kafedra mudiri biriktira oladigan o'qituvchilar:
     *   - HEMIS dagi kafedrasi shu kafedra bo'lganlar;
     *   - mudirning o'zi (HEMIS da boshqa bo'limda bo'lsa ham) va shu kafedraga
     *     mudir qilib tayinlanganlar;
     *   - joriy o'quv yilida shu kafedra fanlaridan dars beradiganlar (HEMIS
     *     yuklamasi) — o'rindoshlar va kafedra bo'linganda eski kafedrada
     *     qolgan o'qituvchilar ham chiqsin.
     */
    private function whereDepartmentTeacher($q, array $context): void
    {
        $deptId = (int) $context['department_hemis_id'];
        // Kafedra bo'lingan bo'lsa — eski kafedra o'qituvchilari ham (config/kafedra.php)
        $deptIds = array_values(array_unique(array_merge([$deptId], array_map('intval', config('kafedra.predecessors.' . $deptId, [])))));

        // HEMIS yuklamasi: shu (va eski) kafedra fanlaridan joriy yoki o'tgan o'quv yilida dars bergan
        // (yangi yil yuklamasi hali kiritilmagan bo'lishi mumkin; dekan/o'rindoshlar HEMIS da boshqa bo'limda)
        $year = (int) DB::table('semesters')->where('current', true)->max('education_year');
        $load = DB::table('curriculum_subject_teachers')
            ->whereIn('department_id', $deptIds)
            ->where('active', true)
            ->when($year, fn ($w) => $w->where('education_year_id', '>=', $year - 1));

        $q->where(function ($w) use ($deptId, $deptIds, $context, $load) {
            $w->whereIn('department_hemis_id', $deptIds)
                ->orWhere('head_department_hemis_id', $deptId)
                ->orWhere('id', $context['actor_id'])
                ->orWhereIn('hemis_id', (clone $load)->whereNotNull('employee_id')->select('employee_id'))
                ->orWhereIn('full_name', (clone $load)->whereNotNull('employee_name')->select('employee_name'));
            // HEMIS da shu kafedrada o'rindosh yoki soatbay ishlaydiganlar (asosiy ish joyi boshqa bo'limda)
            if (Schema::hasTable('teacher_employments')) {
                $w->orWhereIn('id', DB::table('teacher_employments')
                    ->whereIn('department_hemis_id', $deptIds)
                    ->where('is_working', true)
                    ->select('teacher_id'));
            }
        });
    }

    /** HEMIS da shu nomdagi kafedrada ishlayotgan (o'rindosh/soatbay ham) xodimlar ID lari — kafedra nomi bo'yicha qidiruv uchun. */
    private function employmentTeacherIdsByDepartmentName(string $kafedra)
    {
        return DB::table('teacher_employments')
            ->where('department_name', 'like', '%' . trim($kafedra) . '%')
            ->where('is_working', true)
            ->select('teacher_id');
    }

    private function isDepartmentTeacher(Teacher $teacher, array $context): bool
    {
        $q = Teacher::where('id', $teacher->id);
        $this->whereDepartmentTeacher($q, $context);

        return $q->exists();
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

        $rows = ($this->isHemisBoard($board) ? $this->hemisSubjectRows($board) : DB::table('manual_curriculum_subjects as s')
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
            ->get());

        [$kafMap, $overrides] = $this->buildKafedraMap();
        $weeks = max(1, (int) $board->weeks);
        $seasonLookup = $this->subjectSeasonLookup($board);

        // Yo'nalish+kurs bo'yicha hafta soni doska sukutidan farq qilishi mumkin —
        // kartochka yaratish (assembleRows) aynan shu sozlamani ishlatadi, shuning
        // uchun haftalik yuk hisobi ham xuddi shu manbadan olinishi kerak.
        $gset = TimetableGridSetting::where('board_id', $board->id)->get()->filter(fn ($g) => !(int) ($g->curriculum_hemis_id ?? 0))
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
            $programId = (int) ($r->curriculum_hemis_id ?? 0);
            $rowWeeks = (int) ($this->calendarSetting($board, $r->faculty_name ?? $facName, (string) $r->specialty_name, $course, $programId)?->weeks ?? $weeks);
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
                'curriculum_hemis_id' => $programId,
                'subject_name'   => $r->subject_name,
                'kafedra_name'   => $this->rowKafedra($overrides, $kafMap, $r, (string) $r->specialty_name, (int) $course),
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

    /** Joriy foydalanuvchining o'qituvchi (teachers.id) identifikatori, bo'lmasa -1 (hech narsa mos kelmaydi). */
    private function timetableTeacherId(Request $request): int
    {
        $actor = $this->timetableActor($request);
        if ($actor instanceof Teacher) {
            return (int) $actor->id;
        }
        $teacherId = $actor ? (int) ($actor->teacher_id ?? 0) : 0;
        // Web guard: fan mas'uli boshqa sahifalarda ham email -> Teacher.login orqali bog'lanadi.
        if ($teacherId <= 0 && $actor instanceof \App\Models\User && trim((string) $actor->email) !== '') {
            $teacherId = (int) Teacher::where('login', $actor->email)->value('id');
        }

        return $teacherId > 0 ? $teacherId : -1;
    }

    private function isOquvBolimiRole(string $role): bool
    {
        return in_array($role, ['oquv_bolimi', 'oquv_bolimi_boshligi'], true);
    }

    /** Rol bo'yicha sikl jadvalining boshlang'ich qatlami: o'quv bo'limi — tashqi, qolganlar — ichki. */
    private function cycleDefaultLayer(string $role): string
    {
        return $this->isOquvBolimiRole($role) ? 'external' : 'internal';
    }

    /**
     * Qatlamni kim tahrirlaydi: tashqi — o'quv bo'limi, ichki — kafedra mudiri,
     * admin/superadmin — ikkalasini. Boshqalar faqat ko'radi.
     */
    private function cycleLayerEditable(string $role, string $layer): bool
    {
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return true;
        }
        return $layer === 'external'
            ? $this->isOquvBolimiRole($role)
            : $role === 'kafedra_mudiri';
    }

    private function cycleLayerDenied(string $layer)
    {
        return response()->json(['error' => $layer === 'external'
            ? 'Tashqi dars jadvalini faqat o‘quv bo‘limi tahrirlaydi.'
            : 'Ichki dars jadvalini faqat kafedra mudiri tahrirlaydi.'], 403);
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
        // Admin tanlagan kafedra (HEMIS da boshqa bo'limda bo'lsa ham), aks holda HEMIS dagi
        $departmentHemisId = $actor->head_department_hemis_id ?: ($actor->department_hemis_id ?? null);
        if ($departmentHemisId) {
            $department = Department::where('department_hemis_id', $departmentHemisId)->first();
        }

        $departmentName = trim((string) ($actor->department ?? ''));
        if (!$department && $departmentName !== '' && !$actor->head_department_hemis_id) {
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
            if (!$this->isDepartmentTeacher($teacher, $context)) {
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

    /**
     * Excel: kafedralar kesimida o'qituvchi va xona biriktirish holati
     * (faqat admin / superadmin). Dars birligi "biriktirilgan" — barcha
     * kartalarida o'qituvchi (xona) bo'lsa.
     */
    public function assignmentReport(Request $request, TimetableBoard $board)
    {
        abort_unless(in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true), 403, 'Hisobot faqat admin va registrator ofisi uchun.');

        $units = [];
        $approved = $this->approvedOqimGroupKeys($board);
        foreach (TimetableCard::where('board_id', $board->id)->get() as $c) {
            if (!$this->cardInApprovedOqim($c, $approved)) {
                continue;   // oqimdan olib tashlangan guruh
            }
            $k = $this->unitKey($c);
            $units[$k] ??= [
                'kafedra' => trim((string) $c->kafedra_name) ?: '— kafedra aniqlanmagan —',
                'subject' => (string) $c->subject_name,
                'scope' => trim(($c->faculty_name ? $c->faculty_name . ' · ' : '') . $c->specialty_name . ' · ' . (int) $c->course . '-kurs'),
                'type' => $c->training_type === 'lecture' ? "Ma'ruza" : 'Amaliy',
                'group' => (string) ($c->training_type === 'lecture' ? ($c->oqim_label ?: 'oqim') : $c->group_name),
                'cards' => 0, 'with_teacher' => 0, 'with_room' => 0,
                'teacher' => null, 'room' => null,
            ];
            $units[$k]['cards']++;
            if ($c->teacher_id) {
                $units[$k]['with_teacher']++;
                $units[$k]['teacher'] ??= $c->teacher_name;
            }
            if ($c->auditorium_code) {
                $units[$k]['with_room']++;
                $units[$k]['room'] ??= $c->auditorium_name ?: $c->auditorium_code;
            }
        }

        $kafedras = [];
        $missing = [];
        foreach ($units as $u) {
            $hasTeacher = $u['with_teacher'] === $u['cards'];
            $hasRoom = $u['with_room'] === $u['cards'];
            $kafedras[$u['kafedra']] ??= ['name' => $u['kafedra'], 'units' => 0, 'teacher' => 0, 'room' => 0, 'full' => 0, 'subject_set' => []];
            $kafedras[$u['kafedra']]['units']++;
            $kafedras[$u['kafedra']]['teacher'] += $hasTeacher ? 1 : 0;
            $kafedras[$u['kafedra']]['room'] += $hasRoom ? 1 : 0;
            $kafedras[$u['kafedra']]['full'] += ($hasTeacher && $hasRoom) ? 1 : 0;
            $kafedras[$u['kafedra']]['subject_set'][mb_strtolower($u['subject'])] = true;
            if (!$hasTeacher || !$hasRoom) {
                $missing[] = $u + [
                    // Qisman biriktirilgan bo'lsa ham "biriktirilmagan" deb ko'rsatiladi
                    'teacher' => $hasTeacher ? $u['teacher'] : null,
                    'room' => $hasRoom ? $u['room'] : null,
                ];
            }
        }
        foreach ($kafedras as &$k) {
            $k['subjects'] = count($k['subject_set']);
            unset($k['subject_set']);
        }
        unset($k);
        uasort($kafedras, fn ($a, $b) => strcmp(mb_strtolower($a['name']), mb_strtolower($b['name'])));
        usort($missing, fn ($a, $b) => [mb_strtolower($a['kafedra']), mb_strtolower($a['subject']), $a['scope'], $a['group']]
            <=> [mb_strtolower($b['kafedra']), mb_strtolower($b['subject']), $b['scope'], $b['group']]);

        $title = trim(($board->name ?? '') ?: ('Doska #' . $board->id));

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\TimetableAssignmentReportExport($kafedras, $missing, $title),
            'biriktirish-hisoboti-' . now('Asia/Tashkent')->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * Tasdiqlangan oqim(lar)dagi guruh nomlari kalitlari, fakultet bo'yicha: [fakultet => [guruh kaliti => true]]
     * (to'liq nom va til qo'shimchasisiz). Fakultet — kartalar yaratilgandagidek: blokning department_name'i, bo'lmasa
     * snapshot fakulteti. Hech qaysi oqim tasdiqlanmagan bo'lsa — null (filtrlanmaydi). Oqimdan olib tashlangan guruh
     * (HEMIS'da hali faol bo'lsa ham) bu yerda yo'q.
     */
    private function approvedOqimGroupKeys(TimetableBoard $board): ?array
    {
        $snaps = $this->boardSnapshots($board);
        if (!$snaps) {
            return null;
        }
        $facMap = \App\Models\Department::where('structure_type_code', 11)->pluck('name', 'id')->all();
        $keys = [];
        foreach ($snaps as $fk => $snap) {
            $facName = (string) ($facMap[(int) $fk] ?? ($facMap[$fk] ?? ''));
            foreach ($snap->data ?? [] as $bl) {
                $fac = mb_strtolower(trim((string) ($bl['department_name'] ?? '')) ?: trim($facName));
                // Fakultet guruhi qolmagan bo'lsa ham "tasdiqlangan" — eski kartalari chiqmaydi. Fakulteti aniqlanmagan
                // ('' — har qaysi fakultetga tegishli) bo'sh blok esa hech narsani yashirmasin.
                if ($fac !== '') {
                    $keys[$fac] ??= [];
                }
                foreach ($bl['courses'] ?? [] as $co) {
                    foreach ($co['oqims'] ?? [] as $oq) {
                        foreach ($oq['rows'] ?? [] as $r) {
                            $n = trim((string) ($r['name'] ?? ''));
                            if ($n !== '') {
                                $keys[$fac][$this->groupNameKey($n)] = true;
                                $keys[$fac][$this->groupNameKey((string) preg_replace('/\s*\(.*$/u', '', $n))] = true;
                            }
                        }
                    }
                }
            }
        }

        return $keys ?: null;
    }

    /**
     * Karta o'z fakultetining tasdiqlangan oqimidagi kamida bitta guruhga tegishlimi. Fakultetida oqim hali
     * tasdiqlanmagan bo'lsa — ha (filtrlanmaydi); bir xil nomli guruh boshqa fakultet oqimida bo'lishi yetmaydi.
     */
    private function cardInApprovedOqim(TimetableCard $c, ?array $keys): bool
    {
        $fac = mb_strtolower(trim((string) $c->faculty_name));
        if ($keys === null || (!isset($keys[$fac]) && !isset($keys['']))) {
            return true;
        }
        // '' — fakulteti aniqlanmagan bloklar: har qaysi fakultet kartasi uchun.
        $own = ($keys[$fac] ?? []) + ($keys[''] ?? []);
        foreach ($c->occupiedGroups() as $g) {
            if (isset($own[$this->groupNameKey((string) $g)]) || isset($own[$this->groupNameKey((string) preg_replace('/\s*\(.*$/u', '', (string) $g))])) {
                return true;
            }
        }

        return false;
    }

    /** Doskadagi dars birliklari + joriy o'qituvchi (biriktirish matritsasi uchun). */
    public function teacherUnits(Request $request, TimetableBoard $board)
    {
        // Biriktirish ro'yxati tasdiqlangan oqim bo'yicha: oqimdan olib tashlangan guruhning kartalari chiqmaydi.
        $approved = $this->approvedOqimGroupKeys($board);
        $cards = TimetableCard::where('board_id', $board->id)->get()
            ->filter(fn (TimetableCard $c) => $this->cardInApprovedOqim($c, $approved));
        // Kafedra mudiri faqat o'z kafedrasiga tegishli dars birliklarini ko'radi
        $ownKafedra = $this->departmentHeadKafedra($request);
        $units = [];
        foreach ($cards as $c) {
            if ($ownKafedra !== null && !$this->sameKafedra($c->kafedra_name, $ownKafedra)) {
                continue;
            }
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
                    'auditorium_code' => $c->auditorium_code, 'auditorium_name' => $c->auditorium_name,
                    'room_mixed'     => false,
                ];
            }
            if ((string) $units[$k]['auditorium_code'] !== (string) $c->auditorium_code) {
                $units[$k]['room_mixed'] = true;
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

    /**
     * Dars birligining barcha kartalariga auditoriyani biriktirish (O'qituvchi
     * biriktirish oynasidan). Kartochkada bitta-bitta tanlangandagi tekshiruvlar
     * bilan bir xil: xona kartaning o'qituvchisiga ruxsat etilgan bo'lishi va
     * joylashgan kartalarda vaqt to'qnashuvi bo'lmasligi kerak. Biror kartada
     * muammo bo'lsa hech narsa o'zgarmaydi.
     */
    public function assignUnitRoom(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'faculty_name'    => 'nullable|string|max:255',
            'specialty_name'  => 'required|string|max:255',
            'course'          => 'required|integer|min:1|max:7',
            'subject_name'    => 'required|string|max:255',
            'training_type'   => 'required|in:lecture,practice',
            'oqim_label'      => 'nullable|string|max:50',
            'group_name'      => 'nullable|string|max:255',
            'auditorium_code' => 'nullable|string|max:50',
        ]);

        $room = !empty($data['auditorium_code'])
            ? Auditorium::where('code', $data['auditorium_code'])->first()
            : null;
        if (!empty($data['auditorium_code']) && !$room) {
            return response()->json(['error' => 'Auditoriya topilmadi.'], 422);
        }

        $cards = $this->unitCardsQuery($board, $data)->get();
        if ($cards->isEmpty()) {
            return response()->json(['error' => 'Bu dars birligida karta topilmadi.'], 422);
        }
        if ($denied = $this->denyForeignKafedra($request, $cards)) {
            return $denied;
        }

        $roomMap = $room ? $this->auditoriumTeacherMap() : [];
        $problems = [];
        foreach ($cards as $card) {
            if ($room && !$this->auditoriumAllowedForCard($room, $card, $roomMap)) {
                $problems[] = "Xona «{$room->name}» kartaning o'qituvchisiga biriktirilmagan";
                break;
            }
            $card->auditorium_code = $room?->code;
            $card->auditorium_name = $room?->name;
            if ($room && $card->day && $card->pair) {
                foreach ($this->findConflicts($card, $card->day, $card->pair, (int) ($card->start_half ?? 0)) as $conflict) {
                    $problems[] = $conflict;
                }
            }
        }
        if ($problems) {
            return response()->json(['error' => implode(' · ', array_slice(array_unique($problems), 0, 3))], 422);
        }

        DB::transaction(function () use ($cards) {
            foreach ($cards as $card) {
                $card->save();
            }
        });
        $this->recordUnitAssignment($cards->first());

        return response()->json([
            'ok' => true,
            'auditorium_code' => $room?->code,
            'auditorium_name' => $room?->name,
            'affected' => $cards->count(),
        ]);
    }

    /**
     * Kafedra mudiri bo'lsa — uning kafedrasi nomlari, aks holda null (cheklov yo'q).
     * Kartalardagi kafedra nomi o'quv rejadan olinadi va HEMIS da kafedra qayta
     * nomlangan bo'lsa eski nomda qoladi, shuning uchun o'quv rejada shu kafedra
     * ID siga bog'langan barcha nomlar ham qo'shiladi.
     */
    private function departmentHeadKafedra(Request $request): ?array
    {
        if ($this->timetableActiveRole($request) !== 'kafedra_mudiri') {
            return null;
        }

        $context = $this->departmentHeadContext($request);
        $names = DB::table('curriculum_subjects')
            ->where('department_id', $context['department_hemis_id'])
            ->whereNotNull('department_name')->where('department_name', '!=', '')
            ->distinct()->pluck('department_name')->all();
        $names[] = (string) $context['department_name'];

        return array_values(array_unique($names));
    }

    /**
     * Fan mas'uli fanlari kalitlari: "fakultet|specKey|kurs|normFan" => true (fakultet bo'lmasa prefikssiz).
     * Bo'lakli fan ("Fan (a)") uchun asosiy nom ham qo'shiladi — umumiy ma'ruza kartasi ham ko'rinsin.
     */
    private function responsibleSubjectKeys(Request $request): array
    {
        $tid = $this->timetableTeacherId($request);
        if ($tid <= 0 || !Schema::hasTable('teacher_responsible_subjects')) {
            return [];
        }
        $rows = DB::table('teacher_responsible_subjects as t')
            ->join('curriculum_subjects as cs', 'cs.id', '=', 't.curriculum_subject_id')
            ->join('curricula as c', 'c.curricula_hemis_id', '=', 'cs.curricula_hemis_id')
            ->join('specialties as sp', 'sp.specialty_hemis_id', '=', 'c.specialty_hemis_id')
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->where('t.teacher_id', $tid)
            ->get(['cs.subject_name', 'cs.semester_code', 'sp.name as specialty_name', 'f.name as faculty_name']);
        $keys = [];
        foreach ($rows as $r) {
            $sem = (int) $r->semester_code - 10;
            if ($sem < 1) {
                continue;
            }
            [$facultyAlias, $spec] = $this->curriculumScopeAlias((string) $r->specialty_name);
            $faculty = trim((string) ($r->faculty_name ?: $facultyAlias));
            $prefix = ($faculty !== '' ? $this->specKey($faculty) . '|' : '')
                . $this->specKey($spec) . '|' . (int) ceil($sem / 2) . '|';
            $keys[$prefix . $this->normSubject((string) $r->subject_name)] = true;
            $base = $this->cycleSubjectVariantBase((string) $r->subject_name);
            if ($base !== null) {
                $keys[$prefix . $this->normSubject($base)] = true;
            }
        }

        return $keys;
    }

    private function responsibleCardKey($card): string
    {
        $card = (object) (is_array($card) ? $card : ($card instanceof TimetableCard ? $card->getAttributes() : (array) $card));

        return $this->specKey((string) ($card->specialty_name ?? '')) . '|' . (int) ($card->course ?? 0) . '|' . $this->normSubject((string) ($card->subject_name ?? ''));
    }

    /** Doskadagi mas'ul fanlarning aniq bo'laklari va umumiy kartalari id lari. */
    private function responsibleCardIds(Request $request, $cards): array
    {
        try {
            $keys = $this->responsibleSubjectKeys($request);
        } catch (\Throwable $e) {
            return [];
        }
        if (!$keys) {
            return [];
        }
        $ids = [];
        foreach ($cards as $c) {
            if ($this->responsibleMatch($keys, $c)) {
                $ids[] = (int) (is_array($c) ? $c['id'] : $c->id);
            }
        }

        return $ids;
    }

    private function responsibleMatch(array $keys, $card): bool
    {
        if (!$keys) {
            return false;
        }
        $key = $this->responsibleCardKey($card);
        $faculty = (string) (is_array($card) ? ($card['faculty_name'] ?? '') : ($card->faculty_name ?? ''));
        $facultyPrefix = $this->specKey($faculty) . '|';
        // Asosiy nom kaliti umumiy kartaga tegishli; boshqa bo'lak unga aylantirilmaydi.
        return isset($keys[$facultyPrefix . $key]) || isset($keys[$key]);
    }

    /** departmentHeadKafedra, lekin kafedra aniqlanmasa so'rovni yiqitmaydi (null). */
    private function departmentHeadKafedraSafe(Request $request): ?array
    {
        try {
            return $this->departmentHeadKafedra($request);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return null;
        }
    }

    /** Kafedra nomi berilgan nom(lar)dan biriga mosmi (katta-kichik harf, bo'shliq, "kafedrasi" so'zi hisobga olinmaydi). */
    private function sameKafedra(?string $a, string|array|null $b): bool
    {
        if (is_array($b)) {
            foreach ($b as $name) {
                if ($this->sameKafedra($a, $name)) {
                    return true;
                }
            }

            return false;
        }

        $norm = function (?string $value) {
            $value = mb_strtolower(trim((string) $value));
            $value = preg_replace('/\s*kafedra(si)?\s*$/u', '', $value);

            return preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
        };

        return $norm($a) !== '' && $norm($a) === $norm($b);
    }

    /**
     * Kafedra mudiri uchun: shu yo'nalish+kurs bo'yicha o'z kafedrasiga tegishli sikl fanlari
     * [mb_strtolower(trim(nom)) => asl nom]. Boshqa rollar uchun null (cheklov yo'q).
     */
    private function ownKafedraCycleSubjects(Request $request, TimetableBoard $board, string $specialty, int $course): ?array
    {
        $ownKafedra = $this->departmentHeadKafedra($request);
        if ($ownKafedra === null) {
            return null;
        }
        $own = [];
        TimetableCard::where('board_id', $board->id)
            ->where('specialty_name', $specialty)
            ->where('course', $course)
            ->get(['subject_name', 'kafedra_name'])
            ->each(function ($card) use (&$own, $ownKafedra) {
                if ($this->sameKafedra($card->kafedra_name, $ownKafedra)) {
                    $own[mb_strtolower(trim((string) $card->subject_name))] = (string) $card->subject_name;
                }
            });

        return $own;
    }

    /** Kafedra mudiri boshqa kafedraning darsiga biriktira olmasin. */
    private function denyForeignKafedra(Request $request, $cards)
    {
        $ownKafedra = $this->departmentHeadKafedra($request);
        if ($ownKafedra === null) {
            return null;
        }
        foreach ($cards as $card) {
            if (!$this->sameKafedra($card->kafedra_name, $ownKafedra)) {
                return response()->json(['error' => "Bu dars sizning kafedrangizga tegishli emas."], 403);
            }
        }

        return null;
    }

    /** Dars birligi kartalari: yo'nalish + kurs + fan + tur + oqim/guruh (+ fakultet). */
    private function unitCardsQuery(TimetableBoard $board, array $data)
    {
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

        return $q;
    }

    /**
     * Birinchi marta biriktirishmi: o'zgaradigan kartalarda o'qituvchi/xona hali yo'q (yoki xuddi shu qiymat).
     * Shunday bo'lsa kafedra mudirining amali registrator tasdig'isiz qo'llanadi; mavjud o'qituvchi/xonani
     * almashtirish yoki olib tashlash (va sikl vaqtini o'zgartirish) esa tasdiqqa boradi.
     */
    public function isFirstAssignment(TimetableBoard $board, string $route, array $p): bool
    {
        $fresh = function ($cards, string $field, $new): bool {
            $new = ($new === null || $new === '') ? null : (string) $new;
            if ($cards->isEmpty()) {
                return false;
            }
            foreach ($cards as $c) {
                $cur = ($c->{$field} === null || $c->{$field} === '') ? null : (string) $c->{$field};
                if ($cur !== null && $cur !== $new) {
                    return false;   // mavjud qiymat o'zgaradi yoki olib tashlanadi
                }
            }

            return true;
        };
        $unit = fn () => empty($p['specialty_name']) || empty($p['course']) || empty($p['subject_name'])
            || !in_array($p['training_type'] ?? null, ['lecture', 'practice'], true)
            ? collect() : $this->unitCardsQuery($board, $p)->get();

        if ($route === 'admin.timetable.assign-teacher') {
            return !empty($p['teacher_id']) && $fresh($unit(), 'teacher_id', $p['teacher_id']);
        }
        if ($route === 'admin.timetable.assign-room') {
            return !empty($p['auditorium_code']) && $fresh($unit(), 'auditorium_code', $p['auditorium_code']);
        }
        if ($route === 'admin.timetable.cycle-assign') {
            if (empty($p['specialty_name']) || empty($p['course']) || empty($p['subject_name']) || empty($p['group_name'])
                || !Schema::hasTable('timetable_cycle_placements')) {
                return false;
            }
            $placement = $this->findCyclePlacement($board, $p);
            if (!$placement || (trim((string) ($p['lesson_time'] ?? '')) ?: null) !== ($placement->lesson_time ?: null)) {
                return false;   // vaqt o'zgaradi
            }
            $cards = $this->cycleFlowCards($board, $p['specialty_name'], (int) $p['course'], $p['subject_name'], $p['group_name'],
                $p['training_type'] ?? null, $p['view'] ?? 'flow');

            return (!empty($p['teacher_id']) || !empty($p['auditorium_code']))
                && $fresh($cards, 'teacher_id', $p['teacher_id'] ?? null)
                && $fresh($cards, 'auditorium_code', $p['auditorium_code'] ?? null);
        }

        return false;
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

        if ($denied = $this->denyForeignKafedra($request, (clone $q)->get(['id', 'kafedra_name']))) {
            return $denied;
        }

        $teacherName = null;
        if (!empty($data['teacher_id'])) {
            $t = Teacher::findOrFail($data['teacher_id']);
            if ($this->timetableActiveRole($request) === 'kafedra_mudiri') {
                $context = $this->departmentHeadContext($request);
                if (!$this->isDepartmentTeacher($t, $context)) {
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
        $this->recordUnitAssignment((clone $q)->first());

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
            // Oynada faqat dam olish kunlari va ikki belgi bor — qolgan kalitlar (fan ranglari,
            // semestr boshi, bayramlar, tasdiq rejimi) saqlanganda yo'qolmasin. Tasdiq rejimi
            // alohida (registrator) boshqariladi, shu yerdan o'zgartirilmaydi.
            'settings'         => isset($data['settings'])
                ? array_diff_key($data['settings'], ['approval_required' => true]) + ['days_off' => []] + ($board->settings ?: [])
                : $board->settings,
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
