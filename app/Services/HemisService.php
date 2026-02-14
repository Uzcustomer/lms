<?php

namespace App\Services;

use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Group;
use App\Models\MarkingSystemScore;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HemisService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.hemis.base_url');
        $this->token = config('services.hemis.token');
    }

    public function importStudents(): int
    {
        $page = 1;
        $hasMore = true;
        $totalImported = 0;
        $importedHemisIds = [];

        while ($hasMore) {
            $response = $this->fetchStudents($page);

            if ($response['success']) {
                foreach ($response['data']['items'] as $studentData) {
                    $this->updateOrCreateStudent($studentData);
                    $importedHemisIds[] = $studentData['id'];
                    $totalImported++;
                }

                $pagination = $response['data']['pagination'];
                $hasMore = $pagination['page'] < $pagination['pageCount'];
                $page++;
            } else {
                Log::error('Failed to fetch students from HEMIS', $response);
                break;
            }
        }

        // HEMIS da yo'q talabalarni "Chetlashgan" deb belgilash
        if (!empty($importedHemisIds)) {
            $deactivatedCount = Student::whereNotIn('hemis_id', $importedHemisIds)
                ->where('student_status_code', '!=', '60')
                ->update([
                    'student_status_code' => '60',
                    'student_status_name' => 'Chetlashgan (HEMIS da yo\'q)',
                ]);

            Log::info("Deactivated {$deactivatedCount} students not found in HEMIS");
        }

        return $totalImported;
    }

    protected function fetchStudents($page)
    {
        $response = Http::withoutVerifying()->withToken($this->token)
            ->get($this->baseUrl . '/v1/data/student-list', [
                'page' => $page,
                'limit' => 200,
                // "_group"=>650
            ]);

        return $response->json();
    }

    protected function updateOrCreateStudent($data)
    {
        $studentData = $this->transformStudentData($data);

        Student::updateOrCreate(
            ['hemis_id' => $studentData['hemis_id']],
            $studentData
        );
    }

    protected function transformStudentData($data)
    {
        return [
            'hemis_id' => $data['id'],
            'full_name' => $data['full_name'] ?? null,
            'short_name' => $data['short_name'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'second_name' => $data['second_name'] ?? null,
            'third_name' => $data['third_name'] ?? null,
            'image' => $data['image'] ?? null,
            'student_id_number' => $data['student_id_number'] ?? null,
            'birth_date' => isset($data['birth_date']) ? date('Y-m-d', $data['birth_date']) : null,
            'avg_gpa' => $data['avg_gpa'] ?? null,
            'avg_grade' => $data['avg_grade'] ?? null,
            'total_credit' => $data['total_credit'] ?? null,
            'university_code' => $data['university']['code'] ?? null,
            'university_name' => $data['university']['name'] ?? null,
            'gender_code' => $data['gender']['code'] ?? null,
            'gender_name' => $data['gender']['name'] ?? null,
            'department_id' => $data['department']['id'] ?? null,
            'department_name' => $data['department']['name'] ?? null,
            'department_code' => $data['department']['code'] ?? null,
            'specialty_id' => $data['specialty']['id'] ?? null,
            'specialty_name' => $data['specialty']['name'] ?? null,
            'specialty_code' => $data['specialty']['code'] ?? null,
            'group_id' => $data['group']['id'] ?? null,
            'group_name' => $data['group']['name'] ?? null,
            'education_year_code' => $data['educationYear']['code'] ?? null,
            'education_year_name' => $data['educationYear']['name'] ?? null,
            'country_code' => $data['country']['code'] ?? null,
            'country_name' => $data['country']['name'] ?? null,
            'province_code' => $data['province']['code'] ?? null,
            'province_name' => $data['province']['name'] ?? null,
            'district_code' => $data['district']['code'] ?? null,
            'district_name' => $data['district']['name'] ?? null,
            'terrain_code' => $data['terrain']['code'] ?? null,
            'terrain_name' => $data['terrain']['name'] ?? null,
            'citizenship_code' => $data['citizenship']['code'] ?? null,
            'citizenship_name' => $data['citizenship']['name'] ?? null,
            'semester_id' => $data['semester']['id'] ?? null,
            'semester_code' => $data['semester']['code'] ?? null,
            'semester_name' => $data['semester']['name'] ?? null,
            'level_code' => $data['level']['code'] ?? null,
            'level_name' => $data['level']['name'] ?? null,
            'education_form_code' => $data['educationForm']['code'] ?? null,
            'education_form_name' => $data['educationForm']['name'] ?? null,
            'education_type_code' => $data['educationType']['code'] ?? null,
            'education_type_name' => $data['educationType']['name'] ?? null,
            'payment_form_code' => $data['paymentForm']['code'] ?? null,
            'payment_form_name' => $data['paymentForm']['name'] ?? null,
            'student_type_code' => $data['studentType']['code'] ?? null,
            'student_type_name' => $data['studentType']['name'] ?? null,
            'social_category_code' => $data['socialCategory']['code'] ?? null,
            'social_category_name' => $data['socialCategory']['name'] ?? null,
            'accommodation_code' => $data['accommodation']['code'] ?? null,
            'accommodation_name' => $data['accommodation']['name'] ?? null,
            'student_status_code' => $data['studentStatus']['code'] ?? null,
            'student_status_name' => $data['studentStatus']['name'] ?? null,
            'curriculum_id' => $data['_curriculum'] ?? null,
            'hemis_created_at' => isset($data['created_at']) ? date('Y-m-d H:i:s', $data['created_at']) : null,
            'hemis_updated_at' => isset($data['updated_at']) ? date('Y-m-d H:i:s', $data['updated_at']) : null,
            'hash' => $data['hash'] ?? null,

            'language_code' => $data['group']['educationLang']['code'] ?? null,
            'language_name' => $data['group']['educationLang']['name'] ?? null,
            'year_of_enter' => $data['year_of_enter'] ?? null,
            'roommate_count' => $data['roommate_count'] ?? null,
            'total_acload' => $data['total_acload'] ?? null,
            'is_graduate' => $data['is_graduate'] ?? false,
            'other' => $data['other'] ?? null,
        ];
    }

    public function importSemesters()
    {
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->fetchSemesters($page);

            if ($response['success']) {
                foreach ($response['data']['items'] as $semesterData) {
                    $this->updateOrCreateSemester($semesterData);
                }

                $pagination = $response['data']['pagination'];
                $hasMore = $pagination['page'] < $pagination['pageCount'];
                $page++;
            } else {
                Log::error('Failed to fetch semesters from HEMIS', $response);
                break;
            }
        }
    }

    protected function fetchSemesters($page)
    {
        try {
            $response = Http::withoutVerifying()->withToken($this->token)
                ->get($this->baseUrl . 'data/semester-list', [
                    'page' => $page,
                    'limit' => 200,
                ]);

            if (!$response->successful()) {
                Log::error('HEMIS API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'error' => 'API request failed'];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Exception occurred while fetching semesters', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'error' => 'Exception occurred'];
        }
    }

    protected function updateOrCreateSemester($data)
    {
        Semester::updateOrCreate(
            ['hemis_id' => $data['id']],
            [
                'code' => $data['code'],
                'curriculum_id' => $data['_curriculum'],
                'education_year' => $data['_education_year'],
                'name' => $data['name'],
                'level_code' => $data['level']['code'],
                'level_name' => $data['level']['name'],
                'current' => $data['current'],
            ]
        );
    }

    public function importDepartments()
    {
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->fetchDepartments($page);

            if ($response['success']) {
                foreach ($response['data']['items'] as $departmentData) {
                    $this->updateOrCreateDepartment($departmentData);
                }

                $pagination = $response['data']['pagination'];
                $hasMore = $pagination['page'] < $pagination['pageCount'];
                $page++;
            } else {
                Log::error('Failed to fetch departments from HEMIS', $response);
                break;
            }
        }
    }

    protected function fetchDepartments($page)
    {
        try {
            $response = Http::withoutVerifying()->withToken($this->token)
                ->get($this->baseUrl . 'data/department-list', [
                    'page' => $page,
                    'limit' => 200,
                ]);

            if (!$response->successful()) {
                Log::error('HEMIS API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'error' => 'API request failed'];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Exception occurred while fetching departments', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'error' => 'Exception occurred'];
        }
    }

    protected function updateOrCreateDepartment($data)
    {
        Department::updateOrCreate(
            ['hemis_id' => $data['id']],
            [
                'name' => $data['name'],
                'code' => $data['code'],
                'structure_type_code' => $data['structureType']['code'],
                'structure_type_name' => $data['structureType']['name'],
                'locality_type_code' => $data['localityType']['code'],
                'locality_type_name' => $data['localityType']['name'],
                'parent_id' => $data['parent'] ? Department::where('hemis_id', $data['parent'])->value('id') : null,
                'active' => $data['active'],
            ]
        );
    }

    public function importGroups()
    {
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->fetchGroups($page);

            if ($response['success']) {
                foreach ($response['data']['items'] as $groupData) {
                    $this->updateOrCreateGroup($groupData);
                }

                $pagination = $response['data']['pagination'];
                $hasMore = $pagination['page'] < $pagination['pageCount'];
                $page++;
            } else {
                Log::error('Failed to fetch groups from HEMIS', $response);
                break;
            }
        }
    }

    protected function fetchGroups($page)
    {
        try {
            $response = Http::withoutVerifying()->withToken($this->token)
                ->get($this->baseUrl . 'data/group-list', [
                    'page' => $page,
                    'limit' => 200,
                ]);

            if (!$response->successful()) {
                Log::error('HEMIS API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'error' => 'API request failed'];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Exception occurred while fetching groups', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'error' => 'Exception occurred'];
        }
    }

    protected function updateOrCreateGroup($data)
    {
        $department = Department::where('hemis_id', $data['department']['id'])->first();
        $specialty = Specialty::where('hemis_id', $data['specialty']['id'])->first();
        $curriculum = Curriculum::where('hemis_id', $data['_curriculum'])->first();

        if ($department && $specialty && $curriculum) {
            Group::updateOrCreate(
                ['hemis_id' => $data['id']],
                [
                    'name' => $data['name'],
                    'department_id' => $department->id,
                    'specialty_id' => $specialty->id,
                    'education_lang_code' => $data['educationLang']['code'],
                    'education_lang_name' => $data['educationLang']['name'],
                    'curriculum_id' => $curriculum->id,
                ]
            );
        } else {
            Log::warning('Missing related data for group', $data);
        }
    }

    public function importCurricula()
    {
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->fetchCurricula($page);

            if ($response['success']) {
                foreach ($response['data']['items'] as $curriculumData) {
                    $this->updateOrCreateCurriculum($curriculumData);
                }

                $pagination = $response['data']['pagination'];
                $hasMore = $pagination['page'] < $pagination['pageCount'];
                $page++;
            } else {
                Log::error('Failed to fetch curricula from HEMIS', $response);
                break;
            }
        }
    }

    protected function fetchCurricula($page)
    {
        try {
            $response = Http::withoutVerifying()->withToken($this->token)
                ->get($this->baseUrl . 'data/curriculum-list', [
                    'page' => $page,
                    'limit' => 200,
                ]);

            if (!$response->successful()) {
                Log::error('HEMIS API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'error' => 'API request failed'];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Exception occurred while fetching curricula', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'error' => 'Exception occurred'];
        }
    }

    protected function updateOrCreateCurriculum($data)
    {
        $specialty = Specialty::where('hemis_id', $data['specialty']['id'])->first();
        $department = Department::where('hemis_id', $data['department']['id'])->first();

        if ($specialty && $department) {
            Curriculum::updateOrCreate(
                ['hemis_id' => $data['id']],
                [
                    'name' => $data['name'],
                    'specialty_id' => $specialty->id,
                    'department_id' => $department->id,
                    'education_year_code' => $data['educationYear']['code'],
                    'education_year_name' => $data['educationYear']['name'],
                    'education_year_current' => $data['educationYear']['current'],
                    'education_type_code' => $data['educationType']['code'],
                    'education_type_name' => $data['educationType']['name'],
                    'education_form_code' => $data['educationForm']['code'],
                    'education_form_name' => $data['educationForm']['name'],
                    'marking_system_code' => $data['markingSystem']['code'],
                    'marking_system_name' => $data['markingSystem']['name'],
                    'marking_system_minimum_limit' => $data['markingSystem']['minimum_limit'],
                    'marking_system_gpa_limit' => $data['markingSystem']['gpa_limit'],
                    'semester_count' => $data['semester_count'],
                    'education_period' => $data['education_period'],
                ]
            );
        } else {
            Log::warning('Missing related data for curriculum', $data);
        }
    }

    public function importMarkingSystems(): int
    {
        $page = 1;
        $hasMore = true;
        $totalImported = 0;

        while ($hasMore) {
            $response = $this->fetchMarkingSystems($page);

            if ($response['success']) {
                foreach ($response['data']['items'] as $item) {
                    $this->updateOrCreateMarkingSystem($item);
                    $totalImported++;
                }

                $pagination = $response['data']['pagination'];
                $hasMore = $pagination['page'] < $pagination['pageCount'];
                $page++;
            } else {
                Log::error('Failed to fetch marking systems from HEMIS', $response);
                break;
            }
        }

        return $totalImported;
    }

    protected function fetchMarkingSystems($page)
    {
        try {
            $response = Http::withoutVerifying()->withToken($this->token)
                ->get($this->baseUrl . 'data/marking-system-list', [
                    'page' => $page,
                    'limit' => 200,
                ]);

            if (!$response->successful()) {
                Log::error('HEMIS marking-system-list request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['success' => false, 'error' => 'API request failed'];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Exception fetching marking systems', [
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Exception occurred'];
        }
    }

    protected function updateOrCreateMarkingSystem($data)
    {
        $minimumLimit = $data['minimum_limit'] ?? 60;

        MarkingSystemScore::updateOrCreate(
            ['marking_system_code' => $data['code']],
            [
                'marking_system_name' => $data['name'],
                'minimum_limit' => $minimumLimit,
                'gpa_limit' => $data['gpa_limit'] ?? 2.0,
            ]
        );
    }
}