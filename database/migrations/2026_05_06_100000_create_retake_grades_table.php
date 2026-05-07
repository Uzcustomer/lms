<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Qayta o'qish jurnali — alohida jadval, mavjud `student_grades`ga
        // tegmaslik uchun. Har retake_group ichida har talaba uchun har kun
        // bittadan baho yozuvi.
        Schema::create('retake_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('retake_group_id');
            $table->unsignedBigInteger('application_id'); // retake_applications.id
            $table->unsignedBigInteger('student_hemis_id');
            $table->date('lesson_date');

            // 0..100 yoki null (bo'sh — hali baho qo'yilmagan)
            $table->decimal('grade', 5, 2)->nullable();
            $table->text('comment')->nullable();

            // Kim qo'ydi (audit)
            $table->unsignedBigInteger('graded_by_user_id')->nullable();
            $table->string('graded_by_name')->nullable();
            $table->timestamp('graded_at')->nullable();

            $table->timestamps();

            $table->unique(['retake_group_id', 'application_id', 'lesson_date'], 'retake_grades_unique_idx');
            $table->index(['retake_group_id', 'lesson_date']);
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
        Schema::dropIfExists('retake_grades');
    }
};
