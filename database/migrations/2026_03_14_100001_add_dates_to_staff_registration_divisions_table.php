<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_registration_divisions', function (Blueprint $table) {
            $table->date('started_at')->nullable()->after('level_code');
            $table->date('ended_at')->nullable()->after('started_at');
        });

        // Mavjud yozuvlarga bugungi sanani qo'yish
        \DB::table('staff_registration_divisions')
            ->whereNull('started_at')
            ->update(['started_at' => now()->toDateString()]);
    }

    public function down(): void
    {
        Schema::table('staff_registration_divisions', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'ended_at']);
        });
    }
};
