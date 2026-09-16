<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AttendanceException;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Teacher;
use App\Services\AttendanceSessionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web "Davomat" page. A teacher (teacher guard) sees their own lessons;
 * an admin (web guard) first picks a teacher and then acts on their behalf
 * — the resulting session is the teacher's, so it also shows in the app.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceSessionService $service)
    {
    }

    public function index()
    {
        $teacher = $this->resolveTeacher();

        return view('admin.attendance.index', [
            'teacher' => $teacher,
            'canPickTeacher' => !auth()->guard('teacher')->check(),
            'today' => today()->toDateString(),
        ]);
    }

    /** Teacher search for the admin picker. */
    public function teachers(Request $request): JsonResponse
    {
        abort_if(auth()->guard('teacher')->check(), 403);
        $q = trim((string) $request->input('q', ''));

        $rows = Teacher::query()
            ->when($q !== '', fn ($query) => $query->where('full_name', 'like', "%{$q}%"))
            ->orderBy('full_name')
            ->limit(20)
            ->get(['id', 'full_name', 'department']);

        return response()->json(['data' => $rows]);
    }

    public function selectTeacher(Request $request): JsonResponse
    {
        abort_if(auth()->guard('teacher')->check(), 403);
        $data = $request->validate(['teacher_id' => ['required', 'integer', 'exists:teachers,id']]);
        session(['attendance_teacher_id' => (int) $data['teacher_id']]);

        return response()->json(['success' => true]);
    }

    public function lessons(Request $request): JsonResponse
    {
        $teacher = $this->requireTeacher();
        $date = $request->filled('date') ? Carbon::parse($request->input('date'))->startOfDay() : today();

        return response()->json(['data' => [
            'date' => $date->toDateString(),
            'lessons' => $this->service->lessonsFor($teacher, $date),
        ]]);
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer'],
            'lesson_pair_code' => ['required', 'string', 'max:32'],
            'date' => ['nullable', 'date'],
            'window_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);
        $teacher = $this->requireTeacher();
        $date = isset($data['date']) ? Carbon::parse($data['date'])->startOfDay() : today();

        try {
            $session = $this->service->start(
                $teacher,
                (int) $data['subject_id'],
                $data['lesson_pair_code'],
                $date,
                isset($data['window_minutes']) ? (int) $data['window_minutes'] : null,
            );
        } catch (AttendanceException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return $this->live($session);
    }

    public function show(int $id): JsonResponse
    {
        $session = $this->ownSession($id);
        $session->closeIfExpired();

        return $this->live($session);
    }

    public function mark(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'status' => ['required', 'in:present,absent'],
        ]);
        $session = $this->ownSession($id);

        try {
            $this->service->mark($session, (int) $data['student_id'], $data['status']);
        } catch (AttendanceException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return $this->live($session);
    }

    public function remind(int $id): JsonResponse
    {
        $session = $this->ownSession($id);

        try {
            $reminded = $this->service->remind($session);
        } catch (AttendanceException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return $this->live($session, ['reminded' => $reminded]);
    }

    public function close(int $id): JsonResponse
    {
        $session = $this->ownSession($id);
        $this->service->close($session);

        return $this->live($session);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->cancel($this->ownSession($id));

        return response()->json(['success' => true, 'message' => 'Davomat bekor qilindi.']);
    }

    // ── helpers ──────────────────────────────────────────────

    private function resolveTeacher(): ?Teacher
    {
        $teacher = auth()->guard('teacher')->user();
        if ($teacher) {
            return $teacher;
        }
        $id = session('attendance_teacher_id');

        return $id ? Teacher::find($id) : null;
    }

    private function requireTeacher(): Teacher
    {
        $teacher = $this->resolveTeacher();
        abort_if(!$teacher, 422, "Avval o'qituvchini tanlang.");

        return $teacher;
    }

    private function ownSession(int $id): AttendanceSession
    {
        $session = AttendanceSession::with(['beacon', 'groups'])->findOrFail($id);
        $teacher = $this->requireTeacher();
        abort_if($session->teacher_id !== $teacher->id, 403, 'Bu davomat sessiyasi sizga tegishli emas.');

        return $session;
    }

    private function live(AttendanceSession $session, array $extra = []): JsonResponse
    {
        return response()->json(['data' => $this->service->live($session, $extra)]);
    }
}
