<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Talabalar so'rovnoma uchun 2 ta jadval:
 *  - student_survey_completions: kim bajarganini qayd qilish (har talaba 1 marta)
 *  - student_survey_answers: anonim javoblar (talaba bog'lanmagan, faqat token)
 *
 * Survey ta'rifi (savollar, muddat) — config/student_survey.php ichida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_survey_completions', function (Blueprint $table) {
            $table->id();
            $table->string('survey_key', 64);                 // config'dagi survey identifikatori
            $table->unsignedBigInteger('student_hemis_id');
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['survey_key', 'student_hemis_id'], 'survey_completion_unique');
        });

        Schema::create('student_survey_answers', function (Blueprint $table) {
            $table->id();
            $table->string('survey_key', 64);
            $table->uuid('session_token');                    // har bajarish uchun yangi UUID — anonim
            $table->string('question_id', 32);                // config'dagi savol id
            $table->text('answer')->nullable();               // matn / variant kaliti / "Boshqa: ..."
            $table->json('answer_multi')->nullable();         // checkbox uchun massiv
            $table->timestamps();

            $table->index(['survey_key', 'session_token'], 'survey_answers_session_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_survey_answers');
        Schema::dropIfExists('student_survey_completions');
    }
};
