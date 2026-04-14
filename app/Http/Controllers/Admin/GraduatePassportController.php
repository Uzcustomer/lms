<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GraduatePassportController extends Controller
{
    public function index()
    {
        // Fakultetlar (structure_type_code = 11)
        $faculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get(['department_hemis_id', 'name']);

        // Kafedralar (structure_type_code = 12)
        $departments = Department::where('structure_type_code', 12)
            ->where('active', true)
            ->orderBy('name')
            ->get(['department_hemis_id', 'name', 'parent_id']);

        // Fakultet id -> parent_id ni department jadvalida topib, kafedrani fakultet bilan bog'lash
        // parent_id — departments.id (ichki). Uni department_hemis_id ga map qilish uchun:
        $deptIdToHemis = Department::whereIn('id', $departments->pluck('parent_id')->filter()->unique())
            ->pluck('department_hemis_id', 'id');

        // Umumiy statistika
        $stats = DB::table('students')
            ->where('is_graduate', true)
            ->where('education_type_code', '11')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN gender_code = '11' THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN gender_code = '12' THEN 1 ELSE 0 END) as female,
                SUM(CASE WHEN EXISTS(SELECT 1 FROM graduate_student_passports gp WHERE gp.student_id = students.id) THEN 1 ELSE 0 END) as filled
            ")
            ->first();

        // Guruhlar — default ro'yxat
        $groups = DB::table('students')
            ->where('is_graduate', true)
            ->where('education_type_code', '11')
            ->select('group_id', 'group_name', DB::raw('COUNT(*) as total'))
            ->groupBy('group_id', 'group_name')
            ->orderBy('group_name')
            ->get();

        return view('admin.graduate-passports.index', [
            'faculties' => $faculties,
            'departments' => $departments->map(function ($d) use ($deptIdToHemis) {
                $d->faculty_hemis_id = $d->parent_id ? ($deptIdToHemis[$d->parent_id] ?? null) : null;
                return $d;
            }),
            'groups' => $groups,
            'stats' => $stats,
        ]);
    }

    public function data(Request $request)
    {
        $query = DB::table('students as s')
            ->leftJoin('graduate_student_passports as gp', 'gp.student_id', '=', 's.id')
            ->leftJoin('departments as d', 'd.department_hemis_id', '=', 's.department_id')
            ->leftJoin('departments as f', 'f.id', '=', 'd.parent_id')
            ->where('s.is_graduate', true)
            ->where('s.education_type_code', '11');

        // Filtrlash
        if ($request->filled('faculty_id')) {
            $query->where('f.department_hemis_id', $request->faculty_id);
        }
        if ($request->filled('department_id')) {
            $query->where('s.department_id', $request->department_id);
        }
        if ($request->filled('gender_code')) {
            $query->where('s.gender_code', $request->gender_code);
        }
        if ($request->filled('group_id')) {
            $query->where('s.group_id', $request->group_id);
        }
        if ($request->filled('status')) {
            if ($request->status === 'filled') {
                $query->whereNotNull('gp.id');
            } elseif ($request->status === 'empty') {
                $query->whereNull('gp.id');
            }
        }
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('s.full_name', 'like', $search)
                  ->orWhere('s.student_id_number', 'like', $search);
            });
        }

        $students = $query->select(
                's.id', 's.hemis_id', 's.student_id_number', 's.full_name',
                's.department_name', 's.group_name', 's.gender_code', 's.gender_name',
                'd.name as kafedra_name', 'f.name as faculty_name',
                'gp.id as gp_id', 'gp.first_name', 'gp.last_name', 'gp.father_name',
                'gp.first_name_en', 'gp.last_name_en',
                'gp.passport_series', 'gp.passport_number', 'gp.jshshir',
                'gp.passport_front_path', 'gp.passport_back_path', 'gp.foreign_passport_path',
                'gp.created_at as gp_created_at'
            )
            ->orderBy('s.full_name')
            ->get()
            ->map(function ($st) {
                $filled = !empty($st->gp_id);
                return [
                    'full_name' => $st->full_name,
                    'student_id_number' => $st->student_id_number,
                    'group_name' => $st->group_name ?? '',
                    'faculty_name' => $st->faculty_name ?? '',
                    'kafedra_name' => $st->kafedra_name ?? ($st->department_name ?? ''),
                    'gender_code' => $st->gender_code,
                    'gender_name' => $st->gender_name,
                    'filled' => $filled,
                    'gp_id' => $st->gp_id,
                    'name_uz' => $filled ? trim(($st->last_name ?? '') . ' ' . ($st->first_name ?? '') . ' ' . ($st->father_name ?? '')) : '',
                    'name_en' => $filled ? trim(($st->first_name_en ?? '') . ' ' . ($st->last_name_en ?? '')) : '',
                    'passport' => $filled ? ($st->passport_series ?? '') . ($st->passport_number ?? '') : '',
                    'jshshir' => $st->jshshir ?? '',
                    'has_front' => !empty($st->passport_front_path),
                    'has_back' => !empty($st->passport_back_path),
                    'has_foreign' => !empty($st->foreign_passport_path),
                    'created_at' => $st->gp_created_at ? date('d.m.Y', strtotime($st->gp_created_at)) : '',
                ];
            });

        $total = $students->count();
        $male = $students->where('gender_code', '11')->count();
        $female = $students->where('gender_code', '12')->count();
        $filled = $students->where('filled', true)->count();

        return response()->json([
            'students' => $students,
            'stats' => [
                'total' => $total,
                'male' => $male,
                'female' => $female,
                'filled' => $filled,
                'empty' => $total - $filled,
            ],
        ]);
    }

    public function showFile($id, $field)
    {
        $allowed = ['passport_front_path', 'passport_back_path', 'foreign_passport_path'];
        if (!in_array($field, $allowed)) abort(404);

        $passport = DB::table('graduate_student_passports')->where('id', $id)->first();
        if (!$passport || empty($passport->$field)) abort(404);

        $path = storage_path('app/public/' . $passport->$field);
        if (!file_exists($path)) abort(404);

        return response()->file($path);
    }
}
