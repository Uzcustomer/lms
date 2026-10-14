<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yuzi tanilmayotgan talaba uchun o'qituvchi bir martalik ruxsati:
 *   test_kiosk_devices.face_bypass_at / face_bypass_by — shu kompyuterda keyingi
 *     talaba faqat ID bilan (yuz tekshiruvisiz) kiradi; ishlatilgach tozalanadi;
 *   fan_testi_attempts.face_bypass_by — urinish shu ruxsat bilan boshlangan
 *     (jurnalda ko'rinadi, test davomidagi jim yuz solishtirish o'chiriladi).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('test_kiosk_devices') && ! Schema::hasColumn('test_kiosk_devices', 'face_bypass_at')) {
            Schema::table('test_kiosk_devices', function (Blueprint $table) {
                $table->timestamp('face_bypass_at')->nullable();
                $table->string('face_bypass_by', 120)->nullable();
            });
        }

        if (Schema::hasTable('fan_testi_attempts') && ! Schema::hasColumn('fan_testi_attempts', 'face_bypass_by')) {
            Schema::table('fan_testi_attempts', function (Blueprint $table) {
                $table->string('face_bypass_by', 120)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('test_kiosk_devices', 'face_bypass_at')) {
            Schema::table('test_kiosk_devices', fn (Blueprint $table) => $table->dropColumn(['face_bypass_at', 'face_bypass_by']));
        }
        if (Schema::hasColumn('fan_testi_attempts', 'face_bypass_by')) {
            Schema::table('fan_testi_attempts', fn (Blueprint $table) => $table->dropColumn('face_bypass_by'));
        }
    }
};
