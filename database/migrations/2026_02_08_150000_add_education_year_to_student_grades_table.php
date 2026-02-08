<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->string('education_year_code')->nullable()->after('semester_name');
            $table->string('education_year_name')->nullable()->after('education_year_code');

            $table->index('education_year_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->dropIndex(['education_year_code']);
            $table->dropColumn(['education_year_code', 'education_year_name']);
        });
    }
};
