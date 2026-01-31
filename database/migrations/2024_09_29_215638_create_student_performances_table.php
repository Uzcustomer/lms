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
        Schema::create('student_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->unsignedBigInteger('hemis_id')->nullable();
            $table->string('subject_name');
            $table->string('subject_code');
            $table->string('semester_code');
            $table->string('training_type');
            $table->string('teacher_name');
            $table->integer('grade')->nullable();
            $table->timestamp('lesson_date');
            $table->string('reason')->default('low_grade');
            $table->date('deadline')->nullable();
            $table->string('status')->default('pending');
            $table->integer('retake_score')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_performances');
    }
};
