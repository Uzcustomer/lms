<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fan sozlamasining aniqlovchilari: fakultet va o'quv reja (HEMIS curricula_hemis_id). Bir yo'nalish va kursdagi fan turli
 * fakultet / o'quv rejada turli sikl kunlari soniga ega bo'lishi mumkin (masalan Ichki kasalliklar: 2-son davolash — 18,
 * Xalqaro ta'lim xd/23 — 21, xd/22 — 13). Qidiruv: (fakultet + o'quv reja) → (fakultet) → (umumiy).
 *
 * Umumiy yozuv — ('', 0): mavjud yozuvlar shu holatda qoladi (o'zgarmaydi). NULL emas: MySQL noyob kalitda NULL larni teng
 * hisoblamaydi — bir xil umumiy yozuv ikki marta saqlanib qolardi. Fan rejimi (sikl / hafta almashinuvi) — faqat umumiy yozuvda.
 */
return new class extends Migration
{
    private const TABLE = 'timetable_subject_settings';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }
        Schema::table(self::TABLE, function (Blueprint $table) {
            if (!Schema::hasColumn(self::TABLE, 'faculty_name')) {
                // 150 belgi: noyob kalit uzunligi (utf8mb4) InnoDB chegarasidan (3072 bayt) oshmasin.
                $table->string('faculty_name', 150)->default('')->after('subject_name');
            }
            if (!Schema::hasColumn(self::TABLE, 'curriculum_hemis_id')) {
                $table->unsignedBigInteger('curriculum_hemis_id')->default(0)->after('faculty_name');
            }
        });
        Schema::table(self::TABLE, function (Blueprint $table) {
            if (Schema::hasIndex(self::TABLE, 'tss_unique')) {
                $table->dropUnique('tss_unique');
            }
            if (!Schema::hasIndex(self::TABLE, 'tss_scope_unique')) {
                $table->unique(['board_id', 'specialty_name', 'course', 'subject_name', 'faculty_name', 'curriculum_hemis_id'], 'tss_scope_unique');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, 'faculty_name')) {
            return;
        }
        // Aniqlovchili yozuvlar eski noyob kalitga sig'maydi — olib tashlanadi (umumiy yozuvlar qoladi).
        DB::table(self::TABLE)->where('faculty_name', '!=', '')->orWhere('curriculum_hemis_id', '!=', 0)->delete();
        Schema::table(self::TABLE, function (Blueprint $table) {
            if (Schema::hasIndex(self::TABLE, 'tss_scope_unique')) {
                $table->dropUnique('tss_scope_unique');
            }
            if (!Schema::hasIndex(self::TABLE, 'tss_unique')) {
                $table->unique(['board_id', 'specialty_name', 'course', 'subject_name'], 'tss_unique');
            }
        });
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(['faculty_name', 'curriculum_hemis_id']);
        });
    }
};
