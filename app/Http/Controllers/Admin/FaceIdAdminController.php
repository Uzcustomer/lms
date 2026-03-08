<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaceIdDescriptor;
use App\Models\FaceIdLog;
use App\Models\Setting;
use App\Models\Student;
use App\Services\FaceIdService;
use Illuminate\Http\Request;

class FaceIdAdminController extends Controller
{
    /**
     * Face ID sozlamalari sahifasi.
     */
    public function settings()
    {
        $settings = FaceIdService::getSettings();
        $totalStudents      = Student::count();
        $enrolledCount      = FaceIdDescriptor::count();
        $lastDaySuccess     = FaceIdLog::where('result', 'success')->whereDate('created_at', today())->count();
        $lastDayFailed      = FaceIdLog::where('result', '!=', 'success')->whereDate('created_at', today())->count();

        return view('admin.face-id.settings', compact(
            'settings', 'totalStudents', 'enrolledCount', 'lastDaySuccess', 'lastDayFailed'
        ));
    }

    /**
     * Face ID sozlamalarini yangilash.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'faceid_global_enabled'    => 'boolean',
            'faceid_threshold'         => 'required|numeric|min:0.1|max:1.0',
            'faceid_blinks_required'   => 'required|integer|min:0|max:5',
            'faceid_head_turn_required'=> 'boolean',
            'faceid_liveness_timeout'  => 'required|integer|min:10|max:120',
            'faceid_save_snapshots'    => 'boolean',
            'faceid_max_snapshot_kb'   => 'required|integer|min:10|max:500',
        ]);

        Setting::set('faceid_global_enabled',     $request->boolean('faceid_global_enabled') ? '1' : '0');
        Setting::set('faceid_threshold',          $request->faceid_threshold);
        Setting::set('faceid_blinks_required',    $request->faceid_blinks_required);
        Setting::set('faceid_head_turn_required', $request->boolean('faceid_head_turn_required') ? '1' : '0');
        Setting::set('faceid_liveness_timeout',   $request->faceid_liveness_timeout);
        Setting::set('faceid_save_snapshots',     $request->boolean('faceid_save_snapshots') ? '1' : '0');
        Setting::set('faceid_max_snapshot_kb',    $request->faceid_max_snapshot_kb);

        return redirect()->route('admin.face-id.settings')
            ->with('success', 'Face ID sozlamalari saqlandi.');
    }

    /**
     * Face ID urinishlari logi.
     */
    public function logs(Request $request)
    {
        $query = FaceIdLog::with('student')->orderByDesc('created_at');

        if ($request->result) {
            $query->where('result', $request->result);
        }
        if ($request->student_id_number) {
            $query->where('student_id_number', 'like', '%' . $request->student_id_number . '%');
        }
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        }

        $logs      = $query->paginate(50)->appends($request->query());
        $todayTotal   = FaceIdLog::whereDate('created_at', today())->count();
        $todaySuccess = FaceIdLog::where('result', 'success')->whereDate('created_at', today())->count();

        return view('admin.face-id.logs', compact('logs', 'todayTotal', 'todaySuccess'));
    }

    /**
     * Snapshot tasvirini ko'rsatish.
     */
    public function showSnapshot(int $id)
    {
        $log = FaceIdLog::findOrFail($id);
        if (!$log->snapshot) {
            abort(404);
        }

        $imageData = base64_decode($log->snapshot);
        return response($imageData)->header('Content-Type', 'image/jpeg');
    }

    /**
     * Logni o'chirish.
     */
    public function deleteLog(int $id)
    {
        FaceIdLog::findOrFail($id)->delete();
        return back()->with('success', 'Log o\'chirildi.');
    }

    /**
     * Barcha loglarni tozalash.
     */
    public function clearLogs(Request $request)
    {
        $request->validate(['confirm' => 'required|in:DELETE']);
        FaceIdLog::truncate();
        return redirect()->route('admin.face-id.logs')->with('success', 'Barcha loglar o\'chirildi.');
    }

    /**
     * Talabalar ro'yxati — Face ID holati va enrollment holati bilan.
     */
    public function students(Request $request)
    {
        $query = Student::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->search . '%')
                  ->orWhere('student_id_number', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->has('face_id_enabled') && $request->face_id_enabled !== '') {
            $query->where('face_id_enabled', (bool) $request->face_id_enabled);
        }
        if ($request->enrolled === 'yes') {
            $query->whereHas('faceDescriptor');
        } elseif ($request->enrolled === 'no') {
            $query->whereDoesntHave('faceDescriptor');
        }

        $students = $query->orderBy('full_name')->paginate(50)->appends($request->query());

        return view('admin.face-id.students', compact('students'));
    }

    /**
     * Talaba uchun Face ID yoqish/o'chirish.
     */
    public function toggleStudent(Request $request, int $id)
    {
        $student = Student::findOrFail($id);
        $student->update(['face_id_enabled' => !$student->face_id_enabled]);

        $status = $student->face_id_enabled ? 'yoqildi' : 'o\'chirildi';
        return back()->with('success', "{$student->full_name} uchun Face ID {$status}.");
    }

    /**
     * Talaba descriptor saqlash (admin enrollment sahifasidan).
     */
    public function saveDescriptor(Request $request)
    {
        $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'descriptor' => 'required|array|size:128',
            'source_url' => 'nullable|string|max:500',
        ]);

        $student = Student::findOrFail($request->student_id);
        FaceIdService::saveDescriptor($student, $request->descriptor, $request->source_url);

        return response()->json([
            'success' => true,
            'message' => "{$student->full_name} deskriptori saqlandi.",
        ]);
    }

    /**
     * Descriptor o'chirish.
     */
    public function deleteDescriptor(int $studentId)
    {
        FaceIdDescriptor::where('student_id', $studentId)->delete();
        return back()->with('success', 'Deskriptor o\'chirildi.');
    }

    /**
     * Enrollment sahifasi — HEMIS rasmlari asosida descriptor olish.
     */
    public function enrollment(Request $request)
    {
        $query = Student::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->search . '%')
                  ->orWhere('student_id_number', 'like', '%' . $request->search . '%');
            });
        }

        $filter = $request->get('filter', 'all');
        if ($filter === 'enrolled') {
            $query->whereHas('faceDescriptor');
        } elseif ($filter === 'not_enrolled') {
            $query->whereDoesntHave('faceDescriptor');
        }

        $students = $query->whereNotNull('image')->orderBy('full_name')->paginate(30)->appends($request->query());

        return view('admin.face-id.enrollment', compact('students', 'filter'));
    }
}
