<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('retake_applications', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('group_id'); // retake_application_groups.id
            $table->unsignedBigInteger('student_hemis_id');

            // Fan ma'lumotlari (academic_records dan olinadi)
            $table->string('subject_id', 50);
            $table->string('subject_name');
            $table->string('semester_id', 50);
            $table->string('semester_name');
            $table->decimal('credit', 5, 2);

            // ─── Dekan ──────────────────────────────────────
            $table->enum('dean_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('dean_user_id')->nullable(); // teachers.id (no FK; xodimlar teachers jadvalida)
            $table->string('dean_user_name')->nullable();
            $table->timestamp('dean_decision_at')->nullable();
            $table->text('dean_reason')->nullable();

            // ─── Registrator ofis ───────────────────────────
            $table->enum('registrar_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('registrar_user_id')->nullable(); // teachers.id
            $table->string('registrar_user_name')->nullable();
            $table->timestamp('registrar_decision_at')->nullable();
            $table->text('registrar_reason')->nullable();

            // ─── O'quv bo'limi ──────────────────────────────
            // dean+registrar approved bo'lganda 'pending' ga avto o'tadi
            $table->enum('academic_dept_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('academic_dept_user_id')->nullable(); // teachers.id
            $table->string('academic_dept_user_name')->nullable();
            $table->timestamp('academic_dept_decision_at')->nullable();
            $table->text('academic_dept_reason')->nullable();

            // Yakuniy holat (cached, yuqoridagi 3 dan hisoblanadi)
            $table->enum('final_status', ['pending', 'approved', 'rejected'])->default('pending');
            // Qaysi rol rad etgan: dean / registrar / academic_dept / system_hemis
            $table->string('rejected_by', 30)->nullable();

            // O'quv bo'limi guruhga biriktirgach to'ldiriladi
            $table->unsignedBigInteger('retake_group_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indekslar
            $table->index(['student_hemis_id', 'subject_id', 'final_status'], 'retake_app_unique_lookup_idx');
            $table->index('group_id');
            $table->index('retake_group_id');
            $table->index(['dean_status', 'registrar_status'], 'retake_app_parallel_idx');
            $table->index('academic_dept_status');
            $table->index('final_status');

            // Foreign keys
            $table->foreign('group_id')
                ->references('id')->on('retake_application_groups')
                ->onDelete('cascade');

            $table->foreign('retake_group_id')
                ->references('id')->on('retake_groups')
                ->onDelete('set null');

            // Tasdiqlovchi xodimlar teachers jadvalida saqlanadi va polimorfik bo'lishi
            // mumkin (loyiha pattern: unsignedBigInteger + cached name, FK yo'q).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_applications');
    }
};
