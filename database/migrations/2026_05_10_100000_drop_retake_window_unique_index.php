<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * retake_application_windows jadvalidagi unique indexni olib tashlash.
 *
 * Avval bir xil (sessiya, fakultet, yo'nalish, kurs, semestr) kombinatsiyasi
 * uchun bitta oyna yaratilishi mumkin edi. Foydalanuvchilar bir xil tarkibda
 * bir nechta oyna ochishni so'ragani sababli (masalan, ikki davrda bir yo'nalish
 * uchun qayta o'qish), bu cheklov olib tashlandi.
 *
 * Soft-deleted yozuvlar bilan to'qnashishni oldini oluvchi mantiq ham ortiqcha
 * bo'lib qoladi — endi xohlagancha takror oyna yaratish mumkin.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('retake_application_windows')) {
            return;
        }

        Schema::table('retake_application_windows', function (Blueprint $table) {
            try {
                $table->dropUnique('retake_window_unique_idx');
            } catch (\Throwable $e) {
                // indeks mavjud bo'lmasa o'tkazib yuboramiz
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('retake_application_windows')) {
            return;
        }

        // Tiklash uchun avvalgi indeks
        Schema::table('retake_application_windows', function (Blueprint $table) {
            try {
                $table->unique(
                    ['session_id', 'department_hemis_id', 'specialty_id', 'level_code', 'semester_code'],
                    'retake_window_unique_idx'
                );
            } catch (\Throwable $e) {
                //
            }
        });
    }
};
