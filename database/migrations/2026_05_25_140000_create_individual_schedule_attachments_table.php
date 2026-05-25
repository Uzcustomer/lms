<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual imtihon sanasi uchun asoslovchi hujjatlar.
 * Bir exam_schedules yozuviga bir necha fayl ilova qilish mumkin
 * (masalan, tibbiy spravka + pullik xizmat kvitansiyasi).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('individual_schedule_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_schedule_id');
            $table->string('student_hemis_id', 32)->index();
            $table->string('subject_id', 64)->nullable();
            $table->string('semester_code', 32)->nullable();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->string('uploaded_by_guard', 32)->nullable();
            $table->string('uploaded_by_name')->nullable();
            $table->text('note')->nullable()->comment('Fayl haqida qisqa izoh');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('exam_schedule_id')
                ->references('id')->on('exam_schedules')
                ->onDelete('cascade');
            $table->index('exam_schedule_id', 'isa_attach_es_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_schedule_attachments');
    }
};
