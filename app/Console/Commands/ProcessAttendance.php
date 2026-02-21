<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\StudentGrade;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:process';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all attendance records and add them to StudentGrade if not already present';


    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $attendances = Attendance::all();

        foreach ($attendances as $attendance) {
            $existingGrade = StudentGrade::where([
                'student_id' => $attendance->student_id,
                'subject_id' => $attendance->subject_id,
                'lesson_date' => Carbon::parse($attendance->lesson_date)->format('Y-m-d H:i:s'),
                'lesson_pair_code' => $attendance->lesson_pair_code,
                'lesson_pair_start_time' => $attendance->lesson_pair_start_time,
            ])->first();

            $lessonDate = Carbon::parse($attendance->lesson_date)->format('Y-m-d H:i:s');

            if (!$existingGrade) {
                StudentGrade::create([
                    'hemis_id' => $attendance->hemis_id,
                    'student_id' => $attendance->student_id,
                    'student_hemis_id' => $attendance->student_hemis_id,
                    'semester_code' => $attendance->semester_code,
                    'semester_name' => $attendance->semester_name,
                    'subject_schedule_id' => $attendance->subject_schedule_id,
                    'subject_id' => $attendance->subject_id,
                    'subject_name' => $attendance->subject_name,
                    'subject_code' => $attendance->subject_code,
                    'training_type_code' => $attendance->training_type_code,
                    'training_type_name' => $attendance->training_type_name,
                    'employee_id' => $attendance->employee_id,
                    'employee_name' => $attendance->employee_name,
                    'lesson_pair_code' => $attendance->lesson_pair_code,
                    'lesson_pair_name' => $attendance->lesson_pair_name,
                    'lesson_pair_start_time' => $attendance->lesson_pair_start_time,
                    'lesson_pair_end_time' => $attendance->lesson_pair_end_time,
                    'grade' => null,
                    'lesson_date' => $lessonDate,
                    'created_at_api' => Carbon::now(),
                    'reason' => 'absent',
                    'deadline' => Carbon::now()->addWeek(),
                    'status' => 'pending',
                    'is_final' => true,
                ]);

                $this->info('StudentGrade created for student ID: ' . $attendance->student_id);
            } else {
                $this->info('StudentGrade already exists for student ID: ' . $attendance->student_id);
            }
        }

        $this->info('Attendance processing completed.');
    }
}
