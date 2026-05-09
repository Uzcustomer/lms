<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditRetakeGradeOverflow extends Command
{
    protected $signature = 'grades:audit-overflow
        {--csv= : CSV faylga eksport qilish (path)}
        {--limit=0 : Konsolga chiqarilayotgan qatorlar soni (0 = hammasi)}';

    protected $description = 'retake_grade > 100 bo\'lgan buzilgan yozuvlarni topish va taklif qilingan to\'g\'ri qiymatlarni ko\'rsatish';

    public function handle(): int
    {
        $rows = DB::table('student_grades as sg')
            ->leftJoin('students as s', 's.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->where('sg.retake_grade', '>', 100)
            ->orderByDesc('sg.retake_grade')
            ->orderByDesc('sg.lesson_date')
            ->get([
                'sg.id',
                'sg.student_hemis_id',
                's.full_name',
                'sg.subject_id',
                'sg.subject_name',
                'sg.semester_code',
                'sg.lesson_date',
                'sg.lesson_pair_code',
                'sg.reason',
                'sg.status',
                'sg.grade as original_grade',
                'sg.retake_grade',
                'sg.retake_was_sababli',
                'sg.retake_graded_at',
                'sg.updated_at',
            ]);

        $total = $rows->count();
        $this->info("Topildi: {$total} ta retake_grade > 100 yozuv");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $report = $rows->map(function ($r) {
            // Bug: reconcile divided rg by 0.8 va keyin × current_pct qildi.
            // Demak hozirgi rg = entered / 0.8 (sababli holatda),
            // ya'ni entered ≈ rg × 0.8.
            $likelyEntered = round($r->retake_grade * 0.8, 2);
            $currentSababliExcuse = $this->checkCurrentSababli($r);
            $proposedCoeff = $currentSababliExcuse ? 1.0 : 0.8;
            $proposedRg = round($likelyEntered * $proposedCoeff, 2);

            return [
                'id' => $r->id,
                'student' => $r->full_name ?: $r->student_hemis_id,
                'subject' => mb_strimwidth($r->subject_name ?? '', 0, 28, '…'),
                'date' => $r->lesson_date,
                'pair' => $r->lesson_pair_code,
                'reason' => $r->reason,
                'status' => $r->status,
                'rg' => $r->retake_grade,
                'flag' => $r->retake_was_sababli === null ? '—' : ($r->retake_was_sababli ? 'true' : 'false'),
                'now_sababli' => $currentSababliExcuse ? 'true' : 'false',
                'entered≈' => $likelyEntered,
                'proposed_rg' => $proposedRg,
            ];
        });

        $limit = (int) $this->option('limit');
        $tableRows = $limit > 0 ? $report->take($limit)->all() : $report->all();

        $this->table(
            ['id', 'student', 'subject', 'date', 'pair', 'reason', 'status', 'rg', 'flag', 'now_sababli', 'entered≈', 'proposed_rg'],
            $tableRows
        );

        if ($csvPath = $this->option('csv')) {
            $fp = fopen($csvPath, 'w');
            if (!$fp) {
                $this->error("CSV ochib bo'lmadi: {$csvPath}");
                return self::FAILURE;
            }
            fputcsv($fp, array_keys($report->first()));
            foreach ($report as $row) {
                fputcsv($fp, $row);
            }
            fclose($fp);
            $this->info("CSV yozildi: {$csvPath}");
        }

        return self::SUCCESS;
    }

    private function checkCurrentSababli($row): bool
    {
        if ($row->reason !== 'absent') {
            return false;
        }

        $hasLmsExcuse = DB::table('absence_excuses as ae')
            ->join('absence_excuse_makeups as aem', 'aem.absence_excuse_id', '=', 'ae.id')
            ->where('ae.student_hemis_id', $row->student_hemis_id)
            ->where('ae.status', 'approved')
            ->whereDate('ae.start_date', '<=', $row->lesson_date)
            ->whereDate('ae.end_date', '>=', $row->lesson_date)
            ->where('aem.subject_id', $row->subject_id)
            ->exists();

        if ($hasLmsExcuse) {
            return true;
        }

        return DB::table('attendances')
            ->where('student_hemis_id', $row->student_hemis_id)
            ->where('subject_id', $row->subject_id)
            ->whereDate('lesson_date', $row->lesson_date)
            ->where('lesson_pair_code', $row->lesson_pair_code)
            ->where('absent_on', '>', 0)
            ->exists();
    }
}
