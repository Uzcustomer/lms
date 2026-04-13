<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_admission_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students')->onDelete('cascade');

            // Shaxsiy ma'lumotlar
            $table->string('familya')->nullable();
            $table->string('ism')->nullable();
            $table->string('otasining_ismi')->nullable();
            $table->date('tugilgan_sana')->nullable();
            $table->string('jshshir', 14)->nullable();
            $table->string('jinsi', 10)->nullable();
            $table->string('tel1', 20)->nullable();
            $table->string('tel2', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('millat')->nullable();

            // Tug'ilgan joy
            $table->string('tugilgan_davlat')->nullable();
            $table->string('tugilgan_viloyat')->nullable();
            $table->string('tugulgan_tuman')->nullable();

            // Doimiy manzil
            $table->string('doimiy_manzil')->nullable();
            $table->string('yashash_davlat')->nullable();
            $table->string('yashash_viloyat')->nullable();
            $table->string('yashash_tuman')->nullable();
            $table->string('yashash_manzil')->nullable();

            // Pasport
            $table->string('passport_seriya', 10)->nullable();
            $table->string('passport_raqam', 10)->nullable();
            $table->date('passport_sana')->nullable();
            $table->string('passport_joy')->nullable();

            // Ta'lim
            $table->string('oliy_malumot')->nullable();
            $table->string('otm_nomi')->nullable();
            $table->string('talim_turi')->nullable();
            $table->string('talim_shakli')->nullable();
            $table->string('mutaxassislik')->nullable();
            $table->string('toplagan_ball')->nullable();
            $table->string('tolov_shakli')->nullable();
            $table->string('muassasa_nomi')->nullable();
            $table->string('hujjat_seriya')->nullable();
            $table->string('ortalacha_ball')->nullable();

            // Til sertifikatlari
            $table->string('sertifikat_turi')->nullable();
            $table->string('sertifikat_ball')->nullable();
            $table->string('milliy_sertifikat')->nullable();

            // Ota ma'lumotlari
            $table->string('ota_familiya')->nullable();
            $table->string('ota_ismi')->nullable();
            $table->string('ota_sharifi')->nullable();
            $table->string('ota_tel', 20)->nullable();
            $table->string('ota_ish_joyi')->nullable();
            $table->string('ota_lavozimi')->nullable();

            // Ona ma'lumotlari
            $table->string('ona_familiya')->nullable();
            $table->string('ona_ismi')->nullable();
            $table->string('ona_sharifi')->nullable();
            $table->string('ona_tel', 20)->nullable();
            $table->string('ona_ish_joyi')->nullable();
            $table->string('ona_lavozimi')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_admission_data');
    }
};
