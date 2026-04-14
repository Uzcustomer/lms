<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GraduatePassportController extends Controller
{
    public function index()
    {
        // Umumiy statistika (barcha bakalavr bitiruvchilar)
        $stats = (object) [
            'total' => 0,
            'male' => 0,
            'female' => 0,
            'filled' => 0,
        ];

        $agg = DB::table('students as s')
            ->leftJoin('graduate_student_passports as gp', 'gp.student_id', '=', 's.id')
            ->where('s.is_graduate', true)
            ->where('s.education_type_code', '11')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN s.gender_code = '11' THEN 1 ELSE 0 END) as male"),
                DB::raw("SUM(CASE WHEN s.gender_code = '12' THEN 1 ELSE 0 END) as female"),
                DB::raw('SUM(CASE WHEN gp.id IS NOT NULL THEN 1 ELSE 0 END) as filled')
            )
            ->first();

        if ($agg) {
            $stats->total = (int) $agg->total;
            $stats->male = (int) $agg->male;
            $stats->female = (int) $agg->female;
            $stats->filled = (int) $agg->filled;
        }

        // Fakultetlar ro'yxati (faqat bakalavr bitiruvchilari bor fakultetlar)
        $faculties = DB::table('students as s')
            ->leftJoin('departments as d', 'd.department_hemis_id', '=', 's.department_id')
            ->leftJoin('departments as f', 'f.id', '=', 'd.parent_id')
            ->where('s.is_graduate', true)
            ->where('s.education_type_code', '11')
            ->whereNotNull('f.id')
            ->select('f.id', 'f.name', DB::raw('COUNT(*) as total'))
            ->groupBy('f.id', 'f.name')
            ->orderBy('f.name')
            ->get();

        // Guruhlar ro'yxati (fakultet tanlanganda client tomondan filterlanadi)
        $groups = DB::table('students as s')
            ->leftJoin('departments as d', 'd.department_hemis_id', '=', 's.department_id')
            ->leftJoin('departments as f', 'f.id', '=', 'd.parent_id')
            ->where('s.is_graduate', true)
            ->where('s.education_type_code', '11')
            ->whereNotNull('s.group_id')
            ->select(
                's.group_name',
                's.group_id',
                'f.id as faculty_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN EXISTS(SELECT 1 FROM graduate_student_passports gp WHERE gp.student_id = s.id) THEN 1 ELSE 0 END) as filled')
            )
            ->groupBy('s.group_name', 's.group_id', 'f.id')
            ->orderBy('s.group_name')
            ->get();

        return view('admin.graduate-passports.index', compact('stats', 'faculties', 'groups'));
    }

    public function data(Request $request)
    {
        $query = DB::table('students as s')
            ->leftJoin('graduate_student_passports as gp', 'gp.student_id', '=', 's.id')
            ->leftJoin('departments as d', 'd.department_hemis_id', '=', 's.department_id')
            ->leftJoin('departments as f', 'f.id', '=', 'd.parent_id')
            ->where('s.is_graduate', true)
            ->where('s.education_type_code', '11');

        if ($request->filled('faculty_id')) {
            $query->where('f.id', $request->faculty_id);
        }

        if ($request->filled('group_id')) {
            $query->where('s.group_id', $request->group_id);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('s.full_name', 'like', "%{$s}%")
                  ->orWhere('s.student_id_number', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'filled') {
                $query->whereNotNull('gp.id');
            } elseif ($request->status === 'empty') {
                $query->whereNull('gp.id');
            }
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
