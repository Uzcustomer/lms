<?php

namespace App\Console\Commands;

use App\Models\OraliqNazorat;
use App\Models\Oski;
use App\Models\Student;
use App\Models\StudentGrade;
use Illuminate\Console\Command;

class OskiError extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:oski-error';

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
        $oskis = Oski::where('status', 1)->get();
        foreach ($oskis as $oski) {
            StudentGrade::leftJoin('students', 'students.hemis_id', '=', 'student_grades.student_hemis_id')
                ->where('students.group_id', $oski->group_hemis_id)
                ->where('student_grades.subject_code', $oski->subject->subject_code)
                ->where('lesson_date', $oski->start_date)

                ->where('student_grades.training_type_code', 101)
                ->update(
                    [
                        'student_grades.training_type_name' => "Oski",
                        'student_grades.oski_id' => $oski->id
                    ]
                );
        }
        $oraliqnazorats = OraliqNazorat::where('status', 1)->get();
        foreach ($oraliqnazorats as $oraliqnazorat) {
            StudentGrade::leftJoin('students', 'students.hemis_id', '=', 'student_grades.student_hemis_id')
                ->where('students.group_id', $oraliqnazorat->group_hemis_id)
                ->where('student_grades.subject_code', $oraliqnazorat->subject->subject_code)
                ->where('student_grades.training_type_code', 100)
                ->where('lesson_date', $oraliqnazorat->start_date)
                ->update(
                    [
                        'student_grades.oraliq_nazorat_id' => $oraliqnazorat->id
                    ]
                );
        }
    }
}