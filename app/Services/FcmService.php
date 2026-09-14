<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging (HTTP v1) without the SDK: the service-account
 * JSON signs a JWT, Google swaps it for a short-lived access token (cached
 * ~50 min), and messages are POSTed one token at a time.
 *
 * Credentials path: config('services.firebase.credentials') —
 * storage/app/firebase.json by default (gitignored).
 */
class FcmService
{
    public const RESULT_OK = 'ok';
    public const RESULT_INVALID_TOKEN = 'invalid';
    public const RESULT_ERROR = 'error';

    private const TOKEN_CACHE_KEY = 'fcm_access_token';

    private ?array $credentials = null;
    private bool $credentialsLoaded = false;

    public function enabled(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * @param  array{title?: string, body?: string}  $notification  empty → silent data message
     * @param  array<string, scalar|null>  $data
     */
    public function send(string $token, array $notification, array $data = []): string
    {
        $credentials = $this->credentials();
        if ($credentials === null) {
            return self::RESULT_ERROR;
        }

        $message = [
            'token' => $token,
            'data' => array_map(fn ($v) => (string) $v, $data),
            'android' => [
                'priority' => 'high',
            ],
            'apns' => [
                'headers' => ['apns-priority' => $notification ? '10' : '5'],
                'payload' => ['aps' => ['content-available' => 1]],
            ],
        ];
        if ($notification !== []) {
            $message['notification'] = $notification;
            $message['android']['notification'] = ['channel_id' => 'attendance', 'sound' => 'default'];
            $message['apns']['payload']['aps']['sound'] = 'default';
        }

        try {
            $response = Http::withToken($this->accessToken())
                ->timeout(15)
                ->post(
                    "https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send",
                    ['message' => $message]
                );
        } catch (\Throwable $e) {
            Log::warning('FCM send exception: ' . $e->getMessage());
            return self::RESULT_ERROR;
        }

        if ($response->successful()) {
            return self::RESULT_OK;
        }

        $errorStatus = (string) $response->json('error.status');
        $details = json_encode($response->json('error.details') ?? []);
        $dead = $response->status() === 404
            || $errorStatus === 'NOT_FOUND'
            || str_contains($details, 'UNREGISTERED')
            || ($response->status() === 400 && str_contains($details, 'INVALID_ARGUMENT'));
        if ($dead) {
            return self::RESULT_INVALID_TOKEN;
        }

        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
        }
        Log::warning('FCM send failed', [
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 300),
        ]);

        return self::RESULT_ERROR;
    }

    private function credentials(): ?array
    {
        if ($this->credentialsLoaded) {
            return $this->credentials;
        }
        $this->credentialsLoaded = true;

        $path = (string) config('services.firebase.credentials');
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);
        if (!is_array($json) || empty($json['client_email']) || empty($json['private_key']) || empty($json['project_id'])) {
            Log::warning('FCM credentials file is not a valid service account JSON', ['path' => $path]);
            return null;
        }

        return $this->credentials = $json;
    }

    private function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function () {
            $c = $this->credentials();
            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64Url(json_encode([
                'iss' => $c['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $signature = '';
            if (!openssl_sign("{$header}.{$claims}", $signature, $c['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('FCM: could not sign JWT with the service account key');
            }
            $jwt = "{$header}.{$claims}." . $this->base64Url($signature);

            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
            $token = $response->json('access_token');
            if (!$response->successful() || !is_string($token) || $token === '') {
                throw new \RuntimeException('FCM: token exchange failed: ' . mb_substr($response->body(), 0, 300));
            }

            return $token;
        });
    }

    private function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
