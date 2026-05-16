<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qayta o'qish jurnali endi kunlik baho jadvalini ishlatmaydi —
 * har talaba uchun bitta yagona Joriy nazorat (JN) bahosi.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_applications', function (Blueprint $table) {
            $table->decimal('joriy_score', 5, 2)->nullable()->after('test_score');
            $table->string('joriy_graded_by_name', 255)->nullable()->after('joriy_score');
            $table->timestamp('joriy_graded_at')->nullable()->after('joriy_graded_by_name');
        });

        // Mavjud retake_grades dan migratsiya — eng oxirgi sananing bahosini joriy_score ga ko'chirish
        if (Schema::hasTable('retake_grades')) {
            \Illuminate\Support\Facades\DB::statement("
                UPDATE retake_applications a
                INNER JOIN (
                    SELECT application_id, AVG(grade) AS avg_grade,
                           MAX(graded_by_name) AS last_grader,
                           MAX(graded_at) AS last_at
                    FROM retake_grades
                    WHERE grade IS NOT NULL
                    GROUP BY application_id
                ) g ON g.application_id = a.id
                SET a.joriy_score = ROUND(g.avg_grade),
                    a.joriy_graded_by_name = g.last_grader,
                    a.joriy_graded_at = g.last_at
                WHERE a.joriy_score IS NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('retake_applications', function (Blueprint $table) {
            $table->dropColumn(['joriy_score', 'joriy_graded_by_name', 'joriy_graded_at']);
        });
    }
};
