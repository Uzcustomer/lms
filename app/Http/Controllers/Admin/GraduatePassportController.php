<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentPassport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GraduatePassportController extends Controller
{
    public function index()
    {
        // Statistika: fakultet → yo'nalish → guruh bo'yicha
        $stats = DB::table('graduate_student_passports as gp')
            ->join('students as s', 's.id', '=', 'gp.student_id')
            ->select(
                's.department_name',
                's.specialty_name',
                's.group_name',
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('s.department_name', 's.specialty_name', 's.group_name')
            ->orderBy('s.department_name')
            ->orderBy('s.specialty_name')
            ->orderBy('s.group_name')
            ->get();

        $total = $stats->sum('cnt');

        // Fakultet bo'yicha guruhlash
        $byFaculty = [];
        foreach ($stats as $row) {
            $fac = $row->department_name ?? 'Noma\'lum';
            $spec = $row->specialty_name ?? 'Noma\'lum';
            $grp = $row->group_name ?? '-';

            if (!isset($byFaculty[$fac])) {
                $byFaculty[$fac] = ['total' => 0, 'specialties' => []];
            }
            $byFaculty[$fac]['total'] += $row->cnt;

            if (!isset($byFaculty[$fac]['specialties'][$spec])) {
                $byFaculty[$fac]['specialties'][$spec] = ['total' => 0, 'groups' => []];
            }
            $byFaculty[$fac]['specialties'][$spec]['total'] += $row->cnt;
            $byFaculty[$fac]['specialties'][$spec]['groups'][$grp] = $row->cnt;
        }

        return view('admin.graduate-passports.index', compact('byFaculty', 'total'));
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
                's.level_name', 's.semester_name'
            );

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('s.full_name', 'like', "%{$search}%")
                  ->orWhere('s.student_id_number', 'like', "%{$search}%")
                  ->orWhere('gp.passport_number', 'like', "%{$search}%")
                  ->orWhere('gp.jshshir', 'like', "%{$search}%")
                  ->orWhere('s.group_name', 'like', "%{$search}%")
                  ->orWhere('s.department_name', 'like', "%{$search}%");
            });
        }

        $query->orderBy('gp.created_at', 'desc');

        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);
        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $data = $query->offset($offset)->limit($perPage)->get()->map(function ($row, $i) use ($offset) {
            return [
                'row_num' => $offset + $i + 1,
                'id' => $row->id,
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
