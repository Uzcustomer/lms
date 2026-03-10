<?php

namespace App\Console\Commands;

use App\Models\ContractList;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportContracts extends Command
{
    protected $signature = 'import:contracts';

    protected $description = 'Import contracts from HEMIS API (contract-list)';

    public function handle(TelegramService $telegram)
    {
        $telegram->notify("Kontraktlar importi boshlandi");
        $this->info('Fetching contracts from HEMIS API...');

        // 1-QADAM: HEMIS ning bank bilan sinxronizatsiyasini ishga tushirish
        $this->triggerHemisBankSync($telegram);

        $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 200;
        $totalProcessed = 0;

        do {
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->withToken($token)
                ->get("https://student.ttatf.uz/rest/v1/data/contract-list", [
                    'limit' => $pageSize,
                    'page' => $page,
                ]);

            if (!$response->successful()) {
                $telegram->notify("Kontraktlar importida xatolik (HTTP " . $response->status() . ")");
                $this->error('API request failed: HTTP ' . $response->status());
                break;
            }

            $json = $response->json();
            $data = $json['data'] ?? [];
            $items = $data['items'] ?? [];
            $totalPages = $data['pagination']['pageCount'] ?? 1;

            $this->info("Page {$page}/{$totalPages} — " . count($items) . " ta kontrakt");

            $rows = [];
            foreach ($items as $item) {
                $d = $item['_data'] ?? [];
                $now = now();

                $rows[] = [
                    'hemis_id' => $item['id'],
                    'key' => $item['key'] ?? null,
                    'education_year' => $item['_education_year'] ?? null,
                    'student_hemis_id' => $item['_student'] ?? null,
                    'year' => $d['year'] ?? null,
                    'status' => $d['status'] ?? null,
                    'status_id' => $d['statusId'] ?? null,
                    'edu_form' => $d['eduForm'] ?? null,
                    'edu_form_id' => $d['eduFormId'] ?? null,
                    'edu_year' => $d['eduYear'] ?? null,
                    'full_name' => $d['fullName'] ?? null,
                    'edu_course' => $d['eduCourse'] ?? null,
                    'edu_cours_id' => $d['eduCoursId'] ?? null,
                    'edu_type_code' => $d['eduTypeCode'] ?? null,
                    'edu_type_name' => $d['eduTypeName'] ?? null,
                    'faculty_code' => $d['facultyCode'] ?? null,
                    'faculty_name' => $d['facultyName'] ?? null,
                    'contract_number' => $d['contractNumber'] ?? null,
                    'edu_contract_sum' => $d['eduContractSum'] ?? null,
                    'edu_organization' => $d['eduOrganization'] ?? null,
                    'edu_organization_code' => $d['eduOrganizationCode'] ?? null,
                    'paid_credit_amount' => $d['paidCreditAmount'] ?? null,
                    'edu_speciality_code' => $d['eduSpecialityCode'] ?? null,
                    'edu_speciality_name' => $d['eduSpecialityName'] ?? null,
                    'end_rest_debet_amount' => $d['endRestDebetAmount'] ?? null,
                    'unpaid_credit_amount' => $d['unPaidCreditAmount'] ?? null,
                    'vozvrat_debet_amount' => $d['vozvratDebetAmount'] ?? null,
                    'contract_debet_amount' => $d['contractDebetAmount'] ?? null,
                    'edu_contract_type_code' => $d['eduContractTypeCode'] ?? null,
                    'edu_contract_type_name' => $d['eduContractTypeName'] ?? null,
                    'end_rest_credit_amount' => $d['endRestCreditAmount'] ?? null,
                    'begin_rest_debet_amount' => $d['beginRestDebetAmount'] ?? null,
                    'begin_rest_credit_amount' => $d['beginRestCreditAmount'] ?? null,
                    'edu_contract_sum_type_code' => $d['eduContractSumTypeCode'] ?? null,
                    'edu_contract_sum_type_name' => $d['eduContractSumTypeName'] ?? null,
                    'hemis_created_at' => $item['created_at'] ?? null,
                    'hemis_updated_at' => $item['updated_at'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    ContractList::upsert($chunk, ['hemis_id'], [
                        'key', 'education_year', 'student_hemis_id',
                        'year', 'status', 'status_id', 'edu_form', 'edu_form_id',
                        'edu_year', 'full_name', 'edu_course', 'edu_cours_id',
                        'edu_type_code', 'edu_type_name', 'faculty_code', 'faculty_name',
                        'contract_number', 'edu_contract_sum', 'edu_organization', 'edu_organization_code',
                        'paid_credit_amount', 'edu_speciality_code', 'edu_speciality_name',
                        'end_rest_debet_amount', 'unpaid_credit_amount', 'vozvrat_debet_amount',
                        'contract_debet_amount', 'edu_contract_type_code', 'edu_contract_type_name',
                        'end_rest_credit_amount', 'begin_rest_debet_amount', 'begin_rest_credit_amount',
                        'edu_contract_sum_type_code', 'edu_contract_sum_type_name',
                        'hemis_created_at', 'hemis_updated_at', 'updated_at',
                    ]);
                }
                $totalProcessed += count($rows);
            }

            $page++;
        } while ($page <= $totalPages);

        $msg = "Kontraktlar importi tugadi. Jami: {$totalProcessed} ta";
        $telegram->notify($msg);
        $this->info($msg);
    }

    protected function triggerHemisBankSync(TelegramService $telegram): void
    {
        $webUrl   = rtrim(config('services.hemis.web_url', 'https://hemis.ttatf.uz'), '/');
        $login    = config('services.hemis.web_login');
        $password = config('services.hemis.web_password');

        if (!$login || !$password) {
            $this->warn('HEMIS_WEB_LOGIN yoki HEMIS_WEB_PASSWORD sozlanmagan — bank sinxi o\'tkazib yuborildi');
            return;
        }

        $this->info('HEMIS ga login qilinmoqda...');

        try {
            // 1. Login sahifasidan CSRF token va cookie olish
            $loginPage = Http::withoutVerifying()
                ->timeout(15)
                ->withOptions(['allow_redirects' => true])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; LMS-Sync/1.0)'])
                ->get("{$webUrl}/site/login");

            if (!$loginPage->successful()) {
                $this->warn('HEMIS login sahifasi ochilmadi (HTTP ' . $loginPage->status() . ')');
                return;
            }

            // Response cookie larini olish
            $sessionCookie = $this->parseCookiesFromResponse($loginPage);

            // CSRF tokenni HTML dan olish
            preg_match('/name="_csrf-backend"\s+value="([^"]+)"/i', $loginPage->body(), $csrfMatch);
            if (empty($csrfMatch[1])) {
                preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/i', $loginPage->body(), $csrfMatch);
            }
            $csrfToken = $csrfMatch[1] ?? '';

            // 2. Login POST
            $loginResp = Http::withoutVerifying()
                ->timeout(15)
                ->withOptions(['allow_redirects' => ['max' => 5, 'track_redirects' => true]])
                ->withHeaders([
                    'Referer'    => "{$webUrl}/site/login",
                    'Origin'     => $webUrl,
                    'Cookie'     => $sessionCookie,
                    'User-Agent' => 'Mozilla/5.0 (compatible; LMS-Sync/1.0)',
                ])
                ->asForm()
                ->post("{$webUrl}/site/login", [
                    'LoginForm[username]' => $login,
                    'LoginForm[password]' => $password,
                    '_csrf-backend'       => $csrfToken,
                ]);

            // Login muvaffaqiyatini tekshirish
            $redirectHistory = $loginResp->transferStats?->getHandlerStats()['redirect_count'] ?? null;
            $responseBody    = $loginResp->body();

            if (str_contains($responseBody, 'LoginForm') && !str_contains($responseBody, 'logout')) {
                $this->warn('HEMIS login muvaffaqiyatsiz (noto\'g\'ri login/parol)');
                return;
            }

            // Login dan qaytgan yangi cookie larni olish
            $sessionCookie = $this->parseCookiesFromResponse($loginResp) ?: $sessionCookie;

            $this->info('HEMIS ga login muvaffaqiyatli. Bank sinxi boshlandi...');

            // 3. Bank sync trigger
            $syncResp = Http::withoutVerifying()
                ->timeout(30)
                ->withOptions(['allow_redirects' => true])
                ->withHeaders([
                    'Referer'    => "{$webUrl}/student-data/contract",
                    'Cookie'     => $sessionCookie,
                    'User-Agent' => 'Mozilla/5.0 (compatible; LMS-Sync/1.0)',
                ])
                ->get("{$webUrl}/student-data/contract", ['sync' => 1]);

            if ($syncResp->successful()) {
                $this->info('HEMIS bank sinxi muvaffaqiyatli. 5 soniya kutilmoqda...');
                sleep(5);
            } else {
                $this->warn('HEMIS bank sinxi HTTP ' . $syncResp->status() . ' qaytardi — import davom ettiriladi');
            }
        } catch (\Exception $e) {
            $this->warn('HEMIS bank sinxi xatosi: ' . $e->getMessage() . ' — import davom ettiriladi');
        }
    }

    protected function parseCookiesFromResponse($response): string
    {
        $cookies = [];
        $headers = $response->headers();
        $setCookies = (array) ($headers['Set-Cookie'] ?? $headers['set-cookie'] ?? []);

        if (is_string($setCookies)) {
            $setCookies = [$setCookies];
        }

        foreach ($setCookies as $line) {
            // "name=value; Path=/; ..." dan faqat name=value qismini olish
            $parts = explode(';', $line);
            if (!empty($parts[0])) {
                $cookies[] = trim($parts[0]);
            }
        }

        return implode('; ', $cookies);
    }
}
