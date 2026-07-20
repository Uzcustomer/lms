<?php

namespace App\Console\Commands;

use App\Models\ManualCurriculum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bir HEMIS o'quv reja uchun namunaviy BITTA bo'lishi kerak. Tarixda xato bilan
 * bir nechta namunaviy yuklangan bo'lsa, bu buyruq har bir HEMIS reja uchun
 * kanonik (eng to'liq — eng ko'p fan qatorli, teng bo'lsa eng oxirgi) namunaviyni
 * qoldirib, qolgan dublikatlarni o'chiradi. Dublikatning fanlari va saqlangan
 * solishtirishlari kaskad orqali tozalanadi.
 *
 * Standart holatda faqat hisobot ko'rsatadi (dry-run). Haqiqatan o'chirish uchun
 * --apply bayrog'i bilan ishga tushiring.
 */
class DedupeNamunaviyCurricula extends Command
{
    protected $signature = 'curriculum:dedupe-namunaviy {--apply : Dublikatlarni haqiqatan o\'chirish (aks holda faqat hisobot)}';

    protected $description = "Bir HEMIS reja uchun ortiqcha namunaviy o'quv rejalarni topib, kanonigini qoldirib qolganini o'chiradi";

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        // curricula_hemis_id NULL bo'lganlar (rejalashtirilgan rejalar) dedup qilinmaydi —
        // ular HEMIS rejaga bog'lanmagan va o'zaro dublikat sanalmaydi.
        $groups = ManualCurriculum::query()
            ->where('type', 'namunaviy')
            ->whereNotNull('curricula_hemis_id')
            ->withCount('subjects')
            ->get()
            ->groupBy('curricula_hemis_id')
            ->filter(fn ($group) => $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('Dublikat namunaviy topilmadi — har bir HEMIS reja uchun bitta namunaviy bor.');
            return self::SUCCESS;
        }

        $this->warn(($apply ? '' : '[DRY-RUN] ') . "Dublikat namunaviy topilgan HEMIS rejalar: {$groups->count()} ta");
        $this->newLine();

        $toDeleteIds = [];

        foreach ($groups as $hemisId => $group) {
            $keep = $group->sortByDesc(fn ($c) => ManualCurriculum::canonicalRank($c))->first();
            $drop = $group->reject(fn ($c) => $c->id === $keep->id);

            $this->line("HEMIS reja #{$hemisId} — {$keep->name}");
            $this->line("  QOLADI:   #{$keep->id} ({$keep->subjects_count} fan qatori, yuklangan {$keep->created_at})");
            foreach ($drop as $d) {
                $this->line("  O'CHADI:  #{$d->id} ({$d->subjects_count} fan qatori, yuklangan {$d->created_at})");
                $toDeleteIds[] = $d->id;
            }
            $this->newLine();
        }

        $this->line('Jami o\'chiriladigan dublikat: ' . count($toDeleteIds) . ' ta');

        if (!$apply) {
            $this->newLine();
            $this->comment('Bu faqat hisobot. Haqiqatan o\'chirish uchun: php artisan curriculum:dedupe-namunaviy --apply');
            return self::SUCCESS;
        }

        $deleted = 0;
        DB::transaction(function () use ($toDeleteIds, &$deleted) {
            foreach (ManualCurriculum::whereIn('id', $toDeleteIds)->get() as $old) {
                $old->delete(); // fanlar + solishtirishlar kaskad orqali o'chadi
                $deleted++;
            }
        });

        $this->newLine();
        $this->info("Tayyor: {$deleted} ta dublikat namunaviy o'chirildi.");

        return self::SUCCESS;
    }
}
