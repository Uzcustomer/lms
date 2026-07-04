<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apelyatsiya "qayta o'qish" (retake_applications) OSKE/Test natijalarini ham
 * qamragani uchun — qaysi retake arizasi va komponenti tuzatilganini audit
 * jadvalida saqlaymiz. student_grade_id li klassik apelyatsiyalarda bu ustunlar
 * NULL bo'ladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('quiz_grade_appeals')) {
            return;
        }

        Schema::table('quiz_grade_appeals', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_grade_appeals', 'retake_application_id')) {
                $table->unsignedBigInteger('retake_application_id')->nullable()->after('quiz_result_id');
            }
            if (!Schema::hasColumn('quiz_grade_appeals', 'retake_component')) {
                $table->string('retake_component', 10)->nullable()->after('retake_application_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('quiz_grade_appeals')) {
            return;
        }

        Schema::table('quiz_grade_appeals', function (Blueprint $table) {
            foreach (['retake_application_id', 'retake_component'] as $col) {
                if (Schema::hasColumn('quiz_grade_appeals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
