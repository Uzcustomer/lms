<?php

namespace App\Console\Commands;

use App\Services\VedomostSubmissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Vedomost sync zanjiri qayerda uzilayotganini ko'rsatadi:
 * talaba -> guruh -> o'quv reja -> curriculum_subjects -> vedomost.
 * Misol: php artisan vedomost:diagnose 17
 */
class DiagnoseVedomostSemester extends Command
{
    protected $signature = 'vedomost:diagnose {semester : Semestr kodi, masalan 17}';

    protected $description = 'Berilgan semestr uchun vedomost sync zanjirini diagnostika qiladi';

    public function handle(VedomostSubmissionService $service): int
    {
        $sem = (string) $this->argument('semester');
        $forms = VedomostSubmissionService::CLOSING_FORMS_WITH_VEDOMOST;

        $this->line('');
        $this->info("=== Vedomost diagnostika: {$sem}-semestr kodi ===");

        // 1) Faol talabalar (status 11) shu semestrda, guruhli
        $studentRows = DB::table('students')
            ->where('student_status_code', 11)
            ->where('semester_code', $sem)
            ->whereNotNull('group_id')
            ->selectRaw('group_id, count(*) as cnt, max(curriculum_id) as curriculum_id')
            ->groupBy('group_id')
            ->get();

        $this->line('');
        $this->line("1) Faol talaba (status=11), semester_code={$sem}, group_id bor:");
        $this->line("   Guruhlar soni: " . $studentRows->count() . ", jami talaba: " . $studentRows->sum('cnt'));
        if ($studentRows->isEmpty()) {
            $this->warn("   ❌ Bu semestrda guruhli faol talaba yo'q — zanjir shu yerda uziladi.");
            $this->line("   (Talabalar status 11 emasmi yoki semester_code boshqami tekshiring.)");
            return self::SUCCESS;
        }

        // 1b) currentSemestersByGroup bu semestrni shu guruhlar uchun TANLAYDIMI?
        $byGroup = $service->currentSemestersByGroup();
        $chosen = $studentRows->filter(fn($r) => (string) optional($byGroup->get($r->group_id))->code === $sem);
        $this->line('');
        $this->line("1b) currentSemestersByGroup ushbu semestrni tanlagan guruhlar: " . $chosen->count() . " / " . $studentRows->count());
        $overridden = $studentRows->reject(fn($r) => (string) optional($byGroup->get($r->group_id))->code === $sem);
        if ($overridden->isNotEmpty()) {
            $this->warn("   ⚠ {$overridden->count()} guruhda boshqa semestr ko'pchilikni tashkil etadi (shu guruhlar uchun {$sem} olinmaydi):");
            foreach ($overridden->take(10) as $r) {
                $other = optional($byGroup->get($r->group_id))->code;
                $this->line("     group_id={$r->group_id}: tanlangan={$other}");
            }
        }

        // 2) Bu guruhlar groups jadvalida active va curriculum_hemis_id bor?
        $groupIds = $studentRows->pluck('group_id')->all();
        $groups = DB::table('groups')->whereIn('group_hemis_id', $groupIds)->get();
        $activeGroups = $groups->where('active', 1)->whereNotNull('curriculum_hemis_id');
        $this->line('');
        $this->line("2) Guruhlar groups jadvalida:");
        $this->line("   Topilgan: {$groups->count()}, active+curriculum_hemis_id bor: {$activeGroups->count()}");
        $missing = collect($groupIds)->diff($groups->pluck('group_hemis_id'));
        if ($missing->isNotEmpty()) {
            $this->warn("   ⚠ groups jadvalida yo'q group_id: " . $missing->take(10)->implode(', '));
        }
        $inactive = $groups->where('active', '!=', 1);
        if ($inactive->isNotEmpty()) {
            $this->warn("   ⚠ active=false guruhlar: {$inactive->count()} (bular sync'ga kirmaydi)");
        }
        $noCurr = $groups->whereNull('curriculum_hemis_id');
        if ($noCurr->isNotEmpty()) {
            $this->warn("   ⚠ curriculum_hemis_id NULL guruhlar: {$noCurr->count()}");
        }

        // 3) Ushbu rejalar + semestr uchun curriculum_subjects
        $currIds = $activeGroups->pluck('curriculum_hemis_id')->unique()->filter()->all();
        $this->line('');
        $this->line("3) curriculum_subjects (rejalar: " . count($currIds) . " ta, semester_code={$sem}):");
        if (empty($currIds)) {
            $this->warn("   ❌ Tegishli o'quv reja yo'q.");
            return self::SUCCESS;
        }
        $allSubj = DB::table('curriculum_subjects')
            ->whereIn('curricula_hemis_id', $currIds)
            ->where('semester_code', $sem)
            ->get();
        $activeSubj = $allSubj->where('is_active', 1);
        $vedomostSubj = $activeSubj->whereIn('closing_form', $forms);
        $this->line("   Jami: {$allSubj->count()}, is_active: {$activeSubj->count()}, vedomostli closing_form: {$vedomostSubj->count()}");

        if ($allSubj->isEmpty()) {
            $this->warn("   ❌ Bu rejalarda {$sem}-semestr uchun umuman fan yo'q.");
            $this->line("   curriculum_subjects.semester_code qiymatlari (shu rejalarda):");
            $codes = DB::table('curriculum_subjects')->whereIn('curricula_hemis_id', $currIds)
                ->select('semester_code', DB::raw('count(*) as c'))->groupBy('semester_code')->orderBy('semester_code')->get();
            foreach ($codes as $c) {
                $this->line("     {$c->semester_code}: {$c->c} fan");
            }
            return self::SUCCESS;
        }
        if ($vedomostSubj->isEmpty()) {
            $this->warn("   ❌ {$sem}-semestrda vedomost talab qiluvchi closing_form (".implode(', ', $forms).") yo'q.");
            $this->line("   Mavjud closing_form qiymatlari:");
            foreach ($activeSubj->groupBy('closing_form') as $cf => $g) {
                $this->line("     " . ($cf ?: 'NULL') . ": {$g->count()} fan");
            }
            return self::SUCCESS;
        }

        // 4) Hozir bazada shu semestr bo'yicha vedomost qatorlari
        $existing = DB::table('vedomost_submissions')->where('semester_code', $sem)->count();
        $this->line('');
        $this->line("4) Hozirgi vedomost_submissions (semester_code={$sem}): {$existing} ta");

        $this->line('');
        $this->info("Xulosa: zanjir to'liq — sync {$chosen->count()} guruh × {$vedomostSubj->count()} fan atrofida yozuv yaratishi kerak.");
        $this->line("Agar hali 0 bo'lsa: 'php artisan vedomost:sync' ni ishga tushiring.");

        return self::SUCCESS;
    }
}
