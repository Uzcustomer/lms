<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_subject_lesson_test_questions', function (Blueprint $table) {
            $table->json('prompt_translations')->nullable()->after('prompt');
            $table->json('helper_text_translations')->nullable()->after('helper_text');
            $table->json('correct_answer_translations')->nullable()->after('correct_answer_text');
        });

        Schema::table('test_subject_lesson_test_options', function (Blueprint $table) {
            $table->json('option_text_translations')->nullable()->after('option_text');
        });
    }

    public function down(): void
    {
        Schema::table('test_subject_lesson_test_options', function (Blueprint $table) {
            $table->dropColumn('option_text_translations');
        });

        Schema::table('test_subject_lesson_test_questions', function (Blueprint $table) {
            $table->dropColumn([
                'prompt_translations',
                'helper_text_translations',
                'correct_answer_translations',
            ]);
        });
    }
};
