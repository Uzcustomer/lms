<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_card_overrides', function (Blueprint $table) {
            $table->string('auditorium_code', 50)->nullable()->after('cancelled');
            $table->string('auditorium_name')->nullable()->after('auditorium_code');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_card_overrides', function (Blueprint $table) {
            $table->dropColumn(['auditorium_code', 'auditorium_name']);
        });
    }
};
