<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dateTime('week_start_time')->nullable()->change();
            $table->dateTime('week_end_time')->nullable()->change();
            $table->dateTime('lesson_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dateTime('week_start_time')->nullable(false)->change();
            $table->dateTime('week_end_time')->nullable(false)->change();
            $table->dateTime('lesson_date')->nullable(false)->change();
        });
    }
};