<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Q-1 (YN baholari 3 ish kuni ichida HEMISga) uchun bahoning kiritilgan sanasi.
 *  - hemis_created_at — HEMIS API qaytargan created_at (unix vaqt), bo'lsa;
 *  - first_seen_at — baho mark'ga birinchi marta sinxron bo'lgan vaqt (zaxira).
 * Mavjud qatorlar uchun first_seen_at = created_at (birinchi sinxron vaqti).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hemis_exam_grades', function (Blueprint $table) {
            if (!Schema::hasColumn('hemis_exam_grades', 'hemis_created_at')) {
                $table->unsignedBigInteger('hemis_created_at')->nullable();
            }
            if (!Schema::hasColumn('hemis_exam_grades', 'first_seen_at')) {
                $table->timestamp('first_seen_at')->nullable();
            }
        });

        DB::table('hemis_exam_grades')->whereNull('first_seen_at')->update(['first_seen_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('hemis_exam_grades', function (Blueprint $table) {
            $table->dropColumn(['hemis_created_at', 'first_seen_at']);
        });
    }
};
