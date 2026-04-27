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
 * O'qituvchi dashbordi uchun joriy semestrdagi baholash sifati statistikasini
 * hisoblab keshga yozadi. Har kuni 03:00 da bir marta ishlaydi.
 *
 * Kategoriyalar (StudentGrade.created_at_api ni Schedule vaqti va kun chegaralariga
 * solishtirib aniqlanadi):
 *   - dars_vaqtida: dars boshlanishi va tugashi oralig'ida (yoki undan oldin)
 *   - ish_vaqtida:  dars tugagandan keyin shu kuni 18:00 gacha
 *   - kech:         shu kuni 18:00 — 23:59 oralig'ida
 *   - baholanmagan: 23:59 dan keyin baholangan YOKI umuman baholanmagan
 *                   (grade IS NULL va NB ham qo'yilmagan)
 *
 * Filtrlar:
 *   - Faqat joriy semestr (Semester.current = true)
 *   - Mashg'ulot turi: amaliy, seminar, laboratoriya
 *     (ma'ruza, mustaqil ta'lim, kurs ishi, oraliq nazorat, oski, testlar — chiqib ketadi)
 *   - Otrobotka/qayta baho hisoblanmaydi (retake_grade IS NULL, status != 'retake')
 *   - Mustaqil ta'lim baholari hisoblanmaydi (independent_id IS NULL)
 *
 * NB (reason='absent' yoki status='absent') "BAHOLANGAN" deb hisoblanadi —
 * created_at_api qaysi vaqtda bo'lsa o'sha kategoriyaga tushadi.
 * (JournalController:1706 — "Jurnal NB ni student_grades.reason='absent' dan o'qiydi")
 */
class CalculateTeacherDashboardStats extends Command
{
    protected $signature = 'dashboard:teacher-stats {--teacher= : Faqat bitta o\'qituvchi (employee_id) uchun hisoblash}';

    protected $description = "O'qituvchi dashbordi statistikasini joriy semestr bo'yicha hisoblab keshga yozadi";

    public const CACHE_TTL_SECONDS = 25 * 3600; // 24 soat + 1 soat buffer
    public const CACHE_VERSION = 'v1';

    /**
     * Top 10 ga kirishi uchun o'qituvchining minimal baholar soni —
     * chunki 1/1 = 100% kabi statistik shovqinni cheklaydi.
     */
    public const MIN_GRADES_FOR_TOP10 = 30;

    /**
     * Loyihada keng qo'llanadigan filtr (JournalController, QuizResultController
     * va h.k. dan olindi). "Kurs ishi" foydalanuvchi talabiga ko'ra qo'shildi.
     */
    public const EXCLUDED_TRAINING_TYPES = [
        "Ma'ruza",
        "Mustaqil ta'lim",
        "Oraliq nazorat",
        "Oski",
        "Yakuniy test",
        "Quiz test",
        "Kurs ishi",
    ];

    public function handle(): int
    {
        $startTime = microtime(true);

        $semester = Semester::where('current', true)->first();
        if (!$semester) {
            $this->error('Joriy semestr topilmadi (Semester.current = true).');
            Log::warning('[TeacherDashboardStats] Joriy semestr topilmadi.');
            return self::FAILURE;
        }

        $this->info("Joriy semestr: {$semester->code} — {$semester->name}");

        $teacherFilter = $this->option('teacher');
        $rows = $this->fetchStats($semester->code, $teacherFilter);
        $totalTeachers = count($rows);
        $this->info("{$totalTeachers} ta o'qituvchi uchun ma'lumot topildi.");

        $top10Source = [];
        $cachedCount = 0;

        foreach ($rows as $row) {
            $stats = $this->buildStatsArray($row, $semester);

            Cache::put(
                self::employeeCacheKey($row->employee_id, $semester->id),
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
            self::top10CacheKey($semester->id),
            [
                'items'        => $top10,
                'last_updated' => Carbon::now('Asia/Tashkent')->toDateTimeString(),
            ],
            self::CACHE_TTL_SECONDS
        );

        $elapsed = round(microtime(true) - $startTime, 1);
        $this->info("Tayyor: {$cachedCount} o'qituvchi keshga yozildi, top 10 hisoblandi ({$elapsed}s).");

        // nightly:run ichida ishlasa, progress xabari yuboriladi
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

    private function fetchStats(string $semesterCode, ?string $teacherFilter): array
    {
        $excludedPlaceholders = implode(',', array_fill(0, count(self::EXCLUDED_TRAINING_TYPES), '?'));
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
                    WHEN (grade IS NULL AND (reason IS NULL OR reason != 'absent') AND (status IS NULL OR status != 'absent'))
                         OR ((grade IS NOT NULL OR reason = 'absent' OR status = 'absent')
                             AND (created_at_api IS NULL
                                  OR created_at_api > TIMESTAMP(DATE(lesson_date), '23:59:59')))
                    THEN 1 ELSE 0 END) AS baholanmagan,
                COUNT(*) AS jami
            FROM student_grades
            WHERE deleted_at IS NULL
                AND semester_code = ?
                AND training_type_name NOT IN ({$excludedPlaceholders})
                AND independent_id IS NULL
                AND retake_grade IS NULL
                AND (status IS NULL OR status != 'retake')
                AND lesson_date IS NOT NULL
                AND lesson_pair_end_time IS NOT NULL
                AND lesson_pair_end_time != ''
                {$teacherFilterSql}
            GROUP BY employee_id
        SQL;

        $bindings = array_merge([$semesterCode], self::EXCLUDED_TRAINING_TYPES);
        if ($teacherFilter) {
            $bindings[] = $teacherFilter;
        }

        return DB::select($sql, $bindings);
    }

    private function buildStatsArray(object $row, Semester $semester): array
    {
        $jami = (int) $row->jami;

        $stats = [
            'dars_vaqtida' => (int) $row->dars_vaqtida,
            'ish_vaqtida'  => (int) $row->ish_vaqtida,
            'kech'         => (int) $row->kech,
            'baholanmagan' => (int) $row->baholanmagan,
            'jami'         => $jami,
            'semester_id'   => $semester->id,
            'semester_code' => $semester->code,
            'semester_name' => $semester->name,
            'last_updated'  => Carbon::now('Asia/Tashkent')->toDateTimeString(),
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

    public static function employeeCacheKey(int|string $employeeId, int $semesterId): string
    {
        return 'teacher_dashboard_stats:' . self::CACHE_VERSION . ":{$employeeId}:{$semesterId}";
    }

    public static function top10CacheKey(int $semesterId): string
    {
        return 'teacher_dashboard_top10:' . self::CACHE_VERSION . ":{$semesterId}";
    }
}
