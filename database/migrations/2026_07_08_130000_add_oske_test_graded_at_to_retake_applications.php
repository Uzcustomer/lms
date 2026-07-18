<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OSKE va TEST natijalari qo'yilgan sanani alohida saqlaymiz — jurnalda
 * ustunga sichqoncha olib borilganda "baho qo'yilgan sana" ko'rsatish uchun.
 * (JN uchun `joriy_graded_at`, MT uchun mustaqil `graded_at` allaqachon bor.)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_applications', function (Blueprint $table) {
            $table->timestamp('oske_graded_at')->nullable()->after('oske_score');
            $table->timestamp('test_graded_at')->nullable()->after('test_score');
        });

        // Mavjud yozuvlar uchun eng yaqin taxminiy sana — final_grade_set_at
        // (OSKE/TEST natijasi tizimga kiritilganda yakuniy baho ham qo'yilgan).
        DB::table('retake_applications')
            ->whereNotNull('oske_score')
            ->whereNull('oske_graded_at')
            ->update(['oske_graded_at' => DB::raw('final_grade_set_at')]);

        DB::table('retake_applications')
            ->whereNotNull('test_score')
            ->whereNull('test_graded_at')
            ->update(['test_graded_at' => DB::raw('final_grade_set_at')]);
    }

    public function down(): void
    {
        Schema::table('retake_applications', function (Blueprint $table) {
            $table->dropColumn(['oske_graded_at', 'test_graded_at']);
        });
    }
};
