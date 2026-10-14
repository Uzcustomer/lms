<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bahoni o'zi bog'langan jadval yozuviga moslash.
 *
 * HEMIS bahoni dars jadvaliga ichki ID (subject_schedule_id) orqali bog'laydi.
 * Dars jadvalda boshqa juftlik yoki kunga ko'chirilsa, baho yozuvi eski
 * juftlik/sanani saqlab qoladi — HEMIS da baho baribir to'g'ri ustunda turadi,
 * LMS jurnali esa bahoni sana + juftlik bo'yicha joylashtirgani uchun uni
 * ko'rmaydi va kunni "baho qo'yilmagan" deb hisoblaydi.
 *
 * Bu qadam baho importidan keyin ishlaydi: faol jadval yozuviga bog'langan,
 * lekin sanasi yoki juftligi undan farq qiladigan baholarni jadvaldagisiga
 * to'g'rilaydi. Jadval ID si yo'q baholar (LMS da qo'yilgan, MT va h.k.) va
 * jadvalda topilmaganlari o'zgarmaydi. updated_at tegilmaydi — baho o'zgarmaydi.
 */
class GradeScheduleAlign
{
    /** @return int to'g'rilangan baho yozuvlari soni */
    public static function run(?string $since = null): int
    {
        $since = $since ?: now('Asia/Tashkent')->subDays(60)->toDateString();

        $updated = DB::update(
            "UPDATE student_grades sg
             JOIN schedules sch ON sch.schedule_hemis_id = sg.subject_schedule_id AND sch.deleted_at IS NULL
             SET sg.lesson_date = DATE(sch.lesson_date),
                 sg.lesson_pair_code = sch.lesson_pair_code,
                 sg.lesson_pair_name = sch.lesson_pair_name
             WHERE sg.deleted_at IS NULL
               AND sg.subject_schedule_id > 0
               AND sg.lesson_date >= ?
               AND sch.lesson_date IS NOT NULL
               AND (sg.lesson_pair_code <> sch.lesson_pair_code OR DATE(sg.lesson_date) <> DATE(sch.lesson_date))",
            [$since . ' 00:00:00']
        );

        if ($updated > 0) {
            Log::info('[GradeScheduleAlign] Baholar jadval yozuviga moslandi', ['updated' => $updated, 'since' => $since]);
        }

        return $updated;
    }
}
