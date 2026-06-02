<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vedomost_submissions', function (Blueprint $table) {
            $table->id();

            // Identifikatsiya (joriy semestr, guruh, fan)
            $table->string('education_year')->index();
            $table->string('semester_code');
            $table->unsignedBigInteger('group_hemis_id');
            $table->string('group_name')->nullable();
            $table->unsignedBigInteger('curriculum_hemis_id')->nullable();
            $table->unsignedBigInteger('curriculum_subject_id')->nullable(); // curriculum_subjects.id
            $table->unsignedBigInteger('subject_id')->nullable();            // HEMIS subject_id (moslashtirish uchun)
            $table->string('subject_name')->nullable();

            // Kafedra / yo'nalish
            $table->unsignedBigInteger('department_hemis_id')->nullable();
            $table->string('department_name')->nullable();
            $table->string('specialty_name')->nullable();

            // Yopilish shakli
            $table->string('closing_form', 20)->nullable();

            // O'qituvchi (dars jadvalidan asosiy o'qituvchi)
            $table->unsignedBigInteger('teacher_hemis_id')->nullable();
            $table->string('teacher_name')->nullable();

            // Deadline hisobi
            $table->string('base_type', 20)->nullable(); // 'lesson' | 'exam'
            $table->date('base_date')->nullable();        // oxirgi dars yoki YN sanasi
            $table->date('deadline')->nullable();         // base_date + 3 ish kuni

            // Status oqimi
            $table->enum('status', ['pending', 'received', 'reviewing', 'approved', 'rejected'])
                ->default('pending');

            // Yuklangan fayllar (registrator ofisi skaner qiladi)
            $table->string('pdf_path')->nullable();
            $table->string('excel_path')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('uploaded_by_name')->nullable();
            $table->timestamp('uploaded_at')->nullable();

            // Tekshiruv
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('reviewed_by_name')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable(); // rad etilganda xatolar

            // O'quv prorektoriga bildirgi (rad etilganda)
            $table->timestamp('prorektor_notified_at')->nullable();

            // Kechikish ogohlantirishlari (takror yubormaslik uchun)
            $table->string('warning_stage', 20)->nullable(); // 'soon' | 'overdue'
            $table->timestamp('warned_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['group_hemis_id', 'subject_id', 'semester_code', 'education_year'],
                'vedomost_subm_group_subject_sem_year_unique'
            );
            $table->index('status');
            $table->index('deadline');
            $table->index('department_hemis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vedomost_submissions');
    }
};
