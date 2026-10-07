<?php

namespace App\Services;

use App\Models\CurriculumSubject;
use App\Models\CurriculumSubjectTeacher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fan testini ishlashi mumkin bo'lgan guruhlar (hemis id).
 *
 * O'qituvchi sahifasidagi guruhlar ro'yxati ham, kiosk tekshiruvi ham shu
 * yerdan oladi — ikki joyda qoida ajralib ketmasligi uchun.
 */
class FanTestiGroups
{
    /**
     * HEMIS biriktirmasida curriculum_id = fanning curricula_hemis_id si,
     * semester_id esa fanning semester_code i. Bu shartlarsiz bitta subject_id
     * barcha yillar va rejalardagi guruhlarni qaytaradi.
     *
     * Biriktirma to'ldirilmagan fanlar uchun guruhlar joriy o'quv yilining dars
     * jadvalidan olinadi. Hech narsa topilmasa bo'sh ro'yxat qaytadi — test
     * hech kimga ochilmaydi (noto'g'ri keng ro'yxatdan ko'ra shu ma'qul).
     */
    public static function forSubject(?CurriculumSubject $subject): Collection
    {
        if (!$subject?->subject_id || !$subject->curricula_hemis_id || !$subject->semester_code) {
            return collect();
        }

        $assigned = Schema::hasTable('curriculum_subject_teachers')
            ? CurriculumSubjectTeacher::query()
                ->where('subject_id', $subject->subject_id)
                ->where('curriculum_id', $subject->curricula_hemis_id)
                ->where('semester_id', $subject->semester_code)
                ->where('active', true)
                ->whereNotNull('group_id')
                ->pluck('group_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
            : collect();

        if ($assigned->isNotEmpty()) {
            return $assigned;
        }

        return DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->where('sch.subject_id', $subject->subject_id)
            ->where('sch.semester_code', $subject->semester_code)
            ->where('g.curriculum_hemis_id', $subject->curricula_hemis_id)
            ->where('sch.education_year_current', true)
            ->whereNull('sch.deleted_at')
            ->distinct()
            ->pluck('sch.group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
