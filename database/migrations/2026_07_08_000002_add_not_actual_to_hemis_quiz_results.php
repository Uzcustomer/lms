<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnostikadagi ba'zi natijalar (mas. talaba xato semestrni ishlagani
 * uchun) ortiqcha/kerakmas bo'lib qoladi. Operator ularni "noaktual" deb
 * belgilaydi — shunda diagnostikada alohida status ("Noaktual") ko'rinadi
 * va kunlik monitoringda "farq" emas, "ogohlantirish" sifatida sanaladi.
 *
 * Bu ustunlar Moodle sync upsertida (full_new) yangilanmaydi — shuning
 * uchun natija qayta sinxronlanganda ham noaktual belgisi (izi) saqlanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hemis_quiz_results', function (Blueprint $table) {
            $table->boolean('not_actual')->default(false)->after('is_active');
            $table->string('not_actual_reason', 255)->nullable()->after('not_actual');
            $table->dateTime('not_actual_at')->nullable()->after('not_actual_reason');
            $table->string('not_actual_by', 120)->nullable()->after('not_actual_at');
            $table->index('not_actual');
        });
    }

    public function down(): void
    {
        Schema::table('hemis_quiz_results', function (Blueprint $table) {
            $table->dropIndex(['not_actual']);
            $table->dropColumn(['not_actual', 'not_actual_reason', 'not_actual_at', 'not_actual_by']);
        });
    }
};
