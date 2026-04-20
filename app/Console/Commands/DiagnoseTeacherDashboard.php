<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseTeacherDashboard extends Command
{
    protected $signature = 'teachers:diagnose-dashboard {name : O\'qituvchi to\'liq ismining bir qismi}';
    protected $description = 'Dashboard snapshot uchun bir o\'qituvchi ma\'lumotlarini tekshirish';

    public function handle(): int
    {
        $name = $this->argument('name');

        $teachers = DB::table('teachers')
            ->where('full_name', 'like', "%{$name}%")
            ->select('id', 'hemis_id', 'full_name')
            ->limit(5)
            ->get();

        if ($teachers->isEmpty()) {
            $this->error("'{$name}' bo'yicha o'qituvchi topilmadi.");
            return self::FAILURE;
        }

        foreach ($teachers as $t) {
            $this->line('');
            $this->info("=== {$t->full_name} (hemis_id={$t->hemis_id}) ===");

            $totals = DB::table('student_grades')
                ->where('employee_id', $t->hemis_id)
                ->whereNull('deleted_at')
                ->count();
            $this->line("Jami baholar (deleted_at=null): {$totals}");

            $breakdown = DB::table('student_grades')
                ->where('employee_id', $t->hemis_id)
                ->whereNull('deleted_at')
                ->selectRaw('training_type_code, status, semester_code, COUNT(*) as c')
                ->groupBy('training_type_code', 'status', 'semester_code')
                ->orderByDesc('c')
                ->limit(20)
                ->get();

            $this->line('Breakdown (training_type_code | status | semester_code | count):');
            foreach ($breakdown as $b) {
                $this->line("  {$b->training_type_code} | {$b->status} | {$b->semester_code} | {$b->c}");
            }

            $currentSemesters = DB::table('semesters')
                ->where('current', true)
                ->whereNotNull('code')
                ->pluck('code')
                ->toArray();
            $this->line('Joriy semester kodlari: ' . implode(',', $currentSemesters));

            $matchSnapshot = DB::table('student_grades')
                ->where('employee_id', $t->hemis_id)
                ->whereNull('deleted_at')
                ->whereIn('semester_code', $currentSemesters)
                ->where('training_type_code', 100)
                ->where('status', 'recorded')
                ->whereNotNull('lesson_date')
                ->count();
            $this->line("Snapshot filter bilan mos keladi: {$matchSnapshot}");

            $snapshot = DB::table('teacher_dashboard_snapshots')
                ->where('scope', 'teacher')
                ->where('teacher_hemis_id', (string) $t->hemis_id)
                ->first();

            if ($snapshot) {
                $payload = json_decode($snapshot->payload, true);
                $this->line('Snapshot payload:');
                $this->line('  grading: ' . ($payload['grading'] ? 'bor' : 'null'));
                $this->line('  tutor: ' . ($payload['tutor'] ? 'bor' : 'null'));
                $this->line('  workload: ' . ($payload['workload'] ? 'bor (' . $payload['workload']['total_checked'] . ' yozuv)' : 'null'));
                $this->line('  subjects: ' . ($payload['subjects'] ? count($payload['subjects']) . ' ta fan' : 'null'));
            } else {
                $this->line('Snapshot: YO\'Q');
            }
        }

        return self::SUCCESS;
    }
}
