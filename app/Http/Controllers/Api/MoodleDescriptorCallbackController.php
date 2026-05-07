<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentPhoto;
use App\Services\StudentPhotoNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MoodleDescriptorCallbackController extends Controller
{
    public function confirm(Request $request, StudentPhotoNotifier $notifier): JsonResponse
    {
        $secret = (string) config('services.moodle.pull_secret');
        $got = (string) $request->header('X-SYNC-SECRET', '');

        if ($secret === '' || !hash_equals($secret, $got)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'idnumbers' => ['required', 'array', 'min:1'],
            'idnumbers.*' => ['string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid payload',
                'details' => $validator->errors(),
            ], 422);
        }

        $idnumbers = collect($request->input('idnumbers', []))
            ->map(fn($v) => trim((string) $v))
            ->filter(fn($v) => $v !== '')
            ->unique()
            ->values();

        $photos = StudentPhoto::query()
            ->whereIn('student_id_number', $idnumbers)
            ->where('status', StudentPhoto::STATUS_APPROVED)
            ->get();

        $now = now();
        $confirmed = 0;
        $notified = 0;

        foreach ($photos as $photo) {
            $isFirstConfirm = $photo->descriptor_confirmed_at === null;

            $photo->descriptor_confirmed_at = $photo->descriptor_confirmed_at ?: $now;

            if ($isFirstConfirm && $photo->descriptor_confirmed_notified_at === null) {
                $notifier->notifyApproved($photo);
                $photo->descriptor_confirmed_notified_at = $now;
                $notified++;
            }

            $photo->save();

            if ($isFirstConfirm) {
                $confirmed++;
            }
        }

        Log::info('moodle.descriptor_confirmed: callback handled', [
            'requested' => $idnumbers->count(),
            'matched_approved' => $photos->count(),
            'newly_confirmed' => $confirmed,
            'tutors_notified' => $notified,
        ]);

        return response()->json([
            'ok' => true,
            'requested' => $idnumbers->count(),
            'matched_approved' => $photos->count(),
            'newly_confirmed' => $confirmed,
            'tutors_notified' => $notified,
        ]);
    }
}
