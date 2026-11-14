<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fanlar farqi tekshiruvlari: boshqa OTMdan ko'chib kelgan talabaning transkripti
 * bizning o'quv rejamiz bilan solishtiriladi.
 *
 *  - files       — yuklangan PDF'lar (transkript + qo'shimcha ko'chirma);
 *  - scale       — xodim belgilagan baho shkalasi;
 *  - transcript  — o'qilgan qatorlar: text (pdftotext) / ai (Gemini) va qaysi biri asosiy;
 *  - levels      — bosqichlar: har biri tanlangan o'quv reja + "qaysi semestrgacha",
 *                  reja fanlari, qoida/AI moslashtirish, xodim tuzatishlari, GPA, qaror.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subject_difference_checks')) {
            return;
        }

        Schema::create('subject_difference_checks', function (Blueprint $table) {
            $table->id();
            $table->string('student_name')->nullable();
            $table->string('source_institution')->nullable();
            $table->json('files')->nullable();
            $table->json('scale')->nullable();
            $table->json('transcript')->nullable();
            $table->json('levels')->nullable();
            $table->string('status', 20)->default('new'); // new | read | checked | error
            $table->text('error')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->string('created_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_difference_checks');
    }
};
