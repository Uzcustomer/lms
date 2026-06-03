<?php

namespace App\Console\Commands;

use App\Models\ComputerAssignment;
use App\Models\ExamSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2-/3-urinish (resit) bo'yicha noto'g'ri yaratilgan ComputerAssignment
 * qatorlarini tozalaydi. Avvalgi Word generatsiyalari per-student override
 * va student_grades eligibility filtridan o'tmagan paytda butun guruhni
 * yozib qo'ygan edi — natijada Moodle proctor sahifasi va /tv/jadval ekrani
 * haqiqiy retaker'lardan ko'p talabani ko'rsatardi.
 *
 * Bu komanda har (schedule_id, yn_type, attempt) bo'yicha generateYnOldiWord
 * ichidagi filter mantiqini qaytadan ishlatib, noeligible/override qilingan
 * talabalarning "scheduled" qatorlarini o'chiradi. status != scheduled
 * (in_progress, finished, abandoned) tegmaydi — tarix saqlanadi.
 */
class CleanupResitComputerAssignments extends Command
{
    protected $signature = 'exam:cleanup-resit-assignments
        {--dry-run : Faqat ko\'rsat, o\'chirma}
        {--date= : Faqat shu kunga taalluqli (Y-m-d). Bo\'sh bo\'lsa hammasiga}';

    protected $description = '2-/3-urinish ComputerAssignment qatorlarini retake eligibility bo\'yicha tozalash';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $date = $this->option('date');

        $this->line('=== Resit ComputerAssignment cleanup ===');
        $this->line($dryRun ? 'DRY-RUN rejimi (hech narsa o\'chirilmaydi)' : 'JONLI rejim (qatorlar o\'chiriladi)');
        if ($date) $this->line("Faqat sana: {$date}");

        $hasAttemptCol = Schema::hasColumn('student_grades', 'attempt');

        // 2-/3-urinish ga tegishli scheduled qatorlarni (schedule_id, yn_type, attempt)
        // bo'yicha guruhlaymiz.
        $bucketsQuery = ComputerAssignment::query()
            ->where('status', ComputerAssignment::STATUS_SCHEDULED)
            ->where('attempt', '>=', 2)
            ->when($date, fn($q) => $q->whereDate('planned_start', $date))
            ->select('exam_schedule_id', 'yn_type', 'attempt')
            ->distinct();

        $buckets = $bucketsQuery->get();
        $this->line("Tekshiriladigan (schedule, yn, attempt) buckets: " . $buckets->count());

        $totalDeleted = 0;
        $bucketsTouched = 0;

        foreach ($buckets as $b) {
            $scheduleId = (int) $b->exam_schedule_id;
            $ynType = (string) $b->yn_type;
            $attempt = (int) $b->attempt;

            $schedule = ExamSchedule::find($scheduleId);
            if (!$schedule) {
                // Schedule o'chirilgan — buni allaqachon cascade o'chirgan bo'lardi,
                // skip.
                continue;
            }

            // Eligible retaker hemis_id'lar (generateYnOldiWord bilan bir xil
            // mantiq) — student_grades'da yiqilganlar yoki attempt >= 2.
            $eligibleHemisIds = DB::table('student_grades as sg')
                ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->where('st.group_id', $schedule->group_hemis_id)
                ->where('st.student_status_code', 11)
                ->where('sg.subject_id', $schedule->subject_id)
                ->where('sg.semester_code', $schedule->semester_code)
                ->whereIn('sg.training_type_code', [101, 102])
                ->whereNull('sg.deleted_at')
                ->when($hasAttemptCol, function ($q) {
                    $q->where(function ($w) {
                        $w->where('sg.attempt', '>=', 2)
                            ->orWhere(function ($x) {
                                $x->where(function ($y) {
                                    $y->where('sg.attempt', 1)->orWhereNull('sg.attempt');
                                })->whereRaw('COALESCE(sg.retake_grade, sg.grade) < 60');
                            });
                    });
                }, function ($q) {
                    $q->whereRaw('COALESCE(sg.retake_grade, sg.grade) < 60');
                })
                ->distinct()
                ->pluck('sg.student_hemis_id')
                ->map(fn($v) => (string) $v)
                ->all();

            // Per-student ExamSchedule override'lar — guruh-level bucket'idan
            // chiqarib tashlash kerak (ular alohida ExamSchedule qatorda).
            // Bu bucket o'zi per-student qatorga taalluqli bo'lsa, schedule'ning
            // student_hemis_id'si bor — uni eligible'da saqlaymiz.
            if (empty($schedule->student_hemis_id)) {
                $overriddenHemisIds = DB::table('exam_schedules')
                    ->where('group_hemis_id', $schedule->group_hemis_id)
                    ->where('subject_id', $schedule->subject_id)
                    ->where('semester_code', $schedule->semester_code)
                    ->whereNotNull('student_hemis_id')
                    ->pluck('student_hemis_id')
                    ->map(fn($v) => (string) $v)
                    ->all();
                if (!empty($overriddenHemisIds)) {
                    $eligibleHemisIds = array_values(array_diff($eligibleHemisIds, $overriddenHemisIds));
                }
            } else {
                // Per-student schedule: faqat shu talaba eligible bo'lishi mumkin.
                $only = (string) $schedule->student_hemis_id;
                $eligibleHemisIds = in_array($only, $eligibleHemisIds, true) ? [$only] : [];
            }

            // Bu (schedule_id, yn_type, attempt) bo'yicha eligible bo'lmagan
            // scheduled qatorlarni topamiz.
            $toDelete = ComputerAssignment::query()
                ->where('exam_schedule_id', $scheduleId)
                ->where('yn_type', $ynType)
                ->where('attempt', $attempt)
                ->where('status', ComputerAssignment::STATUS_SCHEDULED)
                ->when(!empty($eligibleHemisIds), fn($q) => $q->whereNotIn('student_hemis_id', $eligibleHemisIds))
                ->get(['id', 'student_hemis_id', 'computer_number']);

            if ($toDelete->isEmpty()) continue;

            $bucketsTouched++;
            $count = $toDelete->count();
            $totalDeleted += $count;

            $groupName = optional($schedule->group)->name ?? $schedule->group_hemis_id;
            $this->line(sprintf(
                '  schedule=%d (%s · %s · %s · attempt %d) → o\'chiriladi: %d / eligible qoldi: %d',
                $scheduleId, $groupName, $schedule->subject_name ?? '?', $ynType, $attempt,
                $count, count($eligibleHemisIds)
            ));

            if (!$dryRun) {
                ComputerAssignment::whereIn('id', $toDelete->pluck('id')->all())->delete();
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d ta qator %d ta bucket\'dan %s',
            $dryRun ? 'Topildi' : 'O\'chirildi',
            $totalDeleted,
            $bucketsTouched,
            $dryRun ? 'tozalanardi' : 'tozalandi'
        ));

        return self::SUCCESS;
    }
}
