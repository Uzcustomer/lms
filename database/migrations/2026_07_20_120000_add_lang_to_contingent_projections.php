<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bo'lajak kontingent bashoratiga TIL qo'shish: yangi 1-kurs qabuli o'zbek/rus/ingliz
 * tillari bo'yicha alohida kiritiladi (aks holda hammasi bitta tilda — bitta oqim
 * bo'lib qolardi). Har bir yozuv endi (yil, yo'nalish, kurs, til) bo'yicha noyob.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('contingent_projections', function (Blueprint $table) {
            $table->string('lang', 10)->default('uz')->after('level_code'); // uz | rus | ing
        });

        // Eski noyob indeksni (til'siz) yangisiga (til bilan) almashtiramiz
        Schema::table('contingent_projections', function (Blueprint $table) {
            try {
                $table->dropUnique('contingent_unique');
            } catch (\Throwable $e) {
                // indeks bo'lmasa — o'tkazib yuboramiz
            }
        });
        Schema::table('contingent_projections', function (Blueprint $table) {
            $table->unique(['academic_year', 'specialty_code', 'level_code', 'lang'], 'contingent_unique_lang');
        });
    }

    public function down(): void
    {
        Schema::table('contingent_projections', function (Blueprint $table) {
            try {
                $table->dropUnique('contingent_unique_lang');
            } catch (\Throwable $e) {
            }
            $table->unique(['academic_year', 'specialty_code', 'level_code'], 'contingent_unique');
            $table->dropColumn('lang');
        });
    }
};
