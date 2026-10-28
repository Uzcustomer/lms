<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_grid_settings', function (Blueprint $table) {
            // 0 preserves every existing faculty/direction/course default.
            $table->unsignedBigInteger('curriculum_hemis_id')->default(0);
            $table->json('weekly_breaks')->nullable();
            $table->dropUnique('ttgs_unique_faculty');
            $table->unique(['board_id', 'faculty_name', 'specialty_name', 'course', 'curriculum_hemis_id'], 'ttgs_unique_program');
        });
    }

    public function down(): void
    {
        // Program overrides cannot be silently collapsed back into one legacy row.
        if (DB::table('timetable_grid_settings')->where('curriculum_hemis_id', '>', 0)->exists()) {
            throw new RuntimeException('Remove curriculum calendar overrides before rolling back this migration.');
        }
        Schema::table('timetable_grid_settings', function (Blueprint $table) {
            $table->dropUnique('ttgs_unique_program');
            $table->dropColumn(['curriculum_hemis_id', 'weekly_breaks']);
            $table->unique(['board_id', 'faculty_name', 'specialty_name', 'course'], 'ttgs_unique_faculty');
        });
    }
};
