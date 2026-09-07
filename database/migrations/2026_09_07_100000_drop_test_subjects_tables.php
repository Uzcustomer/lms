<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Test fanlar" moduli olib tashlandi — uning jadvallari ham tushiriladi.
 * Yangi "Fan testlari" (fan_testlari*) moduli bunga bog'liq emas.
 *
 * Tartib muhim: bolalar avval, chunki foreign key'lar ota jadvallarga bog'liq.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'test_subject_lesson_test_answers',
            'test_subject_lesson_test_attempts',
            'test_subject_lesson_test_options',
            'test_subject_lesson_test_questions',
            'test_subject_lesson_tests',
            'test_subject_lessons',
            'test_subject_groups',
            'test_subjects',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();

        // Fayllari o'chirilgan migratsiyalar jurnalda osilib qolmasin.
        DB::table('migrations')->where('migration', 'like', '%test_subject%')->delete();
    }

    public function down(): void
    {
        // Modul butunlay olib tashlangan — jadvallar tiklanmaydi.
    }
};
