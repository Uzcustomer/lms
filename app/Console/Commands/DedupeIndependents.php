<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Avtomatik yaratilgan mustaqil ta'lim topshiriqlarining dublikatlarini tozalash.
 *
 * command:independent-auto-create (endi olib tashlangan) avval har bir dars kuniga alohida topshiriq
 * ochardi (2026-10-31 da bir kunda 138 954 ta) — talabaga bitta fandan o'nlab
 * topshiriq chiqdi. Har bir (guruh, fan, semestr) uchun bittasi qoldiriladi.
 *
 * Xavfsizlik:
 *  - faqat avtomatik yozuvlar (user_id = 0); o'qituvchi/admin yaratganlariga tegilmaydi;
 *  - talaba fayl yuklagan, baho yoki baho tarixi bor, o'qituvchi fayl biriktirgan
 *    topshiriq hech qachon o'chirilmaydi;
 *  - sukut bo'yicha faqat hisobot; o'chirish --force bilan.
 */
class DedupeIndependents extends Command
{
    protected $signature = 'independents:dedupe {--force : Haqiqatan o\'chirish (bo\'lmasa faqat hisobot)}';

    protected $description = "Mustaqil ta'lim: bitta fan uchun bittadan ortiq avtomatik topshiriqni tozalash";

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $groups = DB::table('independents')
            ->where('user_id', 0)
            ->select('group_hemis_id')
            ->groupBy('group_hemis_id', 'subject_hemis_id', 'semester_code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('group_hemis_id')
            ->unique()
            ->values();

        $this->info(($force ? '' : '[hisobot] ') . "Dublikati bor guruhlar: {$groups->count()}");

        $toDelete = 0;
        $deleted = 0;
        $combos = 0;
        $stillMany = [];

        foreach ($groups as $groupId) {
            $rows = DB::table('independents')
                ->where('user_id', 0)
                ->where('group_hemis_id', $groupId)
                ->orderBy('id')
                ->get(['id', 'group_name', 'subject_hemis_id', 'subject_name', 'semester_code', 'teacher_name', 'file_path']);

            $ids = $rows->pluck('id')->all();
            $used = $this->usedIds($ids);

            foreach ($rows->groupBy(fn ($r) => $r->subject_hemis_id . '|' . $r->semester_code) as $items) {
                if ($items->count() < 2) {
                    continue;
                }
                $combos++;

                $keep = $items->filter(fn ($r) => isset($used[$r->id]) || !empty($r->file_path));
                if ($keep->isEmpty()) {
                    // Hech biriga tegilmagan: o'qituvchisi yozilgani, eng eskisi
                    $keep = collect([$items->sortBy(fn ($r) => [empty($r->teacher_name) ? 1 : 0, $r->id])->first()]);
                }
                if ($keep->count() > 1) {
                    $stillMany[] = $items->first()->group_name . ' — ' . $items->first()->subject_name . ' (' . $keep->count() . ' ta)';
                }

                $drop = $items->pluck('id')->diff($keep->pluck('id'))->values();
                $toDelete += $drop->count();
                if ($force && $drop->isNotEmpty()) {
                    foreach ($drop->chunk(1000) as $chunk) {
                        $deleted += DB::table('independents')->whereIn('id', $chunk->all())->delete();
                    }
                }
            }
        }

        $this->info("Bir nechta topshirig'i bor fan (guruh bo'yicha): {$combos}");
        $this->info(($force ? "O'chirildi: {$deleted}" : "O'chiriladi: {$toDelete}") . ' ta ortiqcha topshiriq');

        if ($stillMany) {
            $this->warn(count($stillMany) . " ta fanda bir nechta topshiriqqa talaba fayl/baho qo'ygan — ular qoldirildi (qo'lda ko'rish kerak):");
            foreach (array_slice($stillMany, 0, 30) as $line) {
                $this->line('  ' . $line);
            }
            if (count($stillMany) > 30) {
                $this->line('  ...');
            }
        }
        if (!$force) {
            $this->comment("Bu faqat hisobot. O'chirish uchun: php artisan independents:dedupe --force");
        }

        return self::SUCCESS;
    }

    /** Talaba fayli, baho yoki baho tarixi bog'langan topshiriqlar: id => true */
    private function usedIds(array $ids): array
    {
        $used = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (['independent_submissions', 'independent_grade_history', 'student_grades'] as $table) {
                foreach (DB::table($table)->whereIn('independent_id', $chunk)->distinct()->pluck('independent_id') as $id) {
                    $used[$id] = true;
                }
            }
        }

        return $used;
    }
}
