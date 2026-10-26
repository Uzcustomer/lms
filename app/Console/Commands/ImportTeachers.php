<?php

namespace App\Console\Commands;

use App\Enums\ProjectRole;
use App\Models\Group;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherEmployment;
use App\Models\TutorHistory;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use App\Console\Commands\Concerns\ReportsImportChanges;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class ImportTeachers extends Command
{
    use ReportsImportChanges;

    protected $signature = 'import:teachers';

    protected $description = 'Imports employees from HEMIS API (/v1/data/employee-list)';

    public function handle(TelegramService $telegram)
    {
        $telegram->notify("Xodimlar importi boshlandi");
        $this->info('Fetching employee data from HEMIS API...');

        $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 40;
        $this->resetImportStats();

        // HEMIS har bir ish joyini (asosiy, o'rindoshlik, soatbay) alohida yozuv
        // qilib beradi. Avval hammasi yig'iladi va xodim bo'yicha guruhlanadi —
        // aks holda oxirgi kelgan yozuv (masalan "Bo'shagan" eski lavozim)
        // asosiy ish joyining ustiga yozilib qolardi.
        $byEmployee = [];
        do {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->get("https://student.ttatf.uz/rest/v1/data/employee-list?limit=$pageSize&type=all&page=$page");

            if (!$response->successful()) {
                // Qisman ro'yxat bilan davom etilsa, qolgan xodimlar "HEMIS da yo'q"
                // deb deaktivatsiya qilinardi — shuning uchun import to'xtatiladi
                $telegram->notify("Xodimlar importida xatolik yuz berdi (API, {$page}-sahifa) — import to'xtatildi, hech narsa o'zgartirilmadi");
                $this->error("Failed to fetch page {$page} from the API. Nothing was changed.");
                return self::FAILURE;
            }

            $data = $response->json()['data'];
            $totalPages = $data['pagination']['pageCount'];
            $this->info("Fetched page $page of $totalPages...");

            foreach ($data['items'] as $employeeData) {
                $byEmployee[(string) $employeeData['employee_id_number']][] = $employeeData;
            }

            $page++;
        } while ($page <= $totalPages);

        $hasEmployments = Schema::hasTable('teacher_employments');
        $importedEmployeeIds = [];

        foreach ($byEmployee as $employeeIdNumber => $records) {
            $employeeData = $this->primaryRecord($records);

            // hemis_id jadval va baholarda (employee_id) ishlatiladi — asosiy yozuv
            // almashganda ham o'zgarmasin: mavjud qiymat yozuvlar orasida bo'lsa saqlanadi
            $existingHemisId = Teacher::where('employee_id_number', $employeeData['employee_id_number'])->value('hemis_id');
            $hemisId = $existingHemisId !== null && in_array((string) $existingHemisId, array_map(fn ($r) => (string) $r['id'], $records), true)
                ? $existingHemisId
                : $employeeData['id'];

            $teacher = Teacher::updateOrCreate(
                ['employee_id_number' => $employeeData['employee_id_number']],
                [
                    'hemis_id' => $hemisId,
                    'meta_id' => $employeeData['meta_id'],
                    'full_name' => $employeeData['full_name'],
                    'short_name' => $employeeData['short_name'],
                    'first_name' => $employeeData['first_name'],
                    'second_name' => $employeeData['second_name'],
                    'third_name' => $employeeData['third_name'] ?? null,
                    'birth_date' => date('Y-m-d', $employeeData['birth_date']),
                    'image' => $employeeData['image'] ?? null,
                    'year_of_enter' => $employeeData['year_of_enter'],
                    'specialty' => $employeeData['specialty'] ?? null,
                    'gender' => $employeeData['gender']['name'] ?? null,
                    'department' => $employeeData['department']['name'] ?? null,
                    'department_hemis_id' => $employeeData['department']['id'] ?? null,
                    'employment_form' => $employeeData['employmentForm']['name'] ?? null,
                    'employment_staff' => $employeeData['employmentStaff']['name'] ?? null,
                    'staff_position' => $employeeData['staffPosition']['name'] ?? null,
                    'employee_status' => $employeeData['employeeStatus']['name'] ?? null,
                    'employee_type' => $employeeData['employeeType']['name'] ?? null,
                    'contract_number' => $employeeData['contract_number'],
                    'decree_number' => $employeeData['decree_number'] ?? 0,
                    'contract_date' => date('Y-m-d', $employeeData['contract_date']),
                    'decree_date' => date('Y-m-d', $employeeData['decree_date']),
                    'is_active' => true,
                ]
            );

            // Sanash shu yerda: pastdagi login/parol save() lari
            // wasChanged() natijasini o'zgartirib yuboradi.
            $this->trackImport($teacher, $teacher->full_name);

            $importedEmployeeIds[] = $employeeData['employee_id_number'];

            if ($hasEmployments) {
                $this->syncEmployments($teacher, $records, (int) $employeeData['meta_id']);
            }

            if (!$teacher->login) {
                $teacher->login = $employeeData['employee_id_number'];
                $teacher->save();
            }
            if (!$teacher->password) {
                $teacher->password = '12345678';
                $teacher->save();
            }

            if (!$teacher->hasRole(ProjectRole::TEACHER->value)) {
                $teacher->assignRole(ProjectRole::TEACHER->value);
            }

            // Tyutor guruhlarini sync qilish va tarixni yozish (barcha ish joylaridan)
            $oldGroupIds = $teacher->groups()->pluck('groups.id')->toArray();
            $newGroupIds = [];
            $newGroups = [];

            foreach ($records as $record) {
                foreach ($record['tutorGroups'] ?? [] as $tutorGroup) {
                    $group = Group::where('group_hemis_id', $tutorGroup['id'])->first();
                    if ($group && !isset($newGroups[$group->id])) {
                        $newGroupIds[] = $group->id;
                        $newGroups[$group->id] = $group;
                    }
                }
            }

            // Sync groups
            if (!empty($newGroupIds)) {
                $teacher->groups()->sync($newGroupIds);
            } else {
                $teacher->groups()->detach();
            }

            // Tutor tarixini yozish (faqat jadval mavjud bo'lsa)
            if (Schema::hasTable('tutor_history')) {
                // Olib tashlangan guruhlar — removed_at ni yozish
                $removedGroupIds = array_diff($oldGroupIds, $newGroupIds);
                if (!empty($removedGroupIds)) {
                    $removedGroups = Group::whereIn('id', $removedGroupIds)->get();
                    foreach ($removedGroups as $removedGroup) {
                        $studentIds = Student::where('group_id', $removedGroup->group_hemis_id)->pluck('id');
                        if ($studentIds->isNotEmpty()) {
                            TutorHistory::where('teacher_id', $teacher->id)
                                ->whereIn('student_id', $studentIds)
                                ->whereNull('removed_at')
                                ->update(['removed_at' => now()]);
                        }
                    }
                }

                // Yangi qo'shilgan guruhlar — tarix yozuvlarini yaratish
                $addedGroupIds = array_diff($newGroupIds, $oldGroupIds);
                foreach ($addedGroupIds as $addedGroupId) {
                    $group = $newGroups[$addedGroupId];
                    $students = Student::where('group_id', $group->group_hemis_id)->get();
                    foreach ($students as $student) {
                        TutorHistory::create([
                            'student_id' => $student->id,
                            'teacher_id' => $teacher->id,
                            'teacher_name' => $teacher->full_name,
                            'group_hemis_id' => $group->group_hemis_id,
                            'group_name' => $group->name,
                            'assigned_at' => now(),
                        ]);
                    }
                }
            }
        }

        // HEMIS'da bo'lmagan xodimlarni is_active=0 qilish (o'chirmasdan)
        // Telefon, telegram va boshqa lokal ma'lumotlari saqlanib qoladi
        if (!empty($importedEmployeeIds)) {
            $deactivatedCount = Teacher::whereNotIn('employee_id_number', $importedEmployeeIds)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            if ($deactivatedCount > 0) {
                $this->info("Deactivated: {$deactivatedCount} employees not found in HEMIS");
                $telegram->notify("HEMIS'da topilmagan {$deactivatedCount} ta xodim deaktivatsiya qilindi (ma'lumotlari saqlanib qoldi)");
            }
        }

        $this->renderImportSummary("Xodimlar");
        $telegram->notify($this->importTelegramMessage("Xodimlar"));

        return self::SUCCESS;
    }

    /**
     * teachers jadvaliga yoziladigan asosiy ish joyi: avval "Ishlamoqda", keyin
     * "Asosiy ish joy", teng bo'lsa eng yangi (katta meta_id) yozuv.
     */
    private function primaryRecord(array $records): array
    {
        usort($records, function ($a, $b) {
            return [$this->isWorking($b), $this->isMainJob($b), (int) $b['meta_id']]
                <=> [$this->isWorking($a), $this->isMainJob($a), (int) $a['meta_id']];
        });

        return $records[0];
    }

    private function isWorking(array $record): int
    {
        return str_contains(mb_strtolower((string) ($record['employeeStatus']['name'] ?? '')), 'ishlamoqda') ? 1 : 0;
    }

    private function isMainJob(array $record): int
    {
        return str_contains(mb_strtolower((string) ($record['employmentForm']['name'] ?? '')), 'asosiy ish') ? 1 : 0;
    }

    /** Xodimning barcha ish joylari: yangilari qo'shiladi, HEMIS da qolmaganlari o'chiriladi. */
    private function syncEmployments(Teacher $teacher, array $records, int $primaryMetaId): void
    {
        $metaIds = [];
        foreach ($records as $record) {
            $metaId = (int) $record['meta_id'];
            $metaIds[] = $metaId;
            TeacherEmployment::updateOrCreate(['meta_id' => $metaId], [
                'teacher_id' => $teacher->id,
                'department_hemis_id' => $record['department']['id'] ?? null,
                'department_name' => $record['department']['name'] ?? null,
                'staff_position' => $record['staffPosition']['name'] ?? null,
                'employment_form' => $record['employmentForm']['name'] ?? null,
                'employment_staff' => $record['employmentStaff']['name'] ?? null,
                'employee_status' => $record['employeeStatus']['name'] ?? null,
                'is_primary' => $metaId === $primaryMetaId,
                'is_working' => (bool) $this->isWorking($record),
            ]);
        }

        TeacherEmployment::where('teacher_id', $teacher->id)->whereNotIn('meta_id', $metaIds)->delete();
    }
}
