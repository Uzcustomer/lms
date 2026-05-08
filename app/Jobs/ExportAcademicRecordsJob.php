<?php

namespace App\Jobs;

use App\Models\Department;
use App\Models\Group;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportAcademicRecordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800; // 30 daqiqa

    private array $filters;
    private string $exportKey;

    public function __construct(array $filters, string $exportKey)
    {
        $this->filters = $filters;
        $this->exportKey = $exportKey;
    }

    public function handle(): void
    {
        ini_set('memory_limit', '1024M');

        try {
            $this->updateProgress('Talabalar ro\'yxati tayyorlanmoqda...', 5);

            $studentHemisIds = $this->resolveStudentHemisIds();

            if (empty($studentHemisIds)) {
                $emptyPath = $this->saveBytesToExportDir($this->generateEmptyExcel(), 'Academic_records_empty.xlsx');
                Cache::put($this->exportKey, [
                    'status'     => 'done',
                    'message'    => 'Talaba topilmadi',
                    'percent'    => 100,
                    'file_name'  => 'Academic_records_empty.xlsx',
                    'file_path'  => $emptyPath,
                    'updated_at' => now()->toDateTimeString(),
                ], 1800);
                return;
            }

            $this->updateProgress('Academic records yuklanmoqda...', 20);

            $fileData = $this->generateExcel($studentHemisIds);

            Log::info('[ExportAcademicRecordsJob] Tayyor', [
                'export_key'    => $this->exportKey,
                'file_name'     => $fileData['file_name'],
                'students_count' => count($studentHemisIds),
                'file_size'     => filesize($fileData['file_path']),
            ]);

            Cache::put($this->exportKey, [
                'status'     => 'done',
                'message'    => 'Tayyor',
                'percent'    => 100,
                'file_name'  => $fileData['file_name'],
                'file_path'  => $fileData['file_path'],
                'updated_at' => now()->toDateTimeString(),
            ], 1800);
        } catch (\Throwable $e) {
            Log::error('[ExportAcademicRecordsJob] Xato: ' . $e->getMessage(), [
                'trace' => mb_substr($e->getTraceAsString(), 0, 500),
            ]);
            $this->updateProgress('Xato: ' . mb_substr($e->getMessage(), 0, 120), 0, 'failed');
        }
    }

    private function resolveStudentHemisIds(): array
    {
        $f = $this->filters;

        $q = DB::table('students as s')->whereNotNull('s.curriculum_id');

        if (!empty($f['student_status'])) {
            $q->where('s.student_status_code', $f['student_status']);
        }
        if (!empty($f['student_name'])) {
            $q->where('s.full_name', 'like', '%' . $f['student_name'] . '%');
        }
        if (!empty($f['faculty'])) {
            $faculty = Department::find($f['faculty']);
            if ($faculty) {
                $q->where('s.department_id', $faculty->department_hemis_id);
            }
        }
        if (!empty($f['specialty'])) {
            $q->where('s.specialty_id', $f['specialty']);
        }
        if (!empty($f['level_code'])) {
            $q->where('s.level_code', $f['level_code']);
        }
        if (!empty($f['group'])) {
            $group = Group::find($f['group']);
            if ($group) {
                $q->where('s.group_id', $group->group_hemis_id);
            }
        }
        if (!empty($f['education_type'])) {
            $q->where('s.education_type_code', $f['education_type']);
        }
        if (!empty($f['student_type'])) {
            $q->where('s.student_type_code', $f['student_type']);
        }

        return $q->pluck('s.hemis_id')->map(fn ($v) => (int) $v)->all();
    }

    private function generateExcel(array $studentHemisIds): array
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Academic Records');

        $headers = [
            '#', 'Hemis ID', 'Talaba ID', 'Talaba FISH', 'Curriculum ID',
            "O'quv yili", 'Semestr kodi', 'Semestr nomi',
            'Fan ID', 'Fan nomi', 'Xodim ID', 'Xodim FISH',
            "O'quv soati", 'Kredit', 'Ball', 'Baho',
            'Kredit yopildi', 'Qayta o\'qish',
            'HEMIS yaratilgan', 'HEMIS yangilangan',
        ];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);

        $row = 2;
        $serial = 0;
        $totalChunks = max(1, (int) ceil(count($studentHemisIds) / 500));
        $processedChunks = 0;

        foreach (array_chunk($studentHemisIds, 500) as $chunk) {
            DB::table('academic_records')
                ->whereIn('student_id', $chunk)
                ->orderBy('student_id')
                ->orderBy('semester_id')
                ->orderBy('subject_name')
                ->select(
                    'hemis_id', 'student_id', 'student_name', 'curriculum_id',
                    'education_year', 'semester_id', 'semester_name',
                    'subject_id', 'subject_name', 'employee_id', 'employee_name',
                    'total_acload', 'credit', 'total_point', 'grade',
                    'finish_credit_status', 'retraining_status',
                    'hemis_created_at', 'hemis_updated_at'
                )
                ->chunk(2000, function ($rows) use ($sheet, &$row, &$serial) {
                    foreach ($rows as $ar) {
                        $serial++;
                        $sheet->setCellValue([1, $row], $serial);
                        $sheet->setCellValue([2, $row], $ar->hemis_id);
                        $sheet->setCellValue([3, $row], $ar->student_id);
                        $sheet->setCellValue([4, $row], $ar->student_name);
                        $sheet->setCellValue([5, $row], $ar->curriculum_id);
                        $sheet->setCellValue([6, $row], $ar->education_year);
                        $sheet->setCellValue([7, $row], $ar->semester_id);
                        $sheet->setCellValue([8, $row], $ar->semester_name);
                        $sheet->setCellValue([9, $row], $ar->subject_id);
                        $sheet->setCellValue([10, $row], $ar->subject_name);
                        $sheet->setCellValue([11, $row], $ar->employee_id);
                        $sheet->setCellValue([12, $row], $ar->employee_name);
                        $sheet->setCellValue([13, $row], $ar->total_acload);
                        $sheet->setCellValue([14, $row], $ar->credit);
                        $sheet->setCellValue([15, $row], $ar->total_point);
                        $sheet->setCellValue([16, $row], $ar->grade);
                        $sheet->setCellValue([17, $row], $ar->finish_credit_status ? 'Ha' : "Yo'q");
                        $sheet->setCellValue([18, $row], $ar->retraining_status ? 'Ha' : "Yo'q");
                        $sheet->setCellValue([19, $row], $ar->hemis_created_at);
                        $sheet->setCellValue([20, $row], $ar->hemis_updated_at);
                        $row++;
                    }
                });

            $processedChunks++;
            $percent = 20 + (int) (75 * ($processedChunks / $totalChunks));
            $this->updateProgress("Yozilmoqda... ({$serial} ta yozuv)", min(95, $percent));
        }

        $widths = [5, 11, 11, 30, 11, 12, 9, 14, 11, 35, 11, 25, 8, 7, 7, 8, 8, 9, 18, 18];
        foreach ($widths as $i => $w) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }

        if ($row > 2) {
            $sheet->getStyle("A2:{$lastCol}" . ($row - 1))->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $this->updateProgress('Fayl saqlanmoqda...', 97);

        $fileName = 'Academic_records_' . date('Y-m-d_H-i') . '.xlsx';
        $dir = $this->ensureExportDir();
        $relativePath = 'exports/academic_records/' . uniqid('ar_', true) . '.xlsx';
        $absPath = storage_path('app/' . $relativePath);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($absPath);
        $spreadsheet->disconnectWorksheets();

        return ['file_name' => $fileName, 'file_path' => $absPath];
    }

    private function generateEmptyExcel(): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', "Ma'lumot topilmadi");
        $temp = tempnam(sys_get_temp_dir(), 'ar_empty_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();
        $content = file_get_contents($temp);
        @unlink($temp);
        return $content;
    }

    private function ensureExportDir(): string
    {
        $dir = storage_path('app/exports/academic_records');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function saveBytesToExportDir(string $bytes, string $hint): string
    {
        $this->ensureExportDir();
        $path = storage_path('app/exports/academic_records/' . uniqid('ar_', true) . '.xlsx');
        file_put_contents($path, $bytes);
        return $path;
    }

    private function updateProgress(string $message, int $percent, string $status = 'running'): void
    {
        Cache::put($this->exportKey, [
            'status'     => $status,
            'message'    => $message,
            'percent'    => $percent,
            'updated_at' => now()->toDateTimeString(),
        ], 1800);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[ExportAcademicRecordsJob] Failed: ' . $exception->getMessage());
        Cache::put($this->exportKey, [
            'status'     => 'failed',
            'message'    => mb_substr($exception->getMessage(), 0, 120),
            'percent'    => 0,
            'updated_at' => now()->toDateTimeString(),
        ], 1800);
    }
}
