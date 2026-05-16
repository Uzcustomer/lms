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

class ExportAcademicRecordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    private array $filters;
    private string $exportKey;

    public function __construct(array $filters, string $exportKey)
    {
        $this->filters = $filters;
        $this->exportKey = $exportKey;
    }

    public static function dirPath(): string
    {
        $dir = storage_path('app/exports/academic_records');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public static function pathsFor(string $exportKey): array
    {
        $hash = md5($exportKey);
        $dir = self::dirPath();
        return [
            'xlsx' => $dir . '/' . $hash . '.xlsx',
            'meta' => $dir . '/' . $hash . '.json',
        ];
    }

    public function handle(): void
    {
        ini_set('memory_limit', '1024M');

        try {
            $this->updateStatus('running', 'Talabalar ro\'yxati tayyorlanmoqda...', 5);

            $studentHemisIds = $this->resolveStudentHemisIds();

            if (empty($studentHemisIds)) {
                $this->writeEmptyFile();
                $this->updateStatus('done', 'Talaba topilmadi', 100, 'Academic_records_empty.xlsx');
                return;
            }

            $this->updateStatus('running', 'Academic records yuklanmoqda...', 20);

            $fileName = $this->generateExcel($studentHemisIds);

            $paths = self::pathsFor($this->exportKey);
            Log::info('[ExportAcademicRecordsJob] Tayyor', [
                'export_key'    => $this->exportKey,
                'file_name'     => $fileName,
                'students_count' => count($studentHemisIds),
                'file_size'     => file_exists($paths['xlsx']) ? filesize($paths['xlsx']) : 0,
            ]);

            $this->updateStatus('done', 'Tayyor', 100, $fileName);
        } catch (\Throwable $e) {
            Log::error('[ExportAcademicRecordsJob] Xato: ' . $e->getMessage(), [
                'trace' => mb_substr($e->getTraceAsString(), 0, 500),
            ]);
            $this->updateStatus('failed', 'Xato: ' . mb_substr($e->getMessage(), 0, 120), 0);
        }
    }

    private function resolveStudentHemisIds(): array
    {
        $f = $this->filters;
        $q = DB::table('students as s')->whereNotNull('s.curriculum_id');

        if (!empty($f['student_status'])) $q->where('s.student_status_code', $f['student_status']);
        if (!empty($f['student_name']))   $q->where('s.full_name', 'like', '%' . $f['student_name'] . '%');
        if (!empty($f['faculty'])) {
            $faculty = Department::find($f['faculty']);
            if ($faculty) $q->where('s.department_id', $faculty->department_hemis_id);
        }
        if (!empty($f['specialty']))      $q->where('s.specialty_id', $f['specialty']);
        if (!empty($f['level_code']))     $q->where('s.level_code', $f['level_code']);
        if (!empty($f['group'])) {
            $group = Group::find($f['group']);
            if ($group) $q->where('s.group_id', $group->group_hemis_id);
        }
        if (!empty($f['education_type'])) $q->where('s.education_type_code', $f['education_type']);
        if (!empty($f['student_type']))   $q->where('s.student_type_code', $f['student_type']);

        return $q->pluck('s.hemis_id')->map(fn ($v) => (int) $v)->all();
    }

    private function generateExcel(array $studentHemisIds): string
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
            $this->updateStatus('running', "Yozilmoqda... ({$serial} ta yozuv)", min(95, $percent));
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

        $this->updateStatus('running', 'Fayl saqlanmoqda...', 97);

        $fileName = 'Academic_records_' . date('Y-m-d_H-i') . '.xlsx';
        $paths = self::pathsFor($this->exportKey);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($paths['xlsx']);
        $spreadsheet->disconnectWorksheets();

        return $fileName;
    }

    private function writeEmptyFile(): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', "Ma'lumot topilmadi");
        $paths = self::pathsFor($this->exportKey);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($paths['xlsx']);
        $spreadsheet->disconnectWorksheets();
    }

    private function updateStatus(string $status, string $message, int $percent, ?string $fileName = null): void
    {
        $payload = [
            'status'     => $status,
            'message'    => $message,
            'percent'    => $percent,
            'file_name'  => $fileName,
            'updated_at' => now()->toDateTimeString(),
        ];

        // Cache (status polling uchun, kichik payload)
        try {
            Cache::put($this->exportKey, $payload, 1800);
        } catch (\Throwable $e) {
            Log::warning('[ExportAcademicRecordsJob] Cache::put muvaffaqiyatsiz: ' . $e->getMessage());
        }

        // Disk meta — cache yo'qolsa ham download topa olishi uchun
        try {
            $paths = self::pathsFor($this->exportKey);
            file_put_contents($paths['meta'], json_encode($payload));
        } catch (\Throwable $e) {
            Log::warning('[ExportAcademicRecordsJob] Meta yozish muvaffaqiyatsiz: ' . $e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[ExportAcademicRecordsJob] Failed: ' . $exception->getMessage());
        $this->updateStatus('failed', mb_substr($exception->getMessage(), 0, 120), 0);
    }
}
