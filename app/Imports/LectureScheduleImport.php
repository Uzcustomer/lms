<?php

namespace App\Imports;

use App\Models\Group;
use App\Models\LectureSchedule;
use App\Models\LectureScheduleBatch;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class LectureScheduleImport implements ToCollection, WithHeadingRow, WithValidation
{
    public array $errors = [];
    public int $importedCount = 0;

    private LectureScheduleBatch $batch;

    // Kun nomlari -> raqamga
    private const DAY_MAP = [
        'dushanba' => 1, 'du' => 1, '1' => 1,
        'seshanba' => 2, 'se' => 2, '2' => 2,
        'chorshanba' => 3, 'chor' => 3, 'ch' => 3, '3' => 3,
        'payshanba' => 4, 'pay' => 4, '4' => 4,
        'juma' => 5, 'ju' => 5, '5' => 5,
        'shanba' => 6, 'sha' => 6, 'sh' => 6, '6' => 6,
    ];

    public function __construct(LectureScheduleBatch $batch)
    {
        $this->batch = $batch;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // +2 chunki heading row + 0-index

            // Kun -> raqamga o'tkazish (yangi ustun nomi: kuni)
            $dayRaw = mb_strtolower(trim($row['kuni'] ?? $row['kun'] ?? ''));
            $weekDay = self::DAY_MAP[$dayRaw] ?? null;

            if (!$weekDay) {
                $this->errors[] = [
                    'row' => $rowNum,
                    'error' => "Noto'g'ri kun nomi: '{$dayRaw}'. Kutilmoqda: Dushanba, Seshanba, ...",
                ];
                continue;
            }

            // Juftlik kodi
            $pairRaw = trim($row['juftlik'] ?? '');
            $pairCode = preg_replace('/[^0-9]/', '', $pairRaw) ?: $pairRaw;

            // Vaqtlarni parse qilish
            $startTime = $this->parseTime($row['boshlanish'] ?? null);
            $endTime = $this->parseTime($row['tugash'] ?? null);

            // Guruh nomiga qarab hemis_id topish
            $groupName = trim($row['guruh'] ?? '');
            $group = $groupName ? Group::where('name', $groupName)->first() : null;

            // Guruh_source (birlashtirilgan ma'ruza guruhi)
            $groupSource = trim($row['guruh_source'] ?? '');

            // O'qituvchi nomiga qarab hemis_id topish
            $employeeName = trim($row['oqituvchi'] ?? '');
            $employee = null;
            if ($employeeName) {
                $employee = Teacher::where('full_name', $employeeName)
                    ->orWhere('short_name', $employeeName)
                    ->first();
            }

            // Xona (eski formatda auditoriya)
            $roomName = trim($row['xona'] ?? $row['auditoriya'] ?? '');

            // Qavat va Bino
            $floor = trim($row['qavat'] ?? '');
            $buildingName = trim($row['bino'] ?? '');

            // Dars turi (ixtiyoriy)
            $trainingType = trim($row['turi'] ?? '');

            // Haftalar davomiyligi va Juft-toq
            $weeks = trim($row['haftalar_davomiyligi'] ?? '');
            $weekParity = mb_strtolower(trim($row['juft-toq'] ?? $row['jufttoq'] ?? ''));

            LectureSchedule::create([
                'batch_id' => $this->batch->id,
                'week_day' => $weekDay,
                'lesson_pair_code' => $pairCode,
                'lesson_pair_name' => $pairRaw,
                'lesson_pair_start_time' => $startTime,
                'lesson_pair_end_time' => $endTime,
                'group_name' => $groupName,
                'group_id' => $group?->group_hemis_id,
                'group_source' => $groupSource ?: null,
                'subject_name' => trim($row['fan'] ?? ''),
                'employee_name' => $employeeName ?: null,
                'employee_id' => $employee?->hemis_id,
                'auditorium_name' => $roomName ?: null,
                'floor' => $floor ?: null,
                'building_name' => $buildingName ?: null,
                'training_type_name' => $trainingType ?: null,
                'weeks' => $weeks ?: null,
                'week_parity' => $weekParity ?: null,
            ]);

            $this->importedCount++;
        }

        $this->batch->update([
            'total_rows' => $this->importedCount,
            'status' => count($this->errors) > 0 && $this->importedCount === 0 ? 'error' : 'completed',
        ]);
    }

    public function rules(): array
    {
        return [
            'kuni' => 'required',
            'juftlik' => 'required',
            'guruh' => 'required',
            'fan' => 'required',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'kuni.required' => ':attribute ustuni bo\'sh bo\'lmasligi kerak (qator :row)',
            'juftlik.required' => ':attribute ustuni bo\'sh bo\'lmasligi kerak (qator :row)',
            'guruh.required' => ':attribute ustuni bo\'sh bo\'lmasligi kerak (qator :row)',
            'fan.required' => ':attribute ustuni bo\'sh bo\'lmasligi kerak (qator :row)',
        ];
    }

    private function parseTime($value): ?string
    {
        if (!$value) {
            return null;
        }

        $value = trim($value);

        // "08:00" yoki "8:00" format
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m)) {
            return sprintf('%02d:%s:00', $m[1], $m[2]);
        }

        // Excel numeric format (0.333... = 08:00)
        if (is_numeric($value)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                return $date->format('H:i:s');
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}
