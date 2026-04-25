<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retake bahosi saqlanayotgan vaqtdagi "sababli" holatini yozib qo'yamiz.
 * Keyinchalik HEMIS absent_on yoki LMS ariza holati o'zgarsa — bu flag yordamida
 * eski koeffitsientni aniqlab, to'g'ri qayta hisoblaymiz.
 *
 * Default qiymat: false (historic yozuvlar odatda sababsiz holda saqlangan).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('student_grades', 'retake_was_sababli')) {
            Schema::table('student_grades', function (Blueprint $table) {
                $table->boolean('retake_was_sababli')->nullable()->after('retake_grade');
            });
        }

        // Backfill: retake bahosi bor, lekin retake_was_sababli yo'q yozuvlar.
        // Eski yozuvlar uchun taxmin: sababsiz (false) — bu aksariyat holatlarda to'g'ri.
        try {
            DB::table('student_grades')
                ->whereNotNull('retake_grade')
                ->whereNull('retake_was_sababli')
                ->update(['retake_was_sababli' => false]);
        } catch (\Throwable $e) {
            Log::warning('[Migration] retake_was_sababli backfill: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('student_grades', 'retake_was_sababli')) {
            Schema::table('student_grades', function (Blueprint $table) {
                $table->dropColumn('retake_was_sababli');
            });
        }
    }
};
