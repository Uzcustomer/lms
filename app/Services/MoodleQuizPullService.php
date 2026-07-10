<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Moodle `local_quizexport_get_results` webservice'idan quiz natijalarini
 * TO'G'RIDAN-TO'G'RI tortib `hemis_quiz_results` jadvaliga upsert qiladi.
 *
 * Bu Moodle serveridagi push-skriptiga (/opt/scripts/...) bog'liq emas —
 * "Yangilash" tugmasi shu orqali bugungi natijalarni darhol keltiradi.
 *
 * Kech tugagan urinishlarni (qa.id kichik bo'lsa ham) tutish uchun mavjud
 * eng katta attempt_id dan ORQAGA "overlap" oynasi bilan so'raladi.
 */
class MoodleQuizPullService
{
    public function pull(?int $overlap = null, int $maxPages = 200): array
    {
        $url = (string) config('services.moodle.ws_url');
        $token = (string) (config('services.moodle.quiz_ws_token') ?: config('services.moodle.ws_token'));

        if ($url === '' || $token === '') {
            return ['ok' => false, 'error' => 'Moodle WS sozlanmagan (MOODLE_WS_URL / MOODLE_QUIZ_WS_TOKEN)', 'imported' => 0];
        }

        $overlap = $overlap ?? (int) config('services.moodle.quiz_pull_overlap', 5000);
        $limit = 200;
        $timeout = max(30, (int) config('services.moodle.ws_timeout', 60));

        $maxId = (int) DB::table('hemis_quiz_results')->max('attempt_id');
        $since = max(0, $maxId - $overlap);

        $imported = 0;
        $pages = 0;
        $page = 1;

        while ($page <= $maxPages) {
            try {
                $resp = Http::asForm()->timeout($timeout)->post($url, [
                    'wstoken' => $token,
                    'wsfunction' => 'local_quizexport_get_results',
                    'moodlewsrestformat' => 'json',
                    'page' => $page,
                    'limit' => $limit,
                    'since_attempt_id' => $since,
                    'mode' => 'full',
                ]);
            } catch (\Throwable $e) {
                Log::warning('MoodleQuizPull: WS xatolik', ['page' => $page, 'error' => $e->getMessage()]);
                return ['ok' => false, 'error' => $e->getMessage(), 'imported' => $imported, 'pages' => $pages];
            }

            if (!$resp->successful()) {
                return ['ok' => false, 'error' => 'Moodle WS HTTP ' . $resp->status(), 'imported' => $imported, 'pages' => $pages];
            }

            $body = $resp->json();
            if (isset($body['exception'])) {
                return ['ok' => false, 'error' => (string) ($body['message'] ?? $body['exception']), 'imported' => $imported, 'pages' => $pages];
            }

            $records = $body['records'] ?? [];
            if (empty($records)) {
                break;
            }

            $now = now();
            $rows = [];
            foreach ($records as $r) {
                $aid = (int) ($r['attempt_id'] ?? 0);
                if ($aid <= 0) {
                    continue;
                }
                $old = (isset($r['grade']) && $r['grade'] !== '' && $r['grade'] !== null) ? (float) $r['grade'] : null;
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
                    'grade'           => $old === null ? null : (int) round($old),
                    'old_grade'       => $old,
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
                // MUHIM: fan_id/fan_name YANGILANMAYDI — mavjud qatorda operator
                // qo'lda almashtirgan fan (orig_fan_*) saqlanib qolsin. Yangi
                // qatorlarga esa insert paytida fan baribir yoziladi.
                DB::table('hemis_quiz_results')->upsert(
                    $rows,
                    ['attempt_id'],
                    [
                        'date_start', 'date_finish', 'category_path', 'category_id', 'category_name',
                        'faculty', 'direction', 'semester', 'student_id', 'student_name',
                        'quiz_type', 'attempt_name', 'shakl', 'attempt_number',
                        'grade', 'old_grade', 'course_id', 'course_idnumber', 'is_valid_format',
                        'is_active', 'synced_at', 'updated_at',
                    ]
                );
                $imported += count($rows);
            }

            $pages++;
            $hasNext = (bool) ($body['pagination']['has_next'] ?? false);
            if (!$hasNext) {
                break;
            }
            $page++;
        }

        return ['ok' => true, 'imported' => $imported, 'pages' => $pages, 'since_attempt_id' => $since];
    }
}
