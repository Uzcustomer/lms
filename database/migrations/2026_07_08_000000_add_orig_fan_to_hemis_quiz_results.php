<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnostikada "Qayta o'qish arizasi topilmadi" qatorda fan qayta o'qish
 * arizasidagi fanga qo'lda almashtirilganda, ASL (HEMIS quiz) fanini saqlab
 * qo'yish uchun ustunlar. Shunda jadvalda "qaysi fandan qaysi fanga"
 * almashtirilgani ko'rinib turadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hemis_quiz_results', function (Blueprint $table) {
            $table->unsignedBigInteger('orig_fan_id')->nullable()->after('fan_name');
            $table->string('orig_fan_name', 255)->nullable()->after('orig_fan_id');
            $table->dateTime('fan_reassigned_at')->nullable()->after('orig_fan_name');
        });
    }

    public function down(): void
    {
        Schema::table('hemis_quiz_results', function (Blueprint $table) {
            $table->dropColumn(['orig_fan_id', 'orig_fan_name', 'fan_reassigned_at']);
        });
    }
};
