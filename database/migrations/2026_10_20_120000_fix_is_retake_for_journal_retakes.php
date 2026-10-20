<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * "Qayta topshirishlar" tabida faqat "Qayta topshirish testlari"dan ochilgan test
 * orqali topshirganlar bo'ladi. 2026_10_20_100000 migratsiyasi attempt_no > 1
 * bo'lgan hammasini qayta topshirish deb belgilagan edi — jurnaldagi "Qayta
 * topshirish" tugmasi bilan ochilganlari (oldingi urinishida ruxsat belgisi bor)
 * asosiy tabga qaytariladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('fan_testi_attempts', 'is_retake')) {
            return;
        }

        DB::statement("
            UPDATE fan_testi_attempts a
            JOIN fan_testi_attempts p
              ON p.fan_testi_id = a.fan_testi_id
             AND p.student_id = a.student_id
             AND p.attempt_no = a.attempt_no - 1
            SET a.is_retake = 0
            WHERE a.is_retake = 1
              AND p.retake_allowed_at IS NOT NULL
        ");
    }

    public function down(): void
    {
        // Ma'lumot tuzatish — qaytarilmaydi
    }
};
