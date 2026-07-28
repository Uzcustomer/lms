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

        // Eski unique index board_id foreign key uchun yagona index bo'lib turgan
        // bo'lishi mumkin. Avval alohida index yaratib olamiz.
        Schema::table('timetable_grid_settings', function (Blueprint $table) {
            $table->index('board_id', 'ttgs_board_id_index');
        });

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
            $table->dropIndex('ttgs_board_id_index');
        });

        if (Schema::hasColumn('timetable_grid_settings', 'faculty_name')) {
            Schema::table('timetable_grid_settings', function (Blueprint $table) {
                $table->dropColumn('faculty_name');
            });
        }
    }
};
