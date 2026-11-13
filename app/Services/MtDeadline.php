<?php

namespace App\Services;

use App\Models\CurriculumSubject;
use App\Models\Group;
use App\Models\Independent;
use App\Models\Semester;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mustaqil ta'lim (MT) topshiriq muddati — yagona qoida: guruh × fanning dars jadvalidagi HAQIQIY
 * dars sanalaridan, sozlama bo'yicha (mt_deadline_type: last — oxirgi dars, before_last — oxirgidan
 * oldingi; bitta dars bo'lsa — o'sha kun), soati — mt_deadline_time. Kelgusi darslar taxmin
 * qilinmaydi. Talaba sahifalari, mobil ilova va fayl yuklash shu qoidadan foydalanadi; muddatdan
 * keyin yuklash faqat tasdiqlangan sababli ariza (SababliMtWindow) bilan, superadmin esa jurnaldan.
 */
class MtDeadline
{
    /** Guruh × fan × semestr bo'yicha muddat sanasi (Y-m-d); jadvalda dars bo'lmasa — null */
    public static function date(string $groupHemisId, $subjectId, string $semesterCode): ?string
    {
        $dates = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->whereNotNull('lesson_date')
            ->pluck('lesson_date');

        return self::fromLessonDates($dates);
    }

    /** Dars sanalaridan muddat (sozlama bo'yicha) */
    public static function fromLessonDates(Collection $lessonDates): ?string
    {
        $dates = $lessonDates->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))->unique()->sort()->values();
        if ($dates->isEmpty()) {
            return null;
        }
        if ($dates->count() === 1 || Setting::get('mt_deadline_type', 'before_last') === 'last') {
            return $dates->last();
        }

        return $dates[$dates->count() - 2];
    }

    /** Muddat tugaydigan payt — sana va sozlamadagi soat (mt_deadline_time) */
    public static function closesAt(string $date): Carbon
    {
        $parts = explode(':', (string) Setting::get('mt_deadline_time', '17:00'));

        return Carbon::parse($date)->setTime((int) ($parts[0] ?? 17), (int) ($parts[1] ?? 0), 0);
    }

    /**
     * Topshiriq muddati — dars jadvalidan; bazadagisi farq qilsa yangilanadi (ro'yxatlar shundan
     * ko'rsatadi). Jadvalda dars topilmasa — bazadagi qiymat.
     */
    public static function forIndependent(Independent $independent): ?string
    {
        $subjectId = CurriculumSubject::where('curriculum_subject_hemis_id', $independent->subject_hemis_id)->value('subject_id');
        $date = $subjectId ? self::date((string) $independent->group_hemis_id, $subjectId, (string) $independent->semester_code) : null;
        if ($date === null) {
            return $independent->deadline ? Carbon::parse($independent->deadline)->format('Y-m-d') : null;
        }
        if ((string) $independent->deadline !== $date) {
            $independent->update(['deadline' => $date]);
        }

        return $date;
    }

    /**
     * Guruh × fan uchun avtomatik MT topshirig'i (bo'lmasa yaratiladi) — dars jadvalidan, muddati
     * shu qoida bo'yicha. Jadvalda dars bo'lmasa — null.
     */
    public static function ensureIndependent(string $groupHemisId, CurriculumSubject $subject, string $semesterCode): ?Independent
    {
        $lessons = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subject->subject_id)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->whereNotNull('lesson_date')
            ->orderBy('lesson_date')
            ->get();
        $deadline = self::fromLessonDates($lessons->pluck('lesson_date'));
        if ($deadline === null) {
            return null;
        }
        $latest = $lessons->last();
        $group = Group::where('group_hemis_id', $groupHemisId)->first();
        $semester = $group ? Semester::where('code', $semesterCode)->where('curriculum_hemis_id', $group->curriculum_hemis_id)->first() : null;

        return Independent::updateOrCreate(
            [
                'group_hemis_id' => $groupHemisId,
                'subject_hemis_id' => $subject->curriculum_subject_hemis_id,
                'semester_code' => $semesterCode,
            ],
            [
                'schedule_id' => $latest->id,
                'group_name' => $latest->group_name,
                'teacher_hemis_id' => $latest->employee_id,
                'teacher_name' => $latest->employee_name ?? '',
                'teacher_short_name' => '',
                'department_hemis_id' => $latest->faculty_id ?? '',
                'deportment_name' => $latest->faculty_name ?? '',
                'start_date' => Carbon::parse($lessons->first()->lesson_date)->format('Y-m-d'),
                'semester_hemis_id' => $semester?->semester_hemis_id ?? '',
                'semester_name' => $latest->semester_name ?? '',
                'subject_name' => $subject->subject_name,
                'user_id' => 0,
                'deadline' => $deadline,
            ]
        );
    }
}
