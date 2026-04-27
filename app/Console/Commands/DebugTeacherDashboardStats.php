<?php

namespace App\Console\Commands;

use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Teacher dashboard statistikasini sinash uchun diagnostic command.
 * Top 5 o'qituvchi va bitta tanlangan o'qituvchining barcha vaqtlarini
 * ko'rsatadi — natijani chatga ko'chirib yuborish uchun.
 */
class DebugTeacherDashboardStats extends Command
{
    protected $signature = 'dashboard:teacher-stats:debug {--teacher= : Bitta o\'qituvchi (employee_id) bo\'yicha batafsil}';
    protected $description = 'Teacher dashboard statistikasini sinash uchun diagnostic chiqishi';

    public function handle(): int
    {
        // Joriy o'quv yili — current=1 yozuvlar orasidagi eng katta education_year
        $currentYear = Semester::where('current', true)->max('education_year');
        if (!$currentYear) {
            $this->error('Joriy o\'quv yili topilmadi.');
            return self::FAILURE;
        }

        // Hozir bahor (juft) yoki kuz (toq) ekanini aniqlash
        $month = (int) Carbon::now('Asia/Tashkent')->month;
        $isSpring = $month >= 1 && $month <= 6;
        $period = $isSpring ? 'bahor (juft semestrlar)' : 'kuz (toq semestrlar)';

        // Faqat shu o'quv yilidagi current=1 semestrlardan juft/toq filtrlash
        $currentCodes = Semester::where('current', true)
            ->where('education_year', $currentYear)
            ->get(['code', 'name'])
            ->filter(function ($s) use ($isSpring) {
                if (!preg_match('/^(\d+)-?\s*semestr/i', $s->name, $matches)) {
                    return false;
                }
                $num = (int) $matches[1];
                return $isSpring ? ($num % 2 === 0) : ($num % 2 === 1);
            })
            ->pluck('code')->unique()->values()->all();
        if (empty($currentCodes)) {
            $this->error('Joriy semestrlar topilmadi.');
            return self::FAILURE;
        }
        $this->info("=== Joriy o'quv yili: {$currentYear} ({$period}) ===");
        $this->info('=== Joriy semestr kodlari: ' . implode(', ', $currentCodes) . " ===\n");

        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);
        $subjectPatterns = config('app.grade_excluded_subject_patterns', ['tanishuv amaliyoti', 'quv amaliyoti']);

        $semesterPlaceholders = implode(',', array_fill(0, count($currentCodes), '?'));
        $excludedCodePlaceholders = implode(',', array_fill(0, count($excludedCodes), '?'));
        $subjectFilterSql = '';
        foreach ($subjectPatterns as $_) {
            $subjectFilterSql .= " AND LOWER(subject_name) NOT LIKE ?";
        }

        // Asosiy bindings (3 ta SQL hammasi uchun bir xil filter bindings)
        $baseBindings = [];
        foreach ($currentCodes as $c)    { $baseBindings[] = $c; }
        $baseBindings[] = $currentYear;
        foreach ($excludedCodes as $c)   { $baseBindings[] = $c; }
        foreach ($subjectPatterns as $p) { $baseBindings[] = '%' . strtolower($p) . '%'; }

        // 1-bo'lim: Top 5 o'qituvchi
        $this->line("--- TOP 5 o'qituvchi (joriy semestrlar bo'yicha) ---");
        $sql1 = "
            SELECT
                employee_id,
                MAX(employee_name) AS employee_name,
                COUNT(*) AS jami,
                SUM(CASE WHEN grade IS NOT NULL THEN 1 ELSE 0 END) AS bilan_bahosi,
                SUM(CASE WHEN reason = 'absent' THEN 1 ELSE 0 END) AS nb_count,
                SUM(CASE WHEN grade IS NULL AND (reason IS NULL OR reason != 'absent') AND (status IS NULL OR status != 'absent') THEN 1 ELSE 0 END) AS bosh,
                MIN(created_at_api) AS eng_erta_baho,
                MAX(created_at_api) AS eng_kech_baho,
                MIN(lesson_date) AS eng_erta_dars,
                MAX(lesson_date) AS eng_kech_dars
            FROM student_grades
            WHERE deleted_at IS NULL
                AND semester_code IN ({$semesterPlaceholders})
                AND (education_year_code IS NULL OR education_year_code = ?)
                AND training_type_code NOT IN ({$excludedCodePlaceholders})
                {$subjectFilterSql}
                AND independent_id IS NULL
                AND retake_grade IS NULL
                AND (status IS NULL OR status != 'retake')
            GROUP BY employee_id
            ORDER BY jami DESC
            LIMIT 5
        ";
        $rows = DB::select($sql1, $baseBindings);

        $headers = ['employee_id', 'name', 'jami', 'baho', 'NB', 'bosh', 'eng_erta_dars', 'eng_kech_dars'];
        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                $r->employee_id,
                mb_substr($r->employee_name, 0, 25),
                $r->jami,
                $r->bilan_bahosi,
                $r->nb_count,
                $r->bosh,
                $r->eng_erta_dars,
                $r->eng_kech_dars,
            ];
        }
        $this->table($headers, $tableRows);

        // 2-bo'lim: Tanlangan o'qituvchi uchun batafsil
        $teacherFilter = $this->option('teacher');
        if (!$teacherFilter && count($rows) > 0) {
            $teacherFilter = $rows[0]->employee_id;
            $this->line("\n--- Birinchi o'qituvchi uchun batafsil: {$rows[0]->employee_name} (id={$teacherFilter}) ---");
        } elseif ($teacherFilter) {
            $this->line("\n--- Tanlangan o'qituvchi (id={$teacherFilter}) uchun batafsil ---");
        } else {
            return self::SUCCESS;
        }

        $sql2 = "
            SELECT
                COUNT(*) AS jami,
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
                             AND (created_at_api IS NULL OR created_at_api > TIMESTAMP(DATE(lesson_date), '23:59:59')))
                    THEN 1 ELSE 0 END) AS baholanmagan
            FROM student_grades
            WHERE deleted_at IS NULL
                AND semester_code IN ({$semesterPlaceholders})
                AND (education_year_code IS NULL OR education_year_code = ?)
                AND training_type_code NOT IN ({$excludedCodePlaceholders})
                {$subjectFilterSql}
                AND independent_id IS NULL
                AND retake_grade IS NULL
                AND (status IS NULL OR status != 'retake')
                AND lesson_date IS NOT NULL
                AND lesson_pair_end_time IS NOT NULL
                AND lesson_pair_end_time != ''
                AND employee_id = ?
        ";
        $bindings = array_merge($baseBindings, [$teacherFilter]);
        $stat = DB::select($sql2, $bindings)[0] ?? null;

        if ($stat) {
            $this->table(
                ['kategoriya', 'soni'],
                [
                    ['Jami', $stat->jami],
                    ['Dars vaqtida', $stat->dars_vaqtida],
                    ['Ish vaqtida', $stat->ish_vaqtida],
                    ['Kech (18:00-23:59)', $stat->kech],
                    ['Baholanmagan', $stat->baholanmagan],
                ]
            );
        }

        // 3-bo'lim: Birinchi 15 ta yozuvning vaqt tasniflanishi
        $this->line("\n--- So'nggi 15 ta yozuvning vaqt tafsiloti ---");
        $sql3 = "
            SELECT
                DATE(lesson_date) AS dars_sanasi,
                lesson_pair_end_time AS dars_tugash,
                student_hemis_id,
                created_at_api,
                grade,
                reason,
                status,
                training_type_code,
                training_type_name,
                CASE
                    WHEN (grade IS NULL AND (reason IS NULL OR reason != 'absent') AND (status IS NULL OR status != 'absent')) THEN 'BOSH'
                    WHEN created_at_api IS NULL THEN 'NULL_API'
                    WHEN created_at_api <= TIMESTAMP(DATE(lesson_date), lesson_pair_end_time) THEN 'DARS'
                    WHEN created_at_api <= TIMESTAMP(DATE(lesson_date), '18:00:00') THEN 'ISH'
                    WHEN created_at_api <= TIMESTAMP(DATE(lesson_date), '23:59:59') THEN 'KECH'
                    ELSE 'TUGAGAN'
                END AS kategoriya
            FROM student_grades
            WHERE deleted_at IS NULL
                AND semester_code IN ({$semesterPlaceholders})
                AND (education_year_code IS NULL OR education_year_code = ?)
                AND training_type_code NOT IN ({$excludedCodePlaceholders})
                {$subjectFilterSql}
                AND independent_id IS NULL
                AND retake_grade IS NULL
                AND (status IS NULL OR status != 'retake')
                AND employee_id = ?
            ORDER BY lesson_date DESC, created_at_api DESC
            LIMIT 15
        ";
        $details = DB::select($sql3, $bindings);

        $detailRows = [];
        foreach ($details as $d) {
            $detailRows[] = [
                $d->dars_sanasi,
                $d->dars_tugash,
                $d->student_hemis_id,
                $d->created_at_api ?? '-',
                $d->grade ?? '-',
                $d->reason ?? '-',
                $d->status ?? '-',
                $d->training_type_code,
                $d->kategoriya,
            ];
        }
        $this->table(
            ['dars_sana', 'tugash', 'student_id', 'created_at_api', 'grade', 'reason', 'status', 'tt_code', 'kategoriya'],
            $detailRows
        );

        return self::SUCCESS;
    }
}
