<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('retake_groups', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            // Fan
            $table->string('subject_id', 50);
            $table->string('subject_name');
            $table->string('subject_code')->nullable();

            // Semestr
            $table->string('semester_id', 50);
            $table->string('semester_name');

            // O'qituvchi (teachers.id)
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->string('teacher_name')->nullable();

            // Sanalar
            $table->date('start_date');
            $table->date('end_date');

            $table->unsignedInteger('max_students')->nullable();

            // forming → scheduled → in_progress → completed
            $table->enum('status', ['forming', 'scheduled', 'in_progress', 'completed'])
                ->default('forming');

            $table->unsignedBigInteger('created_by_user_id')->nullable(); // teachers.id (no FK)
            $table->string('created_by_name')->nullable();

            $table->timestamps();

            $table->index(['subject_id', 'semester_id']);
            $table->index('status');
            $table->index(['status', 'start_date', 'end_date'], 'retake_group_transition_idx');

            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_groups');
    }
};
