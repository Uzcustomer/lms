<?php

namespace App\Services\Retake;

use App\Models\RetakeApplication;
use App\Models\RetakeGrade;
use App\Models\RetakeGroup;
use App\Models\Teacher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Qayta o'qish jurnali — kunlik baholar.
 *
 * Asosiy mantiq:
 *  - Guruh `start_date` dan `end_date` gacha har kun jurnalda alohida ustun
 *  - O'qituvchi guruh muddati ichida istalgan kunga baho qo'ya oladi (oxirgi
 *    kungacha, ya'ni guruh muddati ichida tahrirlash erkin)
 *  - Guruh tugagandan keyin (status=completed) yoki end_date'dan keyin
 *    baho qo'yib bo'lmaydi (faqat super-admin override)
 */
class RetakeJournalService
{
    /**
     * Guruh ichidagi kunlar (start_date dan end_date gacha).
     *
     * @return array<int, string>  Y-m-d formatdagi sanalar
     */
    public function lessonDates(RetakeGroup $group): array
    {
        if (!$group->start_date || !$group->end_date) {
            return [];
        }

        $dates = [];
        $cursor = Carbon::parse($group->start_date);
        $end = Carbon::parse($group->end_date);
        while ($cursor->lte($end)) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }
        return $dates;
    }

    /**
     * Guruhdagi tasdiqlangan arizalar (talaba ro'yxati).
     *
     * @return Collection<RetakeApplication>
     */
    public function applications(RetakeGroup $group): Collection
    {
        return RetakeApplication::query()
            ->where('retake_group_id', $group->id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->with(['group.student'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Guruh uchun barcha baholar — [application_id => [Y-m-d => RetakeGrade]] xarita.
     */
    public function gradesMap(RetakeGroup $group): array
    {
        $rows = RetakeGrade::query()
            ->where('retake_group_id', $group->id)
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->lesson_date)->format('Y-m-d');
            $map[$row->application_id][$date] = $row;
        }
        return $map;
    }

    /**
     * Guruhga tegishli o'qituvchi shu actor ekanligini tekshirish.
     */
    public function isAssignedTeacher(RetakeGroup $group, Teacher $teacher): bool
    {
        return (int) $group->teacher_id === (int) $teacher->id;
    }

    /**
     * Guruh hozir tahrir qilinishi mumkinmi?
     * (start_date <= bugun <= end_date) yoki status `forming`/`scheduled`/`in_progress`.
     */
    public function isEditable(RetakeGroup $group): bool
    {
        if (!$group->start_date || !$group->end_date) {
            return false;
        }
        $today = Carbon::today();
        if ($group->end_date->lt($today)) {
            return false;
        }
        return in_array($group->status, [
            RetakeGroup::STATUS_FORMING,
            RetakeGroup::STATUS_SCHEDULED,
            RetakeGroup::STATUS_IN_PROGRESS,
        ], true);
    }

    /**
     * Bitta katakka baho qo'yish/tahrirlash.
     */
    public function saveGrade(
        RetakeGroup $group,
        int $applicationId,
        string $lessonDate,
        ?float $grade,
        ?string $comment,
        Teacher $actor,
        bool $isAdmin = false,
    ): RetakeGrade {
        // Sana guruh muddati ichida ekanini tekshirish
        $valid = in_array($lessonDate, $this->lessonDates($group), true);
        if (!$valid) {
            throw ValidationException::withMessages([
                'lesson_date' => 'Sana guruh muddati ichida bo\'lishi kerak (' . $group->start_date->format('Y-m-d') . ' dan ' . $group->end_date->format('Y-m-d') . ' gacha)',
            ]);
        }

        // Tahrir mumkinmi?
        if (!$isAdmin && !$this->isEditable($group)) {
            throw ValidationException::withMessages([
                'group' => 'Bu guruh muddati tugagan, baho qo\'yib bo\'lmaydi',
            ]);
        }

        // Ariza guruhga tegishli ekanini tekshirish
        $app = RetakeApplication::where('id', $applicationId)
            ->where('retake_group_id', $group->id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->first();
        if (!$app) {
            throw ValidationException::withMessages([
                'application_id' => 'Ariza guruhga tegishli emas yoki tasdiqlanmagan',
            ]);
        }

        // Baho oralig'i 0..100
        if ($grade !== null && ($grade < 0 || $grade > 100)) {
            throw ValidationException::withMessages([
                'grade' => 'Baho 0 dan 100 gacha bo\'lishi kerak',
            ]);
        }

        return RetakeGrade::updateOrCreate(
            [
                'retake_group_id' => $group->id,
                'application_id' => $app->id,
                'lesson_date' => $lessonDate,
            ],
            [
                'student_hemis_id' => $app->student_hemis_id,
                'grade' => $grade,
                'comment' => $comment,
                'graded_by_user_id' => $actor->id,
                'graded_by_name' => $actor->full_name,
                'graded_at' => now(),
            ]
        );
    }
}
