<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Talabaga beriladigan savollar soni: to'plamda bundan ko'p faol savol bo'lsa,
 * har urinishda tasodifiy shuncha savol tanlanadi (har safar boshqa to'plam).
 * NULL — barcha savollar beriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fan_testlari', function (Blueprint $table) {
            $table->unsignedSmallInteger('questions_per_attempt')->nullable()->default(25)->after('shuffle_questions');
        });
    }

    public function down(): void
    {
        Schema::table('fan_testlari', function (Blueprint $table) {
            $table->dropColumn('questions_per_attempt');
        });
    }
};
