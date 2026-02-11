<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_subject_teachers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id')->unique()->comment('HEMIS dagi ID');
            $table->unsignedBigInteger('semester_id')->nullable();
            $table->unsignedBigInteger('education_year_id')->nullable();
            $table->unsignedBigInteger('curriculum_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('training_type_id')->nullable();

            // Fan ma'lumotlari
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_code')->nullable();
            $table->string('subject_name')->nullable();

            // O'qituvchi ma'lumotlari
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('employee_name')->nullable();

            // Mashg'ulot turi
            $table->string('training_type_code')->nullable();
            $table->string('training_type_name')->nullable();
            $table->unsignedBigInteger('curriculum_subject_detail_id')->nullable();
            $table->integer('academic_load')->nullable();

            $table->boolean('active')->default(true);
            $table->integer('students_count')->nullable();

            $table->timestamps();

            $table->index('employee_id');
            $table->index('subject_id');
            $table->index('group_id');
            $table->index(['employee_id', 'subject_id']);
            $table->index(['employee_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_subject_teachers');
    }
};
