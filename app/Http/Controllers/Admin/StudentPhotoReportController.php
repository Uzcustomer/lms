<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentPhoto;
use App\Models\Teacher;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StudentPhotoReportController extends Controller
{
    public function index(Request $request)
    {
        $query = StudentPhoto::query()
            ->leftJoin('students', 'students.student_id_number', '=', 'student_photos.student_id_number')
            ->select([
                'student_photos.*',
                'students.image as student_profile_image',
                'students.department_name as department_name',
                'students.specialty_name as specialty_name',
                'students.level_name as level_name',
                'students.group_name as student_group_name',
            ])
            ->orderByDesc('student_photos.created_at');

        if ($request->filled('status')) {
            $query->where('student_photos.status', $request->status);
        }

        if ($request->filled('search')) {
            $needle = trim($request->search);
            $query->where(function ($q) use ($needle) {
                $q->where('student_photos.full_name', 'like', "%{$needle}%")
                  ->orWhere('student_photos.student_id_number', 'like', "%{$needle}%");
            });
        }

        if ($request->filled('department')) {
            $query->where('students.department_name', $request->department);
        }

        if ($request->filled('specialty')) {
            $query->where('students.specialty_name', $request->specialty);
        }

        if ($request->filled('level')) {
            $query->where('students.level_name', $request->level);
        }

        if ($request->filled('group')) {
            $query->where(function ($q) use ($request) {
                $q->where('students.group_name', $request->group)
                  ->orWhere('student_photos.group_name', $request->group);
            });
        }

        if ($request->filled('tutor')) {
            $query->where('student_photos.uploaded_by', 'like', '%' . $request->tutor . '%');
        }

        if ($request->filled('date_from')) {
            $query->where('student_photos.created_at', '>=', $request->date_from . ' 00:00:00');
        }

        if ($request->filled('date_to')) {
            $query->where('student_photos.created_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('similarity')) {
            if ($request->similarity === 'match') {
                $query->where('student_photos.similarity_status', 'match');
            } elseif ($request->similarity === 'mismatch') {
                $query->where('student_photos.similarity_status', 'mismatch');
            } elseif ($request->similarity === 'unchecked') {
                $query->whereNull('student_photos.similarity_checked_at');
            }
        }

        if ($request->filled('min_similarity')) {
            $query->where('student_photos.similarity_score', '>=', (float) $request->min_similarity);
        }
        if ($request->filled('max_similarity')) {
            $query->where('student_photos.similarity_score', '<=', (float) $request->max_similarity);
        }

        $perPage = (int) $request->get('per_page', 30);
        $perPage = in_array($perPage, [10, 25, 30, 50, 100, 200]) ? $perPage : 30;

        $photos = $query->paginate($perPage)->withQueryString();

        $stats = [
            'total' => StudentPhoto::count(),
            'pending' => StudentPhoto::where('status', StudentPhoto::STATUS_PENDING)->count(),
            'approved' => StudentPhoto::where('status', StudentPhoto::STATUS_APPROVED)->count(),
            'rejected' => StudentPhoto::where('status', StudentPhoto::STATUS_REJECTED)->count(),
        ];

        $departments = Student::select('department_name')
            ->whereNotNull('department_name')
            ->distinct()
            ->orderBy('department_name')
            ->pluck('department_name');

        $specialties = Student::select('specialty_name')
            ->whereNotNull('specialty_name')
            ->distinct()
            ->orderBy('specialty_name')
            ->pluck('specialty_name');

        $levels = Student::select('level_name')
            ->whereNotNull('level_name')
            ->distinct()
            ->orderBy('level_name')
            ->pluck('level_name');

        $groups = Student::select('group_name')
            ->whereNotNull('group_name')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name');

        $tutors = StudentPhoto::select('uploaded_by')
            ->whereNotNull('uploaded_by')
            ->distinct()
            ->orderBy('uploaded_by')
            ->pluck('uploaded_by');

        return view('admin.student-photos.index', compact(
            'photos', 'stats', 'departments', 'specialties', 'levels', 'groups', 'tutors'
        ));
    }

    public function pendingIds(Request $request)
    {
        $query = StudentPhoto::query()
            ->leftJoin('students', 'students.student_id_number', '=', 'student_photos.student_id_number')
            ->select('student_photos.id')
            ->where('student_photos.status', StudentPhoto::STATUS_PENDING);

        if ($request->filled('search')) {
            $needle = trim($request->search);
            $query->where(function ($q) use ($needle) {
                $q->where('student_photos.full_name', 'like', "%{$needle}%")
                  ->orWhere('student_photos.student_id_number', 'like', "%{$needle}%");
            });
        }

        if ($request->filled('department')) {
            $query->where('students.department_name', $request->department);
        }

        if ($request->filled('specialty')) {
            $query->where('students.specialty_name', $request->specialty);
        }

        if ($request->filled('level')) {
            $query->where('students.level_name', $request->level);
        }

        if ($request->filled('group')) {
            $query->where(function ($q) use ($request) {
                $q->where('students.group_name', $request->group)
                  ->orWhere('student_photos.group_name', $request->group);
            });
        }

        if ($request->filled('tutor')) {
            $query->where('student_photos.uploaded_by', $request->tutor);
        }

        if ($request->filled('date_from')) {
            $query->where('student_photos.created_at', '>=', $request->date_from . ' 00:00:00');
        }

        if ($request->filled('date_to')) {
            $query->where('student_photos.created_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('min_similarity')) {
            $query->where('student_photos.similarity_score', '>=', (float) $request->min_similarity);
        }
        if ($request->filled('max_similarity')) {
            $query->where('student_photos.similarity_score', '<=', (float) $request->max_similarity);
        }

        // For bulk AI, restrict to photos not yet checked
        if ($request->boolean('only_unchecked')) {
            $query->whereNull('student_photos.similarity_checked_at');
        }

        // Safety cap to avoid runaway browser loops
        $ids = $query->orderBy('student_photos.id')
            ->limit(500)
            ->pluck('student_photos.id');

        return response()->json([
            'ids' => $ids,
            'count' => $ids->count(),
        ]);
    }

    public function approve(Request $request, $id)
    {
        $photo = StudentPhoto::findOrFail($id);

        if (!$photo->isPending()) {
            return $this->reviewResponse($request, false, 'Bu rasm allaqachon ko\'rib chiqilgan.');
        }

        $user = Auth::user();
        $photo->update([
            'status' => StudentPhoto::STATUS_APPROVED,
            'reviewed_by_name' => $user->name ?? $user->full_name ?? $user->short_name ?? 'Admin',
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->notifyTutor($photo, true);

        return $this->reviewResponse($request, true, 'Rasm tasdiqlandi.');
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $photo = StudentPhoto::findOrFail($id);

        if (!$photo->isPending()) {
            return $this->reviewResponse($request, false, 'Bu rasm allaqachon ko\'rib chiqilgan.');
        }

        $user = Auth::user();
        $photo->update([
            'status' => StudentPhoto::STATUS_REJECTED,
            'reviewed_by_name' => $user->name ?? $user->full_name ?? $user->short_name ?? 'Admin',
            'reviewed_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        $this->notifyTutor($photo, false, $request->rejection_reason);

        return $this->reviewResponse($request, true, 'Rasm rad etildi.');
    }

    protected function reviewResponse(Request $request, bool $ok, string $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }
        return back()->with($ok ? 'success' : 'error', $message);
    }

    protected function notifyTutor(StudentPhoto $photo, bool $approved, ?string $reason = null): void
    {
        $teacher = null;
        if ($photo->uploaded_by_teacher_id) {
            $teacher = Teacher::find($photo->uploaded_by_teacher_id);
        }
        if (!$teacher && $photo->uploaded_by) {
            $teacher = Teacher::where('full_name', $photo->uploaded_by)->first();
        }

        if (!$teacher || empty($teacher->telegram_chat_id)) {
            return;
        }

        if ($approved) {
            $text = "✅ Talaba rasmi qabul qilindi\n\n"
                  . "Talaba: {$photo->full_name}\n"
                  . "Guruh: " . ($photo->group_name ?: '—') . "\n\n"
                  . "Admin tomonidan tasdiqlandi.";
        } else {
            $text = "❌ Talaba rasmi rad etildi\n\n"
                  . "Talaba: {$photo->full_name}\n"
                  . "Guruh: " . ($photo->group_name ?: '—') . "\n"
                  . "Sabab: " . ($reason ?: 'Standartlarga mos emas') . "\n\n"
                  . "Iltimos, talaba rasmi standartlarga (tirsakdan yuqori, oq xalatda, oq fonda) mos ravishda qayta yuklang.";
        }

        try {
            app(TelegramService::class)->sendAndGetId((string) $teacher->telegram_chat_id, $text);
        } catch (\Throwable $e) {
            Log::warning('Tyutorga telegram bildirish yuborilmadi', [
                'photo_id' => $photo->id,
                'teacher_id' => $teacher->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function checkSimilarity(Request $request, $id)
    {
        $photo = StudentPhoto::findOrFail($id);

        $student = Student::where('student_id_number', $photo->student_id_number)->first();
        if (!$student || empty($student->image)) {
            return response()->json([
                'error' => 'Talabaning HEMIS profil rasmi topilmadi.',
            ], 422);
        }

        $uploadedFullPath = public_path($photo->photo_path);
        if (!file_exists($uploadedFullPath)) {
            return response()->json([
                'error' => 'Yuklangan rasm fayli topilmadi.',
            ], 422);
        }

        // Face-compare service may run outside the Laravel container, so
        // local filesystem paths don't translate. Send a URL instead and
        // let the service download the image itself.
        $uploadedUrl = asset($photo->photo_path);

        $serviceUrl = rtrim(config('services.face_compare.url'), '/');
        $timeout = config('services.face_compare.timeout', 60);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($serviceUrl . '/compare', [
                    'image1' => $student->image,
                    'image2' => $uploadedUrl,
                ]);
        } catch (\Throwable $e) {
            Log::error('Face compare service unreachable', [
                'photo_id' => $photo->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'AI servisga ulanib bo\'lmadi: ' . $e->getMessage(),
            ], 503);
        }

        if (!$response->successful()) {
            Log::warning('Face compare service returned error', [
                'photo_id' => $photo->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return response()->json([
                'error' => 'AI servis xatoligi: ' . ($response->json('detail') ?? $response->body()),
            ], 502);
        }

        $data = $response->json();
        $percent = (float) ($data['similarity_percent'] ?? 0);
        $match = (bool) ($data['match'] ?? false);

        $photo->update([
            'similarity_score' => $percent,
            'similarity_status' => $match ? 'match' : 'mismatch',
            'similarity_checked_at' => now(),
        ]);

        return response()->json([
            'similarity_percent' => $percent,
            'distance' => $data['distance'] ?? null,
            'threshold' => $data['threshold'] ?? null,
            'match' => $match,
            'status' => $match ? 'match' : 'mismatch',
            'checked_at' => $photo->similarity_checked_at->toIso8601String(),
        ]);
    }
}
