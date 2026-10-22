<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Yo'nalish+kurs panjarasining o'z semestr boshlanishi (masalan PIMU 1-kurs 12.10 dan). */
    public function up(): void
    {
        if (!Schema::hasColumn('timetable_grid_settings', 'start_date')) {
            Schema::table('timetable_grid_settings', function (Blueprint $table) {
                $table->date('start_date')->nullable()->after('weeks');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('timetable_grid_settings', 'start_date')) {
            Schema::table('timetable_grid_settings', function (Blueprint $table) {
                $table->dropColumn('start_date');
            });
        }
    }
};
