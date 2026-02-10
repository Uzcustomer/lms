<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mt_grade_history', function (Blueprint $table) {
            $table->id();
            $table->string('student_hemis_id');
            $table->string('subject_id');
            $table->string('semester_code');
            $table->integer('attempt_number')->default(1);
            $table->decimal('grade', 5, 2);
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('graded_by')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->index(['student_hemis_id', 'subject_id', 'semester_code'], 'mt_history_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mt_grade_history');
    }
};
