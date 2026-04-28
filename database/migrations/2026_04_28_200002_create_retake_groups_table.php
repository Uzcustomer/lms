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
            // HEMIS subject_id (academic_records.subject_id ga mos)
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name');
            // HEMIS semester_hemis_id
            $table->unsignedBigInteger('semester_id');
            $table->string('semester_name')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            // Teacher.id — o'qituvchi
            $table->foreignId('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->unsignedInteger('max_students')->nullable();
            $table->string('status', 20)->default('forming');
            // Yaratuvchi (o'quv bo'limi xodimi)
            $table->unsignedBigInteger('created_by');
            $table->string('created_by_guard', 10)->default('web');
            $table->timestamps();

            $table->index(['subject_id', 'semester_id'], 'retake_groups_subject_sem_idx');
            $table->index('status', 'retake_groups_status_idx');
            $table->index(['start_date', 'end_date'], 'retake_groups_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_groups');
    }
};
