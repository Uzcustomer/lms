<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taqsimot rejasi (draft).
 *
 * MUHIM: bu jadval faqat rejani saqlaydi. Talabaning LMS dagi guruhi
 * (students.group_id) hech qachon o'zgartirilmaydi — reja faqat shu sahifada
 * va Excel eksportida ko'rinadi. Shu sababli HEMIS importi rejaga ta'sir
 * qilmaydi va aksincha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_draft_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('from_group_hemis_id')->index();
            $table->unsignedBigInteger('to_group_hemis_id')->index();

            // Reja tuzilgan paytdagi nusxa — keyin nom o'zgarsa ham
            // hisobotda o'sha paytdagi holat ko'rinadi.
            $table->string('student_name');
            $table->string('student_id_number')->nullable();
            $table->string('from_group_name')->nullable();
            $table->string('to_group_name')->nullable();

            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();

            // Bitta talaba rejada faqat bir marta bo'ladi.
            $table->unique('student_id', 'dda_student_unique');
            $table->foreign('student_id', 'dda_student_fk')
                ->references('id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_draft_assignments');
    }
};
