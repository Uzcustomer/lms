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
        Schema::create('lesson_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('teacher_id');
            $table->string('teacher_name');
            $table->string('group_id')->nullable();
            $table->string('group_name')->nullable();
            $table->string('student_hemis_id')->nullable();
            $table->string('student_name')->nullable();
            $table->string('semester_name');
            $table->string('training_type_name');
            $table->string('training_type_code');
            $table->string('subject_name');
            $table->enum('type', ['group', 'student']);
            $table->json('schedule_info');
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->json('student_grade_ids')->nullable();
            $table->json('student_hemis_ids')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_histories');
    }
};
