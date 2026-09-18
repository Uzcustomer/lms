<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * 1:1 face check for the mobile app: a photo taken on the phone is matched
 * against the student's approved photo through the ArcFace service.
 *
 * Uses exactly what the web Face ID login uses — the same on/off switches,
 * the same reference photo (FaceIdService::referenceImageFor) and the same
 * threshold (faceid_arcface_threshold) — and writes to the same
 * face_id_logs. It only reports the outcome; each caller decides which
 * outcomes let the student through.
 */
class StudentFaceVerifier
{
    public const OK = 'ok';                    // matched
    public const MISMATCH = 'mismatch';        // checked, not the same person
    public const NO_REFERENCE = 'no_photo';    // student has no approved photo
    public const UNAVAILABLE = 'no_service';   // compare service down / errored
    public const DISABLED = 'disabled';        // face id switched off
    public const UNREADABLE = 'bad_photo';     // photo could not be processed

    /**
     * @param  string  $attemptType  face_id_logs.attempt_type, e.g. 'mobile_login', 'attendance'
     * @return array{result: string, similarity: ?float, threshold: float}
     */
    public function check(Student $student, UploadedFile $photo, string $attemptType): array
    {
        $threshold = FaceIdService::getArcFaceThreshold();

        if (!FaceIdService::isEnabledForStudent($student) || !FaceIdService::isArcFaceEnabled()) {
            $this->log($student, $attemptType, 'disabled', null, null, "Face ID o'chirilgan");

            return $this->result(self::DISABLED, $threshold);
        }

        $reference = FaceIdService::referenceImageFor($student);
        if ($reference === null) {
            $this->log($student, $attemptType, 'failed', null, null, "student_photos da tasdiqlangan rasm yo'q");

            return $this->result(self::NO_REFERENCE, $threshold);
        }

        // Sent inline, so the photo never has to be written to public/.
        $liveImage = PhotoQualityGate::fileToDataUri($photo->getRealPath());
        if ($liveImage === null) {
            return $this->result(self::UNREADABLE, $threshold);
        }

        $comparison = FaceIdService::compareViaArcFace($liveImage, $reference);
        if ($comparison === null) {
            $this->log($student, $attemptType, 'failed', null, null, 'ArcFace service javob bermadi');

            return $this->result(self::UNAVAILABLE, $threshold);
        }

        $similarity = (float) $comparison['similarity_percent'];
        $matched = $similarity >= $threshold;

        $this->log(
            $student,
            $attemptType,
            $matched ? 'success' : 'failed',
            $similarity,
            (float) ($comparison['distance'] ?? 0),
            $matched ? null : "ArcFace: yuz mos kelmadi ({$similarity}% < {$threshold}%)",
            $liveImage,
        );

        return $this->result($matched ? self::OK : self::MISMATCH, $threshold, $similarity);
    }

    private function result(string $result, float $threshold, ?float $similarity = null): array
    {
        return ['result' => $result, 'similarity' => $similarity, 'threshold' => $threshold];
    }

    private function log(
        Student $student,
        string $attemptType,
        string $result,
        ?float $similarity,
        ?float $distance,
        ?string $reason,
        ?string $snapshot = null,
    ): void {
        try {
            FaceIdService::logAttempt([
                'student_id' => $student->id,
                'target_student_id' => $student->id,
                'student_id_number' => $student->student_id_number,
                'target_student_id_number' => $student->student_id_number,
                'attempt_type' => $attemptType,
                'result' => $result,
                'confidence' => $similarity === null ? null : round($similarity / 100, 4),
                'distance' => $distance === null ? null : round($distance, 4),
                'failure_reason' => $reason,
                'snapshot' => $snapshot,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[FaceID/mobile] could not write the audit log: ' . $e->getMessage());
        }
    }
}
