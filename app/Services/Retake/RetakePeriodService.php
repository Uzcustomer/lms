<?php

namespace App\Services\Retake;

use App\Models\RetakeApplicationPeriod;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * O'quv bo'limi qabul oynalarini boshqaradi:
 *  - Yo'nalish + kurs + semestr kombinatsiyasi uchun bittadan unique oyna
 *  - Yaratilgach sanalarni o'zgartirib bo'lmaydi (faqat super-admin override)
 *  - Talaba uchun joriy faol oynani aniqlash
 */
class RetakePeriodService
{
    /**
     * Yangi qabul oynasini yaratish. Yaratuvchi: Teacher (oquv_bolimi roli)
     * yoki User (superadmin/admin).
     */
    public function create(
        Authenticatable $actor,
        int $specialtyId,
        int $course,
        int $semesterId,
        Carbon $startDate,
        Carbon $endDate,
    ): RetakeApplicationPeriod {
        $this->validateDates($startDate, $endDate);

        // Bir (specialty, course, semester) uchun bittadan oyna unique constraint
        // bilan DB darajasida qo'riqlanadi, lekin foydalanuvchiga aniq xabar
        // berish uchun oldindan tekshirib chiqamiz.
        $existing = RetakeApplicationPeriod::query()
            ->where('specialty_id', $specialtyId)
            ->where('course', $course)
            ->where('semester_id', $semesterId)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'period' => "Bu yo'nalish, kurs va semestr uchun qabul oynasi allaqachon mavjud.",
            ]);
        }

        return DB::transaction(function () use ($actor, $specialtyId, $course, $semesterId, $startDate, $endDate) {
            return RetakeApplicationPeriod::create([
                'specialty_id' => $specialtyId,
                'course' => $course,
                'semester_id' => $semesterId,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'created_by' => $actor->getAuthIdentifier(),
                'created_by_guard' => $actor instanceof Teacher ? 'teacher' : 'web',
            ]);
        });
    }

    /**
     * Sanalarni o'zgartirish — FAQAT superadmin uchun (adolatlilik qoidasi).
     * Boshqa hamma rollar uchun bu metod ValidationException bilan qaytadi.
     */
    public function overrideDates(
        Authenticatable $actor,
        RetakeApplicationPeriod $period,
        Carbon $startDate,
        Carbon $endDate,
    ): RetakeApplicationPeriod {
        // Spatie role tekshiruvi: faqat superadmin
        if (! method_exists($actor, 'hasRole') || ! $actor->hasRole('superadmin')) {
            throw ValidationException::withMessages([
                'period' => 'Qabul oynasi sanalarini faqat super-admin o\'zgartirishi mumkin.',
            ]);
        }

        $this->validateDates($startDate, $endDate);

        $period->update([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);

        return $period->fresh();
    }

    /**
     * Talaba uchun joriy faol oyna (yo'nalish + kurs + semestr bo'yicha).
     * Talabaning level_code'i kursga, semester_id'i semestrga, specialty_id'i
     * yo'nalishga aylantiriladi.
     */
    public function findActiveForStudent(Student $student): ?RetakeApplicationPeriod
    {
        $course = $this->resolveCourseFromStudent($student);
        if ($course === null
            || $student->specialty_id === null
            || $student->semester_id === null) {
            return null;
        }

        return RetakeApplicationPeriod::query()
            ->forStudent((int) $student->specialty_id, $course, (int) $student->semester_id)
            ->active()
            ->first();
    }

    /**
     * Talaba uchun yaqinlashayotgan/o'tib ketgan oyna (faol bo'lmasa ham).
     */
    public function findLatestForStudent(Student $student): ?RetakeApplicationPeriod
    {
        $course = $this->resolveCourseFromStudent($student);
        if ($course === null
            || $student->specialty_id === null
            || $student->semester_id === null) {
            return null;
        }

        return RetakeApplicationPeriod::query()
            ->forStudent((int) $student->specialty_id, $course, (int) $student->semester_id)
            ->orderByDesc('start_date')
            ->first();
    }

    private function resolveCourseFromStudent(Student $student): ?int
    {
        // students.level_code odatda HEMIS'da kurs raqami sifatida keladi
        // (masalan "11" = 1-kurs bakalavr, "21" = 2-kurs va h.k.) yoki to'g'ridan
        // to'g'ri "1", "2", ... bo'lishi mumkin. Avval level_name'dan parse qilamiz.
        if (! empty($student->level_name) && preg_match('/(\d+)/', $student->level_name, $m)) {
            return (int) $m[1];
        }

        // level_code odatda 2-xonali: birinchi raqam — kurs darajasi
        if (! empty($student->level_code)) {
            $code = (string) $student->level_code;
            if (ctype_digit($code)) {
                return strlen($code) === 1 ? (int) $code : (int) substr($code, 0, 1);
            }
        }

        return null;
    }

    private function validateDates(Carbon $startDate, Carbon $endDate): void
    {
        if ($startDate->greaterThan($endDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'Tugash sanasi boshlanish sanasidan keyin bo\'lishi shart.',
            ]);
        }
    }
}
