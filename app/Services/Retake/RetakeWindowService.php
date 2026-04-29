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
     */
    public function activeWindowForStudent(Student $student): ?RetakeApplicationWindow
    {
        if (!$student->specialty_id || !$student->level_code) {
            return null;
        }

        return RetakeApplicationWindow::query()
            ->forStudent((int) $student->specialty_id, $student->level_code)
            ->active()
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
            ->forStudent((int) $student->specialty_id, $student->level_code)
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Yangi qabul oynasini ochish (O'quv bo'limi).
     */
    public function createWindow(array $data, Teacher $createdBy): RetakeApplicationWindow
    {
        $this->validateDateRange($data['start_date'], $data['end_date']);

        $exists = RetakeApplicationWindow::query()
            ->where('specialty_id', $data['specialty_id'])
            ->where('level_code', $data['level_code'])
            ->where('semester_code', $data['semester_code'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'specialty_id' => 'Bu yo\'nalish, kurs va semestr uchun oyna allaqachon mavjud',
            ]);
        }

        return RetakeApplicationWindow::create([
            ...$data,
            'created_by_user_id' => $createdBy->id,
            'created_by_name' => $createdBy->full_name,
        ]);
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
