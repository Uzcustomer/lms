<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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
 *
 * Vaqt chegarasi ($timeBudget) berilsa, tortish shu vaqtdan keyin to'xtaydi
 * va to'xtagan joy saqlanadi — keyingi chaqiruv o'sha joydan davom etadi.
 * Aks holda har bosishda yana overlap boshidan boshlanib, oxiriga hech qachon
 * yetib bo'lmasdi.
 */
class MoodleQuizPullService
{
    private const RESUME_KEY = 'moodle_quiz_pull_resume';

    public function pull(?int $overlap = null, int $maxPages = 200, ?float $timeBudget = null): array
    {
        $url = (string) config('services.moodle.ws_url');
        $token = (string) (config('services.moodle.quiz_ws_token') ?: config('services.moodle.ws_token'));

        if ($url === '' || $token === '') {
            return ['ok' => false, 'error' => 'Moodle WS sozlanmagan (MOODLE_WS_URL / MOODLE_QUIZ_WS_TOKEN)', 'imported' => 0];
        }

        $overlap = $overlap ?? (int) config('services.moodle.quiz_pull_overlap', 5000);
        $limit = 200;
        $timeout = max(30, (int) config('services.moodle.ws_timeout', 60));
        $started = microtime(true);

        // Oldingi tortish yarim qolgan bo'lsa — o'sha "since" va sahifadan davom
        // etiladi. Sahifalar bir xil "since" bilan so'ralgani uchun tartib
        // saqlanadi; ikki marta kelgan yozuv upsert tufayli zarar qilmaydi.
        $resume = Cache::get(self::RESUME_KEY);
        if (is_array($resume) && isset($resume['since'], $resume['page'])) {
            $since = (int) $resume['since'];
            $page = max(1, (int) $resume['page']);
        } else {
            $maxId = (int) DB::table('hemis_quiz_results')->max('attempt_id');
            $since = max(0, $maxId - $overlap);
            $page = 1;
        }

        $imported = 0;
        $pages = 0;
        $lastPage = $page + $maxPages - 1;

        while ($page <= $lastPage) {
            // Vaqt chegarasi: kamida bitta sahifa olingandan keyin tekshiriladi.
            if ($timeBudget !== null && $pages > 0 && (microtime(true) - $started) >= $timeBudget) {
                $this->saveResume($since, $page);

                return $this->result(true, $imported, $pages, $since, $started, true);
            }

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
                // Olingan sahifalar saqlangan — keyingi urinish shu sahifadan davom etadi.
                $this->saveResume($since, $page);

                return $this->result(false, $imported, $pages, $since, $started) + ['error' => $this->readableError($e)];
            }

            if (!$resp->successful()) {
                $this->saveResume($since, $page);

                return $this->result(false, $imported, $pages, $since, $started) + ['error' => 'Moodle WS HTTP ' . $resp->status()];
            }

            $body = $resp->json();
            if (isset($body['exception'])) {
                $this->saveResume($since, $page);

                return $this->result(false, $imported, $pages, $since, $started)
                    + ['error' => (string) ($body['message'] ?? $body['exception'])];
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

        // Sahifa chegarasiga yetib, davomi qolgan bo'lsa — keyingi safar davom etadi.
        if ($page > $lastPage) {
            $this->saveResume($since, $page);

            return $this->result(true, $imported, $pages, $since, $started, true);
        }

        // Oxirigacha yetildi — keyingi tortish yana overlap oynasidan boshlanadi.
        Cache::forget(self::RESUME_KEY);

        return $this->result(true, $imported, $pages, $since, $started);
    }

    private function result(bool $ok, int $imported, int $pages, int $since, float $started, bool $partial = false): array
    {
        return [
            'ok' => $ok,
            'partial' => $partial,
            'imported' => $imported,
            'pages' => $pages,
            'since_attempt_id' => $since,
            'seconds' => round(microtime(true) - $started, 1),
        ];
    }

    /** To'xtagan joy 2 soat saqlanadi; eskirsa tortish boshidan boshlanadi. */
    private function saveResume(int $since, int $page): void
    {
        Cache::put(self::RESUME_KEY, ['since' => $since, 'page' => $page], now()->addHours(2));
    }

    /** Moodle ulanish xatosini operator tushunadigan matnga aylantiradi. */
    private function readableError(\Throwable $e): string
    {
        $message = $e->getMessage();
        if (stripos($message, 'timed out') !== false || stripos($message, 'cURL error 28') !== false) {
            return 'Moodle belgilangan vaqtda javob bermadi';
        }
        if (stripos($message, 'cURL error 6') !== false || stripos($message, 'cURL error 7') !== false) {
            return "Moodle serveriga ulanib bo'lmadi";
        }

        return $message;
    }
}
