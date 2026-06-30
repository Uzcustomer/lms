<?php

namespace App\Console\Commands;

use App\Models\CurriculumSubject;
use App\Models\Group;
use App\Models\VedomostSubmission;
use App\Services\VedomostSubmissionService;
use App\Services\YnStageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nega (guruh, fan) uchun qo'shimcha/12a/12b shakl ochilmagan — diagnostika.
 *
 * Misol:
 *   php artisan yn:why-qoshimcha "d2/25-05" "biolog"
 *   php artisan yn:why-qoshimcha "d2" "Umumiy xirurgiya"
 */
class WhyQoshimcha extends Command
{
    protected $signature = 'yn:why-qoshimcha {group : guruh nomidan bo\'lak} {subject : fan nomidan bo\'lak}';

    protected $description = 'Nega (guruh, fan) uchun qo\'shimcha/12b shakl ochilmagan — diagnostika';

    public function handle(VedomostSubmissionService $vsvc, YnStageService $stage): int
    {
        $groupNeedle = (string) $this->argument('group');
        $subjectNeedle = (string) $this->argument('subject');

        $hasSababli = Schema::hasColumn('student_grades', 'retake_was_sababli');
        $hasAttempt = Schema::hasColumn('student_grades', 'attempt');
        $hasQosh = Schema::hasColumn('student_grades', 'is_qoshimcha');

        $semByGroup = $vsvc->currentSemestersByGroup();

        $groups = Group::where('active', true)
            ->where('name', 'like', '%' . $groupNeedle . '%')
            ->orderBy('name')
            ->get();

        if ($groups->isEmpty()) {
            $this->error("Guruh topilmadi: '{$groupNeedle}' (faqat active guruhlar).");
            return self::FAILURE;
        }

        $this->info("Topilgan guruhlar: " . $groups->pluck('name')->implode(', '));

        foreach ($groups as $group) {
            $gid = (string) $group->group_hemis_id;
            $curSem = $semByGroup->get($gid)?->code;

            $this->line('');
            $this->info("● {$group->name}  (group_hemis_id={$gid}, joriy semestr=" . ($curSem ?? '—') . ")");

            if ($curSem === null) {
                $this->warn("   Joriy semestr aniqlanmadi (faol talaba yo'q?) — sync bu guruhni o'tkazib yuboradi.");
                continue;
            }

            // Fanni topish — shu reja+semestrda nom bo'yicha.
            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $curSem)
                ->where('subject_name', 'like', '%' . $subjectNeedle . '%')
                ->get();

            if ($subjects->isEmpty()) {
                $this->warn("   Fan topilmadi: '{$subjectNeedle}' (reja={$group->curriculum_hemis_id}, semestr={$curSem}).");
                $this->line("   → Bu guruhда bu fan shu semestrда yo'q yoki nom boshqacha.");
                continue;
            }

            foreach ($subjects as $subj) {
                $subjectId = (string) $subj->subject_id;
                $this->line("   ── Fan: {$subj->subject_name}  (subject_id={$subjectId}, yopilish={$subj->closing_form})");

                // Sababli / qo'shimcha baho sanog'i (shu guruh+fan+semestr).
                $gq = DB::table('student_grades as sg')
                    ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                    ->where('st.group_id', $gid)
                    ->where('sg.subject_id', $subjectId)
                    ->where('sg.semester_code', $curSem)
                    ->whereNull('sg.deleted_at');
                $sababliCnt = $hasSababli ? (clone $gq)->where('sg.retake_was_sababli', 1)->count() : 0;
                $qoshCnt = $hasQosh ? (clone $gq)->where('sg.is_qoshimcha', 1)->count() : 0;
                $this->line("      sababli baholar: {$sababliCnt} | is_qoshimcha baholar: {$qoshCnt}");

                // VEDOMOST qo'shimcha varaq ochish mantig'i: is_qoshimcha (101/102)
                // IMTIHON bahosi MAVJUDLIGI (o'tdi-yiqildi ahamiyatsiz), urinish bo'yicha.
                $qoshAttempts = $hasQosh
                    ? (clone $gq)->where('sg.is_qoshimcha', 1)
                        ->whereIn('sg.training_type_code', [101, 102])
                        ->select('sg.attempt')->distinct()->pluck('attempt')
                        ->map(fn($a) => (int) ($a ?? 1))->unique()->sort()->values()->all()
                    : [];
                $opensMap = [1 => '12-qo\'shimcha', 2 => '12a-qo\'shimcha', 3 => '12b-qo\'shimcha'];
                $opens = collect($qoshAttempts)->map(fn($a) => $opensMap[$a] ?? null)->filter()->implode(', ');
                $this->line("      → VEDOMOST ochadi (farmoyish imtihoni bor): " . ($opens ?: '— (farmoyish imtihoni yo\'q)'));

                // Stage hisobi (informativ — qaydnoma bosqichi; varaq ochishga ta'sir qilmaydi)
                $res = $stage->computeForGroupSubject($gid, $subjectId, (string) $curSem);
                if ($res !== null) {
                    $byStage = [];
                    foreach ($res['stages'] as $s) {
                        $byStage[$s] = ($byStage[$s] ?? 0) + 1;
                    }
                    arsort($byStage);
                    $this->line("      Bosqichlar (informativ): " . collect($byStage)->map(fn($c, $s) => "$s=$c")->implode(', '));
                }

                // Bazadagi mavjud vedomost shakllar (shu fan+semestr bo'yicha).
                $allRows = VedomostSubmission::where('subject_id', $subjectId)
                    ->where('semester_code', $curSem)
                    ->get(['form_type', 'group_hemis_id', 'group_name', 'status']);
                $forms = $allRows->pluck('form_type')->unique()->sort()->implode(', ');
                $this->line("      Bazadagi shakllar (subject+sem bo'yicha): " . ($forms ?: '— (hech narsa)'));
            }
        }

        return self::SUCCESS;
    }
}
