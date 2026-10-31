<?php

namespace App\Services;

use App\Models\VedomostAiKey;
use App\Models\VedomostAiRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Vedomost AI tekshiruvi uchun Gemini API mijozi.
 *
 * - Kalitlar vedomost_ai_keys dan olinadi: avval pullik, keyin (ruxsat bo'lsa) bepul.
 * - Har kalitning daqiqalik (RPM) va kunlik limiti, hamda umumiy kunlik limit hisobga olinadi.
 * - Vaqtinchalik xato (429/5xx/timeout) bo'lsa — keyingi kalit, so'ng zaxira model.
 * - Har bir HTTP so'rov vedomost_ai_requests ga yoziladi (statistika uchun).
 */
class VedomostGeminiClient
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    /** Kalitni shu chaqiruv davomida boshqa ishlatmaslik kerak bo'lgan xato turlari. */
    private const KEY_DEAD_ERRORS = ['auth', 'quota'];

    /** Qayta urinish ma'nosiz bo'lgan (so'rovning o'zi xato) turlari. */
    private const FATAL_ERRORS = ['bad_request', 'blocked'];

    public function __construct(private VedomostAiSettings $settings)
    {
    }

    /**
     * Kamida bitta ishlatsa bo'ladigan kalit bormi.
     */
    public static function isConfigured(): bool
    {
        try {
            return app(self::class)->usableKeys()->isNotEmpty();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Ishlatish mumkin bo'lgan kalitlar (yoqilgan; bepullar faqat ruxsat bo'lsa), pullik birinchi.
     */
    public function usableKeys(): Collection
    {
        return VedomostAiKey::where('enabled', true)
            ->when(!$this->settings->get('allow_free_keys'), fn ($q) => $q->where('is_paid', true))
            ->orderByDesc('is_paid')
            ->orderBy('id')
            ->get();
    }

    // ───────── kalit ijarasi: bitta kalit — bitta ishchi ─────────
    // Bir necha ishchi parallel ishlaganda har biri o'z kalitini «ijaraga» oladi — bitta kalitga
    // bir vaqtda faqat bitta tekshiruv so'rov yuboradi (daqiqalik limit oshib ketmasin).

    private const LEASE_PREFIX = 'vedomost-ai-lease:';

    private const LEASE_TTL = 1000; // job timeout'idan (900 s) uzoqroq — ishchi halok bo'lsa ham o'zi bo'shaydi

    /** Shu jarayon (ishchi) ijaraga olgan kalit. */
    private static ?int $leasedKeyId = null;

    /**
     * Bo'sh (boshqa ishchi ijaraga olmagan, kunlik limiti tugamagan) kalitni ijaraga oladi.
     * Hammasi band bo'lsa null — job keyinroq qayta navbatga qo'yiladi.
     */
    public function leaseKey(?int $submissionId, array $except = []): ?VedomostAiKey
    {
        foreach ($this->usableKeys() as $key) {
            if (in_array($key->id, $except, true) || $this->dailyExhausted($key)) {
                continue;
            }
            if (Cache::add(self::LEASE_PREFIX . $key->id, ['submission' => $submissionId, 'at' => now()->toDateTimeString(), 'pid' => getmypid()], self::LEASE_TTL)) {
                self::$leasedKeyId = $key->id;

                return $key;
            }
        }

        return null;
    }

    public function releaseLease(): void
    {
        if (self::$leasedKeyId !== null) {
            Cache::forget(self::LEASE_PREFIX . self::$leasedKeyId);
            self::$leasedKeyId = null;
        }
    }

    public static function leasedKeyId(): ?int
    {
        return self::$leasedKeyId;
    }

    /** Monitoring: kalit id => ijara (qaysi vedomost, qachondan). */
    public function leases(): array
    {
        $out = [];
        foreach ($this->usableKeys() as $key) {
            if ($l = Cache::get(self::LEASE_PREFIX . $key->id)) {
                $out[$key->id] = $l;
            }
        }

        return $out;
    }

    /**
     * Strukturali JSON javob oladi.
     *
     * @param  array  $parts  Gemini "parts" (text / inline_data)
     * @param  array  $schema  responseSchema (OpenAPI subset)
     * @return array{data: array, model: string, key_id: int}
     */
    public function generateJson(
        array $parts,
        array $schema,
        ?string $systemPrompt = null,
        ?int $submissionId = null,
        string $purpose = 'transcribe'
    ): array {
        $models = array_values(array_unique(array_filter([
            $this->settings->get('model'),
            $this->settings->get('fallback_model'),
        ])));
        if (empty($models)) {
            throw new RuntimeException('AI modeli tanlanmagan.');
        }

        $keys = $this->usableKeys();
        if ($keys->isEmpty()) {
            throw new RuntimeException("Ishlatsa bo'ladigan Gemini API kaliti yo'q (AI sozlamalari).");
        }
        // Ijara rejimi (navbat ishchisi): faqat o'z kaliti; u ishlamay qolsa — boshqa bo'sh kalitga o'tadi.
        if (self::$leasedKeyId !== null) {
            $keys = $keys->where('id', self::$leasedKeyId)->values();
        }

        $body = $this->buildBody($parts, $schema, $systemPrompt);
        $maxRetries = max(0, (int) $this->settings->get('max_retries'));
        $dead = [];
        $lastError = null;

        foreach ($models as $model) {
            for ($round = 0; $round <= $maxRetries; $round++) {
                $this->assertGlobalLimit();

                $available = $keys->reject(fn ($k) => isset($dead[$k->id]) || $this->dailyExhausted($k))->values();
                if ($available->isEmpty() && self::$leasedKeyId !== null) {
                    $old = self::$leasedKeyId;
                    $this->releaseLease();
                    $next = $this->leaseKey($submissionId, array_merge(array_keys($dead), [$old]));
                    if ($next) {
                        $keys = collect([$next]);
                        $available = $keys->reject(fn ($k) => isset($dead[$k->id]))->values();
                    }
                }
                if ($available->isEmpty()) {
                    throw new VedomostAiLimitException(
                        'Barcha kalitlarning limiti tugadi yoki ular ishlamayapti.'
                        . ($lastError ? ' Oxirgi xato: ' . $lastError : '')
                    );
                }

                $tried = false;
                foreach ($available as $key) {
                    if (!$this->takeRpmSlot($key)) {
                        continue;
                    }
                    $tried = true;

                    $res = $this->call($key, $model, $body, $submissionId, $purpose);
                    if ($res['ok']) {
                        return ['data' => $res['data'], 'model' => $model, 'key_id' => $key->id];
                    }

                    $lastError = $res['message'];
                    if (in_array($res['type'], self::FATAL_ERRORS, true)) {
                        throw new RuntimeException($res['message']);
                    }
                    if (in_array($res['type'], self::KEY_DEAD_ERRORS, true)) {
                        $dead[$key->id] = true;
                    }
                    if ($res['type'] === 'model_not_found') {
                        continue 3; // keyingi model
                    }
                }

                if (!$tried) {
                    // Hamma kalitning daqiqalik limiti band — keyingi daqiqani kutamiz.
                    sleep(max(1, 61 - (int) now()->format('s')));
                    $round--;
                    continue;
                }

                if ($round < $maxRetries) {
                    sleep(min(30, 2 ** ($round + 1)));
                }
            }
        }

        throw new RuntimeException('Gemini javob bermadi: ' . ($lastError ?? "noma'lum xato"));
    }

    /**
     * generateContent ni qo'llab-quvvatlaydigan Gemini modellari ro'yxati.
     *
     * @return array<int, array{name: string, label: string}>
     */
    public function listModels(VedomostAiKey $key): array
    {
        $resp = Http::withHeaders(['x-goog-api-key' => $key->api_key])
            ->timeout(20)
            ->get(self::BASE_URL . '/models', ['pageSize' => 1000]);

        if (!$resp->successful()) {
            throw new RuntimeException('Modellar ro\'yxatini olib bo\'lmadi: HTTP ' . $resp->status());
        }

        return collect($resp->json('models') ?? [])
            ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
            ->map(fn ($m) => [
                'name' => preg_replace('#^models/#', '', $m['name'] ?? ''),
                'label' => $m['displayName'] ?? ($m['name'] ?? ''),
            ])
            ->filter(fn ($m) => str_starts_with($m['name'], 'gemini'))
            ->sortBy('name')
            ->values()
            ->all();
    }

    private function buildBody(array $parts, array $schema, ?string $systemPrompt): array
    {
        $gen = [
            'responseMimeType' => 'application/json',
            'responseSchema' => $schema,
            'temperature' => 0,
            'maxOutputTokens' => 32768,
        ];
        $budget = $this->settings->get('thinking_budget');
        if ($budget !== null) {
            $gen['thinkingConfig'] = ['thinkingBudget' => (int) $budget];
        }

        $body = [
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => $gen,
        ];
        if ($systemPrompt) {
            $body['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
        }

        return $body;
    }

    /**
     * Bitta HTTP so'rov. Natija jurnalga yoziladi.
     *
     * @return array{ok: bool, data?: array, type?: string, message?: string}
     */
    private function call(VedomostAiKey $key, string $model, array $body, ?int $submissionId, string $purpose): array
    {
        $started = microtime(true);
        $log = [
            'vedomost_submission_id' => $submissionId,
            'vedomost_ai_key_id' => $key->id,
            'model' => $model,
            'purpose' => $purpose,
        ];

        try {
            $resp = Http::withHeaders(['x-goog-api-key' => $key->api_key])
                ->timeout(max(10, (int) $this->settings->get('timeout')))
                ->post(self::BASE_URL . '/models/' . rawurlencode($model) . ':generateContent', $body);
        } catch (ConnectionException $e) {
            return $this->fail($key, $log, $started, null, 'timeout', 'Ulanish/timeout: ' . $e->getMessage());
        }

        $usage = $resp->json('usageMetadata') ?? [];
        $log['input_tokens'] = $usage['promptTokenCount'] ?? null;
        $log['output_tokens'] = isset($usage['candidatesTokenCount']) || isset($usage['thoughtsTokenCount'])
            ? (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0)
            : null;

        if (!$resp->successful()) {
            [$type, $msg] = $this->classifyHttpError($resp->status(), (string) $resp->body());

            return $this->fail($key, $log, $started, $resp->status(), $type, $msg);
        }

        if ($resp->json('promptFeedback.blockReason')) {
            return $this->fail($key, $log, $started, $resp->status(), 'blocked',
                'Gemini so\'rovni blokladi: ' . $resp->json('promptFeedback.blockReason'));
        }

        $candidate = $resp->json('candidates.0') ?? [];
        $finish = $candidate['finishReason'] ?? null;
        $text = collect($candidate['content']['parts'] ?? [])
            ->reject(fn ($p) => !empty($p['thought']))
            ->pluck('text')
            ->implode('');

        if ($finish === 'MAX_TOKENS') {
            return $this->fail($key, $log, $started, $resp->status(), 'truncated',
                'Gemini javobi token chegarasida kesildi — natija to\'liq emas.');
        }
        if (in_array($finish, ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII'], true)) {
            return $this->fail($key, $log, $started, $resp->status(), 'blocked', "Gemini javobni to'xtatdi: {$finish}");
        }

        $data = json_decode(trim($text), true);
        if (!is_array($data)) {
            return $this->fail($key, $log, $started, $resp->status(), 'invalid_json',
                'Gemini javobini JSON sifatida o\'qib bo\'lmadi.');
        }

        VedomostAiRequest::create($log + [
            'status' => 'ok',
            'http_status' => $resp->status(),
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
        $key->forceFill(['last_used_at' => now()])->saveQuietly();

        return ['ok' => true, 'data' => $data];
    }

    private function fail(VedomostAiKey $key, array $log, float $started, ?int $http, string $type, string $msg): array
    {
        $msg = mb_substr($msg, 0, 1000);
        VedomostAiRequest::create($log + [
            'status' => 'error',
            'http_status' => $http,
            'error_type' => $type,
            'error_message' => $msg,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
        $key->forceFill([
            'last_used_at' => now(),
            'last_error' => $msg,
            'last_error_at' => now(),
        ])->saveQuietly();

        return ['ok' => false, 'type' => $type, 'message' => $msg];
    }

    /**
     * @return array{0: string, 1: string} [turi, xabar]
     */
    private function classifyHttpError(int $status, string $body): array
    {
        $msg = (string) (json_decode($body, true)['error']['message'] ?? mb_substr($body, 0, 300));
        $lower = mb_strtolower($msg);

        return match (true) {
            $status === 429 && (str_contains($lower, 'per day') || str_contains($lower, 'quota exceeded')
                || str_contains($lower, 'billing')) => ['quota', "Kalit kvotasi tugagan (429): {$msg}"],
            $status === 429 => ['rate_limit', "So'rovlar ko'p (429): {$msg}"],
            $status === 401, $status === 403 => ['auth', "Kalit yaroqsiz yoki ruxsat yo'q ({$status}): {$msg}"],
            $status === 404 => ['model_not_found', "Model topilmadi (404): {$msg}"],
            $status === 400 && str_contains($lower, 'api key') => ['auth', "Kalit yaroqsiz (400): {$msg}"],
            $status === 400 => ['bad_request', "Noto'g'ri so'rov (400): {$msg}"],
            $status === 503 => ['overloaded', "Gemini band (503): {$msg}"],
            default => ['server', "Gemini xatosi ({$status}): {$msg}"],
        };
    }

    private function assertGlobalLimit(): void
    {
        $limit = $this->settings->get('daily_limit');
        if ($limit && VedomostAiRequest::where('created_at', '>=', now()->startOfDay())->count() >= $limit) {
            throw new VedomostAiLimitException("Kunlik umumiy so'rov limiti ({$limit}) tugadi.");
        }
    }

    private function dailyExhausted(VedomostAiKey $key): bool
    {
        if (!$key->daily_limit) {
            return false;
        }

        return VedomostAiRequest::where('vedomost_ai_key_id', $key->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count() >= $key->daily_limit;
    }

    /**
     * Kalitning shu daqiqadagi RPM ulushini band qiladi. Limit to'lgan bo'lsa false.
     */
    private function takeRpmSlot(VedomostAiKey $key): bool
    {
        if (!$key->rpm) {
            return true;
        }

        $bucket = 'vedomost_ai_rpm:' . $key->id . ':' . now()->format('YmdHi');
        Cache::add($bucket, 0, 120);
        if ((int) Cache::get($bucket, 0) >= $key->rpm) {
            return false;
        }
        Cache::increment($bucket);

        return true;
    }
}
