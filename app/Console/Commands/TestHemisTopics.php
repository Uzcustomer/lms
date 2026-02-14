<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestHemisTopics extends Command
{
    protected $signature = 'hemis:test-topics {--endpoint= : Custom endpoint to test} {--curriculum= : Curriculum ID} {--semester= : Semester ID}';
    protected $description = 'Test HEMIS API topic endpoints';

    public function handle()
    {
        $baseUrl = rtrim(config('services.hemis.base_url', 'https://student.ttatf.uz/rest'), '/');
        if (!str_contains($baseUrl, '/v1')) {
            $baseUrl .= '/v1';
        }
        $token = config('services.hemis.token');

        $this->info("Base URL: {$baseUrl}");
        $this->info("Token: " . (empty($token) ? 'EMPTY!' : substr($token, 0, 10) . '...'));
        $this->line('');

        // If custom endpoint provided, test only that
        if ($endpoint = $this->option('endpoint')) {
            $this->testEndpoint($baseUrl, $token, $endpoint);
            return;
        }

        // Try various possible endpoint names
        $endpoints = [
            'data/curriculum-subject-topic-list',
            'data/topic-list',
            'data/curriculum-topic-list',
            'data/subject-topic-list',
            'data/syllabus-list',
            'data/curriculum-subject-list',
        ];

        $params = ['limit' => 5, 'page' => 1];

        if ($this->option('curriculum')) {
            $params['_curriculum'] = $this->option('curriculum');
        }
        if ($this->option('semester')) {
            $params['_semester'] = $this->option('semester');
        }

        $this->info("Params: " . json_encode($params));
        $this->line('');

        foreach ($endpoints as $endpoint) {
            $this->testEndpoint($baseUrl, $token, $endpoint, $params);
        }
    }

    private function testEndpoint($baseUrl, $token, $endpoint, $params = ['limit' => 5, 'page' => 1])
    {
        $url = $baseUrl . '/' . ltrim($endpoint, '/');
        $this->line("Testing: {$url}");

        try {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(15)
                ->get($url, $params);

            $status = $response->status();
            $body = $response->body();

            if ($response->successful()) {
                $data = $response->json();
                $this->info("  ✓ Status: {$status}");

                if (isset($data['data']['items'])) {
                    $count = count($data['data']['items']);
                    $total = $data['data']['pagination']['totalCount'] ?? '?';
                    $this->info("  Items: {$count} (total: {$total})");

                    if ($count > 0) {
                        $this->info("  First item keys: " . implode(', ', array_keys($data['data']['items'][0])));
                        $this->line("  First item: " . json_encode($data['data']['items'][0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    }
                } else {
                    $this->info("  Response: " . substr(json_encode($data, JSON_UNESCAPED_UNICODE), 0, 500));
                }
            } else {
                $this->error("  ✗ Status: {$status}");
                $this->error("  Body: " . substr($body, 0, 300));
            }
        } catch (\Exception $e) {
            $this->error("  ✗ Exception: " . $e->getMessage());
        }

        $this->line('');
    }
}
