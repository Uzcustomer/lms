<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Kompyuter tiklash kodi (4 raqam): brauzer cookie'si o'chib ketsa, kutish
 * ekranida shu kod kiritiladi va kompyuter admin logini shart bo'lmasdan o'z
 * nomi/xonasi bilan qayta bog'lanadi. Kod o'qituvchi va admin ro'yxatida ko'rinadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('test_kiosk_devices') || Schema::hasColumn('test_kiosk_devices', 'recovery_code')) {
            return;
        }

        Schema::table('test_kiosk_devices', function (Blueprint $table) {
            $table->char('recovery_code', 4)->nullable()->unique()->after('name');
        });

        // Mavjud kompyuterlarga ham kod beriladi (bekor qilinganlarga ham — qayta tiklash uchun)
        $used = [];
        foreach (DB::table('test_kiosk_devices')->orderBy('id')->pluck('id') as $id) {
            do {
                $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            } while (isset($used[$code]));
            $used[$code] = true;
            DB::table('test_kiosk_devices')->where('id', $id)->update(['recovery_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('test_kiosk_devices', function (Blueprint $table) {
            $table->dropUnique(['recovery_code']);
            $table->dropColumn('recovery_code');
        });
    }
};
