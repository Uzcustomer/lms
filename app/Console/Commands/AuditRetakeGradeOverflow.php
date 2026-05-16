<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditRetakeGradeOverflow extends Command
{
    protected $signature = 'grades:audit-overflow
        {--csv= : CSV faylga eksport qilish (path)}
        {--limit=0 : Konsolga chiqarilayotgan qatorlar soni (0 = hammasi)}
        {--apply : Tuzatishni haqiqatan qo\'llash (default — dry-run)}
        {--force-yn-locked : YN ga yuborilgan yozuvlarni ham tuzatish}
        {--backup= : Tuzatishdan oldin original qiymatlarni CSV ga saqlash}';

    protected $description = 'retake_grade > 100 bo\'lgan buzilgan yozuvlarni topish va (ixtiyoriy) tuzatish';

    public function handle(): int
    {
        $rows = DB::table('student_grades as sg')
            ->leftJoin('students as s', 's.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->where('sg.retake_grade', '>', 100)
            ->orderByDesc('sg.retake_grade')
            ->orderByDesc('sg.lesson_date')
            ->get([
                'sg.id',
                'sg.student_hemis_id',
                's.full_name',
                'sg.subject_id',
                'sg.subject_name',
                'sg.semester_code',
                'sg.lesson_date',
                'sg.lesson_pair_code',
                'sg.reason',
                'sg.status',
                'sg.grade as original_grade',
                'sg.retake_grade',
                'sg.retake_was_sababli',
                'sg.is_yn_locked',
                'sg.retake_graded_at',
                'sg.updated_at',
            ]);

        $total = $rows->count();
        $this->info("Topildi: {$total} ta retake_grade > 100 yozuv");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $forceYnLocked = (bool) $this->option('force-yn-locked');

        $plans = [];        // Tuzatishga tayyor yozuvlar
        $skippedAnomaly = []; // entered > 100 (mantiqsiz)
        $skippedYnLocked = []; // YN qulflangan

        foreach ($rows as $r) {
            // Hozirgi rg = entered / 0.8 (sababli holatda saqlangan).
            // Ya'ni asl kiritilgan baho ≈ rg × 0.8.
            $likelyEntered = round($r->retake_grade * 0.8, 2);
            $currentSababli = $this->checkCurrentSababli($r);
            $proposedCoeff = $currentSababli ? 1.0 : 0.8;
            $proposedRg = round($likelyEntered * $proposedCoeff, 2);

            $entry = [
                'id' => $r->id,
                'student' => $r->full_name ?: $r->student_hemis_id,
                'subject' => mb_strimwidth($r->subject_name ?? '', 0, 28, '…'),
                'date' => $r->lesson_date,
                'pair' => $r->lesson_pair_code,
                'reason' => $r->reason,
                'status' => $r->status,
                'rg' => $r->retake_grade,
                'flag' => $r->retake_was_sababli === null ? '—' : ($r->retake_was_sababli ? 'true' : 'false'),
                'now_sababli' => $currentSababli ? 'true' : 'false',
                'entered≈' => $likelyEntered,
                'proposed_rg' => $proposedRg,
                '_raw' => $r,
                '_currentSababli' => $currentSababli,
            ];

            if ($likelyEntered > 100) {
                $skippedAnomaly[] = $entry;
                continue;
            }

            if (!empty($r->is_yn_locked) && !$forceYnLocked) {
                $skippedYnLocked[] = $entry;
                continue;
            }

            $plans[] = $entry;
        }

        // Asosiy reja jadvali
        $tableHeaders = ['id', 'student', 'subject', 'date', 'pair', 'reason', 'status', 'rg', 'flag', 'now_sababli', 'entered≈', 'proposed_rg'];
        $stripInternal = fn($e) => array_filter($e, fn($_, $k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_BOTH);

        if (!empty($plans)) {
            $this->info("\n=== Tuzatish rejasi (" . count($plans) . " yozuv) ===");
            $this->table($tableHeaders, array_map($stripInternal, $plans));
        }

        if (!empty($skippedAnomaly)) {
            $this->warn("\n⚠ Mantiqsiz yozuvlar (entered > 100, qo'lda tekshirish kerak): " . count($skippedAnomaly));
            $this->table($tableHeaders, array_map($stripInternal, $skippedAnomaly));
        }

        if (!empty($skippedYnLocked)) {
            $this->warn("\n⚠ YN ga yuborilgan yozuvlar (--force-yn-locked bilan kiritish mumkin): " . count($skippedYnLocked));
            $this->table($tableHeaders, array_map($stripInternal, $skippedYnLocked));
        }

        // CSV eksport
        if ($csvPath = $this->option('csv')) {
            $this->writeCsv($csvPath, array_merge($plans, $skippedAnomaly, $skippedYnLocked), $tableHeaders);
            $this->info("CSV yozildi: {$csvPath}");
        }

        // Backup csv (apply rejimida tavsiya etiladi)
        if ($backupPath = $this->option('backup')) {
            $this->writeCsv($backupPath, $plans, $tableHeaders);
            $this->info("Backup yozildi: {$backupPath}");
        }

        if (!$apply) {
            $this->info("\n[DRY-RUN] Hech narsa o'zgartirilmadi. Haqiqiy tuzatish uchun: --apply");
            $this->info("Tavsiya: --apply bilan birga --backup=/tmp/backup-" . date('Ymd-His') . ".csv ham qo'shing.");
            return self::SUCCESS;
        }

        if (empty($plans)) {
            $this->warn("Tuzatishga yozuv yo'q.");
            return self::SUCCESS;
        }

        if (!$this->confirm(count($plans) . " ta yozuv yangilanadi. Davom etamizmi?", false)) {
            $this->warn("Bekor qilindi.");
            return self::SUCCESS;
        }

        $updated = 0;
        DB::transaction(function () use ($plans, &$updated) {
            foreach ($plans as $p) {
                DB::table('student_grades')
                    ->where('id', $p['id'])
                    ->update([
                        'retake_grade' => $p['proposed_rg'],
                        'retake_was_sababli' => $p['_currentSababli'],
                        'updated_at' => now(),
                    ]);
                $updated++;
            }
        });

        $this->info("✓ {$updated} ta yozuv yangilandi.");

        if (!empty($skippedAnomaly)) {
            $this->warn("Eslatma: " . count($skippedAnomaly) . " ta mantiqsiz yozuv tuzatilmadi — qo'lda ko'rib chiqing.");
        }
        if (!empty($skippedYnLocked)) {
            $this->warn("Eslatma: " . count($skippedYnLocked) . " ta YN qulflangan yozuv tuzatilmadi.");
        }

        return self::SUCCESS;
    }

    private function writeCsv(string $path, array $rows, array $headers): void
    {
        $fp = fopen($path, 'w');
        if (!$fp) {
            $this->error("CSV ochib bo'lmadi: {$path}");
            return;
        }
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            $clean = [];
            foreach ($headers as $h) {
                $clean[] = $row[$h] ?? '';
            }
            fputcsv($fp, $clean);
        }
        fclose($fp);
    }

    private function checkCurrentSababli($row): bool
    {
        if ($row->reason !== 'absent') {
            return false;
        }

        $hasLmsExcuse = DB::table('absence_excuses as ae')
            ->join('absence_excuse_makeups as aem', 'aem.absence_excuse_id', '=', 'ae.id')
            ->where('ae.student_hemis_id', $row->student_hemis_id)
            ->where('ae.status', 'approved')
            ->whereDate('ae.start_date', '<=', $row->lesson_date)
            ->whereDate('ae.end_date', '>=', $row->lesson_date)
            ->where('aem.subject_id', $row->subject_id)
            ->exists();

        if ($hasLmsExcuse) {
            return true;
        }

        return DB::table('attendances')
            ->where('student_hemis_id', $row->student_hemis_id)
            ->where('subject_id', $row->subject_id)
            ->whereDate('lesson_date', $row->lesson_date)
            ->where('lesson_pair_code', $row->lesson_pair_code)
            ->where('absent_on', '>', 0)
            ->exists();
    }
}
