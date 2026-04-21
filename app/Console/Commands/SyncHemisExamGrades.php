<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\HemisExamGrade;
use App\Services\HemisService;
use Illuminate\Console\Command;

class SyncHemisExamGrades extends Command
{
    protected $signature = 'hemis:sync-exam-grades
                            {--full : Barchasini boshidan tortish (updated_at filtersiz)}
                            {--group= : Bitta guruh HEMIS ID (per-student sync)}
                            {--subject= : Bitta fan ID (--group bilan birga)}
                            {--semester= : Semestr kodi (--group bilan birga)}';

    protected $description = 'HEMIS student-performance-list dan baholarni hemis_exam_grades jadvaliga sync qilish';

    public function handle(HemisService $hemis): int
    {
        // Bitta guruh/fan uchun — per-student sync (HEMIS yangilash tugmasi kabi)
        if ($this->option('group') && $this->option('subject')) {
            return $this->syncSingleGroup($hemis);
        }

        // Bulk sync — faqat oxirgi o'zgarishlar (yoki --full bilan barchasi)
        return $this->syncBulk($hemis);
    }

    private function syncSingleGroup(HemisService $hemis): int
    {
        $group = Group::where('group_hemis_id', $this->option('group'))->first();
        if (!$group) {
            $this->error("Guruh topilmadi: {$this->option('group')}");
            return self::FAILURE;
        }

        $studentHemisIds = \App\Models\Student::where('group_id', $group->group_hemis_id)
            ->pluck('hemis_id')->toArray();

        $synced = $hemis->syncExamGradesForGroup(
            $studentHemisIds,
            $this->option('subject'),
            $this->option('semester') ?? '',
            null,
            30
        );

        $this->info("Synced {$synced} exam grade(s).");
        return self::SUCCESS;
    }

    private function syncBulk(HemisService $hemis): int
    {
        $updatedAtFrom = null;

        if (!$this->option('full')) {
            // HEMIS'dagi eng oxirgi updated_at vaqtini olamiz (lokal updated_at
            // EMAS — chunki qo'lda "HEMIS yangilash" tugmasi faqat ayrim
            // guruhlarni yangilaydi va lokal updated_at shu vaqtga o'zgaradi,
            // lekin boshqa guruhlar yangilanmay qoladi. hemis_updated_at esa
            // HEMIS tizimining o'z vaqti — cron shu joydan davom etadi).
            $lastHemisUpdated = HemisExamGrade::max('hemis_updated_at');
            if ($lastHemisUpdated) {
                // 1 soat orqaga — chegaradagi yozuvlar tushib qolmasligi uchun
                $updatedAtFrom = (int) $lastHemisUpdated - 3600;
                $this->info("Oxirgi HEMIS updated_at: {$lastHemisUpdated} — faqat o'zgarganlarni tortamiz (updated_at_from={$updatedAtFrom})");
            } else {
                $this->info("Jadval bo'sh yoki hemis_updated_at yo'q — barchasini tortamiz.");
            }
        } else {
            $this->info("--full rejim: barchasini boshidan tortamiz.");
        }

        $synced = $hemis->syncExamGradesBulk($updatedAtFrom, 30);

        $this->info("Jami {$synced} ta HEMIS exam grade sync qilindi.");
        return self::SUCCESS;
    }
}
