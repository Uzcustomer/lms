<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_disability_infos') && !Schema::hasColumn('student_disability_infos', 'certificate_path')) {
            Schema::table('student_disability_infos', function (Blueprint $table) {
                $table->string('certificate_path', 500)->nullable()->after('reexamination_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('student_disability_infos', 'certificate_path')) {
            Schema::table('student_disability_infos', function (Blueprint $table) {
                $table->dropColumn('certificate_path');
            });
        }
    }
};
