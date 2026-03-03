<?php

namespace App\Imports;

use App\Models\AbsenceExcuse;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class AbsenceExcuseImport implements ToCollection, WithHeadingRow, WithValidation
{
    public array $errors = [];
    public int $importedCount = 0;
    public int $skippedCount = 0;

    private ?int $reviewedBy;
    private ?string $reviewedByName;

    public function __construct(?int $reviewedBy = null, ?string $reviewedByName = null)
    {
        $this->reviewedBy = $reviewedBy;
        $this->reviewedByName = $reviewedByName;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            $hemisId = trim($row['talaba_hemis_id'] ?? '');
            if (empty($hemisId)) {
                $this->errors[] = ['row' => $rowNum, 'error' => 'HEMIS ID bo\'sh'];
                continue;
            }

            // Talabani topish
            $student = Student::where('hemis_id', $hemisId)->first();
            if (!$student) {
                $this->errors[] = ['row' => $rowNum, 'error' => "Talaba topilmadi: HEMIS ID {$hemisId}"];
                continue;
            }

            // Sabab
            $reason = trim($row['sabab'] ?? '');
            if (!array_key_exists($reason, AbsenceExcuse::REASONS)) {
                $this->errors[] = ['row' => $rowNum, 'error' => "Noto'g'ri sabab: '{$reason}'. Mumkin qiymatlar: " . implode(', ', array_keys(AbsenceExcuse::REASONS))];
                continue;
            }

            // Sanalar
            try {
                $startDate = $this->parseDate($row['boshlanish_sanasi'] ?? '');
                $endDate = $this->parseDate($row['tugash_sanasi'] ?? '');
            } catch (\Exception $e) {
                $this->errors[] = ['row' => $rowNum, 'error' => 'Sana formati noto\'g\'ri. Kutilmoqda: KK.OO.YYYY (masalan: 01.02.2026)'];
                continue;
            }

            if ($endDate->lt($startDate)) {
                $this->errors[] = ['row' => $rowNum, 'error' => 'Tugash sanasi boshlanish sanasidan oldin bo\'lmasligi kerak'];
                continue;
            }

            // Max kunlar tekshiruvi
            $maxDays = AbsenceExcuse::REASONS[$reason]['max_days'] ?? null;
            $daysDiff = $startDate->diffInDays($endDate) + 1;
            if ($maxDays && $daysDiff > $maxDays) {
                $this->errors[] = ['row' => $rowNum, 'error' => "'{$reason}' uchun max {$maxDays} kun, lekin {$daysDiff} kun kiritilgan"];
                continue;
            }

            // Dublikat tekshiruvi
            $exists = AbsenceExcuse::where('student_hemis_id', $hemisId)
                ->where('reason', $reason)
                ->where('start_date', $startDate)
                ->where('end_date', $endDate)
                ->exists();

            if ($exists) {
                $this->skippedCount++;
                $this->errors[] = ['row' => $rowNum, 'error' => "Dublikat: bu talaba uchun shu sana oralig'ida ariza mavjud (o'tkazib yuborildi)"];
                continue;
            }

            $status = trim($row['holat'] ?? 'approved');
            if (!in_array($status, ['pending', 'approved', 'rejected'])) {
                $status = 'approved';
            }

            $docNumber = trim($row['hujjat_raqami'] ?? '');
            $description = trim($row['izoh'] ?? '');

            $data = [
                'student_id' => $student->id,
                'student_hemis_id' => $hemisId,
                'student_full_name' => $student->full_name ?? $student->short_name,
                'group_name' => $student->group_name,
                'department_name' => $student->department_name,
                'reason' => $reason,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'doc_number' => $docNumber ?: null,
                'description' => $description ?: null,
                'status' => $status,
                'verification_token' => Str::uuid()->toString(),
            ];

            // Tasdiqlangan bo'lsa reviewer ma'lumotlarini qo'shish
            if ($status === 'approved' || $status === 'rejected') {
                $data['reviewed_by'] = $this->reviewedBy;
                $data['reviewed_by_name'] = $this->reviewedByName;
                $data['reviewed_at'] = now();
            }

            AbsenceExcuse::create($data);
            $this->importedCount++;
        }
    }

    private function parseDate(string $value): Carbon
    {
        $value = trim($value);

        // KK.OO.YYYY format
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m)) {
            return Carbon::createFromFormat('d.m.Y', sprintf('%02d.%02d.%s', $m[1], $m[2], $m[3]))->startOfDay();
        }

        // YYYY-MM-DD format
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $value)) {
            return Carbon::parse($value)->startOfDay();
        }

        // KK/OO/YYYY format
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $m)) {
            return Carbon::createFromFormat('d/m/Y', sprintf('%02d/%02d/%s', $m[1], $m[2], $m[3]))->startOfDay();
        }

        // Excel numeric date
        if (is_numeric($value)) {
            return Carbon::instance(
                \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
            )->startOfDay();
        }

        throw new \Exception("Sana formati tanilmadi: {$value}");
    }

    public function rules(): array
    {
        return [
            'talaba_hemis_id' => 'required',
            'sabab' => 'required',
            'boshlanish_sanasi' => 'required',
            'tugash_sanasi' => 'required',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'talaba_hemis_id.required' => 'Talaba HEMIS ID bo\'sh (qator :row)',
            'sabab.required' => 'Sabab bo\'sh (qator :row)',
            'boshlanish_sanasi.required' => 'Boshlanish sanasi bo\'sh (qator :row)',
            'tugash_sanasi.required' => 'Tugash sanasi bo\'sh (qator :row)',
        ];
    }
}
