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

    // Har bir maydon uchun mumkin bo'lgan ustun nomlari (slug formatda)
    // Excel heading slug formatga o'tadi: "Darslar soni" -> "darslar_soni"
    private const COLUMN_ALIASES = [
        'group_source' => ['potok', 'guruh_source'],
        'lesson_count' => ['darslar_soni', 'haftalar_davomiyligi', 'darslar'],
        'week_parity'  => ['hafta', 'juft_toq', 'hafta_turi'],
        'start_time'   => ['boshlanishi', 'boshlanish', 'vaqt'],
        'end_time'     => ['tugashi', 'tugash', 'stolbec1'],
    ];

    public function __construct(LectureScheduleBatch $batch)
    {
        $this->batch = $batch;
    }

    public function collection(Collection $rows)
    {
        // Debug: heading key'larni log'ga yozish
        if ($rows->isNotEmpty()) {
            $firstRow = $rows->first()->toArray();
            $keys = array_keys($firstRow);
            \Log::info('=== LectureScheduleImport DEBUG ===');
            \Log::info('Excel heading keys: ' . implode(', ', $keys));
            \Log::info('Birinchi qator qiymatlari:', $firstRow);

            // Har bir alias uchun qaysi ustun topilganini ko'rsatish
            foreach (self::COLUMN_ALIASES as $field => $aliases) {
                $found = $this->findMatchingKey($keys, $aliases);
                \Log::info("  {$field}: qidirdi [" . implode(', ', $aliases) . "] => topildi: " . ($found ?? 'TOPILMADI'));
            }
        }

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // +2 chunki heading row + 0-index

            // Kun -> raqamga o'tkazish
            $dayRaw = mb_strtolower(trim($row['kuni'] ?? $row['kun'] ?? ''));
            $weekDay = self::DAY_MAP[$dayRaw] ?? null;

            if (!$weekDay) {
                $this->errors[] = [
                    'row' => $rowNum,
                    'error' => "Noto'g'ri kun nomi: '{$dayRaw}'. Kutilmoqda: Dushanba, Seshanba, ...",
                ];
                continue;
            }

            $rowArray = $row->toArray();
            $keys = array_keys($rowArray);

            // Juftlik kodi
            $pairRaw = trim($row['juftlik'] ?? '');
            $pairCode = preg_replace('/[^0-9]/', '', $pairRaw) ?: $pairRaw;

            // Vaqtlarni parse qilish (alias orqali)
            $startTimeRaw = $this->findColumnValue($rowArray, $keys, self::COLUMN_ALIASES['start_time']);
            $endTimeRaw = $this->findColumnValue($rowArray, $keys, self::COLUMN_ALIASES['end_time']);
            $startTime = $this->parseTime($startTimeRaw);
            $endTime = $this->parseTime($endTimeRaw);

            // Guruh
            $groupName = trim($row['guruh'] ?? '');
            $group = $groupName ? Group::where('name', $groupName)->first() : null;

            // Potok / Guruh_source (alias orqali)
            $groupSource = trim((string) $this->findColumnValue($rowArray, $keys, self::COLUMN_ALIASES['group_source']));

            // O'qituvchi
            $employeeName = trim($row['oqituvchi'] ?? '');
            $employee = null;
            if ($employeeName) {
                $employee = Teacher::where('full_name', $employeeName)
                    ->orWhere('short_name', $employeeName)
                    ->first();
            }

            // Xona
            $roomName = trim($row['xona'] ?? $row['auditoriya'] ?? '');

            // Qavat va Bino
            $floor = trim($row['qavat'] ?? '');
            $buildingName = trim($row['bino'] ?? '');

            // Dars turi
            $trainingType = trim($row['turi'] ?? '');

            // Darslar soni (alias orqali)
            $lessonCountRaw = $this->findColumnValue($rowArray, $keys, self::COLUMN_ALIASES['lesson_count']);
            $lessonCount = is_numeric($lessonCountRaw) ? (int) $lessonCountRaw : null;

            // Hafta turi / Juft-toq (alias orqali)
            $weekParityRaw = trim((string) $this->findColumnValue($rowArray, $keys, self::COLUMN_ALIASES['week_parity']));
            $weekParity = $this->parseWeekParity($weekParityRaw);

            // Debug: birinchi 5 qator
            if ($index < 5) {
                \Log::info("Row {$rowNum}: group_source={$groupSource}, lesson_count={$lessonCount}, week_parity_raw={$weekParityRaw}, week_parity={$weekParity}, group={$groupName}, fan=" . trim($row['fan'] ?? ''));
            }

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
                'weeks' => $lessonCount ? (string) $lessonCount : null,
                'week_parity' => $weekParity ?: null,
            ]);

            $this->importedCount++;
        }

        \Log::info("=== Import yakunlandi: {$this->importedCount} qator, " . count($this->errors) . " xatolik ===");

        $this->batch->update([
            'total_rows' => $this->importedCount,
            'status' => count($this->errors) > 0 && $this->importedCount === 0 ? 'error' : 'completed',
        ]);
    }

    /**
     * Alias ro'yxatidan mos keluvchi ustun kalitini topish.
     */
    private function findMatchingKey(array $keys, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (in_array($alias, $keys, true)) {
                return $alias;
            }
        }
        return null;
    }

    /**
     * Alias ro'yxatidan mos keluvchi ustun qiymatini olish.
     */
    private function findColumnValue(array $rowArray, array $keys, array $aliases)
    {
        $key = $this->findMatchingKey($keys, $aliases);
        return $key !== null ? $rowArray[$key] : null;
    }

    /**
     * Hafta pariteti qiymatini parse qilish.
     * "Juft hafta" -> "juft", "Toq hafta" -> "toq", "Juft" -> "juft", "Toq" -> "toq"
     */
    private function parseWeekParity(string $raw): ?string
    {
        $lower = mb_strtolower(trim($raw));
        if ($lower === '') {
            return null;
        }
        if (str_contains($lower, 'juft')) {
            return 'juft';
        }
        if (str_contains($lower, 'toq')) {
            return 'toq';
        }
        return $lower;
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
