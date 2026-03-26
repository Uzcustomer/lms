<?php

namespace App\Imports;

use App\Models\AbsenceExcuse;
use App\Models\AbsenceExcuseMakeup;
use App\Models\ExamSchedule;
use App\Models\ExamTest;
use App\Models\OraliqNazorat;
use App\Models\Oski;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\SubjectMatcherService;
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

            $excuse = AbsenceExcuse::create($data);

            // Avtomatik ravishda o'tkazib yuborilgan nazoratlarni topib, makeup yaratish
            $this->createMakeups($excuse, $student, $startDate, $endDate);

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

    /**
     * Import qilingan ariza uchun o'tkazib yuborilgan nazoratlarni topib, makeup yozuvlarini yaratish
     */
    private function createMakeups(AbsenceExcuse $excuse, Student $student, Carbon $startDate, Carbon $endDate): void
    {
        $groupId = $student->group_id;
        if (!$groupId) {
            return;
        }

        $missedAssessments = $this->findMissedAssessments($groupId, $startDate, $endDate);

        foreach ($missedAssessments as $assessment) {
            // student_subjects orqali to'g'ri subject_id ni aniqlash
            $resolvedSubjectId = $assessment['subject_id'];
            $match = SubjectMatcherService::resolveSubjectId(
                $assessment['subject_name'],
                $assessment['subject_id'],
                $student
            );
            if ($match) {
                $resolvedSubjectId = $match['subject_id'];
            }

            AbsenceExcuseMakeup::create([
                'absence_excuse_id' => $excuse->id,
                'student_id' => $student->id,
                'subject_name' => $assessment['subject_name'],
                'subject_id' => $resolvedSubjectId,
                'assessment_type' => $assessment['assessment_type'],
                'assessment_type_code' => $assessment['assessment_type_code'],
                'original_date' => $assessment['original_date'],
                'status' => 'scheduled',
            ]);
        }
    }

    private function findMissedAssessments($groupId, Carbon $startDate, Carbon $endDate): Collection
    {
        $missedAssessments = collect();

        // 1. Schedules jadvalidan (dars jadvali)
        $schedules = Schedule::where('group_id', $groupId)
            ->whereDate('lesson_date', '>=', $startDate)
            ->whereDate('lesson_date', '<=', $endDate)
            ->whereIn('training_type_code', [99, 100, 101, 102])
            ->get();

        $typeMap = [100 => 'jn', 99 => 'mt', 101 => 'oski', 102 => 'test'];

        foreach ($schedules as $schedule) {
            $assessmentType = $typeMap[$schedule->training_type_code] ?? null;
            if ($assessmentType) {
                $missedAssessments->push([
                    'subject_name' => $schedule->subject_name,
                    'subject_id' => $schedule->subject_id,
                    'assessment_type' => $assessmentType,
                    'assessment_type_code' => (string) $schedule->training_type_code,
                    'original_date' => Carbon::parse($schedule->lesson_date)->format('Y-m-d'),
                ]);
            }
        }

        // 2. Oraliq nazoratlar (JN)
        $oraliqNazorats = OraliqNazorat::where('group_hemis_id', $groupId)
            ->whereDate('start_date', '>=', $startDate)
            ->whereDate('start_date', '<=', $endDate)
            ->get();

        foreach ($oraliqNazorats as $on) {
            $missedAssessments->push([
                'subject_name' => $on->subject_name,
                'subject_id' => $on->subject_hemis_id,
                'assessment_type' => 'jn',
                'assessment_type_code' => '100',
                'original_date' => Carbon::parse($on->start_date)->format('Y-m-d'),
            ]);
        }

        // 3. OSKI
        $oskis = Oski::where('group_hemis_id', $groupId)
            ->whereDate('start_date', '>=', $startDate)
            ->whereDate('start_date', '<=', $endDate)
            ->get();

        foreach ($oskis as $oski) {
            $missedAssessments->push([
                'subject_name' => $oski->subject_name,
                'subject_id' => $oski->subject_hemis_id,
                'assessment_type' => 'oski',
                'assessment_type_code' => '101',
                'original_date' => Carbon::parse($oski->start_date)->format('Y-m-d'),
            ]);
        }

        // 4. Yakuniy testlar
        $examTests = ExamTest::where('group_hemis_id', $groupId)
            ->whereDate('start_date', '>=', $startDate)
            ->whereDate('start_date', '<=', $endDate)
            ->get();

        foreach ($examTests as $et) {
            $missedAssessments->push([
                'subject_name' => $et->subject_name,
                'subject_id' => $et->subject_hemis_id,
                'assessment_type' => 'test',
                'assessment_type_code' => '102',
                'original_date' => Carbon::parse($et->start_date)->format('Y-m-d'),
            ]);
        }

        // 5. Imtihon jadvali (OSKI va Test sanalari)
        $examSchedules = ExamSchedule::where('group_hemis_id', $groupId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($q2) use ($startDate, $endDate) {
                    $q2->whereDate('oski_date', '>=', $startDate)
                        ->whereDate('oski_date', '<=', $endDate);
                })->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->whereDate('test_date', '>=', $startDate)
                        ->whereDate('test_date', '<=', $endDate);
                });
            })->get();

        foreach ($examSchedules as $es) {
            if ($es->oski_date && $es->oski_date->between($startDate, $endDate)) {
                $missedAssessments->push([
                    'subject_name' => $es->subject_name,
                    'subject_id' => $es->subject_id,
                    'assessment_type' => 'oski',
                    'assessment_type_code' => '101',
                    'original_date' => $es->oski_date->format('Y-m-d'),
                ]);
            }
            if ($es->test_date && $es->test_date->between($startDate, $endDate)) {
                $missedAssessments->push([
                    'subject_name' => $es->subject_name,
                    'subject_id' => $es->subject_id,
                    'assessment_type' => 'test',
                    'assessment_type_code' => '102',
                    'original_date' => $es->test_date->format('Y-m-d'),
                ]);
            }
        }

        // Duplikatlarni olib tashlash
        return $missedAssessments->unique(function ($item) {
            return $item['subject_name'] . '|' . $item['assessment_type'] . '|' . $item['original_date'];
        })->values();
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
