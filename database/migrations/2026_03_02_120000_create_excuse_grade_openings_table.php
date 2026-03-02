<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excuse_grade_openings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('absence_excuse_id');
            $table->unsignedBigInteger('absence_excuse_makeup_id');
            $table->string('student_hemis_id');
            $table->string('subject_id');
            $table->date('date_from');
            $table->date('date_to');
            $table->dateTime('deadline');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->foreign('absence_excuse_id')->references('id')->on('absence_excuses')->onDelete('cascade');
            $table->foreign('absence_excuse_makeup_id')->references('id')->on('absence_excuse_makeups')->onDelete('cascade');
            $table->index(['student_hemis_id', 'subject_id', 'status'], 'ego_student_subject_idx');
            $table->index(['status', 'deadline'], 'ego_status_deadline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excuse_grade_openings');
    }
};
