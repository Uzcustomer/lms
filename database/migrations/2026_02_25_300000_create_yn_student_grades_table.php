<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yn_student_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yn_submission_id')->constrained('yn_submissions')->onDelete('cascade');
            $table->string('student_hemis_id');
            $table->integer('jn')->default(0);
            $table->integer('mt')->default(0);
            $table->string('source')->default('yn_submitted'); // yn_submitted, absence_excuse:ID
            $table->timestamps();

            $table->index(['yn_submission_id', 'student_hemis_id'], 'yn_student_grades_submission_student');
            $table->index('student_hemis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yn_student_grades');
    }
};
