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
use Illuminate\Support\Str;

class ImportAdmissionJson extends Command
{
    protected $signature = 'import:admission-json
        {path : Directory containing the apply form *.json files (relative to storage/app or absolute)}
        {--dry-run : Parse and match but don\'t write to DB}
        {--update : Update rows that already exist (otherwise they are skipped)}
        {--report=admission-import-report.csv : Filename written under storage/app/ with per-file outcome}';

    protected $description = 'Talabaning apply formidan kelgan JSON fayllarini student_admission_data jadvaliga yuklash';

    /**
     * Map from JSON `fields` key → student_admission_data column.
     * Keys that already match the column name 1:1 are listed only once.
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
        'tugilgan_davlat' => 'tugilgan_davlat',
        'tugilgan_viloyat' => 'tugilgan_viloyat',
        'tugulgan_tuman' => 'tugulgan_tuman',
        'passport_seriya' => 'passport_seriya',
        'passport_raqam' => 'passport_raqam',
        'passport_joy' => 'passport_joy',
        'oliy_malumot' => 'oliy_malumot',
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
        'muassasa_nomi' => 'muassasa_nomi',
        'hujjat_seriya' => 'hujjat_seriya',
        'ortalacha_ball' => 'ortalacha_ball',
        'milliy_sertifikat' => 'milliy_sertifikat',
        'sertifikat_turi' => 'sertifikat_turi',
        'sertifikat_ball' => 'sertifikat_ball',
        'yashash_viloyat' => 'yashash_viloyat',
        'yashash_tuman' => 'yashash_tuman',
        'doimiy_manzil' => 'doimiy_manzil',
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

    public function handle(): int
    {
        $rawPath = $this->argument('path');
        $dir = $this->resolvePath($rawPath);
        if (!is_dir($dir)) {
            $this->error("Katalog topilmadi: {$dir}");
            return self::FAILURE;
        }

        $files = collect(File::files($dir))
            ->filter(fn ($f) => str_ends_with(strtolower($f->getFilename()), '.json'))
            ->values();

        if ($files->isEmpty()) {
            $this->error("JSON fayl topilmadi: {$dir}");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updateExisting = (bool) $this->option('update');
        $reportPath = storage_path('app/' . $this->option('report'));
        $report = fopen($reportPath, 'w');
        fputcsv($report, ['file', 'application_number', 'applicant', 'status', 'student_id', 'detail']);

        // Cache list of real columns to handle production schemas that lack
        // some of the newer fields.
        $existingColumns = collect(Schema::getColumnListing('student_admission_data'))->flip();

        $total = $files->count();
        $stats = ['matched' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'unmatched' => 0, 'errors' => 0];

        $this->info("📂 {$total} ta fayl topildi: {$dir}");
        $this->info($dryRun ? '🧪 DRY-RUN — yozish o\'chirilgan' : '💾 LIVE — DB ga yoziladi');
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($files as $file) {
            $bar->advance();
            $filename = $file->getFilename();
            try {
                $json = json_decode(File::get($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $stats['errors']++;
                fputcsv($report, [$filename, '', '', 'json-error', '', $e->getMessage()]);
                continue;
            }

            $appNum = $json['application_number'] ?? '';
            $applicant = $json['applicant']['full'] ?? '';
            $fields = $json['fields'] ?? [];
            if (!is_array($fields) || empty($fields)) {
                $stats['errors']++;
                fputcsv($report, [$filename, $appNum, $applicant, 'no-fields', '', '']);
                continue;
            }

            $student = $this->findStudent($fields);
            if (!$student) {
                $stats['unmatched']++;
                fputcsv($report, [
                    $filename, $appNum, $applicant, 'unmatched', '',
                    'name=' . trim(($fields['familya'] ?? '') . ' ' . ($fields['ism'] ?? ''))
                    . ' birth=' . ($fields['tugilgan_sana'] ?? ''),
                ]);
                continue;
            }
            $stats['matched']++;

            $payload = $this->buildPayload($fields, $existingColumns);

            if ($dryRun) {
                fputcsv($report, [$filename, $appNum, $applicant, 'matched-dry', $student->id, '']);
                continue;
            }

            $existing = StudentAdmissionData::where('student_id', $student->id)->first();
            if ($existing && !$updateExisting) {
                $stats['skipped']++;
                fputcsv($report, [$filename, $appNum, $applicant, 'skipped-exists', $student->id, '']);
                continue;
            }

            try {
                DB::transaction(function () use ($existing, $student, $payload, &$stats, $report, $filename, $appNum, $applicant) {
                    if ($existing) {
                        $existing->fill($payload)->save();
                        $stats['updated']++;
                        fputcsv($report, [$filename, $appNum, $applicant, 'updated', $student->id, '']);
                    } else {
                        StudentAdmissionData::create(array_merge($payload, ['student_id' => $student->id]));
                        $stats['inserted']++;
                        fputcsv($report, [$filename, $appNum, $applicant, 'inserted', $student->id, '']);
                    }
                });
            } catch (\Throwable $e) {
                $stats['errors']++;
                fputcsv($report, [$filename, $appNum, $applicant, 'db-error', $student->id, $e->getMessage()]);
                Log::warning("ImportAdmissionJson DB error", ['file' => $filename, 'error' => $e->getMessage()]);
            }
        }

        $bar->finish();
        fclose($report);
        $this->newLine(2);
        $this->table(
            ['Topildi', 'Qo\'shildi', 'Yangilandi', 'O\'tkazib yuborildi', 'Topilmadi', 'Xato'],
            [[
                $stats['matched'], $stats['inserted'], $stats['updated'],
                $stats['skipped'], $stats['unmatched'], $stats['errors'],
            ]],
        );
        $this->info("📊 Hisobot: {$reportPath}");
        return self::SUCCESS;
    }

    private function resolvePath(string $raw): string
    {
        if (str_starts_with($raw, '/')) return $raw;
        return storage_path('app/' . ltrim($raw, '/'));
    }

    /**
     * Match priority:
     *   1) name parts (familya/ism/otasining_ismi) + birth_date
     *   2) full_name (normalized) + birth_date
     *   3) full_name only (last-resort, may match more than one — skipped)
     */
    private function findStudent(array $fields): ?Student
    {
        $birth = $this->parseDate($fields['tugilgan_sana'] ?? null);
        $familya = $this->norm($fields['familya'] ?? '');
        $ism = $this->norm($fields['ism'] ?? '');
        $otasi = $this->norm($fields['otasining_ismi'] ?? '');

        if ($birth && $familya && $ism) {
            // Try the structured columns first.
            $q = Student::query()
                ->whereDate('birth_date', $birth)
                ->whereRaw('LOWER(second_name) = ?', [$familya])
                ->whereRaw('LOWER(first_name) = ?', [$ism]);
            if ($otasi) {
                $q->where(function ($w) use ($otasi) {
                    $w->whereRaw('LOWER(third_name) = ?', [$otasi])
                        ->orWhereRaw('LOWER(third_name) LIKE ?', [$otasi . '%']);
                });
            }
            $hit = $q->first();
            if ($hit) return $hit;

            // Fallback: full_name contains all parts.
            $haystack = $familya . ' ' . $ism;
            $candidates = Student::query()
                ->whereDate('birth_date', $birth)
                ->whereRaw('LOWER(full_name) LIKE ?', ['%' . $haystack . '%'])
                ->get();
            if ($candidates->count() === 1) return $candidates->first();
            if ($candidates->count() > 1 && $otasi !== '') {
                $narrowed = $candidates->filter(
                    fn ($c) => str_contains(Str::lower($c->full_name ?? ''), $otasi),
                );
                if ($narrowed->count() === 1) return $narrowed->first();
            }
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

        // Date columns need normalization.
        foreach (['tugilgan_sana' => 'tugilgan_sana', 'passport_sana' => 'passport_sana'] as $jsonKey => $col) {
            if (isset($fields[$jsonKey]) && $existingColumns->has($col)) {
                $d = $this->parseDate($fields[$jsonKey]);
                if ($d) $out[$col] = $d;
            }
        }

        // Foreign-language certificate: prefer explicit fields, fall back to
        // joining the chet_tillari array.
        if ($existingColumns->has('chet_til_sertifikat')) {
            $langs = $fields['chet_tillari'] ?? null;
            if (is_array($langs) && $langs) {
                $out['chet_til_sertifikat'] = implode(', ', array_filter($langs));
            }
        }

        return $out;
    }

    private function parseDate(?string $raw): ?string
    {
        if (!$raw) return null;
        $raw = trim($raw);
        foreach (['d.m.Y', 'Y-m-d', 'd/m/Y', 'd-m-Y'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $raw)->format('Y-m-d');
            } catch (\Throwable) {
                // try next format
            }
        }
        return null;
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        // Common ASCII vs. Cyrillic O/apostrophe noise from DTM data.
        $s = str_replace(['ʻ', 'ʼ', '`', "'", '‘', '’'], '', $s);
        return $s;
    }
}
