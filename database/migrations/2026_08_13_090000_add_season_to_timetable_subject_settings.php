<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('timetable_subject_settings')) {
            return;
        }

        Schema::table('timetable_subject_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('timetable_subject_settings', 'season')) {
                $table->string('season', 10)->nullable()->after('mode');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('timetable_subject_settings')
            || !Schema::hasColumn('timetable_subject_settings', 'season')) {
            return;
        }

        Schema::table('timetable_subject_settings', function (Blueprint $table) {
            $table->dropColumn('season');
        });
    }
};
