<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MoodleImportController extends Controller
{
    public function import(Request $request): JsonResponse
    {
        $secret = (string) env('MOODLE_SYNC_SECRET', '');
        $got = (string) $request->header('X-SYNC-SECRET', '');

        if ($secret === '' || !hash_equals($secret, $got)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $mode = (string) $request->input('mode', '');
        $records = $request->input('records', []);

        if (!is_array($records)) $records = [];

        return response()->json([
            'ok' => true,
            'mode' => $mode,
            'received' => count($records),
        ]);
    }
}
