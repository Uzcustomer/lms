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

        $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 200;
        $totalImported = 0;
        $totalUpdated = 0;

        do {
            $response = Http::withoutVerifying()
                ->timeout(30)
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

            foreach ($items as $item) {
                $d = $item['_data'] ?? [];

                $result = ContractList::updateOrCreate(
                    ['hemis_id' => $item['id']],
                    [
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
                    ]
                );

                if ($result->wasRecentlyCreated) {
                    $totalImported++;
                } else {
                    $totalUpdated++;
                }
            }

            $page++;
        } while ($page <= $totalPages);

        $msg = "Kontraktlar importi tugadi. Yangi: {$totalImported}, Yangilangan: {$totalUpdated}";
        $telegram->notify($msg);
        $this->info($msg);
    }
}
