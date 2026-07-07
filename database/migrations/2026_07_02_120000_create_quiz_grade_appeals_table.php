<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quiz_grade_appeals')) {
            return;
        }

        Schema::create('quiz_grade_appeals', function (Blueprint $table) {
            $table->id();

            // Tuzatilgan baho (student_grades qatori) va Moodle manbasi
            $table->unsignedBigInteger('student_grade_id')->nullable();
            $table->unsignedBigInteger('quiz_result_id')->nullable();

            $table->string('student_hemis_id')->nullable();
            $table->string('student_name')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_name')->nullable();

            // 'replace' — bahoni almashtirish; 'delete' — o'chirish
            $table->string('action', 20);
            $table->decimal('old_grade', 5, 2)->nullable();
            $table->decimal('new_grade', 5, 2)->nullable();

            // Asoslash
            $table->text('reason');
            $table->string('document_path');
            $table->string('document_original_name')->nullable();

            // Kim bajardi (o'quv prorektori / superadmin)
            $table->string('performed_by_guard', 20)->nullable();
            $table->unsignedBigInteger('performed_by_id')->nullable();
            $table->string('performed_by_name')->nullable();
            $table->string('performed_by_role', 40)->nullable();

            $table->timestamps();

            $table->index('student_grade_id');
            $table->index('student_hemis_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_grade_appeals');
    }
};
