<?php

namespace App\Console\Commands;

use App\Models\StudentGroupHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guruh tarixidagi ochiq yozuvlarga semestr va to'lov shaklini to'ldiradi.
 *
 * Migratsiya ham shuni qiladi, lekin u faqat bir marta ishlaydi. Bu buyruq
 * migratsiyadan keyin qo'shilgan yoki o'sha paytda semestri bo'sh bo'lgan
 * talabalar uchun qayta ishga tushiriladi.
 */
class BackfillStudentHistoryFields extends Command
{
    protected $signature = 'students:backfill-history-fields {--dry-run : Faqat ko\'rsatadi, yozmaydi}';

    protected $description = 'Guruh tarixidagi ochiq yozuvlarga semestr va to\'lov shaklini to\'ldiradi';

    public function handle(): int
    {
        if (!Schema::hasTable('student_group_history')) {
            $this->error('student_group_history jadvali yo\'q. Avval migratsiyani ishga tushiring.');

            return self::FAILURE;
        }

        $hasSemester = Schema::hasColumn('student_group_history', 'semester_name');
        $hasPayment = Schema::hasColumn('student_group_history', 'payment_form_name');

        if (!$hasSemester && !$hasPayment) {
            $this->error('semester_name / payment_form_name ustunlari yo\'q. php artisan migrate ni ishga tushiring.');

            return self::FAILURE;
        }

        $this->line('Ustunlar: semester=' . ($hasSemester ? 'bor' : 'yo\'q') . ', payment=' . ($hasPayment ? 'bor' : 'yo\'q'));

        $dryRun = (bool) $this->option('dry-run');
        $filled = 0;
        $skipped = 0;

        StudentGroupHistory::query()
            ->whereNull('ended_at')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$filled, &$skipped, $hasSemester, $hasPayment, $dryRun) {
                $students = DB::table('students')
                    ->whereIn('id', $rows->pluck('student_id')->unique())
                    ->get(['id', 'semester_code', 'semester_name', 'payment_form_code', 'payment_form_name'])
                    ->keyBy('id');

                foreach ($rows as $row) {
                    $student = $students->get($row->student_id);
                    if (!$student) {
                        $skipped++;
                        continue;
                    }

                    $values = [];

                    if ($hasSemester && trim((string) $row->semester_name) === '' && $student->semester_name) {
                        $values['semester_code'] = $student->semester_code;
                        $values['semester_name'] = $student->semester_name;
                    }

                    if ($hasPayment && trim((string) $row->payment_form_name) === '' && $student->payment_form_name) {
                        $values['payment_form_code'] = $student->payment_form_code;
                        $values['payment_form_name'] = $student->payment_form_name;
                    }

                    if (!$values) {
                        $skipped++;
                        continue;
                    }

                    if (!$dryRun) {
                        DB::table('student_group_history')->where('id', $row->id)->update($values);
                    }

                    $filled++;
                }
            });

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "To'ldirildi: {$filled} ta yozuv, o'tkazib yuborildi: {$skipped} ta.");

        // Semestri bo'sh talabalar — bularni HEMIS import to'ldirmagan.
        if ($hasSemester) {
            $noSemester = DB::table('students')
                ->where(function ($query) {
                    $query->whereNull('semester_name')->orWhere('semester_name', '');
                })
                ->count();

            if ($noSemester > 0) {
                $this->warn("Diqqat: {$noSemester} ta talabaning semester_name maydoni bo'sh. "
                    . "Ular uchun tarixda semestr ko'rinmaydi — avval HEMIS importini ishga tushiring.");
            }
        }

        return self::SUCCESS;
    }
}
