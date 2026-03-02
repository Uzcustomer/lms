<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exam_appeals')) {
            return;
        }

        Schema::create('exam_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedBigInteger('student_grade_id')->nullable();
            $table->string('subject_name');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('training_type_code')->nullable();
            $table->string('training_type_name')->nullable();
            $table->float('current_grade')->nullable();
            $table->string('employee_name')->nullable();
            $table->date('exam_date')->nullable();
            $table->text('reason');
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('status')->default('pending'); // pending, reviewing, approved, rejected
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('reviewed_by_name')->nullable();
            $table->text('review_comment')->nullable();
            $table->float('new_grade')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('student_id');
            $table->index('status');
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_appeals');
    }
};
