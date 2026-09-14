<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendPushToDevices;
use App\Models\AttendanceConfirmation;
use App\Models\AttendanceSession;
use App\Models\AttendanceSessionGroup;
use App\Models\Beacon;
use App\Models\DeviceToken;
use App\Models\PresenceLog;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Teacher side of beacon attendance: today's lessons from the HEMIS
 * schedule, open a confirmation window for one slot, watch the live list,
 * override, close.
 */
class TeacherAttendanceApiController extends Controller
{
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

    /**
     * The teacher's lessons for a day, one entry per (subject, pair,
     * auditorium) — a lecture attended by several groups is a single slot.
     */
    public function lessons(Request $request): JsonResponse
    {
        $teacher = $request->user();
        $date = $request->filled('date') ? Carbon::parse($request->input('date'))->startOfDay() : today();

        $rows = $this->scheduleRows($teacher->hemis_id, $date)->get();

        $lessons = $rows
            ->groupBy(fn ($l) => $l->subject_id . '|' . $l->lesson_pair_code . '|' . ($l->auditorium_code ?? ''))
            ->map(function ($slot) use ($teacher, $date) {
                $first = $slot->first();
                $groupIds = $slot->pluck('group_id')->unique()->values();

                $session = AttendanceSession::where('teacher_id', $teacher->id)
                    ->whereDate('lesson_date', $date->toDateString())
                    ->where('subject_id', $first->subject_id)
                    ->where('lesson_pair_code', $first->lesson_pair_code)
                    ->with(['beacon', 'groups'])
                    ->latest('id')
                    ->first();
                $session?->closeIfExpired();

                return [
                    'subject_id' => (int) $first->subject_id,
                    'subject_name' => $first->subject_name,
                    'semester_code' => $first->semester_code,
                    'lesson_pair_code' => $first->lesson_pair_code,
                    'lesson_pair_name' => $first->lesson_pair_name,
                    'start_time' => substr((string) $first->lesson_pair_start_time, 0, 5),
                    'end_time' => substr((string) $first->lesson_pair_end_time, 0, 5),
                    'training_type_name' => $first->training_type_name,
                    'auditorium_code' => $first->auditorium_code,
                    'auditorium_name' => $first->auditorium_name,
                    'group_ids' => $groupIds,
                    'group_names' => $slot->pluck('group_name')->unique()->values(),
                    'students_count' => Student::whereIn('group_id', $groupIds)->count(),
                    'has_beacon' => $this->beaconFor($first->auditorium_code) !== null,
                    'session' => $session?->toSummary(),
                ];
            })
            ->values();

        return response()->json(['data' => ['date' => $date->toDateString(), 'lessons' => $lessons]]);
    }

    /** Open the confirmation window for one lesson slot (idempotent while open). */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer'],
            'lesson_pair_code' => ['required', 'string', 'max:32'],
            'date' => ['nullable', 'date'],
            'window_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $teacher = $request->user();
        $date = isset($data['date']) ? Carbon::parse($data['date'])->startOfDay() : today();

        $rows = $this->scheduleRows($teacher->hemis_id, $date)
            ->where('subject_id', $data['subject_id'])
            ->where('lesson_pair_code', $data['lesson_pair_code'])
            ->get();
        if ($rows->isEmpty()) {
            return response()->json(['message' => 'Bu vaqtda jadvalda darsingiz topilmadi.'], 404);
        }
        $first = $rows->first();

        $existing = AttendanceSession::where('teacher_id', $teacher->id)
            ->whereDate('lesson_date', $date->toDateString())
            ->where('subject_id', $first->subject_id)
            ->where('lesson_pair_code', $first->lesson_pair_code)
            ->open()
            ->latest('id')
            ->first();
        if ($existing) {
            return $this->live($existing);
        }

        $beacon = $this->beaconFor($first->auditorium_code);
        $window = (int) ($data['window_minutes'] ?? config('services.attendance.window_minutes', 10));
        $groupIds = $rows->pluck('group_id')->unique()->values();
        $students = Student::whereIn('group_id', $groupIds)->get(['id', 'hemis_id']);
        $seenIds = $beacon ? $this->recentlySeen($beacon->id, $students->pluck('id')->all(), now()) : [];

        $session = DB::transaction(function () use ($teacher, $first, $rows, $beacon, $window, $students, $seenIds) {
            $session = AttendanceSession::create([
                'teacher_id' => $teacher->id,
                'teacher_hemis_id' => $teacher->hemis_id,
                'subject_id' => $first->subject_id,
                'subject_name' => $first->subject_name,
                'semester_code' => $first->semester_code,
                'lesson_date' => Carbon::parse($first->lesson_date)->toDateString(),
                'lesson_pair_code' => $first->lesson_pair_code,
                'lesson_pair_name' => $first->lesson_pair_name,
                'training_type_name' => $first->training_type_name,
                'auditorium_code' => $first->auditorium_code,
                'auditorium_name' => $first->auditorium_name,
                'beacon_id' => $beacon?->id,
                'opened_at' => now(),
                'closes_at' => now()->addMinutes($window),
                'status' => AttendanceSession::STATUS_OPEN,
            ]);

            foreach ($rows->unique('group_id') as $row) {
                AttendanceSessionGroup::create([
                    'session_id' => $session->id,
                    'group_hemis_id' => $row->group_id,
                    'group_name' => $row->group_name,
                ]);
            }

            $seenSet = array_flip($seenIds);
            $now = now();
            $inserts = $students->map(fn ($s) => [
                'session_id' => $session->id,
                'student_id' => $s->id,
                'student_hemis_id' => $s->hemis_id,
                'status' => AttendanceConfirmation::STATUS_PENDING,
                'beacon_seen' => isset($seenSet[$s->id]),
                'notified' => isset($seenSet[$s->id]),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            foreach (array_chunk($inserts, 500) as $chunk) {
                AttendanceConfirmation::insert($chunk);
            }

            return $session;
        });

        $session->load(['beacon', 'groups']);
        $this->notify($session, $seenIds, $students->pluck('id')->diff($seenIds)->values()->all());

        return $this->live($session);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $session = $this->ownSession($request, $id);
        $session->closeIfExpired();

        return $this->live($session);
    }

    /** Manual override — the teacher's decision beats the student's. */
    public function mark(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'status' => ['required', 'in:present,absent'],
        ]);

        $session = $this->ownSession($request, $id);
        $confirmation = AttendanceConfirmation::where('session_id', $session->id)
            ->where('student_id', $data['student_id'])
            ->first();
        if (!$confirmation) {
            return response()->json(['message' => 'Talaba bu sessiyada topilmadi.'], 404);
        }

        $confirmation->update([
            'status' => $data['status'],
            'decided_by' => 'teacher',
            'confirmed_at' => $data['status'] === AttendanceConfirmation::STATUS_PRESENT
                ? ($confirmation->confirmed_at ?? now())
                : $confirmation->confirmed_at,
        ]);

        return $this->live($session);
    }

    /** Re-push to students seen in the room since opening who have not confirmed. */
    public function remind(Request $request, int $id): JsonResponse
    {
        $session = $this->ownSession($request, $id);
        $session->closeIfExpired();
        if (!$session->isOpen()) {
            return response()->json(['message' => 'Davomat oynasi yopilgan.'], 422);
        }

        $pending = AttendanceConfirmation::where('session_id', $session->id)
            ->where('status', AttendanceConfirmation::STATUS_PENDING)
            ->get();
        $pendingIds = $pending->pluck('student_id')->all();

        $seenIds = $session->beacon
            ? $this->recentlySeen($session->beacon->id, $pendingIds, $session->opened_at)
            : [];
        if ($seenIds !== []) {
            AttendanceConfirmation::where('session_id', $session->id)
                ->whereIn('student_id', $seenIds)
                ->update(['beacon_seen' => true, 'notified' => true]);
        }

        $this->notify($session, $seenIds, array_values(array_diff($pendingIds, $seenIds)));

        return $this->live($session, ['reminded' => count($seenIds)]);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $session = $this->ownSession($request, $id);
        if ($session->status === AttendanceSession::STATUS_OPEN) {
            $session->close('teacher');
        }

        return $this->live($session);
    }

    // ── helpers ──────────────────────────────────────────────

    private function scheduleRows(int $employeeHemisId, Carbon $date)
    {
        return DB::table('schedules')
            ->where('employee_id', $employeeHemisId)
            ->whereNull('deleted_at')
            ->whereDate('lesson_date', $date->toDateString())
            ->orderBy('lesson_pair_start_time');
    }

    private function beaconFor(?string $auditoriumCode): ?Beacon
    {
        if (!$auditoriumCode) {
            return null;
        }

        return Beacon::where('auditorium_code', $auditoriumCode)->where('active', true)->first();
    }

    /** Student ids among $studentIds that reported this beacon within the presence TTL before $reference. */
    private function recentlySeen(int $beaconId, array $studentIds, Carbon $reference): array
    {
        if ($studentIds === []) {
            return [];
        }
        $ttl = (int) config('services.attendance.presence_ttl_minutes', 15);

        return PresenceLog::where('beacon_id', $beaconId)
            ->where('seen_at', '>=', $reference->copy()->subMinutes($ttl))
            ->whereIn('student_id', $studentIds)
            ->distinct()
            ->pluck('student_id')
            ->all();
    }

    /**
     * Students already seen at the beacon get the visible "confirm" push;
     * everyone else gets a silent wake-up so their phone scans and, if it is
     * in the room, prompts locally.
     */
    private function notify(AttendanceSession $session, array $eligibleIds, array $otherIds): void
    {
        $beacon = $session->beacon;
        $minutes = max(1, (int) now()->diffInMinutes($session->closes_at, false));
        $data = [
            'type' => 'attendance_confirm',
            'session_id' => $session->id,
            'subject_name' => $session->subject_name,
            'auditorium_name' => (string) $session->auditorium_name,
            'closes_at' => $session->closes_at->toIso8601String(),
            'uuid' => $beacon?->uuid ?? '',
            'major' => $beacon?->major ?? '',
            'minor' => $beacon?->minor ?? '',
        ];

        if ($eligibleIds !== []) {
            SendPushToDevices::dispatch('student', $eligibleIds, [
                'title' => 'Davomatni tasdiqlang',
                'body' => "{$session->subject_name} — {$session->auditorium_name}. {$minutes} daqiqa ichida tasdiqlang.",
            ], $data);
        }
        if ($otherIds !== []) {
            SendPushToDevices::dispatch('student', $otherIds, [], ['type' => 'attendance_opened'] + $data);
        }
    }

    private function ownSession(Request $request, int $id): AttendanceSession
    {
        $session = AttendanceSession::with(['beacon', 'groups'])->findOrFail($id);
        abort_if($session->teacher_id !== $request->user()->id, 403, 'Bu davomat sessiyasi sizga tegishli emas.');

        return $session;
    }

    private function live(AttendanceSession $session, array $extra = []): JsonResponse
    {
        $session->loadMissing(['beacon', 'groups']);

        $students = AttendanceConfirmation::where('session_id', $session->id)
            ->with('student:id,full_name,group_name,group_id,student_id_number')
            ->get()
            ->map(fn (AttendanceConfirmation $c) => [
                'student_id' => $c->student_id,
                'full_name' => $c->student?->full_name,
                'student_id_number' => $c->student?->student_id_number,
                'group_name' => $c->student?->group_name,
                'status' => $c->status,
                'decided_by' => $c->decided_by,
                'beacon_seen' => $c->beacon_seen,
                'notified' => $c->notified,
                'confirmed_at' => $c->confirmed_at?->toIso8601String(),
            ])
            ->sortBy([['group_name', 'asc'], ['full_name', 'asc']])
            ->values();

        return response()->json([
            'data' => ['session' => $session->toSummary(), 'students' => $students] + $extra,
        ]);
    }
}
