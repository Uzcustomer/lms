<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sinf kompyuterining jonli holati — o'qituvchi "Kompyuterlar" oynasida har
 * bir kompyuterda nima bo'layotganini ko'radi va alohida boshqaradi:
 *   kiosk_stage / kiosk_stage_at — qaysi sahifada (kutish, ID, yuz, test, natija);
 *   current_attempt_id / kiosk_answered — kim ishlayapti va nechta savolga javob bergan;
 *   pending_command / pending_attempt_id — o'qituvchi buyrug'i (qayta boshlash);
 *   released_fan_testi_id / released_attempt_id — shu kompyuterda alohida
 *     to'xtatilgan test va talaba (keyin qayta boshlash uchun).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('test_kiosk_devices') || Schema::hasColumn('test_kiosk_devices', 'kiosk_stage')) {
            return;
        }

        Schema::table('test_kiosk_devices', function (Blueprint $table) {
            $table->string('kiosk_stage', 20)->nullable()->after('assigned_by_name');
            $table->timestamp('kiosk_stage_at')->nullable()->after('kiosk_stage');
            $table->unsignedBigInteger('current_attempt_id')->nullable()->after('kiosk_stage_at');
            $table->unsignedSmallInteger('kiosk_answered')->nullable()->after('current_attempt_id');
            $table->string('pending_command', 20)->nullable()->after('kiosk_answered');
            $table->unsignedBigInteger('pending_attempt_id')->nullable()->after('pending_command');
            $table->unsignedBigInteger('released_fan_testi_id')->nullable()->index()->after('pending_attempt_id');
            $table->unsignedBigInteger('released_attempt_id')->nullable()->after('released_fan_testi_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('test_kiosk_devices', 'kiosk_stage')) {
            return;
        }

        Schema::table('test_kiosk_devices', function (Blueprint $table) {
            $table->dropIndex(['released_fan_testi_id']);
            $table->dropColumn([
                'kiosk_stage', 'kiosk_stage_at', 'current_attempt_id', 'kiosk_answered',
                'pending_command', 'pending_attempt_id', 'released_fan_testi_id', 'released_attempt_id',
            ]);
        });
    }
};
