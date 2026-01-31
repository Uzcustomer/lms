<?php

namespace App\Console\Commands;

use App\Models\CurriculumSubject;
use App\Models\Deadline;
use App\Models\Department;
use App\Models\Group;
use App\Models\Independent;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use App\Models\Semester;
use Illuminate\Console\Command;
use function PHPUnit\Framework\returnArgument;

class IndependentAutoCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:independent-auto-create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $schedules = Schedule::select('group_id', 'lesson_date', DB::raw('MAX(id) as id'))->whereNotIn('training_type_code', config('app.training_type_code'))
            ->where('lesson_date', ">", "2025-02-01")
            ->whereNotIn('group_id', [910, 911, 912, 913, 914, 915, 916, 917, 918, 919, 920, 921])->groupBy(['group_id', 'subject_id', 'lesson_date'])
            ->get();
        echo count($schedules);
        foreach ($schedules as $sche) {
            $schedule = Schedule::find($sche->id);
            $deadline = Deadline::where('level_code', $schedule->semester->level_code)->first();

            $start_date = $schedule->lesson_date;
            $end_date = date('Y-m-d', strtotime($start_date . ' +' . ($deadline->deadline_days ?? 1) . ' days')); // 5 kun qo'shish
            $group = Group::where('group_hemis_id', $schedule->group_id)->first();
            $semester = Semester::where('code', $schedule->semester_code)->where('curriculum_hemis_id', $group->curriculum_hemis_id)->first();
            $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)
                ->where('subject_id', $schedule->subject_id)
                ->first();
            $deportment = Department::where('department_hemis_id', $schedule->faculty_id)->first();
            // $deportment = Department::where('department_hemis_id', empty($deportment->parent_id) ? $deportment->department_hemis_id : $deportment->parent_id)->first();

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
        }
    }
}