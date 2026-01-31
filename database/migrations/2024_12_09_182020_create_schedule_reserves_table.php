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
        Schema::create('schedule_reserves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_hemis_id')->unique();
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name');
            $table->string('subject_code');
            $table->string('semester_code');
            $table->string('semester_name');
            $table->string('education_year_code');
            $table->string('education_year_name');
            $table->boolean('education_year_current');
            $table->unsignedBigInteger('group_id');
            $table->string('group_name');
            $table->string('education_lang_code')->nullable();
            $table->string('education_lang_name')->nullable();
            $table->unsignedBigInteger('faculty_id');
            $table->string('faculty_name');
            $table->string('faculty_code');
            $table->string('faculty_structure_type_code')->nullable();
            $table->string('faculty_structure_type_name')->nullable();
            $table->unsignedBigInteger('department_id');
            $table->string('department_name');
            $table->string('department_code');
            $table->string('department_structure_type_code')->nullable();
            $table->string('department_structure_type_name')->nullable();
            $table->string('auditorium_code')->nullable();
            $table->string('auditorium_name')->nullable();
            $table->string('auditorium_type_code');
            $table->string('auditorium_type_name');
            $table->unsignedBigInteger('building_id')->nullable();
            $table->string('building_name');
            $table->string('training_type_code');
            $table->string('training_type_name');
            $table->string('lesson_pair_code');
            $table->string('lesson_pair_name');
            $table->time('lesson_pair_start_time');
            $table->time('lesson_pair_end_time');
            $table->unsignedBigInteger('employee_id');
            $table->string('employee_name');
            $table->dateTime('week_start_time');
            $table->dateTime('week_end_time');
            $table->dateTime('lesson_date');
            $table->unsignedBigInteger('week_number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_reserves');
    }
};
