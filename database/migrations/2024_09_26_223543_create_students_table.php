<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id')->unique();
            $table->string('full_name');
            $table->string('short_name');
            $table->string('first_name');
            $table->string('second_name');
            $table->string('third_name')->nullable();
            $table->string('image')->nullable();
            $table->string('student_id_number')->unique();
            $table->date('birth_date')->nullable();
            $table->decimal('avg_gpa', 5, 2)->nullable();
            $table->decimal('avg_grade', 5, 2)->nullable();
            $table->decimal('total_credit', 8, 2)->nullable();
            $table->string('university_code')->nullable();
            $table->string('university_name')->nullable();
            $table->string('gender_code')->nullable();
            $table->string('gender_name')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('department_name')->nullable();
            $table->string('department_code')->nullable();
            $table->unsignedBigInteger('specialty_id')->nullable();
            $table->string('specialty_name')->nullable();
            $table->string('specialty_code')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('group_name')->nullable();
            $table->string('education_year_code')->nullable();
            $table->string('education_year_name')->nullable();
            $table->string('country_code')->nullable();
            $table->string('country_name')->nullable();
            $table->string('province_code')->nullable();
            $table->string('province_name')->nullable();
            $table->string('district_code')->nullable();
            $table->string('district_name')->nullable();
            $table->string('terrain_code')->nullable();
            $table->string('terrain_name')->nullable();
            $table->string('citizenship_code')->nullable();
            $table->string('citizenship_name')->nullable();
            $table->unsignedBigInteger('semester_id')->nullable();
            $table->string('semester_code')->nullable();
            $table->string('semester_name')->nullable();
            $table->string('level_code')->nullable();
            $table->string('level_name')->nullable();
            $table->string('education_form_code')->nullable();
            $table->string('education_form_name')->nullable();
            $table->string('education_type_code')->nullable();
            $table->string('education_type_name')->nullable();
            $table->string('payment_form_code')->nullable();
            $table->string('payment_form_name')->nullable();
            $table->string('student_type_code')->nullable();
            $table->string('student_type_name')->nullable();
            $table->string('social_category_code')->nullable();
            $table->string('social_category_name')->nullable();
            $table->string('accommodation_code')->nullable();
            $table->string('accommodation_name')->nullable();
            $table->string('student_status_code')->nullable();
            $table->string('student_status_name')->nullable();
            $table->unsignedBigInteger('curriculum_id')->nullable();
            $table->timestamp('hemis_created_at')->nullable();
            $table->timestamp('hemis_updated_at')->nullable();
            $table->string('hash')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
