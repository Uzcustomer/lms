<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_controls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id')->unique();
            $table->unsignedBigInteger('subject_schedule_id');

            // Subject
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_code');
            $table->string('subject_name');

            // Employee
            $table->unsignedBigInteger('employee_id');
            $table->string('employee_name');

            // Education Year
            $table->string('education_year_code');
            $table->string('education_year_name');

            // Semester
            $table->string('semester_code');
            $table->string('semester_name');

            // Group
            $table->unsignedBigInteger('group_id');
            $table->string('group_name');
            $table->string('education_lang_code')->nullable();
            $table->string('education_lang_name')->nullable();

            // Training Type
            $table->string('training_type_code');
            $table->string('training_type_name');

            // Lesson Pair
            $table->string('lesson_pair_code');
            $table->string('lesson_pair_name');
            $table->string('lesson_pair_start_time');
            $table->string('lesson_pair_end_time');

            // Lesson info
            $table->timestamp('lesson_date')->nullable();
            $table->integer('load')->default(2);

            $table->timestamps();

            $table->index(['employee_id']);
            $table->index(['group_id']);
            $table->index(['subject_id']);
            $table->index(['semester_code']);
            $table->index(['education_year_code']);
            $table->index(['lesson_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_controls');
    }
};
