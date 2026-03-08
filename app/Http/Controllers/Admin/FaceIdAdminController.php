<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaceIdDescriptor;
use App\Models\FaceIdLog;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\FaceIdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * Admin uchun sinov sahifasi — real vaqtda debug ma'lumotlari bilan.
     */
    public function testPage()
    {
        $debug = [];
        $errors = [];

        // 1. FaceIdService settings
        try {
            $settings = FaceIdService::getSettings();
            $debug['settings'] = $settings;
        } catch (\Throwable $e) {
            $errors[] = 'FaceIdService::getSettings() — ' . $e->getMessage();
            $settings = ['threshold'=>0.4,'blinks_required'=>2,'head_turn_required'=>true,'liveness_timeout'=>30];
        }

        // 2. FaceIdDescriptor count
        try {
            $enrolledCount = FaceIdDescriptor::count();
            $debug['enrolled_count'] = $enrolledCount;
        } catch (\Throwable $e) {
            $errors[] = 'FaceIdDescriptor::count() — ' . $e->getMessage();
            $enrolledCount = 0;
        }

        // 3. Student count
        try {
            $totalStudents = Student::whereNotNull('image')->count();
            $debug['students'] = $totalStudents;
        } catch (\Throwable $e) {
            $errors[] = 'Student::count() — ' . $e->getMessage();
            $totalStudents = 0;
        }

        // 4. Teacher count
        try {
            $totalTeachers = Teacher::whereNotNull('image')->count();
            $debug['teachers'] = $totalTeachers;
        } catch (\Throwable $e) {
            $errors[] = 'Teacher::count() — ' . $e->getMessage();
            $totalTeachers = 0;
        }

        // 5. View mavjudmi?
        try {
            $viewExists = view()->exists('admin.face-id.test');
            $debug['view_exists'] = $viewExists;
        } catch (\Throwable $e) {
            $errors[] = 'view check — ' . $e->getMessage();
        }

        // Debug chiqish funksiyasi
        $showDebug = function(array $errs, array $dbg) {
            return response('<pre style="background:#1e293b;color:#f8fafc;padding:24px;font-size:13px;line-height:1.8;margin:0;">'
                . '<b style="color:#f87171;font-size:16px;">⚠️ Face ID Test — Xato</b>' . "\n\n"
                . (empty($errs) ? '<span style="color:#4ade80;">✅ Controller OK</span>' : implode("\n", array_map(fn($e) => '❌ ' . htmlspecialchars($e), $errs)))
                . "\n\n<b style=\"color:#38bdf8;\">Debug:</b>\n"
                . htmlspecialchars(json_encode($dbg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                . '</pre>', 200);
        };

        // Controller xatosi bo'lsa — ko'rsat
        if (!empty($errors)) {
            return $showDebug($errors, $debug);
        }

        // View render xatosini ham ushla
        try {
            return view('admin.face-id.test', compact('settings', 'enrolledCount', 'totalStudents', 'totalTeachers'));
        } catch (\Throwable $e) {
            $errors[] = 'view render — ' . $e->getMessage();
            $debug['view_error_trace'] = substr($e->getTraceAsString(), 0, 1000);
            return $showDebug($errors, $debug);
        }
    }

    /**
     * Xodimni employee_id_number bo'yicha topish (test sahifasi uchun).
     */
    public function checkTeacher(Request $request)
    {
        $request->validate(['employee_id_number' => 'required|string|max:50']);

        $teacher = Teacher::where('employee_id_number', trim($request->employee_id_number))->first();

        if (!$teacher) {
            return response()->json(['error' => 'Xodim topilmadi.'], 404);
        }

        return response()->json([
            'teacher_id'  => $teacher->id,
            'full_name'   => $teacher->full_name,
            'position'    => $teacher->staff_position,
            'department'  => $teacher->department,
            'photo_url'   => route('admin.face-id.teacher-photo', ['id' => $teacher->id]),
            'has_photo'   => !empty($teacher->image),
        ]);
    }

    /**
     * Xodim rasmini proxy orqali berish (CORS muammosini hal qilish).
     */
    public function teacherPhoto(int $id)
    {
        $teacher = Teacher::find($id);

        if (!$teacher || empty($teacher->image)) {
            abort(404, 'Rasm topilmadi');
        }

        $url = $teacher->image;
        if (!str_starts_with($url, 'http')) {
            $base = rtrim(config('services.hemis.base_url', 'https://student.ttatf.uz'), '/');
            $url  = $base . '/' . ltrim($url, '/');
        }

        try {
            $response = Http::withoutVerifying()->timeout(10)->get($url);
            if (!$response->successful()) {
                abort(404, 'Rasm yuklab bo\'lmadi');
            }
            return response($response->body())
                ->header('Content-Type', $response->header('Content-Type') ?? 'image/jpeg')
                ->header('Cache-Control', 'private, max-age=3600')
                ->header('Access-Control-Allow-Origin', '*');
        } catch (\Throwable $e) {
            Log::warning('[FaceID] Teacher rasm fetch xatosi', ['id' => $id, 'error' => $e->getMessage()]);
            abort(404, 'Rasm yuklab bo\'lmadi');
        }
    }

    /**
     * Barcha enrolled talabalar descriptorlarini qaytarish (live recognition uchun).
     * Limit: 500 ta so'nggi enrolled.
     */
    public function allDescriptors()
    {
        $rows = FaceIdDescriptor::with(['student:id,full_name,student_id_number,image'])
            ->orderByDesc('enrolled_at')
            ->limit(500)
            ->get();

        $data = $rows->filter(fn($r) => $r->student !== null)->map(fn($r) => [
            'id'         => $r->student->id,
            'name'       => $r->student->full_name,
            'id_number'  => $r->student->student_id_number,
            'photo_url'  => route('student.face-id.photo', ['id' => $r->student->id]),
            'descriptor' => $r->descriptor,   // array[128]
            'type'       => 'student',
        ])->values();

        return response()->json(['people' => $data, 'count' => $data->count()]);
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
