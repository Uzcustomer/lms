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
        $photosQuery = $this->applyFilters($this->baseQuery(), $request)
            ->select([
                'student_photos.*',
                'students.image as student_profile_image',
                'students.department_name as department_name',
                'students.specialty_name as specialty_name',
                'students.level_name as level_name',
                'students.group_name as student_group_name',
            ]);

        $this->applySort($photosQuery, $request);

        $photos = $photosQuery
            ->paginate($this->perPage($request))
            ->withQueryString();

        $stats = [
            'total' => StudentPhoto::count(),
            'pending' => StudentPhoto::where('status', StudentPhoto::STATUS_PENDING)->count(),
            'approved' => StudentPhoto::where('status', StudentPhoto::STATUS_APPROVED)->count(),
            'rejected' => StudentPhoto::where('status', StudentPhoto::STATUS_REJECTED)->count(),
        ];

        // Cascading dropdowns: each list reflects values that survive ALL
        // currently-selected filters EXCEPT the one being built.
        $departments = $this->distinctValues($request, 'department', 'students.department_name');
        $specialties = $this->distinctValues($request, 'specialty', 'students.specialty_name');
        $levels = $this->distinctValues($request, 'level', 'students.level_name');
        $groups = $this->distinctValues($request, 'group', 'students.group_name');
        $tutors = $this->distinctValues($request, 'tutor', 'student_photos.uploaded_by');

        return view('admin.student-photos.index', compact(
            'photos', 'stats', 'departments', 'specialties', 'levels', 'groups', 'tutors'
        ));
    }

    protected function baseQuery()
    {
        return StudentPhoto::query()
            ->leftJoin('students', 'students.student_id_number', '=', 'student_photos.student_id_number');
    }

    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->get('per_page', 30);
        return in_array($perPage, [10, 25, 30, 50, 100, 200]) ? $perPage : 30;
    }

    protected function applySort($query, Request $request): void
    {
        $allowed = [
            'id' => 'student_photos.id',
            'full_name' => 'student_photos.full_name',
            'student_id_number' => 'student_photos.student_id_number',
            'department_name' => 'students.department_name',
            'specialty_name' => 'students.specialty_name',
            'level_name' => 'students.level_name',
            'group_name' => 'students.group_name',
            'uploaded_by' => 'student_photos.uploaded_by',
            'similarity_score' => 'student_photos.similarity_score',
            'quality_score' => 'student_photos.quality_score',
            'status' => 'student_photos.status',
            'created_at' => 'student_photos.created_at',
        ];

        $sort = $request->get('sort');
        $dir = strtolower($request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sort && isset($allowed[$sort])) {
            $query->orderBy($allowed[$sort], $dir);
        } else {
            $query->orderByDesc('student_photos.created_at');
        }
    }

    /**
     * Apply the photo filters, optionally skipping specific ones so the
     * caller can compute the values that would remain visible in a
     * dropdown when that dropdown's own value is excluded.
     */
    protected function applyFilters($query, Request $request, array $except = [])
    {
        $has = fn(string $key) => !in_array($key, $except) && $request->filled($key);

        if ($has('status')) {
            $query->where('student_photos.status', $request->status);
        }
        if ($has('search')) {
            $needle = trim($request->search);
            $query->where(function ($q) use ($needle) {
                $q->where('student_photos.full_name', 'like', "%{$needle}%")
                  ->orWhere('student_photos.student_id_number', 'like', "%{$needle}%");
            });
        }
        if ($has('department')) {
            $query->where('students.department_name', $request->department);
        }
        if ($has('specialty')) {
            $query->where('students.specialty_name', $request->specialty);
        }
        if ($has('level')) {
            $query->where('students.level_name', $request->level);
        }
        if ($has('group')) {
            $query->where(function ($q) use ($request) {
                $q->where('students.group_name', $request->group)
                  ->orWhere('student_photos.group_name', $request->group);
            });
        }
        if ($has('tutor')) {
            $query->where('student_photos.uploaded_by', $request->tutor);
        }
        if ($has('similarity')) {
            if ($request->similarity === 'match') {
                $query->where('student_photos.similarity_status', 'match');
            } elseif ($request->similarity === 'mismatch') {
                $query->where('student_photos.similarity_status', 'mismatch');
            } elseif ($request->similarity === 'unchecked') {
                $query->whereNull('student_photos.similarity_checked_at');
            }
        }
        if ($has('min_similarity')) {
            $query->where('student_photos.similarity_score', '>=', (float) $request->min_similarity);
        }
        if ($has('max_similarity')) {
            $query->where('student_photos.similarity_score', '<=', (float) $request->max_similarity);
        }
        if ($has('quality')) {
            if ($request->quality === 'passed') {
                $query->where('student_photos.quality_passed', true);
            } elseif ($request->quality === 'failed') {
                $query->where('student_photos.quality_passed', false);
            } elseif ($request->quality === 'unchecked') {
                $query->whereNull('student_photos.quality_checked_at');
            }
        }
        if ($has('min_quality')) {
            $query->where('student_photos.quality_score', '>=', (float) $request->min_quality);
        }
        if ($has('max_quality')) {
            $query->where('student_photos.quality_score', '<=', (float) $request->max_quality);
        }

        return $query;
    }

    /**
     * Distinct values for one filter column, scoped by every OTHER filter.
     * This produces cascading dropdowns without an AJAX chain.
     */
    protected function distinctValues(Request $request, string $ownKey, string $column)
    {
        return $this->applyFilters($this->baseQuery(), $request, [$ownKey])
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
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

        // Scope to photos missing specific check(s). Accepts `missing=similarity`,
        // `missing=quality`, `missing=similarity,quality` (either missing), or the
        // legacy `only_unchecked=1` flag which maps to missing similarity.
        // When `rerun=1` is passed we ignore the missing filter entirely so
        // the caller can re-analyze everything matching the page filters.
        if (!$request->boolean('rerun')) {
            $missing = array_filter(explode(',', (string) $request->get('missing', '')));
            if ($request->boolean('only_unchecked') && empty($missing)) {
                $missing = ['similarity'];
            }
            $wantSim = in_array('similarity', $missing, true);
            $wantQual = in_array('quality', $missing, true);
            if ($wantSim && $wantQual) {
                $query->where(function ($q) {
                    $q->whereNull('student_photos.similarity_checked_at')
                      ->orWhereNull('student_photos.quality_checked_at');
                });
            } elseif ($wantSim) {
                $query->whereNull('student_photos.similarity_checked_at');
            } elseif ($wantQual) {
                $query->whereNull('student_photos.quality_checked_at');
            }
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

    public function revert(Request $request, $id)
    {
        $photo = StudentPhoto::findOrFail($id);

        if ($photo->isPending()) {
            return $this->reviewResponse($request, false, 'Bu rasm allaqachon kutilmoqda holatida.');
        }

        $prevStatus = $photo->status;
        $photo->update([
            'status' => StudentPhoto::STATUS_PENDING,
            'reviewed_by_name' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);

        $this->notifyTutorCorrection($photo, $prevStatus);

        return $this->reviewResponse($request, true, 'Rasm kutilmoqda holatiga qaytarildi.');
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

    protected function notifyTutorCorrection(StudentPhoto $photo, string $prevStatus): void
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

        $prevLabel = $prevStatus === 'rejected' ? 'rad etilgan' : 'tasdiqlangan';
        $text = "⚠️ Xato bildirishnoma tuzatilmoqda\n\n"
              . "Talaba: {$photo->full_name}\n"
              . "Guruh: " . ($photo->group_name ?: '—') . "\n\n"
              . "Oldingi \"{$prevLabel}\" xabari noto'g'ri edi — rasm qayta ko'rib chiqilmoqda. "
              . "Yangi qaror chiqarilganda xabar yuboriladi.";

        try {
            app(TelegramService::class)->sendAndGetId((string) $teacher->telegram_chat_id, $text);
        } catch (\Throwable $e) {
            Log::warning('Tyutorga tuzatish bildirishi yuborilmadi', [
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

    public function checkQuality(Request $request, $id)
    {
        $photo = StudentPhoto::findOrFail($id);

        $uploadedFullPath = public_path($photo->photo_path);
        if (!file_exists($uploadedFullPath)) {
            return response()->json(['error' => 'Yuklangan rasm fayli topilmadi.'], 422);
        }

        $serviceUrl = rtrim(config('services.face_compare.url'), '/');
        $timeout = config('services.face_compare.timeout', 60);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($serviceUrl . '/quality-check', [
                    'image' => asset($photo->photo_path),
                ]);
        } catch (\Throwable $e) {
            Log::error('Quality service unreachable', [
                'photo_id' => $photo->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'AI servisga ulanib bo\'lmadi: ' . $e->getMessage()], 503);
        }

        if (!$response->successful()) {
            Log::warning('Quality service returned error', [
                'photo_id' => $photo->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return response()->json([
                'error' => 'AI servis xatoligi: ' . ($response->json('detail') ?? $response->body()),
            ], 502);
        }

        $data = $response->json();
        $score = (float) ($data['quality_score'] ?? 0);
        $passed = (bool) ($data['passed'] ?? false);
        $issues = $data['issues'] ?? [];
        $ok = $data['ok'] ?? [];

        $photo->update([
            'quality_score' => $score,
            'quality_passed' => $passed,
            'quality_issues' => $issues,
            'quality_ok' => $ok,
            'quality_checked_at' => now(),
        ]);

        return response()->json([
            'quality_score' => $score,
            'passed' => $passed,
            'issues' => $issues,
            'ok' => $ok,
            'metrics' => $data['metrics'] ?? null,
            'checked_at' => $photo->quality_checked_at->toIso8601String(),
        ]);
    }
}
