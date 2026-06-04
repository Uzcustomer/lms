<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Original create migration ustunlaridan ba'zilari productionda yo'q
 * (doimiy_manzil, tolov_shakli, milliy_sertifikat allaqachon oldingi migrationda
 * qo'shildi — bu yerda qolganlarini tekshirib qo'shamiz). Idempotent — har bir
 * ustun mavjudligi tekshiriladi, faqat yo'q bo'lganlari qo'shiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            // String ustunlar
            $stringCols = [
                // Shaxsiy
                'familya', 'ism', 'otasining_ismi', 'jshshir', 'jinsi',
                'tel1', 'tel2', 'email', 'millat',
                // Tug'ilgan joy
                'tugilgan_davlat', 'tugilgan_viloyat', 'tugulgan_tuman',
                // Yashash
                'yashash_davlat', 'yashash_viloyat', 'yashash_tuman', 'yashash_manzil',
                // Pasport
                'passport_seriya', 'passport_raqam', 'passport_joy',
                // Ta'lim
                'oliy_malumot', 'otm_nomi', 'talim_turi', 'talim_shakli',
                'mutaxassislik', 'muassasa_nomi', 'hujjat_seriya', 'ortalacha_ball',
                // Sertifikat
                'sertifikat_turi', 'sertifikat_ball',
                // Ota
                'ota_familiya', 'ota_ismi', 'ota_sharifi', 'ota_tel',
                'ota_ish_joyi', 'ota_lavozimi',
                // Ona
                'ona_familiya', 'ona_ismi', 'ona_sharifi', 'ona_tel',
                'ona_ish_joyi', 'ona_lavozimi',
            ];

            foreach ($stringCols as $col) {
                if (!Schema::hasColumn('student_admission_data', $col)) {
                    $table->string($col)->nullable();
                }
            }

            // Date ustunlar
            foreach (['tugilgan_sana', 'passport_sana'] as $col) {
                if (!Schema::hasColumn('student_admission_data', $col)) {
                    $table->date($col)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        // Hech narsa qilmaymiz — bu ustunlar original migrationga tegishli
    }
};
