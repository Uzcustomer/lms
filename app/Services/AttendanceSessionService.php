<?php

namespace App\Services;

use App\Exceptions\AttendanceException;
use App\Jobs\SendPushToDevices;
use App\Models\AttendanceConfirmation;
use App\Models\AttendanceSession;
use App\Models\AttendanceSessionGroup;
use App\Models\Beacon;
use App\Models\PresenceLog;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Teacher-side beacon attendance, shared by the mobile API and the web
 * page: a lesson slot from the HEMIS schedule becomes an attendance
 * session; students confirm from their phones while the window is open.
 */
class AttendanceSessionService
{
    /**
     * The teacher's lessons for a day, one entry per (subject, pair,
     * auditorium) — a lecture attended by several groups is a single slot.
     */
    public function lessonsFor(Teacher $teacher, Carbon $date): array
    {
        $rows = $this->scheduleRows($teacher->hemis_id, $date)->get();

        return $rows
            ->groupBy(fn ($l) => $l->subject_id . '|' . $l->lesson_pair_code . '|' . ($l->auditorium_code ?? ''))
            ->map(function ($slot) use ($teacher, $date) {
                $first = $slot->first();
                $groupIds = $slot->pluck('group_id')->unique()->values();

                // Every session ever opened for this slot (a teacher may open
                // it twice); the newest is the "current" one.
                $sessions = AttendanceSession::where('teacher_id', $teacher->id)
                    ->whereDate('lesson_date', $date->toDateString())
                    ->where('subject_id', $first->subject_id)
                    ->where('lesson_pair_code', $first->lesson_pair_code)
                    ->with(['beacon', 'groups'])
                    ->orderByDesc('id')
                    ->get()
                    ->each(fn (AttendanceSession $s) => $s->closeIfExpired());
                $session = $sessions->first();

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
                    'sessions' => $sessions->map(fn (AttendanceSession $s) => $s->toSummary())->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /** Open the confirmation window for one lesson slot (idempotent while open). */
    public function start(
        Teacher $teacher,
        int $subjectId,
        string $lessonPairCode,
        Carbon $date,
        ?int $windowMinutes = null,
    ): AttendanceSession {
        $rows = $this->scheduleRows($teacher->hemis_id, $date)
            ->where('subject_id', $subjectId)
            ->where('lesson_pair_code', $lessonPairCode)
            ->get();
        if ($rows->isEmpty()) {
            throw new AttendanceException('Bu vaqtda jadvalda darsingiz topilmadi.', 404);
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
            return $existing->load(['beacon', 'groups']);
        }

        $beacon = $this->beaconFor($first->auditorium_code);
        $window = $windowMinutes ?? (int) config('services.attendance.window_minutes', 10);
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

        return $session;
    }

    /** Manual override — the teacher's decision beats the student's. */
    public function mark(AttendanceSession $session, int $studentId, string $status): void
    {
        $confirmation = AttendanceConfirmation::where('session_id', $session->id)
            ->where('student_id', $studentId)
            ->first();
        if (!$confirmation) {
            throw new AttendanceException('Talaba bu sessiyada topilmadi.', 404);
        }

        $confirmation->update([
            'status' => $status,
            'decided_by' => 'teacher',
            'confirmed_at' => $status === AttendanceConfirmation::STATUS_PRESENT
                ? ($confirmation->confirmed_at ?? now())
                : $confirmation->confirmed_at,
        ]);
    }

    /** Re-push to students seen in the room since opening who have not confirmed. Returns how many. */
    public function remind(AttendanceSession $session): int
    {
        $session->closeIfExpired();
        if (!$session->isOpen()) {
            throw new AttendanceException('Davomat oynasi yopilgan.');
        }

        $pendingIds = AttendanceConfirmation::where('session_id', $session->id)
            ->where('status', AttendanceConfirmation::STATUS_PENDING)
            ->pluck('student_id')
            ->all();

        $seenIds = $session->beacon
            ? $this->recentlySeen($session->beacon->id, $pendingIds, $session->opened_at)
            : [];
        if ($seenIds !== []) {
            AttendanceConfirmation::where('session_id', $session->id)
                ->whereIn('student_id', $seenIds)
                ->update(['beacon_seen' => true, 'notified' => true]);
        }

        $this->notify($session, $seenIds, array_values(array_diff($pendingIds, $seenIds)));

        return count($seenIds);
    }

    public function close(AttendanceSession $session): void
    {
        if ($session->status === AttendanceSession::STATUS_OPEN) {
            $session->close('system');
        }
    }

    /**
     * Throw the session away entirely — confirmations included — so the
     * lesson can be started afresh (mainly for trial runs and mistakes).
     */
    public function cancel(AttendanceSession $session): void
    {
        $session->delete(); // confirmations and groups cascade
    }

    /** Session summary + per-student rows, the shape both clients render. */
    public function live(AttendanceSession $session, array $extra = []): array
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
                'rssi' => $c->rssi,
                'confirmed_at' => $c->confirmed_at?->toIso8601String(),
            ])
            ->sortBy([['group_name', 'asc'], ['full_name', 'asc']])
            ->values();

        return ['session' => $session->toSummary(), 'students' => $students] + $extra;
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
}
