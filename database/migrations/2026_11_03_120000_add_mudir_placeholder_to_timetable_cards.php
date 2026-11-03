<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Vaqtincha kafedra mudiriga yozish": to'qnashuv hal bo'lguncha dars SHU HAFTADA kafedra mudiri va «Kafedra mudiri xonasi» bilan
 * joylanadi (karta asosiy o'qituvchisi/xonasi o'zgarmaydi). Haftalik yozuvda mudir_placeholder — mudir (id, nomi, HEMIS id), shu
 * haftaning asl xonasi (override), asl o'qituvchi nomi, sabab, kim/qachon (JSON); bo'sh — oddiy yozuv.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('timetable_card_overrides') && !Schema::hasColumn('timetable_card_overrides', 'mudir_placeholder')) {
            Schema::table('timetable_card_overrides', function (Blueprint $table) {
                $table->json('mudir_placeholder')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('timetable_card_overrides', 'mudir_placeholder')) {
            Schema::table('timetable_card_overrides', function (Blueprint $table) {
                $table->dropColumn('mudir_placeholder');
            });
        }
    }
};
