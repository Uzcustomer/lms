<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HEMIS solishtirish qo'ygan balanslash darslari (jurnal ustunlari soni KTR bilan teng bo'lishi uchun, bandliksiz). HEMIS'dan qaytgan
 * dars shu yozuv bilan tanib olinadi: to'qnashuv, "Bugungi darslar (HEMIS)" va boshqalarda to'qnashuv / bandlik hisoblanmaydi.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('hemis_balance_lessons')) {
            return;
        }
        Schema::create('hemis_balance_lessons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('board_id')->nullable();
            $table->unsignedBigInteger('group_hemis_id');
            $table->unsignedBigInteger('subject_id');
            $table->date('lesson_date');
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->string('training_type_code', 20);
            $table->unsignedSmallInteger('week')->nullable();   // "N-hafta darsi o'rniga"
            $table->timestamps();

            $table->unique(['group_hemis_id', 'subject_id', 'lesson_date', 'start_time', 'training_type_code'], 'hemis_balance_lessons_unique');
            $table->index('lesson_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hemis_balance_lessons');
    }
};
