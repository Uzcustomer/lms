<?php

namespace App\Console\Commands;

use App\Models\Semester;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * O'qituvchi dashbordi uchun joriy semestrlardagi (juft semestrlar bahorda,
 * toq semestrlar kuzda) baholash sifati statistikasini hisoblab keshga yozadi.
 * Har kuni 03:00 da bir marta ishlaydi.
 *
 * MA'LUMOT MANBASI: faqat student_grades jadvali
 *   - lesson_date           — dars sanasi
 *   - lesson_pair_end_time  — dars tugash vaqti (08:30-09:50 dagi 09:50)
 *   - created_at_api        — Hemisda baho qo'yilgan haqiqiy vaqt
 *                             (created_at LMS import vaqti — 8+ kun kech bo'lishi mumkin)
 *
 * Kategoriyalar (created_at_api ni dars vaqti va kun chegaralariga
 * solishtirib aniqlanadi):
 *   - dars_vaqtida: dars boshlanishi va tugashi oralig'ida (yoki undan oldin)
 *   - ish_vaqtida:  dars tugagandan keyin shu kuni 18:00 gacha
 *   - kech:         shu kuni 18:00 — 23:59 oralig'ida
 *   - baholanmagan: 23:59 dan keyin baholangan YOKI umuman baholanmagan
 *                   (grade IS NULL va NB ham qo'yilmagan)
 *
 * Filtrlar:
 *   - Joriy semestrlar: SELECT DISTINCT code FROM semesters WHERE current = 1
 *     (semestrlar jadvalidagi barcha "current" semestrlar — bahorda 2,4,6,8,10,12)
 *   - Mashg'ulot turi (WHITELIST): faqat Amaliy, Seminar, Klinik mashg'ulot
 *     (apostrof variantlari bilan: "Klinik mashg'ulot" va "Klinik mashgulot")
 *   - Fan nomi: subject_name LIKE chiqib ketadi
 *     ("tanishuv amaliyoti", "quv amaliyoti" — O'quv amaliyoti, Tanishuv amaliyoti)
 *   - Otrobotka/qayta baho hisoblanmaydi (retake_grade IS NULL, status != 'retake')
 *   - Mustaqil ta'lim baholari hisoblanmaydi (independent_id IS NULL)
 *
 * NB (reason='absent' yoki status='absent') "BAHOLANGAN" deb hisoblanadi —
 * created_at_api qaysi vaqtda bo'lsa o'sha kategoriyaga tushadi.
 */
class CalculateTeacherDashboardStats extends Command
{
    protected $signature = 'dashboard:teacher-stats {--teacher= : Faqat bitta o\'qituvchi (employee_id) uchun hisoblash}';

    protected $description = "O'qituvchi dashbordi statistikasini joriy semestrlar bo'yicha hisoblab keshga yozadi";

    public const CACHE_TTL_SECONDS = 25 * 3600; // 24 soat + 1 soat buffer
    public const CACHE_VERSION = 'v2';

    /**
     * Top 10 ga kirishi uchun o'qituvchining minimal baholar soni —
     * 1/1 = 100% kabi statistik shovqinni cheklaydi.
     */
    public const MIN_GRADES_FOR_TOP10 = 30;

    public function handle(): int
    {
        $startTime = microtime(true);

        // Joriy o'quv yili (max education_year among current=1 rows)
        $currentYear = $this->getCurrentEducationYear();
        if (!$currentYear) {
            $this->error('Joriy o\'quv yili topilmadi (semesters.current = 1).');
            Log::warning('[TeacherDashboardStats] Joriy o\'quv yili topilmadi.');
            return self::FAILURE;
        }

        // Joriy o'quv yilidagi current=1 semestrlar (bahorda juft, kuzda toq)
        $currentCodes = $this->getCurrentSemesterCodes($currentYear);
        if (empty($currentCodes)) {
            $this->error('Joriy semestrlar topilmadi.');
            return self::FAILURE;
        }
        $this->info("Joriy o'quv yili: {$currentYear}");
        $this->info('Joriy semestr kodlari: ' . implode(', ', $currentCodes));

        $teacherFilter = $this->option('teacher');
        $rows = $this->fetchStats($currentCodes, $currentYear, $teacherFilter);
        $totalTeachers = count($rows);
        $this->info("{$totalTeachers} ta o'qituvchi uchun ma'lumot topildi.");

        $top10Source = [];
        $cachedCount = 0;

        foreach ($rows as $row) {
            $stats = $this->buildStatsArray($row, $currentCodes, $currentYear);

            Cache::put(
                self::employeeCacheKey($row->employee_id),
                $stats,
                self::CACHE_TTL_SECONDS
            );
            $cachedCount++;

            if ($stats['jami'] >= self::MIN_GRADES_FOR_TOP10) {
                $top10Source[] = [
                    'employee_id'   => (int) $row->employee_id,
                    'employee_name' => $row->employee_name,
                    'foiz'          => $stats['vaqtida_foiz'],
                    'jami'          => $stats['jami'],
                ];
            }
        }

        $top10 = $this->buildTop10($top10Source);
        Cache::put(
            self::top10CacheKey(),
            [
                'items'        => $top10,
                'last_updated' => Carbon::now('Asia/Tashkent')->toDateTimeString(),
            ],
            self::CACHE_TTL_SECONDS
        );

        $elapsed = round(microtime(true) - $startTime, 1);
        $this->info("Tayyor: {$cachedCount} o'qituvchi keshga yozildi, top 10 hisoblandi ({$elapsed}s).");

        if (app()->bound('nightly.progress')) {
            try {
                $cb = app('nightly.progress');
                $cb("📊 Dashboard: {$cachedCount} ta o'qituvchi statistikasi yangilandi");
            } catch (\Throwable $e) {
                // jim
            }
        }

        return self::SUCCESS;
    }

    /**
     * Joriy o'quv yili — current=1 yozuvlar orasidagi eng katta education_year.
     */
    private function getCurrentEducationYear(): ?string
    {
        return Semester::where('current', true)->max('education_year');
    }

    /**
     * Hozirgi davr juftmi (bahor) yoki toqmi (kuz)?
     * Kalendar oyga qarab aniqlanadi:
     *   - Yanvar – Iyun (1–6) = bahor → juft semester raqamlari (2,4,6,8,10,12)
     *   - Iyul – Dekabr (7–12) = kuz → toq semester raqamlari (1,3,5,7,9,11)
     */
    private function isSpringPeriod(): bool
    {
        $month = (int) Carbon::now('Asia/Tashkent')->month;
        return $month >= 1 && $month <= 6;
    }

    /**
     * Joriy o'quv yilidagi semester kodlar — faqat hozirgi davrniki
     * (bahorda juft semestrlar, kuzda toq). HEMIS bazaviy 'current' flagi
     * akademik yilning hammasini "current" deb belgilaydi, shuning uchun
     * semester nomidagi raqamga qarab qo'shimcha filtrlash kerak.
     */
    private function getCurrentSemesterCodes(string $educationYear): array
    {
        $isSpring = $this->isSpringPeriod();

        return Semester::where('current', true)
            ->where('education_year', $educationYear)
            ->get(['code', 'name'])
            ->filter(function ($s) use ($isSpring) {
                // "4-semestr" -> 4
                if (!preg_match('/^(\d+)-?\s*semestr/i', $s->name, $matches)) {
                    return false;
                }
                $num = (int) $matches[1];
                return $isSpring ? ($num % 2 === 0) : ($num % 2 === 1);
            })
            ->pluck('code')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Hisoblanadigan mashg'ulot turlari (WHITELIST):
     *   - Amaliy
     *   - Seminar
     *   - Klinik mashg'ulot (2 ta variant — apostrof bilan va apostrofsiz)
     */
    public const INCLUDED_TRAINING_TYPE_NAMES = [
        'Amaliy',
        'Seminar',
        "Klinik mashg'ulot",
        'Klinik mashgulot',
    ];

    /**
     * Fan nomi LIKE filtrlari — config('app.grade_excluded_subject_patterns')
     * Tanishuv amaliyoti, O'quv amaliyoti chiqib ketadi.
     */
    private function excludedSubjectPatterns(): array
    {
        return config('app.grade_excluded_subject_patterns', ['tanishuv amaliyoti', 'quv amaliyoti']);
    }

    private function fetchStats(array $semesterCodes, string $educationYear, ?string $teacherFilter): array
    {
        $semesterPlaceholders = implode(',', array_fill(0, count($semesterCodes), '?'));

        $includedNames = self::INCLUDED_TRAINING_TYPE_NAMES;
        $includedNamePlaceholders = implode(',', array_fill(0, count($includedNames), '?'));

        $subjectPatterns = $this->excludedSubjectPatterns();
        $subjectFilterSql = '';
        foreach ($subjectPatterns as $_) {
            $subjectFilterSql .= " AND LOWER(subject_name) NOT LIKE ?";
        }

        $teacherFilterSql = $teacherFilter ? "AND employee_id = ?" : "";

        $sql = <<<SQL
            SELECT
                employee_id,
                MAX(employee_name) AS employee_name,
                SUM(CASE
                    WHEN (grade IS NOT NULL OR reason = 'absent' OR status = 'absent')
                         AND created_at_api IS NOT NULL
                         AND created_at_api <= TIMESTAMP(DATE(lesson_date), lesson_pair_end_time)
                    THEN 1 ELSE 0 END) AS dars_vaqtida,
                SUM(CASE
                    WHEN (grade IS NOT NULL OR reason = 'absent' OR status = 'absent')
                         AND created_at_api IS NOT NULL
                         AND created_at_api >  TIMESTAMP(DATE(lesson_date), lesson_pair_end_time)
                         AND created_at_api <= TIMESTAMP(DATE(lesson_date), '18:00:00')
                    THEN 1 ELSE 0 END) AS ish_vaqtida,
                SUM(CASE
                    WHEN (grade IS NOT NULL OR reason = 'absent' OR status = 'absent')
                         AND created_at_api IS NOT NULL
                         AND created_at_api >  TIMESTAMP(DATE(lesson_date), '18:00:00')
                         AND created_at_api <= TIMESTAMP(DATE(lesson_date), '23:59:59')
                    THEN 1 ELSE 0 END) AS kech,
                SUM(CASE
                    WHEN (grade IS NULL
                          AND (reason IS NULL OR reason != 'absent')
                          AND (status IS NULL OR status != 'absent'))
                         OR ((grade IS NOT NULL OR reason = 'absent' OR status = 'absent')
                             AND (created_at_api IS NULL
                                  OR created_at_api > TIMESTAMP(DATE(lesson_date), '23:59:59')))
                    THEN 1 ELSE 0 END) AS baholanmagan,
                COUNT(*) AS jami
            FROM student_grades
            WHERE deleted_at IS NULL
                AND semester_code IN ({$semesterPlaceholders})
                AND (education_year_code IS NULL OR education_year_code = ?)
                AND training_type_name IN ({$includedNamePlaceholders})
                {$subjectFilterSql}
                AND independent_id IS NULL
                AND retake_grade IS NULL
                AND (status IS NULL OR status != 'retake')
                AND lesson_date IS NOT NULL
                AND lesson_pair_end_time IS NOT NULL
                AND lesson_pair_end_time != ''
                {$teacherFilterSql}
            GROUP BY employee_id
        SQL;

        $bindings = [];
        foreach ($semesterCodes as $c)   { $bindings[] = $c; }
        $bindings[] = $educationYear;
        foreach ($includedNames as $n)   { $bindings[] = $n; }
        foreach ($subjectPatterns as $p) { $bindings[] = '%' . strtolower($p) . '%'; }
        if ($teacherFilter) { $bindings[] = $teacherFilter; }

        return DB::select($sql, $bindings);
    }

    private function buildStatsArray(object $row, array $currentCodes, string $educationYear): array
    {
        $jami = (int) $row->jami;

        $stats = [
            'dars_vaqtida'   => (int) $row->dars_vaqtida,
            'ish_vaqtida'    => (int) $row->ish_vaqtida,
            'kech'           => (int) $row->kech,
            'baholanmagan'   => (int) $row->baholanmagan,
            'jami'           => $jami,
            'semester_codes' => $currentCodes,
            'education_year' => $educationYear,
            'last_updated'   => Carbon::now('Asia/Tashkent')->toDateTimeString(),
        ];

        $stats['dars_foiz']         = $jami > 0 ? round($stats['dars_vaqtida'] * 100 / $jami, 1) : 0.0;
        $stats['ish_foiz']          = $jami > 0 ? round($stats['ish_vaqtida']  * 100 / $jami, 1) : 0.0;
        $stats['kech_foiz']         = $jami > 0 ? round($stats['kech']         * 100 / $jami, 1) : 0.0;
        $stats['baholanmagan_foiz'] = $jami > 0 ? round($stats['baholanmagan'] * 100 / $jami, 1) : 0.0;
        $stats['vaqtida_foiz']      = $jami > 0
            ? round(($stats['dars_vaqtida'] + $stats['ish_vaqtida']) * 100 / $jami, 1)
            : 0.0;

        return $stats;
    }

    private function buildTop10(array $top10Source): array
    {
        usort($top10Source, function ($a, $b) {
            if ($a['foiz'] === $b['foiz']) {
                return $b['jami'] <=> $a['jami'];
            }
            return $b['foiz'] <=> $a['foiz'];
        });
        $top10Source = array_slice($top10Source, 0, 10);

        if (empty($top10Source)) {
            return [];
        }

        $employeeIds = array_column($top10Source, 'employee_id');
        $teachers = Teacher::whereIn('hemis_id', $employeeIds)->get()->keyBy('hemis_id');

        $top10 = [];
        foreach ($top10Source as $idx => $entry) {
            $teacher = $teachers->get($entry['employee_id']);
            $top10[] = [
                'rank'         => $idx + 1,
                'employee_id'  => $entry['employee_id'],
                'fio'          => $teacher?->full_name ?? $entry['employee_name'] ?? '—',
                'kafedra'      => $teacher?->department ?? '—',
                'foiz'         => $entry['foiz'],
                'jami'         => $entry['jami'],
            ];
        }
        return $top10;
    }

    public static function employeeCacheKey(int|string $employeeId): string
    {
        return 'teacher_dashboard_stats:' . self::CACHE_VERSION . ":{$employeeId}:current";
    }

    public static function top10CacheKey(): string
    {
        return 'teacher_dashboard_top10:' . self::CACHE_VERSION . ':current';
    }
}
