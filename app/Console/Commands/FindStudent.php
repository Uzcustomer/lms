<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;

class FindStudent extends Command
{
    protected $signature = 'student:find {student_id_number : Student ID number}';
    protected $description = 'Find a student by student_id_number and show is_graduate status';

    public function handle()
    {
        $studentIdNumber = $this->argument('student_id_number');

        $student = Student::where('student_id_number', $studentIdNumber)->first();

        if (!$student) {
            $this->error("Student not found: {$studentIdNumber}");
            return 1;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['student_id_number', $student->student_id_number],
                ['full_name', $student->full_name],
                ['group_name', $student->group_name ?? '-'],
                ['is_graduate', $student->is_graduate ? 'Ha ✅' : "Yo'q ❌"],
            ]
        );

        return 0;
    }
}
