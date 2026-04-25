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
            $table->string('student_hemis_id', 50);
            $table->string('subject_id', 50);
            $table->string('semester_code', 20);
            $table->string('education_year', 50)->nullable();
            $table->string('exam_type_code', 20);
            $table->string('exam_type_name')->nullable();
            $table->string('final_exam_type_code', 20)->nullable();
            $table->string('final_exam_type_name')->nullable();
            $table->integer('grade')->nullable();
            $table->integer('regrade')->nullable();
            $table->timestamp('exam_date')->nullable();
            $table->string('employee_hemis_id', 50)->nullable();
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
