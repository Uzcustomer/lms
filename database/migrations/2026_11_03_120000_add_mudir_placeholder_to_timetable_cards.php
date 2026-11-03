<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Vaqtincha kafedra mudiriga yozish": to'qnashuv hal bo'lguncha karta kafedra mudiri va «Kafedra mudiri xonasi» bilan joylanadi.
 * mudir_placeholder — asl o'qituvchi/xona, sabab, kim/qachon, haftalar (JSON); bo'sh — oddiy karta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('timetable_cards') && !Schema::hasColumn('timetable_cards', 'mudir_placeholder')) {
            Schema::table('timetable_cards', function (Blueprint $table) {
                $table->json('mudir_placeholder')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('timetable_cards', 'mudir_placeholder')) {
            Schema::table('timetable_cards', function (Blueprint $table) {
                $table->dropColumn('mudir_placeholder');
            });
        }
    }
};
