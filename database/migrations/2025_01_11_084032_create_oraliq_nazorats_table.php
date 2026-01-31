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
        Schema::create('oraliq_nazorats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('teacher_id');
            $table->string('teacher_hemis_id');
            $table->string('teacher_name');
            $table->string('teacher_short_name');
            $table->unsignedBigInteger('department_hemis_id');
            $table->unsignedBigInteger('department_id');
            $table->string('deportment_name');
            $table->unsignedBigInteger('group_hemis_id');
            $table->unsignedBigInteger('group_id');
            $table->string('group_name');
            $table->string('semester_name');
            $table->string('semester_code');
            $table->unsignedBigInteger('semester_hemis_id');
            $table->unsignedBigInteger('semester_id');
            $table->unsignedBigInteger('subject_hemis_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name');
            $table->date('start_date');
            $table->date('deadline');
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->boolean('status')->default(0);
            $table->string('grade_teacher')->nullable();
            $table->timestamps();
        });
        Schema::table('student_grades', function (Blueprint $table) {
            $table->unsignedBigInteger('oraliq_nazorat_id')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oraliq_nazorats');
    }
};