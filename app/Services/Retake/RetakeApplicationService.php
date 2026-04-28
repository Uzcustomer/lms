<?php

namespace App\Services\Retake;

use App\Enums\RetakeAcademicDeptStatus;
use App\Enums\RetakeLogAction;
use App\Enums\RetakeReviewStatus;
use App\Models\AcademicRecord;
use App\Models\RetakeApplication;
use App\Models\RetakeApplicationPeriod;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Talaba ariza yuborish va qayta yuborish (resubmit) mantiqi.
 *
 * Asosiy qoidalar:
 *  - Faqat akkreditatsiya bahosi mavjud bo'lmagan (academic_records.retraining_status=true) fanlar
 *  - Eng kamida 1 ta, eng ko'pi 3 ta fan
 *  - Faqat qabul oynasi ichida (talabaning yo'nalish+kurs+semestri uchun ochiq)
 *  - Bitta fanga bir vaqtda 1 ta aktiv (rejected emas, approved emas) ariza
 *  - Bitta umumiy kvitansiya (PDF/JPG/PNG, max 5MB)
 *  - Hammasi bitta application_group_id (UUID) bilan bog'lanadi, lekin har fan
 *    alohida retake_applications yozuvi sifatida saqlanadi.
 */
class RetakeApplicationService
{
    public const MAX_SUBJECTS = 3;
    public const RECEIPT_DISK = 'local';
    public const RECEIPT_DIRECTORY = 'private/retake-receipts';
    public const ALLOWED_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];
    public const MAX_RECEIPT_BYTES = 5 * 1024 * 1024; // 5 MB

    public function __construct(
        private readonly RetakePeriodService $periodService,
        private readonly RetakeLogService $logService,
    ) {
    }

    /**
     * Yangi ko'p fanli ariza yuborish.
     *
     * @param  array<int, array{subject_id: int, semester_id: int}>  $subjects
     * @return Collection<int, RetakeApplication>  yaratilgan arizalar
     */
    public function submit(
        Student $student,
        array $subjects,
        UploadedFile $receipt,
        ?string $studentNote = null,
    ): Collection {
        $this->validateSubjectsCount($subjects);
        $this->validateReceipt($receipt);

        $period = $this->resolveActivePeriod($student);
        $debts = $this->loadDebtsForSubjects($student, $subjects);
        $this->ensureNoActiveApplications($student, $debts);

        return DB::transaction(function () use ($student, $debts, $receipt, $studentNote, $period) {
            $groupId = (string) Str::uuid();
            $receiptMeta = $this->storeReceipt($receipt, $student->id, $groupId);
            $now = CarbonImmutable::now();

            $created = collect();
            foreach ($debts as $debt) {
                $application = RetakeApplication::create([
                    'application_group_id' => $groupId,
                    'student_id' => $student->id,
                    'subject_id' => $debt['subject_id'],
                    'subject_name' => $debt['subject_name'],
                    'semester_id' => $debt['semester_id'],
                    'semester_name' => $debt['semester_name'],
                    'credit' => $debt['credit'],
                    'period_id' => $period->id,
                    'receipt_path' => $receiptMeta['path'],
                    'receipt_original_name' => $receiptMeta['original_name'],
                    'receipt_size' => $receiptMeta['size'],
                    'receipt_mime' => $receiptMeta['mime'],
                    'student_note' => $studentNote,
                    'dean_status' => RetakeReviewStatus::PENDING->value,
                    'registrar_status' => RetakeReviewStatus::PENDING->value,
                    'academic_dept_status' => RetakeAcademicDeptStatus::NOT_STARTED->value,
                    'submitted_at' => $now,
                ]);

                $isResubmit = $this->wasPreviouslyRejected($student, $debt);
                $action = $isResubmit ? RetakeLogAction::RESUBMITTED : RetakeLogAction::SUBMITTED;
                $this->logService->log($application, $action, $student);

                $created->push($application);
            }

            return $created;
        });
    }

    /**
     * @param  array<int, array{subject_id: int, semester_id: int}>  $subjects
     */
    private function validateSubjectsCount(array $subjects): void
    {
        $count = count($subjects);
        if ($count < 1) {
            throw ValidationException::withMessages([
                'subjects' => 'Eng kamida 1 ta fan tanlash kerak.',
            ]);
        }
        if ($count > self::MAX_SUBJECTS) {
            throw ValidationException::withMessages([
                'subjects' => 'Maksimal ' . self::MAX_SUBJECTS . ' ta fan tanlash mumkin.',
            ]);
        }
    }

    private function validateReceipt(UploadedFile $receipt): void
    {
        if ($receipt->getSize() > self::MAX_RECEIPT_BYTES) {
            throw ValidationException::withMessages([
                'receipt' => 'Kvitansiya hajmi 5 MB dan oshmasligi kerak.',
            ]);
        }

        // MIME magic bytes (extension emas) — finfo orqali
        $mime = $receipt->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'receipt' => 'Kvitansiya faqat PDF, JPG yoki PNG bo\'lishi mumkin.',
            ]);
        }
    }

    private function resolveActivePeriod(Student $student): RetakeApplicationPeriod
    {
        $period = $this->periodService->findActiveForStudent($student);
        if ($period === null) {
            throw ValidationException::withMessages([
                'period' => 'Sizning yo\'nalishingiz va kursingiz uchun qabul oynasi ochiq emas.',
            ]);
        }
        return $period;
    }

    /**
     * Tanlangan fanlar haqiqatan akademik qarzdorlik ekanligini tekshirish va
     * ariza uchun snapshot ma'lumotlarini olib qaytarish.
     *
     * @param  array<int, array{subject_id: int, semester_id: int}>  $subjects
     * @return Collection<int, array<string, mixed>>
     */
    private function loadDebtsForSubjects(Student $student, array $subjects): Collection
    {
        $pairs = collect($subjects)->map(fn ($s) => [
            'subject_id' => (int) $s['subject_id'],
            'semester_id' => (int) $s['semester_id'],
        ]);

        $records = AcademicRecord::query()
            ->where('student_id', $student->hemis_id)
            ->where('retraining_status', true)
            ->whereIn('subject_id', $pairs->pluck('subject_id'))
            ->whereIn('semester_id', $pairs->pluck('semester_id'))
            ->get();

        $debts = collect();
        foreach ($pairs as $pair) {
            $record = $records->first(fn ($r) => (int) $r->subject_id === $pair['subject_id']
                && (int) $r->semester_id === $pair['semester_id']);

            if ($record === null) {
                throw ValidationException::withMessages([
                    'subjects' => 'Tanlangan fanlardan biri akademik qarzdorlik ro\'yxatida topilmadi.',
                ]);
            }

            $debts->push([
                'subject_id' => (int) $record->subject_id,
                'subject_name' => $record->subject_name,
                'semester_id' => (int) $record->semester_id,
                'semester_name' => $record->semester_name,
                'credit' => (float) ($record->credit ?? 0),
            ]);
        }
        return $debts;
    }

    /**
     * Tanlangan fanlardan birortasi uchun aktiv (kutilayotgan/tasdiqlangan) ariza
     * mavjud bo'lmasligini ta'minlash. Aks holda ValidationException.
     *
     * @param  Collection<int, array<string, mixed>>  $debts
     */
    private function ensureNoActiveApplications(Student $student, Collection $debts): void
    {
        foreach ($debts as $debt) {
            $exists = RetakeApplication::query()
                ->activeForStudentSubject($student->id, $debt['subject_id'], $debt['semester_id'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'subjects' => "{$debt['subject_name']} fani uchun siz allaqachon aktiv ariza yuborgansiz.",
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $debt
     */
    private function wasPreviouslyRejected(Student $student, array $debt): bool
    {
        return RetakeApplication::query()
            ->where('student_id', $student->id)
            ->where('subject_id', $debt['subject_id'])
            ->where('semester_id', $debt['semester_id'])
            ->where(function ($q) {
                $q->where('dean_status', RetakeReviewStatus::REJECTED->value)
                    ->orWhere('registrar_status', RetakeReviewStatus::REJECTED->value)
                    ->orWhere('academic_dept_status', RetakeAcademicDeptStatus::REJECTED->value);
            })
            ->exists();
    }

    /**
     * Kvitansiyani saqlash. Storage path:
     *   storage/app/private/retake-receipts/{year}/{month}/{group_uuid}.{ext}
     *
     * @return array{path: string, original_name: string, size: int, mime: string}
     */
    private function storeReceipt(UploadedFile $receipt, int $studentId, string $groupId): array
    {
        $now = CarbonImmutable::now();
        $extension = $receipt->getClientOriginalExtension() ?: $this->guessExtension($receipt->getMimeType());
        $directory = self::RECEIPT_DIRECTORY . '/' . $now->format('Y') . '/' . $now->format('m');
        $filename = $groupId . '.' . $extension;
        $path = $receipt->storeAs($directory, $filename, self::RECEIPT_DISK);

        return [
            'path' => $path,
            'original_name' => $receipt->getClientOriginalName(),
            'size' => $receipt->getSize(),
            'mime' => $receipt->getMimeType(),
        ];
    }

    private function guessExtension(?string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'bin',
        };
    }
}
