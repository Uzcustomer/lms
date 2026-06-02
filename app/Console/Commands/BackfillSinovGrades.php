<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\JournalController;
use App\Models\CurriculumSubject;
use App\Models\YnSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillSinovGrades extends Command
{
    protected $signature = 'sinov:backfill
        {--dry-run : Faqat ko\'rib chiqish — DB ga yozmaslik}';

    protected $description = 'Yopilish shakli "sinov" bo\'lgan barcha YN yuborilgan guruh+fan uchun yo\'qolgan SinovTestGrade va student_grades sinov_yn_test yozuvlarini JN o\'rtachasidan tiklash.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Sinov fanlar ro\'yxati yig\'ilmoqda...');

        // Yopilish shakli 'sinov' bo'lgan subject_id larni topish
        $sinovSubjectIds = CurriculumSubject::where('closing_form', 'sinov')
            ->distinct()
            ->pluck('subject_id')
            ->all();

        if (empty($sinovSubjectIds)) {
            $this->warn('Hech qanday sinov fan topilmadi.');
            return self::SUCCESS;
        }

        // YN submission'lar — sinov subject_id'lari bo'yicha
        $submissions = YnSubmission::whereIn('subject_id', $sinovSubjectIds)
            ->select('subject_id', 'semester_code', 'group_hemis_id')
            ->distinct()
            ->get();

        $total = $submissions->count();
        if ($total === 0) {
            $this->warn('Hech qaysi sinov fani YN ga yuborilmagan.');
            return self::SUCCESS;
        }

        $this->info("Jami {$total} ta (guruh × fan × semestr) topildi.");
        if ($dryRun) {
            $this->warn('DRY-RUN rejim — DB ga hech narsa yozilmaydi.');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $touchedTotal = 0;
        $errors = 0;

        foreach ($submissions as $sub) {
            try {
                if ($dryRun) {
                    // Faqat hisoblab chiqish — qancha yozuv yo'qligini ko'rsatish
                    $studentIds = DB::table('students')
                        ->where('group_id', $sub->group_hemis_id)
                        ->pluck('hemis_id')
                        ->all();
                    $sgCount = DB::table('student_grades')
                        ->whereNull('deleted_at')
                        ->where('subject_id', $sub->subject_id)
                        ->where('semester_code', $sub->semester_code)
                        ->where('training_type_code', 102)
                        ->where('reason', 'sinov_yn_test')
                        ->whereIn('student_hemis_id', $studentIds)
                        ->count();
                    $missing = count($studentIds) - $sgCount;
                    if ($missing > 0) {
                        $touchedTotal += $missing;
                    }
                } else {
                    $touched = JournalController::backfillSinovDataForGroup(
                        (string) $sub->subject_id,
                        (string) $sub->semester_code,
                        (string) $sub->group_hemis_id
                    );
                    $touchedTotal += $touched;
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->error("Xato (subject={$sub->subject_id}, group={$sub->group_hemis_id}): {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("DRY-RUN: jami {$touchedTotal} ta yo'qolgan student_grades yozuvi aniqlandi.");
            $this->line('Haqiqiy tiklash uchun: php artisan sinov:backfill');
        } else {
            $this->info("Tiklash yakunlandi: {$touchedTotal} ta yozuv yangilandi / yaratildi.");
        }

        if ($errors > 0) {
            $this->warn("{$errors} ta xato bo'ldi (yuqorida ko'rinadi).");
        }

        return self::SUCCESS;
    }
}
