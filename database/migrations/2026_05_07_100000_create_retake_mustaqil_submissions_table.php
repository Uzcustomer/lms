<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Mustaqil ta'lim — talaba file yuklaydi, o'qituvchi baholaydi
        Schema::create('retake_mustaqil_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('retake_group_id');
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('student_hemis_id');

            // Talaba yuklagani
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->text('student_comment')->nullable();
            $table->timestamp('submitted_at')->nullable();

            // O'qituvchi bahosi
            $table->decimal('grade', 5, 2)->nullable();
            $table->text('teacher_comment')->nullable();
            $table->unsignedBigInteger('graded_by_user_id')->nullable();
            $table->string('graded_by_name')->nullable();
            $table->timestamp('graded_at')->nullable();

            $table->timestamps();

            $table->unique(['retake_group_id', 'application_id'], 'retake_mustaqil_unique_idx');
            $table->index('student_hemis_id');

            $table->foreign('retake_group_id')
                ->references('id')->on('retake_groups')
                ->onDelete('cascade');

            $table->foreign('application_id')
                ->references('id')->on('retake_applications')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_mustaqil_submissions');
    }
};
