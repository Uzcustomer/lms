<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugGradeCheck extends Command
{
    protected $signature = 'debug:grade-check {employee_name} {--date= : Sana (Y-m-d), standart: kecha}';
    protected $description = 'O\'qituvchi baholarini tekshirish — hisobot nega "Yo\'q" ko\'rsatayotganini aniqlash';

    public function handle(): int
    {
        $searchName = $this->argument('employee_name');
        $reportDate = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::yesterday();
        $reportDateStr = $reportDate->format('Y-m-d');

        $this->info("=== DEBUG: {$searchName} uchun {$reportDateStr} sanasi ===\n");

        // 1. schedules jadvalida darslarini topish
        $schedules = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->join('curricula as c', 'c.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
            ->whereRaw('LOWER(sch.employee_name) LIKE ?', ['%' . mb_strtolower($searchName) . '%'])
            ->whereRaw('DATE(sch.lesson_date) = ?', [$reportDateStr])
            ->whereNull('sch.deleted_at')
            ->whereRaw('LOWER(c.education_type_name) LIKE ?', ['%bakalavr%'])
            ->select(
                'sch.schedule_hemis_id',
                'sch.employee_id',
                'sch.employee_name',
                'sch.subject_id',
                'sch.subject_name',
                'sch.group_id',
                'sch.group_name',
                'sch.training_type_code',
                'sch.training_type_name',
                'sch.lesson_pair_code',
                DB::raw('DATE(sch.lesson_date) as lesson_date_str')
            )
            ->get();

        if ($schedules->isEmpty()) {
            $this->error("Jadvalda {$searchName} uchun {$reportDateStr} sanada dars topilmadi!");
            return 1;
        }

        $this->info("1) JADVALDA TOPILGAN DARSLAR: {$schedules->count()} ta");
        foreach ($schedules as $sch) {
            $this->line("   schedule_hemis_id={$sch->schedule_hemis_id}, group={$sch->group_name}, subject={$sch->subject_name}, pair={$sch->lesson_pair_code}, type={$sch->training_type_code}");
        }
        $this->line('');

        // 2. schedule_hemis_id bo'yicha baholarni tekshirish (1-usul)
        $scheduleHemisIds = $schedules->pluck('schedule_hemis_id')->unique()->values()->toArray();

        $gradesByScheduleId = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->whereNotNull('grade')
            ->where('grade', '>', 0)
            ->select('subject_schedule_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('subject_schedule_id')
            ->pluck('cnt', 'subject_schedule_id');

        $this->info("2) BAHO TEKSHIRISH (1-usul: subject_schedule_id):");
        foreach ($scheduleHemisIds as $sid) {
            $found = $gradesByScheduleId->get($sid, 0);
            $status = $found > 0 ? "BOR ({$found} ta)" : "YO'Q";
            $this->line("   schedule_hemis_id={$sid} → {$status}");
        }
        $this->line('');

        // 3. subject_schedule_id HEMIS format tekshirish — balki student_grades da boshqa qiymat
        $this->info("3) student_grades DA SHU TEACHER UCHUN BARCHA YOZUVLAR (deleted_at IS NULL):");
        $employeeId = $schedules->first()->employee_id;
        $activeGrades = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->where('employee_id', $employeeId)
            ->whereRaw('DATE(lesson_date) = ?', [$reportDateStr])
            ->select('subject_schedule_id', 'subject_id', 'training_type_code', 'lesson_pair_code', 'grade', 'is_final', DB::raw('COUNT(*) as cnt'))
            ->groupBy('subject_schedule_id', 'subject_id', 'training_type_code', 'lesson_pair_code', 'grade', 'is_final')
            ->get();

        if ($activeGrades->isEmpty()) {
            $this->error("   AKTIV BAHOLAR YO'Q!");
        } else {
            foreach ($activeGrades as $g) {
                $this->line("   sched_id={$g->subject_schedule_id}, subj={$g->subject_id}, type={$g->training_type_code}, pair={$g->lesson_pair_code}, grade={$g->grade}, final={$g->is_final}, count={$g->cnt}");
            }
        }
        $this->line('');

        // 4. SOFT-DELETED baholarni tekshirish
        $this->info("4) SOFT-DELETED BAHOLAR (agar yo'qolgan bo'lsa):");
        $deletedGrades = DB::table('student_grades')
            ->whereNotNull('deleted_at')
            ->where('employee_id', $employeeId)
            ->whereRaw('DATE(lesson_date) = ?', [$reportDateStr])
            ->select('subject_schedule_id', 'subject_id', 'training_type_code', 'lesson_pair_code', 'grade', 'is_final', 'deleted_at', DB::raw('COUNT(*) as cnt'))
            ->groupBy('subject_schedule_id', 'subject_id', 'training_type_code', 'lesson_pair_code', 'grade', 'is_final', 'deleted_at')
            ->get();

        if ($deletedGrades->isEmpty()) {
            $this->line("   Soft-deleted baholar yo'q");
        } else {
            foreach ($deletedGrades as $g) {
                $this->warn("   sched_id={$g->subject_schedule_id}, subj={$g->subject_id}, grade={$g->grade}, final={$g->is_final}, deleted_at={$g->deleted_at}, count={$g->cnt}");
            }
        }
        $this->line('');

        // 5. KEY MATCH tekshirish (2-usul)
        $this->info("5) KEY MATCH TEKSHIRISH (2-usul: employee|group|subject|date|type|pair):");
        $groupHemisIds = $schedules->pluck('group_id')->unique()->values()->toArray();

        $gradeByKey = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->where('sg.employee_id', $employeeId)
            ->whereIn('st.group_id', $groupHemisIds)
            ->whereRaw('DATE(sg.lesson_date) = ?', [$reportDateStr])
            ->whereNotNull('sg.grade')
            ->where('sg.grade', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(sg.employee_id, '|', st.group_id, '|', sg.subject_id, '|', DATE(sg.lesson_date), '|', sg.training_type_code, '|', sg.lesson_pair_code) as gk"))
            ->pluck('gk');

        if ($gradeByKey->isEmpty()) {
            $this->error("   KEY MATCH: hech qanday baho topilmadi!");

            // Nima uchun topilmaganini tekshirish
            $this->line("\n   --- Tafsilot ---");

            // students da bor-yo'qligini tekshirish
            $studentCount = DB::table('student_grades as sg')
                ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->whereNull('sg.deleted_at')
                ->where('sg.employee_id', $employeeId)
                ->whereRaw('DATE(sg.lesson_date) = ?', [$reportDateStr])
                ->count();
            $this->line("   student JOIN bilan: {$studentCount} ta yozuv");

            // group_id tekshirish
            $studentsInGrades = DB::table('student_grades as sg')
                ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->whereNull('sg.deleted_at')
                ->where('sg.employee_id', $employeeId)
                ->whereRaw('DATE(sg.lesson_date) = ?', [$reportDateStr])
                ->select('st.group_id')
                ->distinct()
                ->pluck('group_id');
            $this->line("   Student group_id lar: " . $studentsInGrades->implode(', '));
            $this->line("   Schedule group_id lar: " . implode(', ', $groupHemisIds));
            $matchingGroups = $studentsInGrades->intersect($groupHemisIds);
            $this->line("   Mos keluvchi group_id lar: " . ($matchingGroups->isEmpty() ? 'HECH BIRI!' : $matchingGroups->implode(', ')));
        } else {
            $this->info("   Topilgan kalitlar:");
            foreach ($gradeByKey as $k) {
                $this->line("   {$k}");
            }
        }
        $this->line('');

        // 6. Yakuniy xulosa
        $this->info("6) XULOSA:");
        foreach ($schedules->unique('schedule_hemis_id') as $sch) {
            $gradeKey = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                      . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            $method1 = $gradesByScheduleId->has($sch->schedule_hemis_id);
            $method2 = $gradeByKey->contains($gradeKey);
            $hasGrade = $method1 || $method2;

            $this->line(sprintf(
                "   %s | %s | pair=%s | 1-usul=%s | 2-usul=%s | NATIJA=%s",
                $sch->group_name,
                $sch->subject_name,
                $sch->lesson_pair_code,
                $method1 ? 'BOR' : "YO'Q",
                $method2 ? 'BOR' : "YO'Q",
                $hasGrade ? 'BOR' : "YO'Q (XATO!)"
            ));

            if (!$hasGrade) {
                $this->warn("   ↳ Kutilgan kalit: {$gradeKey}");
            }
        }

        return 0;
    }
}
