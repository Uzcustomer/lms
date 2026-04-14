<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GraduatePassportController extends Controller
{
    public function index()
    {
        // Bakalavr bitiruvchi fakultetlarni olish (har bir fakultet uchun to'lgan/jami)
        $faculties = DB::table('students as s')
            ->where('s.is_graduate', true)
            ->where('s.education_type_code', '11') // Bakalavr
            ->whereNotNull('s.department_id')
            ->select(
                's.department_id',
                's.department_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN EXISTS(SELECT 1 FROM graduate_student_passports gp WHERE gp.student_id = s.id) THEN 1 ELSE 0 END) as filled')
            )
            ->groupBy('s.department_id', 's.department_name')
            ->orderBy('s.department_name')
            ->get();

        // Bakalavr bitiruvchi guruhlarni olish
        $groups = DB::table('students as s')
            ->where('s.is_graduate', true)
            ->where('s.education_type_code', '11') // Bakalavr
            ->select(
                's.group_name',
                's.group_id',
                's.department_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN EXISTS(SELECT 1 FROM graduate_student_passports gp WHERE gp.student_id = s.id) THEN 1 ELSE 0 END) as filled')
            )
            ->groupBy('s.group_name', 's.group_id', 's.department_id')
            ->orderBy('s.group_name')
            ->get();

        return view('admin.graduate-passports.index', compact('groups', 'faculties'));
    }

    public function data(Request $request)
    {
        $query = DB::table('students as s')
            ->where('s.is_graduate', true)
            ->where('s.education_type_code', '11')
            ->leftJoin('graduate_student_passports as gp', 'gp.student_id', '=', 's.id');

        // Guruh filtri
        if ($request->filled('group_id')) {
            $query->where('s.group_id', $request->group_id);
        }

        // Fakultet filtri
        if ($request->filled('department_id')) {
            $query->where('s.department_id', $request->department_id);
        }

        // Agar hech qanday filtr yo'q bo'lsa — faqat to'ldirganlar
        if (!$request->filled('group_id') && !$request->filled('department_id')) {
            $query->whereNotNull('gp.id');
        }

        $students = $query->select(
                's.id', 's.hemis_id', 's.student_id_number', 's.full_name',
                's.department_name', 's.group_name',
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

        return response()->json(['students' => $students]);
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
