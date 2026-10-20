<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * "Qayta topshirish testlari": o'qituvchi testni kompyuterlarda qayta topshirish
 * rejimida ochadi. Kompyuter qaysi rejimda berilgani (assigned_mode) va urinish
 * qayta topshirish ekani (is_retake) saqlanadi — test jurnalida alohida tabda chiqadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('test_kiosk_devices', 'assigned_mode')) {
            Schema::table('test_kiosk_devices', function (Blueprint $table) {
                $table->string('assigned_mode', 10)->nullable()->after('assigned_fan_testi_id');
            });
        }

        if (!Schema::hasColumn('fan_testi_attempts', 'is_retake')) {
            Schema::table('fan_testi_attempts', function (Blueprint $table) {
                $table->boolean('is_retake')->default(false)->after('attempt_no')->index();
            });
            // Jurnaldagi "Qayta topshirish" tugmasi bilan ochilgan 2-, 3-urinishlar ham qayta topshirish
            DB::table('fan_testi_attempts')->where('attempt_no', '>', 1)->update(['is_retake' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('fan_testi_attempts', function (Blueprint $table) {
            $table->dropIndex(['is_retake']);
            $table->dropColumn('is_retake');
        });
        Schema::table('test_kiosk_devices', function (Blueprint $table) {
            $table->dropColumn('assigned_mode');
        });
    }
};
