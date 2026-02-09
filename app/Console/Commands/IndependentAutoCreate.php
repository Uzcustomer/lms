<?php

namespace App\Console\Commands;

use App\Models\CurriculumSubject;
use App\Models\CurriculumWeek;
use App\Models\Deadline;
use App\Models\Department;
use App\Models\Group;
use App\Models\Independent;
use App\Models\Schedule;
use App\Models\Semester;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IndependentAutoCreate extends Command
{
    protected $signature = 'command:independent-auto-create';

    protected $description = 'Auto-create independent assignments and recalculate deadlines based on schedule data';

    /**
     * Training type codes to exclude (e.g. exams, etc.)
     */
    private array $excludedTrainingTypes;

    /**
     * Group IDs to exclude
     */
    private array $excludedGroups = [910, 911, 912, 913, 914, 915, 916, 917, 918, 919, 920, 921];

    public function handle()
    {
        $this->excludedTrainingTypes = config('app.training_type_code');

        // Phase 1: Create/update Independent records for each schedule
        $this->createIndependents();

        // Phase 2: Recalculate deadlines using pattern prediction + CurriculumWeek
        $this->recalculateDeadlines();

        $this->info('Independent assignments created and deadlines recalculated.');
    }

    /**
     * Phase 1: Create Independent records for each (group, subject, lesson_date) combination
     */
    private function createIndependents(): void
    {
        $schedules = Schedule::select('group_id', 'lesson_date', DB::raw('MAX(id) as id'))
            ->whereNotIn('training_type_code', $this->excludedTrainingTypes)
            ->where('lesson_date', '>', '2025-02-01')
            ->whereNotIn('group_id', $this->excludedGroups)
            ->groupBy(['group_id', 'subject_id', 'lesson_date'])
            ->get();

        $this->info("Creating/updating {$schedules->count()} independent assignments...");

        foreach ($schedules as $sche) {
            try {
                $schedule = Schedule::find($sche->id);
                if (!$schedule) continue;

                $deadline = Deadline::where('level_code', $schedule->semester->level_code)->first();

                $start_date = $schedule->lesson_date;
                $end_date = date('Y-m-d', strtotime($start_date . ' +' . ($deadline->deadline_days ?? 1) . ' days'));

                $group = Group::where('group_hemis_id', $schedule->group_id)->first();
                if (!$group) continue;

                $semester = Semester::where('code', $schedule->semester_code)
                    ->where('curriculum_hemis_id', $group->curriculum_hemis_id)
                    ->first();
                if (!$semester) continue;

                $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                    ->where('semester_code', $semester->code)
                    ->where('subject_id', $schedule->subject_id)
                    ->first();
                if (!$subject) continue;

                $deportment = Department::where('department_hemis_id', $schedule->faculty_id)->first();
                if (!$deportment) continue;

                Independent::updateOrCreate([
                    'schedule_id' => $schedule->id
                ], [
                    'group_hemis_id' => $schedule->group_id,
                    'group_name' => $schedule->group->name,
                    'teacher_hemis_id' => $schedule->employee_id,
                    'teacher_name' => $schedule->employee->full_name,
                    'teacher_short_name' => $schedule->employee->short_name,
                    'department_hemis_id' => $deportment->department_hemis_id,
                    'deportment_name' => $deportment->name,
                    'start_date' => $schedule->lesson_date,
                    'semester_hemis_id' => $semester->semester_hemis_id,
                    'semester_name' => $semester->name,
                    'semester_code' => $semester->code,
                    'subject_hemis_id' => $subject->curriculum_subject_hemis_id,
                    'subject_name' => $schedule->subject_name,
                    'user_id' => 0,
                    'deadline' => $end_date,
                ]);
            } catch (\Exception $e) {
                $this->error("Error processing schedule {$sche->id}: {$e->getMessage()}");
                continue;
            }
        }
    }

    /**
     * Phase 2: Recalculate deadlines for all Independent assignments.
     *
     * For each (group, subject, semester) combination:
     * 1. Get all known schedule dates
     * 2. Determine weekly pattern (which day(s) of week classes happen)
     * 3. Use CurriculumWeek to find semester end date
     * 4. Predict future class dates based on pattern + semester boundary
     * 5. Set deadline = second-to-last class date (at 17:00)
     */
    private function recalculateDeadlines(): void
    {
        $combinations = Schedule::select('group_id', 'subject_id', 'semester_code')
            ->whereNotIn('training_type_code', $this->excludedTrainingTypes)
            ->where('lesson_date', '>', '2025-02-01')
            ->whereNotIn('group_id', $this->excludedGroups)
            ->groupBy('group_id', 'subject_id', 'semester_code')
            ->get();

        $this->info("Recalculating deadlines for {$combinations->count()} group-subject combinations...");

        $updated = 0;

        foreach ($combinations as $combo) {
            try {
                $deadlineDate = $this->calculateDeadlineForCombination($combo);

                if (!$deadlineDate) continue;

                // Get all schedule IDs for this combination
                $scheduleIds = Schedule::where('group_id', $combo->group_id)
                    ->where('subject_id', $combo->subject_id)
                    ->where('semester_code', $combo->semester_code)
                    ->whereNotIn('training_type_code', $this->excludedTrainingTypes)
                    ->pluck('id');

                // Update deadlines for all Independent records linked to these schedules
                $count = Independent::whereIn('schedule_id', $scheduleIds)
                    ->update(['deadline' => $deadlineDate]);

                $updated += $count;
            } catch (\Exception $e) {
                $this->error("Error recalculating for group={$combo->group_id}, subject={$combo->subject_id}: {$e->getMessage()}");
                continue;
            }
        }

        $this->info("Updated deadlines for {$updated} independent assignments.");
    }

    /**
     * Calculate the deadline date for a specific (group, subject, semester) combination.
     * Returns the second-to-last class date, or null if insufficient data.
     */
    private function calculateDeadlineForCombination($combo): ?string
    {
        // Get all known lesson dates for this combination
        $knownDates = Schedule::where('group_id', $combo->group_id)
            ->where('subject_id', $combo->subject_id)
            ->where('semester_code', $combo->semester_code)
            ->whereNotIn('training_type_code', $this->excludedTrainingTypes)
            ->orderBy('lesson_date')
            ->pluck('lesson_date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->values();

        if ($knownDates->count() < 1) {
            return null;
        }

        // Try to find semester end date from CurriculumWeek
        $semesterEndDate = $this->getSemesterEndDate($combo);

        if ($semesterEndDate) {
            // Predict future class dates based on weekly pattern + semester end
            $allDates = $this->predictFutureClasses($knownDates, $semesterEndDate);
        } else {
            // Fallback: use only known dates
            $allDates = $knownDates;
        }

        if ($allDates->count() < 2) {
            return null;
        }

        // Return the second-to-last date
        return $allDates[$allDates->count() - 2];
    }

    /**
     * Get the semester end date from CurriculumWeek data.
     * Returns the end_date of the last week in the semester.
     */
    private function getSemesterEndDate($combo): ?Carbon
    {
        $group = Group::where('group_hemis_id', $combo->group_id)->first();
        if (!$group) return null;

        $semester = Semester::where('code', $combo->semester_code)
            ->where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->first();
        if (!$semester) return null;

        $lastWeek = CurriculumWeek::where('semester_hemis_id', $semester->semester_hemis_id)
            ->orderBy('end_date', 'desc')
            ->first();

        return $lastWeek ? Carbon::parse($lastWeek->end_date) : null;
    }

    /**
     * Predict future class dates by analyzing the weekly pattern of known dates
     * and extending it to the semester end.
     *
     * Example: if classes are known on Mondays (Feb 3, Feb 10, Feb 17) and semester
     * ends April 30, this will predict Mondays through April 28.
     */
    private function predictFutureClasses(Collection $knownDates, Carbon $semesterEndDate): Collection
    {
        $lastKnownDate = Carbon::parse($knownDates->last());

        // If we already have schedule data up to or past semester end, no prediction needed
        if ($lastKnownDate->gte($semesterEndDate)) {
            return $knownDates;
        }

        // Determine which days of the week classes happen
        $daysOfWeek = $knownDates
            ->map(fn($d) => Carbon::parse($d)->dayOfWeek)
            ->unique()
            ->values();

        if ($daysOfWeek->isEmpty()) {
            return $knownDates;
        }

        // Start predicting from the day after the last known date
        $predicted = collect($knownDates->toArray());
        $current = $lastKnownDate->copy()->addDay();

        while ($current->lte($semesterEndDate)) {
            if ($daysOfWeek->contains($current->dayOfWeek)) {
                $predicted->push($current->format('Y-m-d'));
            }
            $current->addDay();
        }

        return $predicted->unique()->sort()->values();
    }
}
