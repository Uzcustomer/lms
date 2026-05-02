<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'disability_type_code')) {
                $table->string('disability_type_code', 20)->nullable()->index();
            }
            if (!Schema::hasColumn('students', 'disability_type_name')) {
                $table->string('disability_type_name', 191)->nullable();
            }
            if (!Schema::hasColumn('students', 'disability_duration')) {
                $table->string('disability_duration', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            foreach (['disability_type_code', 'disability_type_name', 'disability_duration'] as $col) {
                if (Schema::hasColumn('students', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
