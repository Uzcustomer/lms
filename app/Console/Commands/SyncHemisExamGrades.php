<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\Semester;
use App\Services\HemisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncHemisExamGrades extends Command
{
    protected $signature = 'hemis:sync-exam-grades
                            {--group= : Bitta guruh HEMIS ID}
                            {--subject= : Bitta fan ID}
                            {--semester= : Semestr kodi}';

    protected $description = 'HEMIS student-performance-list dan baholarni hemis_exam_grades jadvaliga sync qilish';

    public function handle(HemisService $hemis): int
    {
        $groupFilter = $this->option('group');
        $subjectFilter = $this->option('subject');
        $semesterFilter = $this->option('semester');

        // Agar aniq parametrlar berilgan bo'lsa — faqat shu uchun sync
        if ($groupFilter && $subjectFilter) {
            $synced = $hemis->syncExamGradesForGroup(
                $groupFilter,
                $subjectFilter,
                $semesterFilter ?? ''
            );
            $this->info("Synced {$synced} exam grade(s) for group={$groupFilter}, subject={$subjectFilter}.");
            return self::SUCCESS;
        }

        // Barcha aktiv guruhlarning joriy semestr fanlarini sync qilish
        $currentSemesters = Semester::where('current', true)->get();
        if ($currentSemesters->isEmpty()) {
            $this->warn('Joriy semestr topilmadi.');
            return self::SUCCESS;
        }

        $totalSynced = 0;
        $groups = Group::where('active', true)->where('department_active', true)->get();

        $bar = $this->output->createProgressBar($groups->count());
        $bar->start();

        foreach ($groups as $group) {
            $semesters = $currentSemesters->where('curriculum_hemis_id', $group->curriculum_hemis_id);

            foreach ($semesters as $semester) {
                $subjects = DB::table('curriculum_subjects')
                    ->where('curricula_hemis_id', $group->curriculum_hemis_id)
                    ->where('semester_code', $semester->code)
                    ->pluck('subject_id');

                foreach ($subjects as $subjectId) {
                    $totalSynced += $hemis->syncExamGradesForGroup(
                        $group->group_hemis_id,
                        $subjectId,
                        $semester->code
                    );
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Jami {$totalSynced} ta HEMIS exam grade sync qilindi.");

        return self::SUCCESS;
    }
}
