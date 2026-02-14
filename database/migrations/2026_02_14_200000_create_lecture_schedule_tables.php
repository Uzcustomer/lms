<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecture_schedule_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('uploaded_by_guard', 20)->default('web'); // web or teacher
            $table->string('file_name');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('conflicts_count')->default(0);
            $table->unsignedInteger('hemis_mismatches_count')->default(0);
            $table->string('semester_code')->nullable();
            $table->string('education_year')->nullable();
            $table->enum('status', ['processing', 'completed', 'error'])->default('processing');
            $table->timestamps();
        });

        Schema::create('lecture_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('lecture_schedule_batches')->cascadeOnDelete();
            $table->tinyInteger('week_day'); // 1=Dushanba ... 6=Shanba
            $table->string('lesson_pair_code')->nullable();
            $table->string('lesson_pair_name')->nullable();
            $table->time('lesson_pair_start_time')->nullable();
            $table->time('lesson_pair_end_time')->nullable();
            $table->string('group_name');
            $table->unsignedBigInteger('group_id')->nullable(); // hemis group id
            $table->string('subject_name');
            $table->unsignedBigInteger('subject_id')->nullable(); // hemis subject id
            $table->string('employee_name')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable(); // hemis employee id
            $table->string('auditorium_name')->nullable();
            $table->string('training_type_name')->nullable();
            // Hemis solishtirish natijalari
            $table->enum('hemis_status', ['not_checked', 'match', 'partial', 'mismatch', 'not_found'])->default('not_checked');
            $table->json('hemis_diff')->nullable(); // farqlar ro'yxati
            // Ichki konflikt
            $table->boolean('has_conflict')->default(false);
            $table->json('conflict_details')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'week_day', 'lesson_pair_code']);
            $table->index(['batch_id', 'employee_name']);
            $table->index(['batch_id', 'auditorium_name']);
            $table->index(['batch_id', 'group_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecture_schedules');
        Schema::dropIfExists('lecture_schedule_batches');
    }
};
