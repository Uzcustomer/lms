<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AttendanceException;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\DeviceToken;
use App\Services\AttendanceSessionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Teacher side of beacon attendance for the mobile app. All logic lives in
 * AttendanceSessionService (shared with the web page); this is transport.
 */
class TeacherAttendanceApiController extends Controller
{
    public function __construct(private readonly AttendanceSessionService $service)
    {
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'in:android,ios,web'],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'owner_type' => 'teacher',
                'owner_id' => $request->user()->id,
                'platform' => $data['platform'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    public function lessons(Request $request): JsonResponse
    {
        $date = $request->filled('date') ? Carbon::parse($request->input('date'))->startOfDay() : today();

        return response()->json(['data' => [
            'date' => $date->toDateString(),
            'lessons' => $this->service->lessonsFor($request->user(), $date),
        ]]);
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer'],
            'lesson_pair_code' => ['required', 'string', 'max:32'],
            'date' => ['nullable', 'date'],
            'window_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'require_face' => ['nullable', 'boolean'],
        ]);
        $date = isset($data['date']) ? Carbon::parse($data['date'])->startOfDay() : today();

        try {
            $session = $this->service->start(
                $request->user(),
                (int) $data['subject_id'],
                $data['lesson_pair_code'],
                $date,
                isset($data['window_minutes']) ? (int) $data['window_minutes'] : null,
                isset($data['require_face']) ? (bool) $data['require_face'] : null,
            );
        } catch (AttendanceException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return $this->live($session);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $session = $this->ownSession($request, $id);
        $session->closeIfExpired();

        return $this->live($session);
    }

    public function mark(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'status' => ['required', 'in:present,absent'],
        ]);
        $session = $this->ownSession($request, $id);

        try {
            $this->service->mark($session, (int) $data['student_id'], $data['status']);
        } catch (AttendanceException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return $this->live($session);
    }

    public function remind(Request $request, int $id): JsonResponse
    {
        $session = $this->ownSession($request, $id);

        try {
            $reminded = $this->service->remind($session);
        } catch (AttendanceException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return $this->live($session, ['reminded' => $reminded]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $session = $this->ownSession($request, $id);
        $this->service->close($session);

        return $this->live($session);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->cancel($this->ownSession($request, $id));

        return response()->json(['success' => true, 'message' => 'Davomat bekor qilindi.']);
    }

    private function ownSession(Request $request, int $id): AttendanceSession
    {
        $session = AttendanceSession::with(['beacon', 'groups'])->findOrFail($id);
        abort_if($session->teacher_id !== $request->user()->id, 403, 'Bu davomat sessiyasi sizga tegishli emas.');

        return $session;
    }

    private function live(AttendanceSession $session, array $extra = []): JsonResponse
    {
        return response()->json(['data' => $this->service->live($session, $extra)]);
    }
}
