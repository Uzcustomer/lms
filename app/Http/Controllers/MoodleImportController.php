<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MoodleImportController extends Controller
{
    public function import(Request $request): JsonResponse
    {
        $secret = (string) env('MOODLE_SYNC_SECRET', '');
        $got = (string) $request->header('X-SYNC-SECRET', '');

        if ($secret === '' || !hash_equals($secret, $got)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $mode = (string) $request->input('mode', 'smart');
        $records = $request->input('records', []);
        if (!is_array($records)) $records = [];

        $now = now();

        if ($mode === 'ids_grade') {
            // records: [{attempt_id, grade, old_grade, grade_rounded}]
            $updated = 0;

            foreach ($records as $r) {
                $aid = (int)($r['attempt_id'] ?? 0);
                if ($aid <= 0) continue;

                DB::table('hemis_quiz_results')
                    ->where('attempt_id', $aid)
                    ->update([
                        'is_active' => 1,
                        'old_grade' => $r['old_grade'] ?? null,
                        // WP table grade DECIMAL(10,2). If you send rounded int, it still fits.
                        'grade'     => $r['grade_rounded'] ?? ($r['grade'] ?? null),
                        'synced_at' => $now,
                        'updated_at'=> $now,
                    ]);

                $updated++;
            }

            return response()->json(['ok' => true, 'mode' => $mode, 'updated' => $updated]);
        }

        if ($mode === 'full_new') {
            // records: full row objects from exporter
            // We'll UPSERT by attempt_id
            $rows = [];
            foreach ($records as $r) {
                $aid = (int)($r['attempt_id'] ?? 0);
                if ($aid <= 0) continue;

                $rows[] = [
                    'attempt_id'      => $aid,
                    'date_start'      => $r['date_start'] ?? null,
                    'date_finish'     => $r['date_finish'] ?? null,
                    'category_path'   => $r['category_path'] ?? null,
                    'category_id'     => $r['category_id'] ?? null,
                    'category_name'   => $r['category_name'] ?? null,
                    'faculty'         => $r['faculty'] ?? null,
                    'direction'       => $r['direction'] ?? null,
                    'semester'        => $r['semester'] ?? null,
                    'student_id'      => $r['student_id'] ?? null,
                    'student_name'    => $r['student_name'] ?? null,
                    'fan_id'          => $r['fan_id'] ?? null,
                    'fan_name'        => $r['fan_name'] ?? null,
                    'quiz_type'       => $r['quiz_type'] ?? null,
                    'attempt_name'    => $r['attempt_name'] ?? null,
                    'shakl'           => $r['shakl'] ?? null,
                    'attempt_number'  => $r['attempt_number'] ?? 1,
                    'grade'           => $r['grade'] ?? null,
                    'old_grade'       => $r['old_grade'] ?? null,
                    'course_id'       => $r['course_id'] ?? null,
                    'course_idnumber' => $r['course_idnumber'] ?? null,
                    'is_valid_format' => $r['is_valid_format'] ?? 0,
                    'is_active'       => 1,
                    'synced_at'       => $now,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }

            if ($rows) {
                DB::table('hemis_quiz_results')->upsert(
                    $rows,
                    ['attempt_id'],
                    [
                        'date_start','date_finish','category_path','category_id','category_name',
                        'faculty','direction','semester','student_id','student_name','fan_id','fan_name',
                        'quiz_type','attempt_name','shakl','attempt_number','grade','old_grade',
                        'course_id','course_idnumber','is_valid_format','is_active','synced_at','updated_at'
                    ]
                );
            }

            return response()->json(['ok' => true, 'mode' => $mode, 'upserted' => count($rows)]);
        }

        return response()->json(['ok' => false, 'error' => 'Unknown mode'], 422);
    }
}
