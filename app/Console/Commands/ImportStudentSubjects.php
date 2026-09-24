<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\Student;
use App\Services\HemisService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ImportStudentSubjects extends Command
{
    protected $signature = 'import:student-subjects
        {--group= : Faqat shu guruh (group_hemis_id yoki guruh nomi, masalan p/p23-03a)}
        {--student= : Faqat shu talaba (hemis_id yoki talaba ID raqami)}';

    protected $description = "Har bir talabaga HEMIS da biriktirilgan fanlarni import qilish (student-subject-list)";

    public function handle(TelegramService $telegram, HemisService $hemisService)
    {
        // Yangi qo'shilgan talaba jurnalda ko'rinishi uchun butun bazani
        // (har talabaga bitta HEMIS so'rovi, soatlab) aylanish shart emas —
        // --group yoki --student bilan faqat kerakli talabalar tortiladi.
        $only = $this->resolveTargets();
        if ($only !== null && empty($only)) {
            $this->error("Berilgan guruh/talaba bazada topilmadi. Avval students:import ni yurgizing.");
            return 1;
        }

        $scope = $only === null ? 'barcha talabalar' : count($only) . ' ta talaba';
        if ($only === null) {
            $telegram->notify("🟢 Talaba fanlar importi boshlandi");
        }
        $this->info("HEMIS dan biriktirilgan fanlar import qilinmoqda ({$scope})...");

        $startTime = microtime(true);

        $totalImported = $hemisService->importStudentSubjects(function ($done, $total, $imported) {
            $percent = round(($done / $total) * 100, 1);
            $this->output->write("\r  Talaba: {$done}/{$total} | Yozuv: {$imported} | {$percent}%");
        }, $only);

        $duration = round((microtime(true) - $startTime) / 60, 1);

        $this->newLine();
        $this->info("Import tugadi! Jami: {$totalImported} ta yozuv, Vaqt: {$duration} daqiqa");
        if ($only === null) {
            $telegram->notify("✅ Talaba fanlar importi tugadi. Jami: {$totalImported} ta, Vaqt: {$duration} daqiqa");
        }

        return 0;
    }

    /**
     * --group / --student dan talabalarning hemis_id ro'yxati.
     * null — opsiya berilmagan (hammasi); bo'sh massiv — berilgan, lekin topilmadi.
     */
    private function resolveTargets(): ?array
    {
        $group = trim((string) $this->option('group'));
        $student = trim((string) $this->option('student'));
        if ($group === '' && $student === '') {
            return null;
        }

        $query = Student::query()->whereNotNull('hemis_id');

        if ($group !== '') {
            $groupHemisId = ctype_digit($group)
                ? (int) $group
                : Group::where('name', $group)->value('group_hemis_id');
            $query->where('group_id', $groupHemisId ?: -1);
        }

        if ($student !== '') {
            $query->where(function ($q) use ($student) {
                $q->where('hemis_id', $student)->orWhere('student_id_number', $student);
            });
        }

        return $query->pluck('hemis_id')->map(fn ($id) => (int) $id)->all();
    }
}
