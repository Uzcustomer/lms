<?php

namespace App\Console\Commands;

use App\Models\QuizGradeAppeal;
use App\Models\RetakeApplication;
use App\Models\StudentGrade;
use App\Services\ActivityLogService;
use App\Services\Retake\RetakeJournalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Apelyatsiya (o'quv prorektori) orqali o'chirilgan test baholarini hisobot
 * qiladi va (--apply bilan) TANLAB tiklaydi.
 *
 * Xavfsizlik: --apply hech qachon HAMMASINI ko'r-ko'rona tiklamaydi — kamida
 * bitta filtr (--id yoki --since) talab qilinadi. Faqat hozir haqiqatan
 * o'chirilgan (komponenti null / qatori soft-delete) yozuvlar tiklanadi;
 * allaqachon qayta yuklangan yoki tiklangan yozuvlarga tegilmaydi.
 *
 * Ikki xil manba:
 *   - Qayta o'qish (OSKE/Test): retake_applications.oske_score/test_score ni
 *     apelyatsiyadagi old_grade qiymatiga qaytaramiz (saveOskeTestScore).
 *   - To'g'ridan-to'g'ri (Moodle) baho: student_grades soft-delete'dan tiklanadi.
 */
class RestoreQuizGradeAppeals extends Command
{
    protected $signature = 'quiz:restore-appeal-deletions
        {--apply : Haqiqatan tiklaydi (aks holda faqat hisobot / dry-run)}
        {--id=* : Faqat shu apelyatsiya id(lar)ini tiklaydi}
        {--since= : Shu sanadan (Y-m-d) keyin yaratilgan o\'chirishlar}
        {--until= : Shu sanagacha (Y-m-d) yaratilgan o\'chirishlar}
        {--student= : Talaba HEMIS id bo\'yicha filtr}
        {--include-reversed : Allaqachon tiklanganlarni ham ko\'rsatadi}';

    protected $description = "Apelyatsiya bilan o'chirilgan test baholarini hisobot qiladi va (--apply bilan) tanlab tiklaydi";

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $ids = array_filter(array_map('intval', (array) $this->option('id')));
        $since = $this->option('since');
        $until = $this->option('until');
        $student = $this->option('student');
        $hasReversedCol = Schema::hasColumn('quiz_grade_appeals', 'reversed_at');

        if ($apply && empty($ids) && !$since) {
            $this->error("--apply uchun kamida --id yoki --since kerak (hammasini ko'r-ko'rona tiklamaymiz).");
            return self::FAILURE;
        }

        $query = QuizGradeAppeal::where('action', QuizGradeAppeal::ACTION_DELETE)
            ->when(!empty($ids), fn ($q) => $q->whereIn('id', $ids))
            ->when($since, fn ($q) => $q->whereDate('created_at', '>=', $since))
            ->when($until, fn ($q) => $q->whereDate('created_at', '<=', $until))
            ->when($student, fn ($q) => $q->where('student_hemis_id', (string) $student))
            ->when(
                $hasReversedCol && !$this->option('include-reversed'),
                fn ($q) => $q->whereNull('reversed_at')
            )
            ->orderBy('created_at');

        $appeals = $query->get();
        if ($appeals->isEmpty()) {
            $this->info('Mos o\'chirish apelyatsiyasi topilmadi.');
            return self::SUCCESS;
        }

        $rows = [];
        $eligible = [];
        foreach ($appeals as $a) {
            [$type, $state, $canRestore, $reason] = $this->inspect($a, $hasReversedCol);
            $rows[] = [
                $a->id,
                $a->created_at?->format('d.m.Y H:i'),
                mb_strimwidth((string) $a->student_name, 0, 22, '…'),
                mb_strimwidth((string) $a->subject_name, 0, 30, '…'),
                $type,
                $a->old_grade !== null ? rtrim(rtrim(number_format((float) $a->old_grade, 2, '.', ''), '0'), '.') : '—',
                $state,
                $canRestore ? 'HA' : ('yo\'q: ' . $reason),
            ];
            if ($canRestore) {
                $eligible[] = $a;
            }
        }

        $this->table(
            ['ID', 'Sana', 'Talaba', 'Fan', 'Tur', 'Eski baho', 'Hozirgi holat', 'Tiklanadimi'],
            $rows
        );
        $this->line("Jami: {$appeals->count()} | Tiklanadigan: " . count($eligible));

        if (!$apply) {
            $this->warn("Bu — DRY-RUN (hisobot). Tiklash uchun --apply va --id=.. yoki --since=.. qo'shing.");
            return self::SUCCESS;
        }

        $restored = 0;
        $failed = 0;
        foreach ($eligible as $a) {
            try {
                $this->restoreOne($a, $hasReversedCol);
                $restored++;
                $this->info("✓ #{$a->id} tiklandi — {$a->student_name} / {$a->subject_name}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("✗ #{$a->id} xato: " . $e->getMessage());
            }
        }

        $this->line("Tiklandi: {$restored} | Xato: {$failed}");
        return self::SUCCESS;
    }

    /**
     * Apelyatsiyaning turi, hozirgi holati va tiklanishi mumkinligini aniqlaydi.
     *
     * @return array{0:string,1:string,2:bool,3:string}
     */
    private function inspect(QuizGradeAppeal $a, bool $hasReversedCol): array
    {
        if ($hasReversedCol && $a->reversed_at !== null) {
            return ['—', 'Allaqachon tiklangan', false, 'reversed'];
        }
        if ($a->old_grade === null) {
            return ['—', 'Eski baho noma\'lum', false, 'old_grade yo\'q'];
        }

        // Qayta o'qish komponenti (OSKE/Test)
        if ($a->retake_application_id && $a->retake_component) {
            $app = RetakeApplication::find($a->retake_application_id);
            if (!$app) {
                return ['Qayta o\'qish', 'Ariza topilmadi', false, 'ariza yo\'q'];
            }
            $col = $a->retake_component === 'oske' ? 'oske_score' : 'test_score';
            $label = 'QO\'-' . strtoupper($a->retake_component);
            if ($app->$col !== null) {
                return [$label, "Qiymat bor ({$app->$col})", false, 'allaqachon to\'ldirilgan'];
            }
            return [$label, 'Bo\'sh (o\'chirilgan)', true, ''];
        }

        // To'g'ridan-to'g'ri (Moodle) baho
        if ($a->student_grade_id) {
            $g = StudentGrade::withTrashed()->find($a->student_grade_id);
            if (!$g) {
                return ['To\'g\'ridan', 'Qator topilmadi', false, 'qator yo\'q'];
            }
            if ($g->trashed()) {
                return ['To\'g\'ridan (quiz)', 'O\'chirilgan (soft)', true, ''];
            }
            // Mavzu retake: qator turibdi, faqat retake_grade bo'shatilgan
            if ($g->retake_grade === null) {
                return ['To\'g\'ridan (mavzu)', 'retake bo\'sh', true, ''];
            }
            return ['To\'g\'ridan', "Qiymat bor ({$g->retake_grade})", false, 'allaqachon bor'];
        }

        return ['—', 'Manba aniqlanmadi', false, 'manba yo\'q'];
    }

    private function restoreOne(QuizGradeAppeal $a, bool $hasReversedCol): void
    {
        $desc = null;

        if ($a->retake_application_id && $a->retake_component) {
            $app = RetakeApplication::findOrFail($a->retake_application_id);
            $comp = $a->retake_component;
            $col = $comp === 'oske' ? 'oske_score' : 'test_score';
            if ($app->$col !== null) {
                throw new \RuntimeException('komponent allaqachon to\'ldirilgan — tiklanmadi');
            }
            $old = (float) $a->old_grade;
            $oske = $comp === 'oske' ? $old : ($app->oske_score !== null ? (float) $app->oske_score : null);
            $test = $comp === 'test' ? $old : ($app->test_score !== null ? (float) $app->test_score : null);
            app(RetakeJournalService::class)->saveOskeTestScore($app, $oske, $test);
            $desc = "Apelyatsiya #{$a->id} tiklandi (qayta o'qish {$comp}): {$old} qaytarildi";
        } elseif ($a->student_grade_id) {
            $g = StudentGrade::withTrashed()->findOrFail($a->student_grade_id);
            if ($g->trashed()) {
                $g->restore();
                $desc = "Apelyatsiya #{$a->id} tiklandi (Moodle baho qatori tiklandi): {$a->old_grade}";
            } elseif ($g->retake_grade === null) {
                $g->retake_grade = (float) $a->old_grade;
                if ($a->quiz_result_id) {
                    $g->quiz_result_id = $a->quiz_result_id;
                }
                $g->save();
                $desc = "Apelyatsiya #{$a->id} tiklandi (mavzu retake): {$a->old_grade}";
            } else {
                throw new \RuntimeException('qatorda qiymat bor — tiklanmadi');
            }
        } else {
            throw new \RuntimeException('manba aniqlanmadi');
        }

        if ($hasReversedCol) {
            $a->reversed_at = now();
            $a->reversed_by = 'cli';
            $a->save();
        }

        ActivityLogService::log('update', 'student_grade', $desc, $a);
    }
}
