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
        Schema::create('student_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id'); // API id field
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('student_hemis_id');

            // Semester
            $table->string('semester_code');
            $table->string('semester_name');

            // Subject Schedule
            $table->unsignedBigInteger('subject_schedule_id');

            // Subject
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name');
            $table->string('subject_code');

            // Training Type
            $table->string('training_type_code');
            $table->string('training_type_name');

            // Employee
            $table->unsignedBigInteger('employee_id');
            $table->string('employee_name');

            // Lesson Pair
            $table->string('lesson_pair_code');
            $table->string('lesson_pair_name');
            $table->string('lesson_pair_start_time');
            $table->string('lesson_pair_end_time');

            // Grade Information
            $table->float('grade')->nullable();
            $table->timestamp('lesson_date');
            $table->timestamp('created_at_api');

            // Additional Fields for Business Logic
            $table->string('reason')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->string('status')->default('recorded');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_grades');
    }
};
