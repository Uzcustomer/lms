<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bo'lajak kontingent proyeksiyasi: kelasi o'quv yili uchun har bir
 * yo'nalish + kursda kutilayotgan talaba soni. Joriy talabalar keyingi
 * kursga o'tkazilmagan bo'lsa ham (avgust oxirida o'tkaziladi), oqim/guruh
 * sonini oldindan hisoblash uchun kerak. Avtomatik bashorat joriy (k-1)
 * kurs talabalaridan olinadi, foydalanuvchi qo'lda tuzatishi mumkin —
 * shu tuzatishlar shu jadvalda saqlanadi.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('contingent_projections', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 20);      // o'qitiladigan o'quv yili, masalan "2026-2027"
            $table->string('specialty_code', 50);
            $table->string('specialty_name')->nullable();
            $table->string('level_code', 20);          // maqsad kurs level_code (11..16)
            $table->unsignedInteger('expected_count')->default(0);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['academic_year', 'specialty_code', 'level_code'], 'contingent_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contingent_projections');
    }
};
