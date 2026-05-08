<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendStudentPhotoToMoodle;
use App\Models\StudentPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MoodlePhotoSyncController extends Controller
{
    public function syncPhotos(Request $request): JsonResponse
    {
        $secret = (string) config('services.moodle.pull_secret');
        $got = (string) $request->header('X-SYNC-SECRET', '');

        if ($secret === '' || !hash_equals($secret, $got)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'idnumbers' => ['required', 'array', 'min:1'],
            'idnumbers.*' => ['string'],
            'requested_at' => ['nullable', 'string'],
            'requested_by' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid payload',
                'details' => $validator->errors(),
            ], 422);
        }

        $idnumbers = array_values(array_unique(array_filter(array_map(
            fn($v) => trim((string) $v),
            $request->input('idnumbers', [])
        ), fn($v) => $v !== '')));

        $requested = count($idnumbers);

        $photoIds = StudentPhoto::query()
            ->whereIn('student_id_number', $idnumbers)
            ->where('status', StudentPhoto::STATUS_APPROVED)
            ->pluck('id');

        foreach ($photoIds as $id) {
            SendStudentPhotoToMoodle::dispatch((int) $id);
        }

        Log::info('moodle.pull_photos: queued', [
            'requested' => $requested,
            'matched_approved' => $photoIds->count(),
            'queued' => $photoIds->count(),
            'requested_at' => $request->input('requested_at'),
            'requested_by' => $request->input('requested_by'),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'requested' => $requested,
            'matched_approved' => $photoIds->count(),
            'queued' => $photoIds->count(),
        ]);
    }
}
