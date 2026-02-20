<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_mt_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('student_hemis_id');
            $table->string('subject_id');
            $table->string('subject_name');
            $table->string('semester_code');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status')->default('pending'); // pending, reviewed, graded
            $table->text('teacher_comment')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'subject_id', 'semester_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_mt_submissions');
    }
};
