<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sikl kalendari endi qatorni GURUH emas, OQIM bo'yicha quradi: `group_name`
 * ustunida kartaning `oqim_label` qiymati ("1-oqim") saqlanadi.
 *
 * Eski yozuvlarda u yerda asosiy guruh kodi ("d1/23-01") turadi — yangi qator
 * kalitlariga mos kelmaydi va sikl jadvalida "osilib" qoladi. Shu sabab eski
 * joylashuvlar tozalanadi; jadval "Avtomatik joylash" bilan qayta quriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('timetable_cycle_placements')) {
            return;
        }

        // Yangi nom har doim "…N-oqim" bilan tugaydi (oldida fakultet, oxirida til
        // bo'lishi mumkin). Eski, guruh kodli yozuvlarda "-oqim" umuman uchramaydi.
        DB::table('timetable_cycle_placements')
            ->where('group_name', 'not like', '%-oqim%')
            ->delete();
    }

    public function down(): void
    {
        // O'chirilgan joylashuvlarni tiklab bo'lmaydi — qaytarish yo'q.
    }
};
