<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_subject_lesson_test_questions', function (Blueprint $table) {
            $table->text('correct_explanation')->nullable()->after('helper_text_translations');
            $table->json('correct_explanation_translations')->nullable()->after('correct_explanation');
        });
    }

    public function down(): void
    {
        Schema::table('test_subject_lesson_test_questions', function (Blueprint $table) {
            $table->dropColumn([
                'correct_explanation_translations',
                'correct_explanation',
            ]);
        });
    }
};
