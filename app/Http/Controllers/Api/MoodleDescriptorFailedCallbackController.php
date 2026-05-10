<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentPhoto;
use App\Services\StudentPhotoNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Moodle (auth_faceid + local_hemisexport) → LMS failure callback.
 *
 * When face-api.js cannot detect a face on a photo that LMS approved
 * and pushed to Moodle, Moodle calls this endpoint with the student's
 * idnumber (HEMIS id) and the reason. We:
 *
 *   1) flip the approved photo back to 'rejected' so the tutor is
 *      forced to upload a better-framed shot,
 *   2) record moodle_sync_status='moodle_face_api_failed' + the reason
 *      for the audit trail and admin filter,
 *   3) clear the cached ArcFace embedding so the Python identify cache
 *      is rebuilt from a real, usable photo,
 *   4) notify the tutor on Telegram with the failure reason.
 */
class MoodleDescriptorFailedCallbackController extends Controller
{
    public function fail(Request $request, StudentPhotoNotifier $notifier): JsonResponse
    {
        $secret = (string) config('services.moodle.pull_secret');
        $got = (string) $request->header('X-SYNC-SECRET', '');

        if ($secret === '' || !hash_equals($secret, $got)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'idnumber' => ['required_without:idnumbers', 'string'],
            'idnumbers' => ['required_without:idnumber', 'array'],
            'idnumbers.*' => ['string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid payload',
                'details' => $validator->errors(),
            ], 422);
        }

        $idnumbers = collect($request->input('idnumbers', []))
            ->push($request->input('idnumber'))
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values();

        if ($idnumbers->isEmpty()) {
            return response()->json(['ok' => true, 'rejected' => 0, 'matched' => 0]);
        }

        $reasonRaw = trim((string) $request->input('reason', ''));
        $reason = $reasonRaw !== '' ? $reasonRaw : 'Yuz topilmadi';
        $rejectionReason = 'Moodle face-api: ' . $reason . '. Iltimos, talabani yelkasidan yuqori qilib, oq xalatda va oq fonda qayta suratga oling.';

        $photos = StudentPhoto::query()
            ->whereIn('student_id_number', $idnumbers)
            ->where('status', StudentPhoto::STATUS_APPROVED)
            ->get();

        $now = now();
        $rejected = 0;
        $notified = 0;

        foreach ($photos as $photo) {
            $photo->status = StudentPhoto::STATUS_REJECTED;
            $photo->reviewed_by_name = 'Moodle (auto)';
            $photo->reviewed_at = $now;
            $photo->rejection_reason = $rejectionReason;
            $photo->moodle_sync_status = 'moodle_face_api_failed';
            $photo->moodle_sync_error = substr('face_api: ' . $reason, 0, 1000);
            // Force a re-send if a future replacement photo lands on the same row.
            $photo->moodle_file_hash = null;
            $photo->descriptor_confirmed_at = null;
            $photo->descriptor_confirmed_notified_at = null;
            $photo->face_embedding = null;
            $photo->embedding_extracted_at = null;
            $photo->save();
            $rejected++;

            try {
                $notifier->notifyRejected($photo, $rejectionReason);
                $notified++;
            } catch (\Throwable $e) {
                Log::warning('moodle.descriptor_failed: notify failed', [
                    'photo_id' => $photo->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('moodle.descriptor_failed: callback handled', [
            'requested' => $idnumbers->count(),
            'matched_approved' => $photos->count(),
            'rejected' => $rejected,
            'tutors_notified' => $notified,
            'reason' => $reason,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'ok' => true,
            'requested' => $idnumbers->count(),
            'matched' => $photos->count(),
            'rejected' => $rejected,
            'tutors_notified' => $notified,
        ]);
    }
}
