<?php

namespace App\Console\Commands;

use App\Models\RetakeApplication;
use App\Services\Retake\RetakeApplicationService;
use App\Services\Retake\RetakeDebtService;
use Illuminate\Console\Command;

class RetakeAutoCancelHemis extends Command
{
    protected $signature = 'retake:auto-cancel-hemis {--dry-run : Faqat hisoblash, o\'zgarish yo\'q}';

    protected $description = 'HEMIS sync\'da baho paydo bo\'lgan yoki retraining_status o\'zgarganda kutilayotgan arizalarni avtomatik bekor qilish';

    public function handle(
        RetakeApplicationService $applicationService,
        RetakeDebtService $debtService,
    ): int {
        $dry = (bool) $this->option('dry-run');

        $candidates = RetakeApplication::query()
            ->where('final_status', RetakeApplication::STATUS_PENDING)
            ->get();

        $cancelled = 0;
        $checked = 0;

        foreach ($candidates as $app) {
            $checked++;
            $stillDebt = $debtService->isStillDebtor(
                (int) $app->student_hemis_id,
                $app->subject_id,
                $app->semester_id,
            );

            if ($stillDebt) {
                continue; // hali qarzdor — daxl qilmaymiz
            }

            $this->line(sprintf(
                "[%s] HEMIS: bekor qilinadi — student=%d, subject=%s, semester=%s",
                $dry ? 'DRY' : 'CANCEL',
                $app->student_hemis_id,
                $app->subject_id,
                $app->semester_id,
            ));

            if (!$dry) {
                $applicationService->autoCancelByHemis($app);
            }
            $cancelled++;
        }

        $this->info("Tekshirildi: {$checked}, bekor qilindi: {$cancelled}");
        return self::SUCCESS;
    }
}
