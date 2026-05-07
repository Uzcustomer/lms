<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

        // Fakultetlar ro'yxati — students.department_id bo'yicha
        // (HEMIS'da student'ning department_id odatda fakultet hemis_id'sini bildiradi)
        $faculties = DB::table('students as s')
            ->where('s.is_graduate', true)
            ->where('s.education_type_code', '11')
            ->whereNotNull('s.department_id')
            ->select(
                's.department_id as id',
                's.department_name as name',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('s.department_id', 's.department_name')
            ->orderBy('s.department_name')
            ->get();

        // Guruhlar ro'yxati (fakultet tanlanganda client tomondan filterlanadi)
        $groups = DB::table('students as s')
            ->where('s.is_graduate', true)
            ->where('s.education_type_code', '11')
            ->whereNotNull('s.group_id')
            ->select(
                's.group_name',
                's.group_id',
                's.department_id as faculty_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN EXISTS(SELECT 1 FROM graduate_student_passports gp WHERE gp.student_id = s.id) THEN 1 ELSE 0 END) as filled')
            )
            ->groupBy('s.group_name', 's.group_id', 's.department_id')
            ->orderBy('s.group_name')
            ->get();

        return view('admin.graduate-passports.index', compact('stats', 'faculties', 'groups'));
    }

    public function data(Request $request)
    {
        try {
            $hasReviewFields = Schema::hasColumn('graduate_student_passports', 'status');

            $query = DB::table('students as s')
                ->leftJoin('graduate_student_passports as gp', 'gp.student_id', '=', 's.id')
                ->leftJoin('departments as d', 'd.department_hemis_id', '=', 's.department_id')
                ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'd.parent_id')
                ->where('s.is_graduate', true)
                ->where('s.education_type_code', '11');

            if ($request->filled('faculty_id')) {
                $query->where('s.department_id', $request->faculty_id);
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

            $columns = [
                's.id', 's.hemis_id', 's.student_id_number', 's.full_name',
                's.department_name', 's.group_name', 's.gender_code', 's.gender_name',
                'd.name as kafedra_name', 'f.name as faculty_name',
                'gp.id as gp_id', 'gp.first_name', 'gp.last_name', 'gp.father_name',
                'gp.first_name_en', 'gp.last_name_en',
                'gp.passport_series', 'gp.passport_number', 'gp.jshshir',
                'gp.passport_front_path', 'gp.passport_back_path', 'gp.foreign_passport_path',
                'gp.created_at as gp_created_at',
            ];
            if ($hasReviewFields) {
                $columns[] = 'gp.status as gp_status';
                $columns[] = 'gp.reviewed_by';
                $columns[] = 'gp.rejection_reason';
            }

            $students = $query->select($columns)
                ->orderBy('s.full_name')
                ->get()
                ->map(function ($st) use ($hasReviewFields) {
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
                        'gp_status' => $hasReviewFields ? ($st->gp_status ?? 'pending') : 'pending',
                        'reviewed_by' => $hasReviewFields ? ($st->reviewed_by ?? '') : '',
                        'rejection_reason' => $hasReviewFields ? ($st->rejection_reason ?? '') : '',
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
        } catch (\Throwable $e) {
            Log::error('GraduatePassportController@data failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => true,
                'message' => 'Ma\'lumotlarni yuklab bo\'lmadi: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function showFile($id, $field)
    {
        $allowed = ['passport_front_path', 'passport_back_path', 'foreign_passport_path'];
        if (!in_array($field, $allowed)) abort(404);

        $passport = DB::table('graduate_student_passports')->where('id', $id)->first();
        if (!$passport || empty($passport->$field)) abort(404);

        $path = storage_path('app/public/' . $passport->$field);
        if (!file_exists($path)) abort(404);

        // Talaba faylni qayta yuklasa, eski cache ko'rinmasligi uchun no-cache:
        // URL bir xil bo'lgani uchun brauzer eski rasmni cache'dan ko'rsatadi —
        // shuni oldini olamiz.
        return response()->file($path, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function approve($id)
    {
        $gp = DB::table('graduate_student_passports')->where('id', $id)->first();
        if (!$gp) abort(404);

        $user = auth()->user();
        $reviewerName = $user->name ?? $user->full_name ?? $user->short_name ?? 'Admin';

        DB::table('graduate_student_passports')->where('id', $id)->update([
            'status' => 'approved',
            'reviewed_by' => $reviewerName,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Tasdiqlandi']);
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['rejection_reason' => 'required|string|max:500']);

        $gp = DB::table('graduate_student_passports')->where('id', $id)->first();
        if (!$gp) abort(404);

        $user = auth()->user();
        $reviewerName = $user->name ?? $user->full_name ?? $user->short_name ?? 'Admin';

        DB::table('graduate_student_passports')->where('id', $id)->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewerName,
            'reviewed_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        return response()->json(['success' => true, 'message' => 'Rad etildi']);
    }

    public function downloadZip(Request $request)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $query = DB::table('students as s')
            ->join('graduate_student_passports as gp', 'gp.student_id', '=', 's.id')
            ->where('s.is_graduate', true)
            ->where('s.education_type_code', '11');

        if ($request->filled('faculty_id')) {
            $query->where('s.department_id', $request->faculty_id);
        }
        if ($request->filled('group_id')) {
            $query->where('s.group_id', $request->group_id);
        }

        $rows = $query->select(
            's.full_name', 's.student_id_number', 's.group_name',
            'gp.passport_front_path', 'gp.passport_back_path', 'gp.foreign_passport_path'
        )->orderBy('s.group_name')->orderBy('s.full_name')->get();

        if ($rows->isEmpty()) {
            return back()->with('error', 'Hujjatlar topilmadi.');
        }

        $zipName = 'Bitiruvchilar_hujjatlari_' . date('Y-m-d_H-i') . '.zip';
        $zipPath = tempnam(sys_get_temp_dir(), 'grad_') . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'ZIP fayl yaratib bo\'lmadi.');
        }

        $addedFiles = 0;
        foreach ($rows as $row) {
            $safeName = preg_replace('/[\/\\\\:*?"<>|]/', '', $row->full_name);
            $folder = trim($row->group_name . '/' . $row->student_id_number . '_' . $safeName);

            foreach (['passport_front_path' => 'passport_old', 'passport_back_path' => 'passport_orqa', 'foreign_passport_path' => 'xorijiy_passport'] as $field => $label) {
                if (empty($row->$field)) continue;
                $filePath = storage_path('app/public/' . $row->$field);
                if (!file_exists($filePath)) continue;
                $ext = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
                $zip->addFile($filePath, $folder . '/' . $label . '.' . $ext);
                $addedFiles++;
            }
        }

        $zip->close();

        if ($addedFiles === 0) {
            @unlink($zipPath);
            return back()->with('error', 'Yuklanadigan fayllar topilmadi.');
        }

        return response()->download($zipPath, $zipName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
}
