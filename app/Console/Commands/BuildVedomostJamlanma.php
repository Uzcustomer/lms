<?php

namespace App\Console\Commands;

use App\Models\VedomostSubmission;
use App\Services\VedomostJamlanmaExcel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tasdiqlangan vedomostlarning jamlanma qatorlarini oldindan hisoblaydi (kesh).
 *   php artisan vedomost:jamlanma-build            — keshi yo'qlari
 *   php artisan vedomost:jamlanma-build --fresh    — hammasini qaytadan (Mark ma'lumoti o'zgargan bo'lsa)
 */
class BuildVedomostJamlanma extends Command
{
    protected $signature = 'vedomost:jamlanma-build {--fresh : Barcha tasdiqlanganlarni qaytadan hisoblash} {--limit=0 : Bir ishga tushishda ko\'pi bilan}';

    protected $description = "Tasdiqlangan vedomostlar jamlanmasi qatorlarini oldindan hisoblash";

    public function handle(VedomostJamlanmaExcel $excel): int
    {
        if (!Schema::hasTable('vedomost_jamlanma_rows')) {
            $this->error('vedomost_jamlanma_rows jadvali yo\'q — avval migratsiya.');

            return self::FAILURE;
        }
        @ini_set('memory_limit', '1024M');

        $q = VedomostSubmission::where('status', VedomostSubmission::STATUS_APPROVED)->orderBy('id');
        if (!$this->option('fresh')) {
            $q->whereNotIn('id', DB::table('vedomost_jamlanma_rows')->select('vedomost_submission_id'));
        }
        if ((int) $this->option('limit') > 0) {
            $q->limit((int) $this->option('limit'));
        }
        $ids = $q->pluck('id');
        $this->info("Hisoblanadi: {$ids->count()} ta vedomost");

        $done = 0;
        $failed = 0;
        foreach ($ids->chunk(50) as $chunk) {
            foreach (VedomostSubmission::whereIn('id', $chunk)->get() as $sub) {
                try {
                    $excel->store($sub);
                    $done++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("#{$sub->id} {$sub->group_name} — {$sub->subject_name}: " . $e->getMessage());
                }
            }
            $this->line("  {$done} / {$ids->count()}");
        }
        $this->info("Tayyor: {$done} ta" . ($failed ? ", xato: {$failed} ta" : ''));

        return self::SUCCESS;
    }
}
