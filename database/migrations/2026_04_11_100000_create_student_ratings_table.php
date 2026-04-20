<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_ratings', function (Blueprint $table) {
            $table->id();
            $table->string('student_hemis_id', 50)->index();
            $table->string('full_name');
            $table->string('group_name', 50)->nullable();
            $table->string('department_code', 50)->nullable()->index();
            $table->string('department_name')->nullable();
            $table->string('specialty_code', 50)->nullable()->index();
            $table->string('specialty_name')->nullable();
            $table->string('level_code', 10)->nullable()->index();
            $table->string('semester_code', 10)->nullable();
            $table->string('education_year_code', 20)->nullable();
            $table->unsignedSmallInteger('subjects_count')->default(0);
            $table->decimal('jn_average', 5, 2)->default(0);
            $table->unsignedInteger('rank')->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['student_hemis_id', 'education_year_code', 'semester_code'], 'student_ratings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_ratings');
    }
};
