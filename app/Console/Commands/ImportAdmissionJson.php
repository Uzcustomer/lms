<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\StudentAdmissionData;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ImportAdmissionJson extends Command
{
    protected $signature = 'import:admission-json
        {path : Talabaning ariza papkalari joylashgan asosiy katalog (relative storage/app ga yoki absolute)}
        {--dry-run : DB ga yozmaslik va fayl ko\'chirmaslik, faqat moslashtirish va hisobot}
        {--update : Mavjud yozuvlarni yangilash (default — o\'tkazib yuborish)}
        {--report=admission-import-report.csv : storage/app/ ichidagi hisobot fayl nomi}';

    protected $description = 'Apply formidan kelgan JSON fayllarni student_admission_data ga import va fayllarni storage/app/public/admission/ ga ko\'chirish';

    /**
     * JSON `fields` → student_admission_data column mapping (1:1 va renamed).
     */
    private array $fieldMap = [
        // 1:1
        'familya' => 'familya',
        'ism' => 'ism',
        'otasining_ismi' => 'otasining_ismi',
        'jshshir' => 'jshshir',
        'jinsi' => 'jinsi',
        'tel1' => 'tel1',
        'tel2' => 'tel2',
        'email' => 'email',
        'millat' => 'millat',
        'millat_other' => 'millat_other',
        'tugilgan_davlat' => 'tugilgan_davlat',
        'tugilgan_viloyat' => 'tugilgan_viloyat',
        'tugulgan_tuman' => 'tugulgan_tuman',
        'tugilgan_viloyat_text' => 'tugilgan_viloyat_text',
        'tugilgan_tuman_text' => 'tugilgan_tuman_text',
        'passport_seriya' => 'passport_seriya',
        'passport_raqam' => 'passport_raqam',
        'passport_joy' => 'passport_joy',
        'oliy_malumot' => 'oliy_malumot',
        'prev_otm_nomi' => 'prev_otm_nomi',
        'otm_nomi' => 'otm_nomi',
        'talim_turi' => 'talim_turi',
        'talim_shakli' => 'talim_shakli',
        'mutaxassislik' => 'mutaxassislik',
        'abituriyent_id' => 'abituriyent_id',
        'imtihon_alifbosi' => 'imtihon_alifbosi',
        'toplagan_ball' => 'toplagan_ball',
        'tolov_shakli' => 'tolov_shakli',
        'talim_viloyat' => 'talim_viloyat',
        'talim_tuman' => 'talim_tuman',
        'talim_viloyat_text' => 'talim_viloyat_text',
        'talim_tuman_text' => 'talim_tuman_text',
        'muassasa_nomi' => 'muassasa_nomi',
        'muassasa_turi' => 'muassasa_turi',
        'hujjat_seriya' => 'hujjat_seriya',
        'ortalacha_ball' => 'ortalacha_ball',
        'milliy_sertifikat' => 'milliy_sertifikat',
        'sertifikat_turi' => 'sertifikat_turi',
        'sertifikat_ball' => 'sertifikat_ball',
        'yashash_viloyat' => 'yashash_viloyat',
        'yashash_tuman' => 'yashash_tuman',
        'yashash_viloyat_text' => 'yashash_viloyat_text',
        'yashash_tuman_text' => 'yashash_tuman_text',
        'doimiy_manzil' => 'doimiy_manzil',
        'kenglik' => 'kenglik',
        'uzunlik' => 'uzunlik',
        'ota_familiya' => 'ota_familiya',
        'ota_ismi' => 'ota_ismi',
        'ota_sharifi' => 'ota_sharifi',
        'ota_tel' => 'ota_tel',
        'ota_ish_joyi' => 'ota_ish_joyi',
        'ota_lavozimi' => 'ota_lavozimi',
        'ona_familiya' => 'ona_familiya',
        'ona_ismi' => 'ona_ismi',
        'ona_sharifi' => 'ona_sharifi',
        'ona_tel' => 'ona_tel',
        'ona_ish_joyi' => 'ona_ish_joyi',
        'ona_lavozimi' => 'ona_lavozimi',

        // Renamed
        'talim_tili_dtm' => 'talim_tili',
        'javob_varaqa_raqami' => 'javoblar_varaqasi',
        'talim_country' => 'talim_davlat',
        'yashash_country' => 'yashash_davlat',
        'yil_boshi' => 'oqigan_yili_boshi',
        'yil_tugashi' => 'oqigan_yili_tugashi',
    ];

    private array $booleanFields = [
        'd_kiritilgan', 'd_oila_azosi', 'kam_taminlangan', 'harbiy_qaytgan',
        'nafaqa_oluvchi', 'nogironligi', 'yetim_talaba',
        'davlat_mukofoti', 'kokrak_nishoni', 'prezident_stip', 'davlat_stip',
        'xalqaro_stip', 'resp_sport', 'xal_sport', 'resp_fan_olimp',
        'xal_fan_olimp', 'boshqa_yutuq',
    ];

    private array $stringPassthroughFields = [
        'd_kiritilgan_turi', 'd_oila_turi',
        'nogiron_guruh', 'nogiron_toifa', 'nogiron_toifa_boshqa',
        'yetim_turi',
        'davlat_mukofoti_desc', 'kokrak_nishoni_desc',
        'prezident_stip_desc', 'davlat_stip_desc', 'xalqaro_stip_desc',
        'resp_sport_desc', 'xal_sport_desc',
        'resp_fan_olimp_desc', 'xal_fan_olimp_desc',
        'boshqa_yutuq_desc',
        'iqtidori_boshqa', 'sport_boshqa', 'chet_til_boshqa',
    ];

    private array $jsonArrayFields = ['iqtidori', 'sport_qobiliyat', 'chet_tillari'];

    /**
     * Pre-loaded student name index: normalized full_name → [student_id, ...]
     */
    private array $studentsByNorm = [];
    private array $studentsBySortedNorm = [];

    public function handle(): int
    {
        $rawPath = $this->argument('path');
        $dir = $this->resolvePath($rawPath);
        if (!is_dir($dir)) {
            $this->error("Katalog topilmadi: {$dir}");
            return self::FAILURE;
        }

        $this->info("📁 Asosiy katalog: {$dir}");
        $this->info('🔍 JSON fayllarni qidirish (recursive, .bak chiqarib tashlanadi)...');

        $jsonFiles = collect(File::allFiles($dir))
            ->filter(function ($f) {
                $name = $f->getFilename();
                return str_ends_with(strtolower($name), '.json')
                    && !str_ends_with(strtolower($name), '.json.bak');
            })
            ->values();

        if ($jsonFiles->isEmpty()) {
            $this->error("JSON fayl topilmadi: {$dir}");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updateExisting = (bool) $this->option('update');
        $reportPath = storage_path('app/' . $this->option('report'));
        $report = fopen($reportPath, 'w');
        fputcsv($report, ['file', 'application_number', 'applicant', 'status', 'student_id', 'files_copied', 'detail']);

        $this->info("📥 {$jsonFiles->count()} ta JSON fayl topildi.");
        $this->info($dryRun ? '🧪 DRY-RUN — DB ga yozilmaydi, fayl ko\'chirilmaydi' : '💾 LIVE — DB ga yoziladi, fayllar ko\'chiriladi');

        $this->info('👥 Talaba indeksi tuzilmoqda (students.full_name)...');
        $this->buildStudentIndex();
        $this->info("   {$this->indexSize()} ta talaba indekslandi.");

        $existingColumns = collect(Schema::getColumnListing('student_admission_data'))->flip();
        $stats = [
            'matched' => 0, 'inserted' => 0, 'updated' => 0,
            'skipped' => 0, 'unmatched' => 0, 'errors' => 0,
            'files_copied' => 0, 'files_missing' => 0,
        ];

        $bar = $this->output->createProgressBar($jsonFiles->count());
        $bar->start();

        foreach ($jsonFiles as $file) {
            $bar->advance();
            $filename = $file->getFilename();
            $jsonPath = $file->getPathname();

            try {
                $json = json_decode(File::get($jsonPath), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $stats['errors']++;
                fputcsv($report, [$filename, '', '', 'json-error', '', 0, $e->getMessage()]);
                continue;
            }

            $appNum = (string)($json['application_number'] ?? '');
            $applicant = $json['applicant']['full'] ?? '';
            $fields = $json['fields'] ?? [];
            if (!is_array($fields) || empty($fields)) {
                $stats['errors']++;
                fputcsv($report, [$filename, $appNum, $applicant, 'no-fields', '', 0, '']);
                continue;
            }

            $student = $this->findStudent($json['applicant'] ?? [], $fields);
            if (!$student) {
                $stats['unmatched']++;
                fputcsv($report, [
                    $filename, $appNum, $applicant, 'unmatched', '', 0,
                    'full=' . $applicant,
                ]);
                continue;
            }
            $stats['matched']++;

            $existing = StudentAdmissionData::where('student_id', $student->id)->first();
            if ($existing && !$updateExisting) {
                $stats['skipped']++;
                fputcsv($report, [$filename, $appNum, $applicant, 'skipped-exists', $student->id, 0, '']);
                continue;
            }

            $payload = $this->buildPayload($fields, $existingColumns);

            if ($existingColumns->has('application_number') && $appNum !== '') {
                $payload['application_number'] = $appNum;
            }
            if ($existingColumns->has('submitted_at') && !empty($json['submitted_at'])) {
                try {
                    $payload['submitted_at'] = Carbon::parse($json['submitted_at']);
                } catch (\Throwable) {
                    // skip
                }
            }
            if ($existingColumns->has('full_name')) {
                $full = trim((string)($json['applicant']['full'] ?? ''));
                if ($full === '') {
                    $full = trim(
                        trim((string)($fields['familya'] ?? '')) . ' ' .
                        trim((string)($fields['ism'] ?? '')) . ' ' .
                        trim((string)($fields['otasining_ismi'] ?? ''))
                    );
                }
                $full = preg_replace('/\s+/u', ' ', $full);
                if ($full !== '') {
                    $payload['full_name'] = $full;
                }
            }

            $storageFullName = (string) ($payload['full_name'] ?? $applicant ?: ('student-' . $student->id));
            $storageFolder = $this->safeFolderName($storageFullName);

            $filesCopied = 0;
            if (!$dryRun && $existingColumns->has('files') && !empty($json['files']) && is_array($json['files'])) {
                $copyResult = $this->copyApplicationFiles($json['files'], $jsonPath, $storageFolder);
                $payload['files'] = $copyResult['files'];
                $filesCopied = $copyResult['copied'];
                $stats['files_copied'] += $copyResult['copied'];
                $stats['files_missing'] += $copyResult['missing'];
            }

            if (!$dryRun) {
                $this->storeAdmissionSnapshot($json, $storageFolder, $storageFullName);
            }

            if ($dryRun) {
                fputcsv($report, [$filename, $appNum, $applicant, 'matched-dry', $student->id, 0, '']);
                continue;
            }

            try {
                DB::transaction(function () use ($existing, $student, $payload, &$stats, $report, $filename, $appNum, $applicant, $filesCopied) {
                    if ($existing) {
                        $existing->fill($payload)->save();
                        $stats['updated']++;
                        fputcsv($report, [$filename, $appNum, $applicant, 'updated', $student->id, $filesCopied, '']);
                    } else {
                        StudentAdmissionData::create(array_merge($payload, ['student_id' => $student->id]));
                        $stats['inserted']++;
                        fputcsv($report, [$filename, $appNum, $applicant, 'inserted', $student->id, $filesCopied, '']);
                    }
                });
            } catch (\Throwable $e) {
                $stats['errors']++;
                fputcsv($report, [$filename, $appNum, $applicant, 'db-error', $student->id, $filesCopied, $e->getMessage()]);
                Log::warning('ImportAdmissionJson DB error', ['file' => $filename, 'error' => $e->getMessage()]);
            }
        }

        $bar->finish();
        fclose($report);
        $this->newLine(2);
        $this->table(
            ['Topildi', 'Qo\'shildi', 'Yangilandi', 'O\'tkazib yuborildi', 'Topilmadi', 'Xato', 'Fayl ko\'chirildi', 'Fayl yo\'q'],
            [[
                $stats['matched'], $stats['inserted'], $stats['updated'],
                $stats['skipped'], $stats['unmatched'], $stats['errors'],
                $stats['files_copied'], $stats['files_missing'],
            ]],
        );
        $this->info("📊 Hisobot: {$reportPath}");
        return self::SUCCESS;
    }

    private function resolvePath(string $raw): string
    {
        if (str_starts_with($raw, '/')) return $raw;

        // Avval loyiha ildiziga nisbatan ko'ramiz — foydalanuvchi `storage/app/X` yozsa ham
        // yoki `cwd` dan boshqa joydan chaqirsa ham ishlasin.
        $fromBase = base_path(ltrim($raw, '/'));
        if (is_dir($fromBase)) return $fromBase;

        // Aks holda — storage/app/ ga nisbatan
        return storage_path('app/' . ltrim($raw, '/'));
    }

    /**
     * Barcha talabalarni 1 marta yuklab, normalize qilingan kalit bo'yicha indeks tuzamiz.
     * Bu har bir JSON uchun alohida SQL so'rovdan ko'ra ancha tezroq.
     */
    private function buildStudentIndex(): void
    {
        Student::query()
            ->select(['id', 'full_name'])
            ->whereNotNull('full_name')
            ->orderBy('id')
            ->chunk(2000, function ($students) {
                foreach ($students as $s) {
                    $norm = $this->norm($s->full_name);
                    if ($norm === '') continue;
                    $this->studentsByNorm[$norm][] = $s->id;

                    $sortedNorm = $this->normSorted($s->full_name);
                    $this->studentsBySortedNorm[$sortedNorm][] = $s->id;
                }
            });
    }

    private function indexSize(): int
    {
        $unique = [];
        foreach ($this->studentsByNorm as $ids) {
            foreach ($ids as $id) $unique[$id] = true;
        }
        return count($unique);
    }

    /**
     * applicant.full bo'yicha qidirish. So'zlar tartibi farq qilsa, sorted match.
     */
    private function findStudent(array $applicant, array $fields): ?Student
    {
        $full = trim((string)($applicant['full'] ?? ''));
        if ($full === '') {
            $full = trim(
                (string)($fields['familya'] ?? '') . ' ' .
                (string)($fields['ism'] ?? '') . ' ' .
                (string)($fields['otasining_ismi'] ?? '')
            );
        }
        if ($full === '') return null;

        $norm = $this->norm($full);
        if (isset($this->studentsByNorm[$norm]) && count($this->studentsByNorm[$norm]) === 1) {
            return Student::find($this->studentsByNorm[$norm][0]);
        }

        $sortedNorm = $this->normSorted($full);
        if (isset($this->studentsBySortedNorm[$sortedNorm]) && count($this->studentsBySortedNorm[$sortedNorm]) === 1) {
            return Student::find($this->studentsBySortedNorm[$sortedNorm][0]);
        }

        return null;
    }

    private function buildPayload(array $fields, $existingColumns): array
    {
        $out = [];
        foreach ($this->fieldMap as $jsonKey => $col) {
            if (!array_key_exists($jsonKey, $fields)) continue;
            if (!$existingColumns->has($col)) continue;
            $val = $fields[$jsonKey];
            if (is_array($val)) $val = implode(', ', $val);
            $val = is_string($val) ? trim($val) : $val;
            if ($val === '' || $val === null) continue;
            $out[$col] = $val;
        }

        foreach (['tugilgan_sana' => 'tugilgan_sana', 'passport_sana' => 'passport_sana'] as $jsonKey => $col) {
            if (isset($fields[$jsonKey]) && $existingColumns->has($col)) {
                $d = $this->parseDate($fields[$jsonKey]);
                if ($d) $out[$col] = $d;
            }
        }

        foreach ($this->booleanFields as $col) {
            if (array_key_exists($col, $fields) && $existingColumns->has($col)) {
                $out[$col] = $this->parseBool($fields[$col]);
            }
        }

        foreach ($this->stringPassthroughFields as $col) {
            if (array_key_exists($col, $fields) && $existingColumns->has($col)) {
                $v = is_string($fields[$col]) ? trim($fields[$col]) : $fields[$col];
                if ($v !== '' && $v !== null) $out[$col] = $v;
            }
        }

        foreach ($this->jsonArrayFields as $col) {
            if (!array_key_exists($col, $fields) || !$existingColumns->has($col)) continue;
            $raw = $fields[$col];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $raw = $decoded;
            }
            if (is_array($raw)) {
                $clean = array_values(array_filter(array_map('trim', $raw), fn ($v) => $v !== ''));
                if (!empty($clean)) $out[$col] = $clean;
            }
        }

        return $out;
    }

    /**
     * JSON files[]={'category' => [{'saved_path', 'original_name', ...}]} dan
     * fayllarni admission disk'iga ko'chiramiz (config/filesystems.php → admission).
     * DB ga disk root'ga nisbatan yo'l saqlanadi: "{full_name}/{category}/{filename}".
     */
    private function copyApplicationFiles(array $filesByCategory, string $jsonPath, string $studentFolderName): array
    {
        $studentFolder = dirname($jsonPath);
        $diskRoot = rtrim(config('filesystems.disks.admission.root', storage_path('app/public/admission')), '/');
        $out = [];
        $copied = 0;
        $missing = 0;

        foreach ($filesByCategory as $category => $files) {
            if (!is_array($files)) continue;
            $cleanCategory = preg_replace('/[^a-z0-9_-]/i', '', $category);
            if ($cleanCategory === '') continue;

            $categoryOut = [];
            foreach ($files as $f) {
                if (!is_array($f) || empty($f['saved_path'])) continue;

                $filename = basename($f['saved_path']);
                $srcPath = $this->resolveSourceFile($studentFolder, $category, $filename, $f['saved_path']);

                if ($srcPath === null) {
                    $missing++;
                    Log::warning('ImportAdmissionJson: source file not found', [
                        'student_folder' => $studentFolderName,
                        'category' => $category,
                        'filename' => $filename,
                        'saved_path' => $f['saved_path'],
                    ]);
                    continue;
                }

                $destDir = "{$diskRoot}/{$studentFolderName}/{$cleanCategory}";
                if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                    Log::warning('ImportAdmissionJson: failed to create dest dir', ['dir' => $destDir]);
                    continue;
                }
                $destPath = $destDir . '/' . $filename;

                if (!@copy($srcPath, $destPath)) {
                    Log::warning('ImportAdmissionJson: copy failed', [
                        'from' => $srcPath, 'to' => $destPath,
                    ]);
                    continue;
                }

                @chmod($destPath, 0644);
                $copied++;

                $categoryOut[] = [
                    'path' => "{$studentFolderName}/{$cleanCategory}/{$filename}",
                    'original_name' => $f['original_name'] ?? $filename,
                    'mime' => $f['mime'] ?? null,
                    'size' => $f['size'] ?? @filesize($destPath),
                ];
            }

            if (!empty($categoryOut)) {
                $out[$cleanCategory] = $categoryOut;
            }
        }

        return ['files' => $out, 'copied' => $copied, 'missing' => $missing];
    }

    /**
     * Import qilingan JSON'ning o'zini ham student papkasiga saqlab qo'yamiz:
     *   {diskRoot}/{FULL_NAME_FOLDER}/{FULL_NAME}.json
     */
    private function storeAdmissionSnapshot(array $json, string $studentFolderName, string $fullName): void
    {
        $diskRoot = rtrim(config('filesystems.disks.admission.root', storage_path('app/public/admission')), '/');
        $studentDir = "{$diskRoot}/{$studentFolderName}";

        if (!is_dir($studentDir) && !mkdir($studentDir, 0755, true) && !is_dir($studentDir)) {
            Log::warning('ImportAdmissionJson: failed to create student snapshot dir', ['dir' => $studentDir]);
            return;
        }

        $safeName = $this->safeFileName($fullName);
        $snapshotPath = "{$studentDir}/{$safeName}.json";
        $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            Log::warning('ImportAdmissionJson: failed to encode snapshot json', ['student_folder' => $studentFolderName]);
            return;
        }

        if (@file_put_contents($snapshotPath, $encoded) === false) {
            Log::warning('ImportAdmissionJson: failed to write snapshot file', ['path' => $snapshotPath]);
            return;
        }

        @chmod($snapshotPath, 0644);
    }

    /**
     * Manba fayl yo'lini bir nechta variantda qidirish (papka nomi farqli bo'lishi mumkin).
     */
    private function resolveSourceFile(string $studentFolder, string $category, string $filename, string $savedPath): ?string
    {
        $candidates = [
            $studentFolder . '/' . $category . '/' . $filename,
            $studentFolder . '/' . $category . '_pdf/' . $filename,
            $studentFolder . '/' . preg_replace('/_pdf$/', '', $category) . '/' . $filename,
            // JSON saved_path ga to'g'ridan-to'g'ri urinish (asosiy katalogga nisbatan)
            dirname($studentFolder) . '/' . $savedPath,
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) return $path;
        }
        return null;
    }

    private function parseDate(?string $raw): ?string
    {
        if (!$raw) return null;
        $raw = trim($raw);
        foreach (['d.m.Y', 'Y-m-d', 'd/m/Y', 'd-m-Y'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $raw)->format('Y-m-d');
            } catch (\Throwable) {
                // try next
            }
        }
        return null;
    }

    private function parseBool($v): bool
    {
        if (is_bool($v)) return $v;
        if (is_int($v)) return $v !== 0;
        if (is_string($v)) {
            $v = strtolower(trim($v));
            return in_array($v, ['1', 'true', 'yes', 'ha', 'on'], true);
        }
        return false;
    }

    private function safeFolderName(string $value): string
    {
        return $this->safeFileName($value);
    }

    private function safeFileName(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'student_admission_data';
        }

        $value = preg_replace('/\s+/u', '_', $value);
        $value = preg_replace('/[^\pL\pN_\-]+/u', '', $value);

        return $value !== '' ? $value : 'student_admission_data';
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(['ʻ', 'ʼ', '`', "'", '‘', '’', "'"], '', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return $s;
    }

    private function normSorted(string $s): string
    {
        $words = explode(' ', $this->norm($s));
        $words = array_filter($words, fn ($w) => $w !== '');
        sort($words);
        return implode(' ', $words);
    }
}
