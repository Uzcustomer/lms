<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('retake_application_windows', function (Blueprint $table) {
            $table->id();

            // Specialty HEMIS ID — students.specialty_id bilan mos keladi
            $table->unsignedBigInteger('specialty_id');
            $table->string('specialty_name')->nullable();

            // Kurs (level) — students.level_code bilan mos
            $table->string('level_code');
            $table->string('level_name')->nullable();

            // Semestr metadata (filtr emas — talaba barcha semestrlarni ko'radi)
            $table->string('semester_code');
            $table->string('semester_name');

            // Sana oralig'i
            $table->date('start_date');
            $table->date('end_date');

            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->timestamps();

            $table->unique(
                ['specialty_id', 'level_code', 'semester_code'],
                'retake_window_unique_idx'
            );
            $table->index(['start_date', 'end_date'], 'retake_window_dates_idx');
            $table->index('specialty_id');

            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_application_windows');
    }
};
