<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\StudentPassport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GraduatePassportController extends Controller
{
    public function index(Request $request)
    {
        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        $faculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return view('admin.graduate-passports.index', compact('educationTypes', 'faculties'));
    }

    public function data(Request $request)
    {
        $query = DB::table('graduate_student_passports as gp')
            ->join('students as s', 's.id', '=', 'gp.student_id')
            ->select(
                'gp.id', 'gp.student_id',
                'gp.first_name', 'gp.last_name', 'gp.father_name',
                'gp.first_name_en', 'gp.last_name_en',
                'gp.passport_series', 'gp.passport_number', 'gp.jshshir',
                'gp.passport_front_path', 'gp.passport_back_path', 'gp.foreign_passport_path',
                'gp.created_at', 'gp.updated_at',
                's.hemis_id', 's.student_id_number', 's.full_name',
                's.department_name', 's.specialty_name', 's.group_name',
                's.level_name', 's.semester_name', 's.education_type_code'
            );

        if ($request->filled('faculty')) {
            $faculty = Department::find($request->faculty);
            if ($faculty) {
                $query->where('s.department_id', $faculty->department_hemis_id);
            }
        }
        if ($request->filled('education_type')) {
            $query->where('s.education_type_code', $request->education_type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('s.full_name', 'like', "%{$search}%")
                  ->orWhere('s.student_id_number', 'like', "%{$search}%")
                  ->orWhere('gp.passport_number', 'like', "%{$search}%")
                  ->orWhere('gp.jshshir', 'like', "%{$search}%");
            });
        }
        if ($request->filled('group_name')) {
            $query->where('s.group_name', 'like', "%{$request->group_name}%");
        }

        $sortColumn = $request->get('sort', 'gp.created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortColumn, $sortDirection);

        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);
        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $data = $query->offset($offset)->limit($perPage)->get()->map(function ($row, $i) use ($offset) {
            return [
                'row_num' => $offset + $i + 1,
                'id' => $row->id,
                'hemis_id' => $row->hemis_id,
                'student_id_number' => $row->student_id_number,
                'full_name' => $row->full_name,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'father_name' => $row->father_name,
                'name_en' => trim(($row->first_name_en ?? '') . ' ' . ($row->last_name_en ?? '')),
                'passport' => ($row->passport_series ?? '') . ($row->passport_number ?? ''),
                'jshshir' => $row->jshshir,
                'department_name' => $row->department_name,
                'specialty_name' => $row->specialty_name,
                'group_name' => $row->group_name,
                'level_name' => $row->level_name,
                'has_front' => !empty($row->passport_front_path),
                'has_back' => !empty($row->passport_back_path),
                'has_foreign' => !empty($row->foreign_passport_path),
                'created_at' => $row->created_at ? date('d.m.Y H:i', strtotime($row->created_at)) : '-',
            ];
        });

        return response()->json([
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => (int) $page,
            'last_page' => (int) ceil($total / max($perPage, 1)),
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
