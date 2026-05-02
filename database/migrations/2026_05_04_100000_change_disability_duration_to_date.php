<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('student_disability_infos')) {
            return;
        }

        if (Schema::hasColumn('student_disability_infos', 'disability_duration')) {
            Schema::table('student_disability_infos', function (Blueprint $table) {
                $table->dropColumn('disability_duration');
            });
        }

        Schema::table('student_disability_infos', function (Blueprint $table) {
            $table->date('disability_duration')->nullable()->after('disability_reason');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('student_disability_infos')) {
            return;
        }

        if (Schema::hasColumn('student_disability_infos', 'disability_duration')) {
            Schema::table('student_disability_infos', function (Blueprint $table) {
                $table->dropColumn('disability_duration');
            });
        }

        Schema::table('student_disability_infos', function (Blueprint $table) {
            $table->string('disability_duration', 100)->nullable()->after('disability_reason');
        });
    }
};
