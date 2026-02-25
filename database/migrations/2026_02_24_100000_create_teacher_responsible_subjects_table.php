<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teacher_responsible_subjects')) {
            return;
        }

        Schema::create('teacher_responsible_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->foreignId('curriculum_subject_id')->constrained('curriculum_subjects')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['teacher_id', 'curriculum_subject_id'], 'teacher_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_responsible_subjects');
    }
};
