<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hemis_exam_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hemis_record_id')->nullable()->unique();
            $table->string('student_hemis_id');
            $table->string('subject_id');
            $table->string('semester_code');
            $table->string('education_year')->nullable();
            $table->string('exam_type_code');
            $table->string('exam_type_name')->nullable();
            $table->string('final_exam_type_code')->nullable();
            $table->string('final_exam_type_name')->nullable();
            $table->integer('grade')->nullable();
            $table->integer('regrade')->nullable();
            $table->timestamp('exam_date')->nullable();
            $table->string('employee_hemis_id')->nullable();
            $table->unsignedBigInteger('exam_schedule_id')->nullable();
            $table->timestamps();

            $table->index(['student_hemis_id', 'subject_id', 'semester_code', 'exam_type_code'], 'hemis_exam_grades_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hemis_exam_grades');
    }
};
