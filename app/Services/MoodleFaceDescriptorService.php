<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Calls the local_hemisexport Moodle web services that store and match
 * 128-d face descriptors directly (descriptor extracted in the registrator's
 * browser using Moodle's face-api.js + models, so the values are guaranteed
 * to be comparable to descriptors used at the test centre).
 */
class MoodleFaceDescriptorService
{
    public function __construct(
        private readonly ?string $wsUrl = null,
        private readonly ?string $wsToken = null,
    ) {}

    /**
     * Append a descriptor to auth_faceid_descriptors for the given HEMIS id.
     *
     * @param array<int, float> $descriptor 128 floats
     * @return array{ok:bool, response?:mixed, error?:string, http_status?:int}
     */
    public function saveDescriptor(string $idnumber, array $descriptor,
            ?float $detScore = null, string $source = 'registrator_webcam'): array
    {
        if (count($descriptor) !== 128) {
            return ['ok' => false, 'error' => 'descriptor must have 128 components'];
        }

        $payload = [
            'wstoken' => $this->wsToken ?: (string) config('services.moodle.ws_token'),
            'wsfunction' => 'local_hemisexport_save_face_descriptor',
            'moodlewsrestformat' => 'json',
            'idnumber' => $idnumber,
            'source' => $source,
        ];
        foreach (array_values($descriptor) as $i => $v) {
            $payload["descriptor[{$i}]"] = (float) $v;
        }
        if ($detScore !== null) {
            $payload['det_score'] = (float) $detScore;
        }

        return $this->call('save', $payload, $idnumber);
    }

    /**
     * Compare a live descriptor against the user's stored descriptors.
     * Returns the response from local_hemisexport_match_face_descriptor:
     *   status: matched|no_match|no_descriptors
     *   matched, distance, confidence, threshold, descriptor_id
     *
     * @param array<int, float> $descriptor 128 floats
     */
    public function matchDescriptor(string $idnumber, array $descriptor): array
    {
        if (count($descriptor) !== 128) {
            return ['ok' => false, 'error' => 'descriptor must have 128 components'];
        }

        $payload = [
            'wstoken' => $this->wsToken ?: (string) config('services.moodle.ws_token'),
            'wsfunction' => 'local_hemisexport_match_face_descriptor',
            'moodlewsrestformat' => 'json',
            'idnumber' => $idnumber,
        ];
        foreach (array_values($descriptor) as $i => $v) {
            $payload["descriptor[{$i}]"] = (float) $v;
        }

        return $this->call('match', $payload, $idnumber);
    }

    private function call(string $op, array $payload, string $idnumber): array
    {
        $url = $this->wsUrl ?: (string) config('services.moodle.ws_url');
        if ($url === '' || empty($payload['wstoken'])) {
            return ['ok' => false, 'error' => 'Moodle WS not configured'];
        }

        $timeout = max(10, (int) config('services.moodle.ws_timeout', 30));
        try {
            $resp = Http::asForm()->timeout($timeout)->post($url, $payload);
            $body = $resp->json();

            $hasError = is_array($body) && isset($body['exception']);
            $ok = $resp->successful() && !$hasError;

            return [
                'ok' => $ok,
                'http_status' => $resp->status(),
                'response' => $body ?? $resp->body(),
                'error' => $hasError ? (string) ($body['message'] ?? 'moodle exception') : null,
            ];
        } catch (\Throwable $e) {
            Log::warning("Moodle face descriptor {$op} failed", [
                'idnumber' => $idnumber,
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
