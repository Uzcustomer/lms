<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceConfirmation;
use App\Models\AttendanceSession;
use App\Models\Beacon;
use App\Models\DeviceToken;
use App\Models\PresenceLog;
use App\Models\Student;
use App\Services\StudentFaceVerifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student side of beacon attendance: the phone registers for push, reports
 * which beacons it hears, and confirms presence in an open session.
 */
class StudentAttendanceApiController extends Controller
{
    public function __construct(private readonly StudentFaceVerifier $faceVerifier)
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
                'owner_type' => 'student',
                'owner_id' => $request->user()->id,
                'platform' => $data['platform'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    /** Beacon catalogue so the app knows which UUID(s) to monitor. */
    public function beacons(): JsonResponse
    {
        $beacons = Beacon::where('active', true)->orderBy('auditorium_code')->get();

        return response()->json([
            'data' => [
                'uuids' => $beacons->pluck('uuid')->map(fn ($u) => strtolower($u))->unique()->values(),
                'beacons' => $beacons->map(fn (Beacon $b) => $b->toApi())->values(),
            ],
        ]);
    }

    /**
     * Background/foreground sightings. One row per student/beacon/minute;
     * unknown beacons are ignored. Returns any open session the student can
     * confirm right now so the app can prompt without another round-trip.
     */
    public function presence(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sightings' => ['required', 'array', 'max:50'],
            'sightings.*.uuid' => ['required', 'string', 'max:36'],
            'sightings.*.major' => ['required', 'integer', 'min:0', 'max:65535'],
            'sightings.*.minor' => ['required', 'integer', 'min:0', 'max:65535'],
            'sightings.*.rssi' => ['nullable', 'integer', 'min:-127', 'max:0'],
            'sightings.*.seen_at' => ['nullable', 'date'],
            'source' => ['nullable', 'in:background,foreground'],
        ]);

        $student = $request->user();
        $source = $data['source'] ?? 'background';
        $now = now();
        $recorded = 0;

        foreach ($data['sightings'] as $sighting) {
            $beacon = Beacon::matching($sighting['uuid'], (int) $sighting['major'], (int) $sighting['minor'])
                ->where('active', true)
                ->first();
            if (!$beacon) {
                continue;
            }

            $seenAt = isset($sighting['seen_at']) ? Carbon::parse($sighting['seen_at']) : $now->copy();
            // Clock skew / stale batches: anything outside (-1h, +2min) is stamped "now".
            if ($seenAt->gt($now->copy()->addMinutes(2)) || $seenAt->lt($now->copy()->subHour())) {
                $seenAt = $now->copy();
            }

            $duplicate = PresenceLog::where('student_id', $student->id)
                ->where('beacon_id', $beacon->id)
                ->where('seen_at', '>=', $seenAt->copy()->subMinute())
                ->exists();
            if ($duplicate) {
                continue;
            }

            PresenceLog::create([
                'student_id' => $student->id,
                'beacon_id' => $beacon->id,
                'rssi' => $sighting['rssi'] ?? null,
                'seen_at' => $seenAt,
                'source' => $source,
            ]);
            $recorded++;
        }

        return response()->json([
            'success' => true,
            'recorded' => $recorded,
            'pending' => $this->pendingFor($student),
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->pendingFor($request->user())]);
    }

    public function confirm(Request $request, int $sessionId): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['required', 'string', 'max:36'],
            'major' => ['required', 'integer', 'min:0', 'max:65535'],
            'minor' => ['required', 'integer', 'min:0', 'max:65535'],
            'rssi' => ['nullable', 'integer', 'min:-127', 'max:0'],
            // Second layer: a selfie taken at confirm time. Optional in the
            // request so an older app build still works; required by the
            // session when the teacher switched the face layer on.
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $student = $request->user();
        $session = AttendanceSession::with('beacon')->find($sessionId);
        if (!$session) {
            return response()->json(['message' => 'Davomat sessiyasi topilmadi.'], 404);
        }

        $session->closeIfExpired();
        if (!$session->isOpen()) {
            return response()->json(['message' => 'Davomat oynasi yopilgan.'], 422);
        }
        if (!$session->groups()->where('group_hemis_id', $student->group_id)->exists()) {
            return response()->json(['message' => "Bu dars sizning guruhingiz uchun emas."], 403);
        }

        $beacon = $session->beacon;
        if (!$beacon) {
            return response()->json(['message' => 'Bu xona uchun beacon sozlanmagan.'], 422);
        }
        if (!$beacon->matches($data['uuid'], (int) $data['major'], (int) $data['minor'])) {
            return response()->json(['message' => "Siz dars xonasida emassiz — xona beacon signali topilmadi."], 422);
        }

        $minRssi = (int) config('services.attendance.min_rssi', -95);
        if (isset($data['rssi']) && $data['rssi'] < $minRssi) {
            return response()->json(['message' => "Signal juda kuchsiz. Xona ichiga kiring va qayta urinib ko'ring."], 422);
        }

        $confirmation = AttendanceConfirmation::firstOrCreate(
            ['session_id' => $session->id, 'student_id' => $student->id],
            ['student_hemis_id' => $student->hemis_id]
        );

        if ($confirmation->status === AttendanceConfirmation::STATUS_PRESENT) {
            return $this->confirmed($confirmation, 'Davomat allaqachon tasdiqlangan.');
        }
        if ($confirmation->decided_by === 'teacher') {
            return response()->json(['message' => "Davomatingiz o'qituvchi tomonidan belgilangan."], 422);
        }

        // ── Identity layer ───────────────────────────────────
        // The beacon already proves the phone is in the room; the face only
        // has to prove whose phone it is. So a clear "not them" (or an
        // unusable photo) sends the student back to retry, while "could not
        // check" lets attendance through, flagged for the teacher.
        $face = ['result' => null, 'similarity' => null];
        if ($request->hasFile('photo')) {
            $face = $this->faceVerifier->check($student, $request->file('photo'), 'attendance');

            if (in_array($face['result'], [StudentFaceVerifier::MISMATCH, StudentFaceVerifier::UNREADABLE], true)) {
                // Still pending, but the teacher sees the failed attempt.
                if ($face['result'] === StudentFaceVerifier::MISMATCH) {
                    $confirmation->update([
                        'face_verified' => false,
                        'face_similarity' => $face['similarity'],
                        'face_note' => $face['result'],
                        'face_checked_at' => now(),
                    ]);
                }

                return response()->json([
                    'message' => $face['result'] === StudentFaceVerifier::MISMATCH
                        ? "Yuz mos kelmadi. Yorug' joyda, yuzingizni to'liq ko'rsatib qayta urinib ko'ring."
                        : "Suratni o'qib bo'lmadi. Qayta urinib ko'ring.",
                    'face_result' => $face['result'],
                    'face_similarity' => $face['similarity'],
                ], 422);
            }
        } elseif ($session->require_face) {
            return response()->json([
                'message' => "Bu dars uchun yuz tasdig'i talab qilinadi — ilovani yangilang.",
                'face_result' => 'photo_required',
            ], 422);
        }

        $confirmation->update([
            'status' => AttendanceConfirmation::STATUS_PRESENT,
            'decided_by' => 'student',
            'beacon_seen' => true,
            'rssi' => $data['rssi'] ?? null,
            'confirmed_at' => now(),
            // true = matched; null = not checked (an earlier mismatch is cleared).
            'face_verified' => $face['result'] === StudentFaceVerifier::OK ? true : null,
            'face_similarity' => $face['similarity'],
            'face_note' => $face['result'] === StudentFaceVerifier::OK ? null : $face['result'],
            'face_checked_at' => $face['result'] === null ? null : now(),
        ]);

        PresenceLog::create([
            'student_id' => $student->id,
            'beacon_id' => $beacon->id,
            'rssi' => $data['rssi'] ?? null,
            'seen_at' => now(),
            'source' => 'confirm',
        ]);

        return $this->confirmed($confirmation, $this->confirmMessage($face['result']));
    }

    /** What to tell the student, given how far the identity check got. */
    private function confirmMessage(?string $faceResult): string
    {
        return match ($faceResult) {
            StudentFaceVerifier::OK => 'Davomat tasdiqlandi — yuz tasdiqlandi.',
            StudentFaceVerifier::NO_REFERENCE =>
                "Davomat tasdiqlandi. Tizimda tasdiqlangan suratingiz yo'q — yuz tekshirilmadi.",
            StudentFaceVerifier::UNAVAILABLE =>
                'Davomat tasdiqlandi. Yuzni tekshirish xizmati vaqtincha ishlamadi.',
            default => 'Davomat tasdiqlandi.',
        };
    }

    public function history(Request $request): JsonResponse
    {
        $rows = AttendanceConfirmation::with('session')
            ->where('student_id', $request->user()->id)
            ->latest('id')
            ->limit(60)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (AttendanceConfirmation $c) => [
                'session_id' => $c->session_id,
                'subject_name' => $c->session?->subject_name,
                'lesson_date' => $c->session?->lesson_date?->format('Y-m-d'),
                'lesson_pair_name' => $c->session?->lesson_pair_name,
                'auditorium_name' => $c->session?->auditorium_name,
                'status' => $c->status,
                'decided_by' => $c->decided_by,
                'confirmed_at' => $c->confirmed_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    private function confirmed(AttendanceConfirmation $confirmation, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'status' => $confirmation->status,
                'confirmed_at' => $confirmation->confirmed_at?->toIso8601String(),
            ],
        ]);
    }

    /** Open sessions for the student's group, with the student's own status. */
    private function pendingFor(Student $student): array
    {
        $sessions = AttendanceSession::open()
            ->whereHas('groups', fn ($q) => $q->where('group_hemis_id', $student->group_id))
            ->with('beacon')
            ->orderBy('closes_at')
            ->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        $mine = AttendanceConfirmation::whereIn('session_id', $sessions->pluck('id'))
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('session_id');

        return $sessions->map(fn (AttendanceSession $s) => [
            'session_id' => $s->id,
            'subject_name' => $s->subject_name,
            'lesson_pair_name' => $s->lesson_pair_name,
            'auditorium_name' => $s->auditorium_name,
            'closes_at' => $s->closes_at->toIso8601String(),
            'seconds_left' => max(0, (int) now()->diffInSeconds($s->closes_at, false)),
            'beacon' => $s->beacon?->toApi(),
            'require_face' => (bool) $s->require_face,
            'my_status' => $mine[$s->id]->status ?? AttendanceConfirmation::STATUS_PENDING,
        ])->values()->all();
    }
}
