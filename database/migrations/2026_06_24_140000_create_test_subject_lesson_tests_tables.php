<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_subject_lesson_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_subject_lesson_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->default(20);
            $table->unsignedTinyInteger('pass_percent')->nullable();
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('show_result_after_submit')->default(true);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_open')->default(false);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('teachers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('test_subject_lesson_test_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_test_id')->constrained('test_subject_lesson_tests')->cascadeOnDelete();
            $table->enum('type', ['single_choice', 'fill_in_blank']);
            $table->text('prompt');
            $table->text('helper_text')->nullable();
            $table->string('correct_answer_text')->nullable();
            $table->boolean('case_sensitive')->default(false);
            $table->unsignedInteger('points')->default(1);
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('test_subject_lesson_test_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('test_subject_lesson_test_questions')->cascadeOnDelete();
            $table->text('option_text');
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_subject_lesson_test_options');
        Schema::dropIfExists('test_subject_lesson_test_questions');
        Schema::dropIfExists('test_subject_lesson_tests');
    }
};
