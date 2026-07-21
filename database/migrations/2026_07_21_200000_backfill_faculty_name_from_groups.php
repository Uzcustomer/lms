<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Dars kartochkalariga fakultet nomini GURUH ma'lumotidan to'ldirish.
 *
 * Oldingi backfill (snapshot fakultet kontekstidan) "Barcha fakultetlar"
 * bo'yicha tasdiqlangan oqimlarda fakultet konteksti bo'sh bo'lgani uchun
 * ishlamasligi mumkin. Eng ishonchli manba — `groups` jadvali: har bir
 * guruh (`name`) o'z fakultetiga (`department_name`) bog'langan. Kartochkaning
 * `group_name` maydoni guruh nomi bilan aynan mos keladi.
 *
 * 1) Amaliy kartochkalar — guruh nomi bo'yicha to'g'ridan-to'g'ri.
 * 2) Ma'ruza (guruh_name = NULL) va qolganlar — xuddi shu doska+yo'nalish+kurs
 *    dagi to'ldirilgan "qardosh" kartochkadan tarqatiladi.
 *
 * Faqat NULL qiymatlar yangilanadi; xato bo'lsa deploy buzilmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('timetable_cards', 'faculty_name') || !Schema::hasTable('groups')) {
            return;
        }

        try {
            // 1) Guruh nomi → fakultet (department_name)
            $groupFac = DB::table('groups')
                ->whereNotNull('department_name')->where('department_name', '<>', '')
                ->whereNotNull('name')->where('name', '<>', '')
                ->pluck('department_name', 'name');

            foreach ($groupFac as $name => $fac) {
                DB::table('timetable_cards')
                    ->where('group_name', $name)
                    ->whereNull('faculty_name')
                    ->update(['faculty_name' => $fac]);
            }

            // 2) To'ldirilgan kartochkalardan doska+yo'nalish+kurs bo'yicha
            //    qolganlariga (ma'ruzalar) tarqatish.
            $filled = DB::table('timetable_cards')
                ->whereNotNull('faculty_name')->where('faculty_name', '<>', '')
                ->select('board_id', 'specialty_name', 'course', 'faculty_name')
                ->distinct()->get();

            foreach ($filled as $r) {
                DB::table('timetable_cards')
                    ->where('board_id', $r->board_id)
                    ->where('specialty_name', $r->specialty_name)
                    ->where('course', $r->course)
                    ->whereNull('faculty_name')
                    ->update(['faculty_name' => $r->faculty_name]);
            }
        } catch (\Throwable $e) {
            Log::warning('Timetable faculty_name (groups) backfill o\'tkazib yuborildi: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Data backfill — orqaga qaytarilmaydi.
    }
};
