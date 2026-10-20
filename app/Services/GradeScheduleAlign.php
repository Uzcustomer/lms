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
 *
 * Faqat BO'SH katakka ko'chiriladi. HEMIS ba'zan bir kunning ikki juftligidagi
 * baholarni bitta jadval yozuviga bog'laydi (masalan 16.09: 12-juftlik baholari
 * 13-juftlik darsiga bog'langan). Bunday bahoni ko'chirish talabaning o'sha
 * katakdagi boshqa bahosi ustiga tushirib, ikki juftlikni bittaga yig'ardi va
 * jurnalda bitta juftlik bo'sh qolardi. Endi katakda talabaning shu fandan
 * boshqa faol bahosi bo'lsa, baho joyida qoladi (HEMIS juftligida).
 */
class GradeScheduleAlign
{
    /** @return int to'g'rilangan baho yozuvlari soni */
    public static function run(?string $since = null): int
    {
        $since = $since ?: now('Asia/Tashkent')->subDays(60)->toDateString();

        $candidates = DB::select(
            "SELECT sg.id, sg.student_hemis_id, sg.subject_id,
                    DATE(sch.lesson_date) AS target_date, sch.lesson_pair_code AS target_pair, sch.lesson_pair_name AS target_pair_name
             FROM student_grades sg
             JOIN schedules sch ON sch.schedule_hemis_id = sg.subject_schedule_id AND sch.deleted_at IS NULL
             WHERE sg.deleted_at IS NULL
               AND sg.subject_schedule_id > 0
               AND sg.lesson_date >= ?
               AND sch.lesson_date IS NOT NULL
               AND (sg.lesson_pair_code <> sch.lesson_pair_code OR DATE(sg.lesson_date) <> DATE(sch.lesson_date))
             ORDER BY sg.id",
            [$since . ' 00:00:00']
        );

        $updated = 0;
        $skipped = 0;
        $claimed = []; // shu yurishda boshqa baho egallagan katak
        foreach ($candidates as $c) {
            $cell = $c->student_hemis_id . '|' . $c->subject_id . '|' . $c->target_date . '|' . $c->target_pair;

            $occupied = isset($claimed[$cell]) || DB::table('student_grades')
                ->where('student_hemis_id', $c->student_hemis_id)
                ->where('subject_id', $c->subject_id)
                ->whereDate('lesson_date', $c->target_date)
                ->where('lesson_pair_code', $c->target_pair)
                ->whereNull('deleted_at')
                ->where('id', '<>', $c->id)
                ->exists();

            if ($occupied) {
                $skipped++;
                continue;
            }

            $updated += DB::table('student_grades')->where('id', $c->id)->update([
                'lesson_date' => $c->target_date,
                'lesson_pair_code' => $c->target_pair,
                'lesson_pair_name' => $c->target_pair_name,
            ]);
            $claimed[$cell] = true;
        }

        if ($updated > 0 || $skipped > 0) {
            Log::info('[GradeScheduleAlign] Baholar jadval yozuviga moslandi', [
                'updated' => $updated,
                'skipped_occupied' => $skipped,
                'since' => $since,
            ]);
        }

        return $updated;
    }
}
