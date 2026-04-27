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
        $semester = Semester::where('current', true)->first();
        if (!$semester) {
            $this->error('Joriy semestr topilmadi.');
            return self::FAILURE;
        }

        $this->info("=== Joriy semestr: {$semester->code} — {$semester->name} ===\n");

        $excluded = CalculateTeacherDashboardStats::EXCLUDED_TRAINING_TYPES;
        $excludedPlaceholders = implode(',', array_fill(0, count($excluded), '?'));

        // 1-bo'lim: Top 5 o'qituvchi (jami bo'yicha)
        $this->line("--- TOP 5 o'qituvchi (jami yozuvlar bo'yicha) ---");

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
                AND semester_code = ?
                AND training_type_name NOT IN ({$excludedPlaceholders})
                AND independent_id IS NULL
                AND retake_grade IS NULL
                AND (status IS NULL OR status != 'retake')
            GROUP BY employee_id
            ORDER BY jami DESC
            LIMIT 5
        ";
        $rows = DB::select($sql1, array_merge([$semester->code], $excluded));

        $headers = ['employee_id', 'name', 'jami', 'baho', 'NB', 'bosh', 'eng_erta_baho', 'eng_kech_baho'];
        $tableRows = [];
        foreach ($rows as $r) {
            $tableRows[] = [
                $r->employee_id,
                mb_substr($r->employee_name, 0, 25),
                $r->jami,
                $r->bilan_bahosi,
                $r->nb_count,
                $r->bosh,
                $r->eng_erta_baho,
                $r->eng_kech_baho,
            ];
        }
        $this->table($headers, $tableRows);

        // 2-bo'lim: Tanlangan o'qituvchi uchun batafsil tasniflash
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
                         AND created_at_api > TIMESTAMP(DATE(lesson_date), lesson_pair_end_time)
                         AND created_at_api <= TIMESTAMP(DATE(lesson_date), '18:00:00')
                    THEN 1 ELSE 0 END) AS ish_vaqtida,
                SUM(CASE
                    WHEN (grade IS NOT NULL OR reason = 'absent' OR status = 'absent')
                         AND created_at_api IS NOT NULL
                         AND created_at_api > TIMESTAMP(DATE(lesson_date), '18:00:00')
                         AND created_at_api <= TIMESTAMP(DATE(lesson_date), '23:59:59')
                    THEN 1 ELSE 0 END) AS kech,
                SUM(CASE
                    WHEN (grade IS NULL AND (reason IS NULL OR reason != 'absent') AND (status IS NULL OR status != 'absent'))
                         OR ((grade IS NOT NULL OR reason = 'absent' OR status = 'absent')
                             AND (created_at_api IS NULL OR created_at_api > TIMESTAMP(DATE(lesson_date), '23:59:59')))
                    THEN 1 ELSE 0 END) AS baholanmagan
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
                AND employee_id = ?
        ";
        $bindings = array_merge([$semester->code], $excluded, [$teacherFilter]);
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

        // 3-bo'lim: Birinchi 10 ta yozuvning vaqt tasniflanishi
        $this->line("\n--- Birinchi 10 ta yozuvning vaqt tafsiloti ---");

        $sql3 = "
            SELECT
                DATE(lesson_date) AS dars_sanasi,
                lesson_pair_end_time AS dars_tugash,
                created_at_api,
                grade,
                reason,
                status,
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
                AND semester_code = ?
                AND training_type_name NOT IN ({$excludedPlaceholders})
                AND independent_id IS NULL
                AND retake_grade IS NULL
                AND (status IS NULL OR status != 'retake')
                AND employee_id = ?
            ORDER BY lesson_date DESC, created_at_api DESC
            LIMIT 10
        ";
        $details = DB::select($sql3, $bindings);

        $detailRows = [];
        foreach ($details as $d) {
            $detailRows[] = [
                $d->dars_sanasi,
                $d->dars_tugash,
                $d->created_at_api,
                $d->grade ?? '-',
                $d->reason ?? '-',
                $d->status ?? '-',
                mb_substr($d->training_type_name, 0, 15),
                $d->kategoriya,
            ];
        }
        $this->table(
            ['dars_sana', 'dars_tugash', 'created_at_api', 'grade', 'reason', 'status', 'tur', 'kategoriya'],
            $detailRows
        );

        return self::SUCCESS;
    }
}
