<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecture_schedules', function (Blueprint $table) {
            $table->string('group_source')->nullable()->after('group_id');
            $table->string('floor')->nullable()->after('auditorium_name');
            $table->string('building_name')->nullable()->after('floor');
            $table->string('weeks')->nullable()->after('training_type_name');
            $table->string('week_parity')->nullable()->after('weeks');
        });
    }

    public function down(): void
    {
        Schema::table('lecture_schedules', function (Blueprint $table) {
            $table->dropColumn(['group_source', 'floor', 'building_name', 'weeks', 'week_parity']);
        });
    }
};
