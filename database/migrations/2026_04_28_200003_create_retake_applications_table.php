<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('retake_applications', function (Blueprint $table) {
            $table->id();
            // Bir vaqtda yuborilgan ko'p fanli arizalarni bog'laydi
            $table->uuid('application_group_id');

            // Talaba — students.id local PK
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // HEMIS subject_id (academic_records ga mos)
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name');
            // HEMIS semester_hemis_id
            $table->unsignedBigInteger('semester_id');
            $table->string('semester_name')->nullable();
            // Snapshot — kredit
            $table->decimal('credit', 4, 2);

            // Qabul oynasi
            $table->foreignId('period_id')
                ->constrained('retake_application_periods')
                ->restrictOnDelete();

            // Kvitansiya (bitta umumiy kvitansiya hamma fanlar uchun — group_id bo'yicha umumiy)
            $table->string('receipt_path');
            $table->string('receipt_original_name');
            $table->unsignedInteger('receipt_size');
            $table->string('receipt_mime', 50);
            $table->text('student_note')->nullable();

            // Avto-generatsiya qilingan ariza DOCX (group bo'yicha umumiy)
            $table->string('generated_doc_path')->nullable();

            // === Parallel approval (3 ta alohida status) ===
            $table->string('dean_status', 20)->default('pending');
            $table->string('registrar_status', 20)->default('pending');
            // not_started → pending (avto, dekan VA registrator approved bo'lganda) → approved/rejected
            $table->string('academic_dept_status', 20)->default('not_started');

            // Dekan (Teacher modeli, web guard)
            $table->unsignedBigInteger('dean_reviewed_by')->nullable();
            $table->string('dean_reviewed_by_guard', 10)->nullable();
            $table->timestamp('dean_reviewed_at')->nullable();
            $table->text('dean_rejection_reason')->nullable();

            // Registrator
            $table->unsignedBigInteger('registrar_reviewed_by')->nullable();
            $table->string('registrar_reviewed_by_guard', 10)->nullable();
            $table->timestamp('registrar_reviewed_at')->nullable();
            $table->text('registrar_rejection_reason')->nullable();

            // O'quv bo'limi
            $table->unsignedBigInteger('academic_dept_reviewed_by')->nullable();
            $table->string('academic_dept_reviewed_by_guard', 10)->nullable();
            $table->timestamp('academic_dept_reviewed_at')->nullable();
            $table->text('academic_dept_rejection_reason')->nullable();

            // Approved bo'lganda guruhga biriktiriladi
            $table->foreignId('retake_group_id')
                ->nullable()
                ->constrained('retake_groups')
                ->nullOnDelete();

            // Yakuniy approved bo'lganda generatsiya qilinadi (UUID v4)
            $table->uuid('verification_code')->nullable()->unique();
            $table->string('tasdiqnoma_pdf_path')->nullable();

            $table->timestamp('submitted_at');
            $table->timestamps();

            // === Indexlar ===
            $table->index('application_group_id', 'retake_apps_group_idx');
            $table->index(['student_id', 'subject_id'], 'retake_apps_student_subject_idx');
            $table->index('dean_status', 'retake_apps_dean_status_idx');
            $table->index('registrar_status', 'retake_apps_registrar_status_idx');
            $table->index('academic_dept_status', 'retake_apps_acad_status_idx');
            $table->index('period_id', 'retake_apps_period_idx');
            $table->index('retake_group_id', 'retake_apps_group_fk_idx');
            $table->index(['subject_id', 'semester_id'], 'retake_apps_subject_sem_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_applications');
    }
};
