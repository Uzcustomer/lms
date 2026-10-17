<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Qayta topshirish: o'qituvchi ruxsat bersa talaba o'sha testni yana bir marta
 * (2-urinish, 3-urinish ...) topshiradi. Mavjud urinishlar 1-urinish bo'lib qoladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fan_testi_attempts', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempt_no')->default(1)->after('student_id');
            // O'qituvchi "Qayta topshirish" bosganda shu urinishga yoziladi;
            // keyingi urinish ochilgach ruxsat o'z-o'zidan ishlatilgan hisoblanadi.
            $table->timestamp('retake_allowed_at')->nullable()->after('face_bypass_by');
            $table->string('retake_allowed_by', 120)->nullable()->after('retake_allowed_at');

            $table->unique(['fan_testi_id', 'student_id', 'attempt_no'], 'fta_test_student_no_unique');
        });

        Schema::table('fan_testi_attempts', function (Blueprint $table) {
            $table->dropUnique('fta_test_student_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fan_testi_attempts', function (Blueprint $table) {
            $table->unique(['fan_testi_id', 'student_id'], 'fta_test_student_unique');
        });

        Schema::table('fan_testi_attempts', function (Blueprint $table) {
            $table->dropUnique('fta_test_student_no_unique');
            $table->dropColumn(['attempt_no', 'retake_allowed_at', 'retake_allowed_by']);
        });
    }
};
