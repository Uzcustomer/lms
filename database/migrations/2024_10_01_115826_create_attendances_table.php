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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id'); // API id field
            $table->unsignedBigInteger('subject_schedule_id');

            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('student_hemis_id');
            $table->string('student_name');

            // Employee
            $table->unsignedBigInteger('employee_id');
            $table->string('employee_name');

            // Subject
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name');
            $table->string('subject_code');

            // Education Year
            $table->string('education_year_code');
            $table->string('education_year_name');
            $table->boolean('education_year_current');

            // Semester
            $table->string('semester_code');
            $table->string('semester_name');

            // Group
            $table->unsignedBigInteger('group_id');
            $table->string('group_name');
            $table->string('education_lang_code');
            $table->string('education_lang_name');

            // Training Type
            $table->string('training_type_code');
            $table->string('training_type_name');

            // Lesson Pair
            $table->string('lesson_pair_code');
            $table->string('lesson_pair_name');
            $table->string('lesson_pair_start_time');
            $table->string('lesson_pair_end_time');

            // Attendance Information
            $table->integer('absent_on');
            $table->integer('absent_off');
            $table->timestamp('lesson_date');

            // Additional Fields for Business Logic
            $table->string('status')->default('absent');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
