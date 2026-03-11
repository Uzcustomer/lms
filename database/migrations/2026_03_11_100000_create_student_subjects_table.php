<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_subjects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_hemis_id')->index();
            $table->unsignedBigInteger('curriculum_subject_hemis_id')->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index(); // HEMIS subject ID (for joining with academic_records)
            $table->string('semester_id')->nullable()->index();
            $table->string('subject_name')->nullable();
            $table->timestamps();

            $table->unique(['student_hemis_id', 'curriculum_subject_hemis_id'], 'ss_student_cs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subjects');
    }
};
