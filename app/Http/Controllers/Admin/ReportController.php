<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentGrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function jnReport(Request $request)
    {
        // Joriy semestr kodlarini aniqlash
        $currentSemesterCodes = DB::table('semesters')
            ->where('current', true)
            ->pluck('code')
            ->unique()
            ->toArray();

        $students = collect();

        if (!empty($currentSemesterCodes)) {
            $students = StudentGrade::select(
                    'student_hemis_id',
                    DB::raw('MAX(student_id) as student_id'),
                    DB::raw('AVG(grade) as avg_grade'),
                    DB::raw('COUNT(*) as grades_count')
                )
                ->whereIn('semester_code', $currentSemesterCodes)
                ->whereNotNull('grade')
                ->where('grade', '>', 0)
                ->groupBy('student_hemis_id')
                ->orderBy('avg_grade', 'asc')
                ->get()
                ->map(function ($item) {
                    $student = Student::where('hemis_id', $item->student_hemis_id)->first();
                    return (object) [
                        'student_hemis_id' => $item->student_hemis_id,
                        'student_id' => $item->student_id,
                        'full_name' => $student ? $student->full_name : 'Noma\'lum',
                        'group_name' => $student ? $student->group_name : '-',
                        'department_name' => $student ? $student->department_name : '-',
                        'level_name' => $student ? $student->level_name : '-',
                        'semester_name' => $student ? $student->semester_name : '-',
                        'avg_grade' => round($item->avg_grade, 2),
                        'grades_count' => $item->grades_count,
                    ];
                });
        }

        return view('admin.reports.jn', compact('students', 'currentSemesterCodes'));
    }
}
