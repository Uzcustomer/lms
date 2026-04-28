<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('retake_application_periods', function (Blueprint $table) {
            $table->id();
            // HEMIS specialty_hemis_id ga to'g'ri keladi (students.specialty_id formati)
            $table->unsignedBigInteger('specialty_id');
            $table->unsignedTinyInteger('course');
            // HEMIS semester_hemis_id ga to'g'ri keladi (students.semester_id formati)
            $table->unsignedBigInteger('semester_id');
            $table->date('start_date');
            $table->date('end_date');
            // Yaratuvchi: Teacher (web guard) yoki User — guard bilan
            $table->unsignedBigInteger('created_by');
            $table->string('created_by_guard', 10)->default('web');
            $table->timestamps();

            // Bir (yo'nalish, kurs, semestr) uchun bittadan oyna
            $table->unique(['specialty_id', 'course', 'semester_id'], 'retake_periods_unique_idx');
            // Talaba sahifasi tezkor qidiruvi uchun
            $table->index(['start_date', 'end_date'], 'retake_periods_date_idx');
            $table->index(['specialty_id', 'course'], 'retake_periods_specialty_course_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_application_periods');
    }
};
