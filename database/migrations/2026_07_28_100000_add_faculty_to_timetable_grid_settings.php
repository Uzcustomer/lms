<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('timetable_grid_settings', 'faculty_name')) {
            Schema::table('timetable_grid_settings', function (Blueprint $table) {
                $table->string('faculty_name')->nullable()->after('board_id');
            });
        }

        Schema::table('timetable_grid_settings', function (Blueprint $table) {
            $table->dropUnique('ttgs_unique');
            $table->unique(
                ['board_id', 'faculty_name', 'specialty_name', 'course'],
                'ttgs_unique_faculty'
            );
        });
    }

    public function down(): void
    {
        Schema::table('timetable_grid_settings', function (Blueprint $table) {
            $table->dropUnique('ttgs_unique_faculty');
            $table->unique(['board_id', 'specialty_name', 'course'], 'ttgs_unique');
        });

        if (Schema::hasColumn('timetable_grid_settings', 'faculty_name')) {
            Schema::table('timetable_grid_settings', function (Blueprint $table) {
                $table->dropColumn('faculty_name');
            });
        }
    }
};
