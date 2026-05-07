<?php

namespace App\Services\Retake;

use App\Models\RetakeApplicationWindow;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class RetakeWindowService
{
    /**
     * Talabaning yo'nalish va kursi uchun joriy faol oyna.
     * Faqat ochiq sessiyadagi oynalar e'tiborga olinadi — yopilgan
     * sessiyadagi oyna ko'rinmaydi.
     */
    public function activeWindowForStudent(Student $student): ?RetakeApplicationWindow
    {
        if (!$student->specialty_id || !$student->level_code) {
            return null;
        }

        return RetakeApplicationWindow::query()
            ->forStudent((int) $student->specialty_id, $student->level_code, (string) ($student->department_id ?? ''))
            ->active()
            ->whereHas('session', fn ($q) => $q->where('is_closed', false))
            ->orderByDesc('end_date')
            ->first();
    }

    /**
     * Talabaning oynalari tarixi (eski + joriy).
     *
     * @return Collection<RetakeApplicationWindow>
     */
    public function windowsForStudent(Student $student): Collection
    {
        if (!$student->specialty_id || !$student->level_code) {
            return new Collection();
        }

        return RetakeApplicationWindow::query()
            ->forStudent((int) $student->specialty_id, $student->level_code, (string) ($student->department_id ?? ''))
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Yangi qabul oynasini ochish (O'quv bo'limi).
     * `session_id` majburiy — har oyna albatta sessiyaga bog'langan bo'lishi kerak.
     * Bir xil yo'nalish/kurs/semestr kombinatsiyasi har sessiyada bittadan bo'lishi mumkin
     * (boshqa sessiyada — alohida oyna).
     */
    public function createWindow(array $data, Teacher $createdBy): RetakeApplicationWindow
    {
        $this->validateDateRange($data['start_date'], $data['end_date']);

        if (empty($data['session_id'])) {
            throw ValidationException::withMessages([
                'session_id' => 'Sessiyani tanlash majburiy',
            ]);
        }

        // Bir xil (sessiya, fakultet, yo'nalish, kurs, semestr) kombinatsiyasi
        // takroriy oyna sifatida yaratilishi ruxsat etiladi (foydalanuvchi
        // talabiga ko'ra). Avvalgi unique check va trashed-cleanup logikalari
        // olib tashlandi.

        $payload = [
            ...$data,
            'created_by_user_id' => $createdBy->id,
            'created_by_name' => $createdBy->full_name,
        ];

        // Migration hali qo'llanmagan bo'lsa yangi ustunlar yo'q — 500 oldini olish
        foreach (['creation_batch_id', 'department_hemis_id'] as $col) {
            if (isset($payload[$col]) &&
                !\Illuminate\Support\Facades\Schema::hasColumn('retake_application_windows', $col)) {
                unset($payload[$col]);
            }
        }

        return RetakeApplicationWindow::create($payload);
    }

    /**
     * Sanalarni o'zgartirish — faqat super-admin override.
     */
    public function overrideDates(RetakeApplicationWindow $window, string $startDate, string $endDate): void
    {
        $this->validateDateRange($startDate, $endDate);

        $window->update([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    private function validateDateRange(string $start, string $end): void
    {
        if (strtotime($start) > strtotime($end)) {
            throw ValidationException::withMessages([
                'end_date' => 'Tugash sanasi boshlanish sanasidan keyin bo\'lishi kerak',
            ]);
        }
    }
}
