<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * hemis_quiz_results: qisqa matn ustunlarini kengaytirish.
 *
 * Moodle'dan "direction" ga 50 belgidan uzun qiymat kela boshladi
 * ("Ordinatura 2026/2027/1-kurs" kabi) va MySQL "Data too long" bilan
 * butun upsert paketini rad etdi — bitta uzun qator 200 ta natijani
 * to'xtatardi. Bu ham Moodle push'iga (MoodleImportController), ham
 * "Yangilash" tugmasiga (MoodleQuizPullService) ta'sir qilardi.
 *
 * faculty, semester, quiz_type, shakl ham shu xavf ostida — hammasi 255 ga.
 */
return new class extends Migration
{
    private const COLUMNS = ['faculty', 'direction', 'semester', 'quiz_type', 'shakl'];

    public function up(): void
    {
        Schema::table('hemis_quiz_results', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->string($column, 255)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Qisqartirish ma'lumotni kesishi mumkin — ataylab faqat eski
        // uzunliklarga qaytariladi, qator bo'lsa MySQL o'zi rad etadi.
        Schema::table('hemis_quiz_results', function (Blueprint $table) {
            $table->string('faculty', 50)->nullable()->change();
            $table->string('direction', 50)->nullable()->change();
            $table->string('semester', 20)->nullable()->change();
            $table->string('quiz_type', 50)->nullable()->change();
            $table->string('shakl', 50)->nullable()->change();
        });
    }
};
