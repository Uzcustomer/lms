<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Javoblar qoralamasi: talaba belgilagan sari brauzer serverga yuborib turadi.
 * Sahifa yopilsa / kompyuter o'chsa javoblar yo'qolmaydi — qayta kirganda
 * tiklanadi, vaqt tugaganda yoki o'qituvchi to'xtatganda shu qoralama baholanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fan_testi_attempts', function (Blueprint $table) {
            $table->json('draft_answers')->nullable()->after('questions_snapshot');
            $table->timestamp('draft_saved_at')->nullable()->after('draft_answers');
        });
    }

    public function down(): void
    {
        Schema::table('fan_testi_attempts', function (Blueprint $table) {
            $table->dropColumn(['draft_answers', 'draft_saved_at']);
        });
    }
};
