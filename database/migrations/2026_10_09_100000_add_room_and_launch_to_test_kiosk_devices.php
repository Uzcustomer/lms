<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sinf kompyuterlari xonalarga bo'linadi; o'qituvchi tanlagan kompyuterlarga
     * test "beriladi" — kutish ekranidagi kompyuter shu testni o'zi ochadi.
     */
    public function up(): void
    {
        if (!Schema::hasTable('test_kiosk_devices') || Schema::hasColumn('test_kiosk_devices', 'room')) {
            return;
        }

        Schema::table('test_kiosk_devices', function (Blueprint $table) {
            $table->string('room', 60)->nullable()->after('name')->index();
            $table->unsignedBigInteger('assigned_fan_testi_id')->nullable()->after('revoked_reason')->index();
            $table->timestamp('assigned_at')->nullable()->after('assigned_fan_testi_id');
            $table->string('assigned_by_name')->nullable()->after('assigned_at');
            $table->timestamp('last_seen_at')->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('test_kiosk_devices', 'room')) {
            return;
        }

        Schema::table('test_kiosk_devices', function (Blueprint $table) {
            $table->dropColumn(['room', 'assigned_fan_testi_id', 'assigned_at', 'assigned_by_name', 'last_seen_at']);
        });
    }
};
