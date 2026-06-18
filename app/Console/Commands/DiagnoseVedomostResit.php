<?php

namespace App\Console\Commands;

use App\Services\VedomostSubmissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 12a/12b (qayta topshirish) varaqlari nima uchun ochilmayotganini ko'rsatadi.
 * Misol:
 *   php artisan vedomost:diagnose-resit "Biologik kimyo" "Normal fiziologiya"
 *   php artisan vedomost:diagnose-resit          (barcha resitli fanlar)
 */
class DiagnoseVedomostResit extends Command
{
    protected $signature = 'vedomost:diagnose-resit {subject?* : Fan nomidan bo\'lak(lar) — bo\'sh bo\'lsa hammasi}';

    protected $description = '12a/12b qayta topshirish varaqlari nima uchun ochilmayotganini diagnostika qiladi';

    public function handle(VedomostSubmissionService $service): int
    {
        $needles = (array) $this->argument('subject');

        $this->info('12a/12b ochilish shartlari tekshirilmoqda...');
        if (!empty($needles)) {
            $this->line('Fan filtri: ' . implode(' | ', $needles));
        }

        $rows = $service->diagnoseResit($needles);

        if (empty($rows)) {
            $this->warn('Mos keladigan fan topilmadi yoki joriy semestr aniqlanmadi.');
            $this->line("Eslatma: faqat OSKI/Test imtihonli (oski/test/oski_test) fanlarda 12a/12b bo'ladi.");
            return self::SUCCESS;
        }

        foreach ($rows as $r) {
            $this->line('');
            $this->info("● {$r->subject}  [{$r->specialty}]  ({$r->closing_form}, semestr={$r->semester_code}, {$r->groups} guruh)");
            $this->line("   Guruhlar: {$r->group_names}");
            $this->line("   subject_key(lar): {$r->subject_keys}");
            $this->line("   Bugun: {$r->today}");
            $this->line("   1-urinish sanasi: " . ($r->attempt1_date ?? '—')
                . " | resit sanasi: " . ($r->resit_date ?? '—')
                . " | resit2 sanasi: " . ($r->resit2_date ?? '—'));
            $this->line("   Baholangan talaba: {$r->graded} | 1-urinishda yiqilgan: {$r->failed1} | 2-urinishda yiqilgan: {$r->failed2}");
            if ($r->graded === 0) {
                $this->line("   ⚠ baholangan talaba 0 — baho import qilinmagan yoki subject_id mos kelmagan bo'lishi mumkin.");
            }
            $mark12a = $r->open12a ? '✅' : '❌';
            $mark12b = $r->open12b ? '✅' : '❌';
            $this->line("   12a: {$mark12a} {$r->reason12a}");
            $this->line("   12b: {$mark12b} {$r->reason12b}");
        }

        // Bazada hozir mavjud 12a/12b qatorlar — semestr bo'yicha (filtr bilan
        // solishtirish uchun: ro'yxat qaysi semestr/fakultetdan qidirayotganini bilish).
        $this->line('');
        $this->info('=== Bazadagi mavjud 12a/12b qatorlar (semestr bo\'yicha) ===');
        $existing = DB::table('vedomost_submissions')
            ->whereIn('form_type', ['12a', '12b'])
            ->select('form_type', 'semester_code', DB::raw('count(*) as c'))
            ->groupBy('form_type', 'semester_code')
            ->orderBy('semester_code')
            ->get();
        if ($existing->isEmpty()) {
            $this->warn('Bazada birorta ham 12a/12b qator yo\'q — "php artisan vedomost:sync" ishga tushiring.');
        } else {
            foreach ($existing as $e) {
                $this->line("   {$e->form_type}  semestr={$e->semester_code}  →  {$e->c} ta");
            }
        }

        $this->line('');
        $this->info('Tugadi. ✅ = ochilishi kerak, ❌ = sabab yonida ko\'rsatilgan.');
        $this->line("12a ochilishi uchun: yiqilgan > 0 VA 1-urinish sanasi bugundan oldin bo'lishi kerak.");
        $this->line("Eslatma: yuqoridagi 'semestr=' qiymatini ro'yxatdagi SEMESTR filtri bilan solishtiring.");

        return self::SUCCESS;
    }
}
