<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fan_testi_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fan_testi_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('student_hemis_id')->nullable()->index();

            // Talaba topshirgan paytdagi ma'lumotlari — keyin guruhi o'zgarsa ham
            // jurnalda o'sha paytdagi holat ko'rinib tursin.
            $table->string('student_name');
            $table->string('student_id_number')->nullable()->index();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->string('group_name')->nullable()->index();
            $table->string('faculty_name')->nullable();
            $table->string('specialty_name')->nullable();

            $table->enum('status', ['in_progress', 'submitted', 'expired'])->default('in_progress')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->unsignedInteger('questions_count')->default(0);
            $table->unsignedInteger('answers_count')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('total_points')->default(0);
            $table->decimal('score', 8, 2)->default(0);
            $table->decimal('percent', 5, 2)->nullable();
            $table->boolean('is_passed')->default(false);

            // Savollar fan_testlari.questions JSON massivida turadi va o'chirilganda
            // indekslar qayta raqamlanadi. Shuning uchun urinish boshlanganda
            // savollar shu yerga nusxalanadi — o'qituvchi savolni o'zgartirsa ham
            // talabaning ishi va baholash o'zgarmaydi.
            $table->json('questions_snapshot')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign('fan_testi_id', 'fta_test_fk')
                ->references('id')->on('fan_testlari')
                ->cascadeOnDelete();
            $table->foreign('student_id', 'fta_student_fk')
                ->references('id')->on('students')
                ->cascadeOnDelete();

            // Bitta talaba bitta to'plamni faqat bir marta topshiradi.
            $table->unique(['fan_testi_id', 'student_id'], 'fta_test_student_unique');
            $table->index(['fan_testi_id', 'group_name'], 'fta_test_group_idx');
        });

        Schema::create('fan_testi_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attempt_id');

            // Savollar fan_testlari.questions JSON ustunida saqlanadi, shuning uchun
            // savol ID emas, o'sha massivdagi indeks yoziladi.
            $table->unsignedInteger('question_index');
            $table->string('question_type', 32)->default('single_choice');
            $table->text('question_prompt')->nullable();

            $table->unsignedInteger('selected_option_index')->nullable();
            $table->text('selected_option_text')->nullable();
            $table->text('answer_text')->nullable();
            $table->text('correct_answer_text')->nullable();

            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('points_earned')->default(0);
            $table->unsignedInteger('points_possible')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->foreign('attempt_id', 'ftaa_attempt_fk')
                ->references('id')->on('fan_testi_attempts')
                ->cascadeOnDelete();
            $table->unique(['attempt_id', 'question_index'], 'ftaa_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fan_testi_attempt_answers');
        Schema::dropIfExists('fan_testi_attempts');
    }
};
