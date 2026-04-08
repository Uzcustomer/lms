<?php

namespace App\Console\Commands;

use App\Models\AbsenceExcuse;
use App\Models\AbsenceExcuseMakeup;
use App\Models\Student;
use App\Services\SubjectMatcherService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportAdmissionExcuses extends Command
{
    protected $signature = 'import:admission-excuses {--file=tests/re_apply.csv : CSV fayl yo\'li} {--clean : Avval eski admission arizalarni o\'chirish}';
    protected $description = 'Admission (re_apply) CSV dan sababli arizalarni import qilish';

    private array $sababKeyMap = [
        'r1' => 'kasallik',
        'r3' => 'nikoh_toyi',
        'r4' => 'yaqin_qarindosh',
        'r5' => 'homiladorlik',
        'r6' => 'musobaqa_tadbir',
    ];

    public function handle(): int
    {
        $filePath = base_path($this->option('file'));
        if (!file_exists($filePath)) {
            $this->error("Fayl topilmadi: {$filePath}");
            return self::FAILURE;
        }

        // Eski admission arizalarni o'chirish
        if ($this->option('clean')) {
            $oldIds = AbsenceExcuse::where('file_path', 'LIKE', 'https://admission%')->pluck('id');
            if ($oldIds->isNotEmpty()) {
                $makeupCount = AbsenceExcuseMakeup::whereIn('absence_excuse_id', $oldIds)->delete();
                $excuseCount = AbsenceExcuse::whereIn('id', $oldIds)->delete();
                $this->warn("Eski admission arizalar o'chirildi: {$excuseCount} ta ariza, {$makeupCount} ta makeup");
            } else {
                $this->info("Eski admission arizalar topilmadi.");
            }
        }

        $rows = $this->parseCsv($filePath);
        $this->info("CSV dan " . count($rows) . " ta yozuv o'qildi.");

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            $studentIdNumber = trim($row['student_id_number'] ?? '');

            if (empty($studentIdNumber)) {
                $errors[] = "#{$rowNum}: student_id_number bo'sh";
                continue;
            }

            // Talabani topish
            $student = Student::where('student_id_number', $studentIdNumber)->first();
            if (!$student) {
                $errors[] = "#{$rowNum}: Talaba topilmadi ({$studentIdNumber})";
                $skipped++;
                continue;
            }

            // Sabab mapping
            $sababKey = trim($row['sabab_key'] ?? '');
            $reason = $this->sababKeyMap[$sababKey] ?? null;
            if (!$reason) {
                $errors[] = "#{$rowNum}: Sabab key noma'lum ({$sababKey})";
                $skipped++;
                continue;
            }

            // Sanalar
            $startDate = trim($row['sabab_start_date'] ?? '');
            $endDate = trim($row['sabab_end_date'] ?? '');
            if (!$startDate || !$endDate) {
                $errors[] = "#{$rowNum}: Sanalar bo'sh";
                $skipped++;
                continue;
            }

            // Reviewed by
            $reviewerName = 'Admission import';
            $reviewedBy = null;
            $reviewedByJson = json_decode($row['reviewed_by'] ?? '', true);
            if ($reviewedByJson) {
                $reviewerName = $reviewedByJson['custom_full_name'] ?? 'Admission import';
            }

            // Certificate URL
            $approvedPdfPath = null;
            $certificateJson = json_decode($row['certificate'] ?? '', true);
            if ($certificateJson && !empty($certificateJson['url_pdf'])) {
                $approvedPdfPath = $certificateJson['url_pdf'];
            }

            // File path
            $filPathValue = trim($row['uploaded_file_name_link'] ?? '');
            $fileOriginalName = $filPathValue ? basename($filPathValue) : '';

            // Ariza yaratish
            $excuse = AbsenceExcuse::create([
                'student_id' => $student->id,
                'student_hemis_id' => $student->hemis_id,
                'student_full_name' => $student->full_name,
                'group_name' => $student->group_name,
                'department_name' => $student->department_name,
                'reason' => $reason,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'doc_number' => trim($row['doc_number'] ?? '') ?: null,
                'file_path' => $filPathValue ?: '',
                'file_original_name' => $fileOriginalName,
                'status' => 'approved',
                'reviewed_by' => $reviewedBy,
                'reviewed_by_name' => $reviewerName,
                'reviewed_at' => $certificateJson['created_at'] ?? now(),
                'approved_pdf_path' => $approvedPdfPath,
                'verification_token' => Str::uuid()->toString(),
            ]);

            // Makeups yaratish — subjects JSON dan
            $this->createMakeups($excuse, $student, $row['subjects'] ?? '');

            $created++;
            $this->info("  #{$rowNum}: {$student->full_name} ({$startDate} — {$endDate}) ✓");
        }

        $this->newLine();
        $this->info("TUGADI: {$created} ta yaratildi, {$skipped} ta o'tkazib yuborildi.");
        if (!empty($errors)) {
            $this->warn("Xatolar:");
            foreach ($errors as $e) {
                $this->warn("  {$e}");
            }
        }

        return self::SUCCESS;
    }

    private function createMakeups(AbsenceExcuse $excuse, Student $student, string $subjectsJson): void
    {
        $subjects = json_decode($subjectsJson, true);
        if (!is_array($subjects)) return;

        foreach ($subjects as $subject) {
            $subjectId = $subject['id'] ?? null;
            $subjectName = $subject['name'] ?? '';
            $tests = $subject['tests']['keys'] ?? [];
            $dates = $subject['dates'] ?? [];

            // Assessment type mapping
            $typeMap = [
                'JN' => ['type' => 'jn', 'code' => '100'],
                'ON' => ['type' => 'jn', 'code' => '100'],
                'YN' => ['type' => 'oski', 'code' => '101'],
                'YN_TEST' => ['type' => 'test', 'code' => '102'],
            ];

            foreach ($tests as $testKey) {
                $mapping = $typeMap[$testKey] ?? null;
                if (!$mapping) continue;

                // Sanani aniqlash
                $originalDate = null;
                $makeupDate = null;
                $makeupEndDate = null;

                if ($testKey === 'JN' && isset($dates['jn'])) {
                    if (is_array($dates['jn'])) {
                        $originalDate = $this->parseDate($dates['jn']['start'] ?? '');
                        $makeupEndDate = $this->parseDate($dates['jn']['end'] ?? '');
                        $makeupDate = $originalDate;
                    } else {
                        $originalDate = $this->parseDate($dates['jn']);
                    }
                } elseif ($testKey === 'ON' && isset($dates['on']) && $dates['on']) {
                    $originalDate = $this->parseDate($dates['on']);
                } elseif ($testKey === 'YN' && isset($dates['yn']) && $dates['yn']) {
                    $originalDate = $this->parseDate($dates['yn']);
                } elseif ($testKey === 'YN_TEST' && isset($dates['yn_test']) && $dates['yn_test']) {
                    $originalDate = $this->parseDate($dates['yn_test']);
                }

                if (!$originalDate) continue;

                // Subject ID resolve
                $resolvedSubjectId = $subjectId;
                $match = SubjectMatcherService::resolveSubjectId($subjectName, $subjectId, $student);
                if ($match) {
                    $resolvedSubjectId = $match['subject_id'];
                }

                AbsenceExcuseMakeup::create([
                    'absence_excuse_id' => $excuse->id,
                    'student_id' => $student->id,
                    'subject_name' => $subjectName,
                    'subject_id' => $resolvedSubjectId,
                    'assessment_type' => $mapping['type'],
                    'assessment_type_code' => $mapping['code'],
                    'original_date' => $originalDate,
                    'makeup_date' => $makeupDate,
                    'makeup_end_date' => $makeupEndDate,
                    'jn_submitted' => false,
                    'status' => 'scheduled',
                ]);
            }
        }
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if (!$value) return null;

        // dd.mm.yyyy
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m)) {
            return sprintf('%s-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        // yyyy-mm-dd
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $value)) {
            return $value;
        }

        return null;
    }

    private function parseCsv(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        if (!$handle) return [];

        // BOM o'chirish
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        if (!$headers) return [];

        // Header nomlarini tozalash
        $headers = array_map(fn($h) => trim($h, " \t\n\r\0\x0B\""), $headers);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) !== count($headers)) continue;
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = $data[$i] ?? '';
            }
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }
}
