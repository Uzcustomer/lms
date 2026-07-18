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
            $table->unsignedBigInteger('test_subject_lesson_test_id');
            $table->unsignedBigInteger('test_subject_id');
            $table->unsignedBigInteger('test_subject_lesson_id');
            $table->unsignedBigInteger('student_id');
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

            $table->foreign('test_subject_lesson_test_id', 'tslta_test_fk')
                ->references('id')->on('test_subject_lesson_tests')
                ->cascadeOnDelete();
            $table->foreign('test_subject_id', 'tslta_subject_fk')
                ->references('id')->on('test_subjects')
                ->cascadeOnDelete();
            $table->foreign('test_subject_lesson_id', 'tslta_lesson_fk')
                ->references('id')->on('test_subject_lessons')
                ->cascadeOnDelete();
            $table->foreign('student_id', 'tslta_student_fk')
                ->references('id')->on('students')
                ->cascadeOnDelete();
            $table->unique(['test_subject_lesson_test_id', 'student_id'], 'ts_test_student_unique');
        });

        Schema::create('test_subject_lesson_test_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attempt_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('selected_option_id')->nullable();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('points_earned')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->foreign('attempt_id', 'tslta_answer_attempt_fk')
                ->references('id')->on('test_subject_lesson_test_attempts')
                ->cascadeOnDelete();
            $table->foreign('question_id', 'tslta_answer_question_fk')
                ->references('id')->on('test_subject_lesson_test_questions')
                ->cascadeOnDelete();
            $table->foreign('selected_option_id', 'tslta_answer_option_fk')
                ->references('id')->on('test_subject_lesson_test_options')
                ->nullOnDelete();
            $table->unique(['attempt_id', 'question_id'], 'ts_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_subject_lesson_test_answers');
        Schema::dropIfExists('test_subject_lesson_test_attempts');
    }
};
