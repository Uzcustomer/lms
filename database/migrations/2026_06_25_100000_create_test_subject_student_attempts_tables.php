<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_subject_lesson_test_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_subject_lesson_test_id')->constrained('test_subject_lesson_tests')->cascadeOnDelete();
            $table->foreignId('test_subject_id')->constrained('test_subjects')->cascadeOnDelete();
            $table->foreignId('test_subject_lesson_id')->constrained('test_subject_lessons')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedBigInteger('student_hemis_id')->nullable()->index();
            $table->enum('status', ['in_progress', 'submitted', 'expired'])->default('in_progress')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('answers_count')->default(0);
            $table->unsignedInteger('total_points')->default(0);
            $table->decimal('score', 8, 2)->default(0);
            $table->decimal('percent', 5, 2)->nullable();
            $table->boolean('is_passed')->default(false);
            $table->json('question_order')->nullable();
            $table->timestamps();

            $table->unique(['test_subject_lesson_test_id', 'student_id'], 'ts_test_student_unique');
        });

        Schema::create('test_subject_lesson_test_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('test_subject_lesson_test_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('test_subject_lesson_test_questions')->cascadeOnDelete();
            $table->foreignId('selected_option_id')->nullable()->constrained('test_subject_lesson_test_options')->nullOnDelete();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('points_earned')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id'], 'ts_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_subject_lesson_test_answers');
        Schema::dropIfExists('test_subject_lesson_test_attempts');
    }
};
