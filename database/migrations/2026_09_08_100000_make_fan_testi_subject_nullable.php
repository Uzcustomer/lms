<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Test to'plami fansiz ham yaratilsin — "qoralama".
 *
 * Dars jadvali va guruh biriktirmalari hali yakunlanmagani uchun o'qituvchi
 * fanni tanlay olmaydi. Shu sababli to'plam nomi va savollari oldindan
 * kiritiladi, fan esa keyinroq biriktiriladi. Fansiz to'plam talabalarga
 * ochilmaydi (kiosk uni to'sadi), ya'ni yarim tayyor test tarqalib ketmaydi.
 *
 * MODIFY ishlatiladi: ustunni nullable qiladi, tashqi kalitni buzmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fan_testlari') || !Schema::hasColumn('fan_testlari', 'curriculum_subject_id')) {
            return;
        }

        DB::statement('ALTER TABLE fan_testlari MODIFY curriculum_subject_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('fan_testlari') || !Schema::hasColumn('fan_testlari', 'curriculum_subject_id')) {
            return;
        }

        // Fansiz to'plamlar qolgan bo'lsa orqaga qaytarib bo'lmaydi.
        if (DB::table('fan_testlari')->whereNull('curriculum_subject_id')->exists()) {
            return;
        }

        DB::statement('ALTER TABLE fan_testlari MODIFY curriculum_subject_id BIGINT UNSIGNED NOT NULL');
    }
};
