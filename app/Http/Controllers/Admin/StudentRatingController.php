<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentRating;
use Illuminate\Http\Request;

class StudentRatingController extends Controller
{
    public function index(Request $request)
    {
        // Filtrlar uchun unique qiymatlarni olish
        $departments = StudentRating::whereNotNull('department_name')
            ->select('department_code', 'department_name')
            ->distinct()
            ->orderBy('department_name')
            ->get();

        $specialties = collect();
        $selectedDepartment = $request->input('department');
        $selectedSpecialty = $request->input('specialty');
        $selectedLevel = $request->input('level');

        if ($selectedDepartment) {
            $specialties = StudentRating::where('department_code', $selectedDepartment)
                ->whereNotNull('specialty_name')
                ->select('specialty_code', 'specialty_name')
                ->distinct()
                ->orderBy('specialty_name')
                ->get();
        }

        // Top 10 va qolganlar
        $query = StudentRating::query();

        if ($selectedDepartment) {
            $query->where('department_code', $selectedDepartment);
        }
        if ($selectedSpecialty) {
            $query->where('specialty_code', $selectedSpecialty);
        }
        if ($selectedLevel) {
            $query->where('level_code', $selectedLevel);
        }

        $query->orderBy('rank')->orderByDesc('jn_average');

        $top10 = (clone $query)->limit(10)->get();
        $others = (clone $query)->offset(10)->paginate(20)->withQueryString();
        $totalStudents = (clone $query)->count();

        $lastUpdated = StudentRating::max('calculated_at');

        return view('admin.student-ratings.index', compact(
            'departments', 'specialties', 'top10', 'others', 'totalStudents',
            'selectedDepartment', 'selectedSpecialty', 'selectedLevel', 'lastUpdated'
        ));
    }
}
