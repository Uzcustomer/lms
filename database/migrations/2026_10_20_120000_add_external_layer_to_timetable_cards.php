<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Haftalik (1-3 kurs) jadval ham sikl kabi ikki qatlamli:
 *   tashqi — o'quv bo'limi tuzadi (umumiy ko'rinish, o'qituvchi/xonasiz): ext_day / ext_pair;
 *   ichki  — kafedra mudiri tuzadi (talaba va o'qituvchi ko'radigan haqiqiy jadval): day / pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_cards', function (Blueprint $table) {
            if (!Schema::hasColumn('timetable_cards', 'ext_day')) {
                $table->unsignedTinyInteger('ext_day')->nullable()->after('pair');
                $table->unsignedTinyInteger('ext_pair')->nullable()->after('ext_day');
            }
        });
    }

    public function down(): void
    {
        Schema::table('timetable_cards', function (Blueprint $table) {
            $table->dropColumn(['ext_day', 'ext_pair']);
        });
    }
};
