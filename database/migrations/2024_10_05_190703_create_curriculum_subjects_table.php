<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('curriculum_subjects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curriculum_subject_hemis_id')->unique();
            $table->unsignedBigInteger('curricula_hemis_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name');
            $table->string('subject_code');
            $table->string('subject_type_code')->nullable();
            $table->string('subject_type_name')->nullable();
            $table->string('subject_block_code')->nullable();
            $table->string('subject_block_name')->nullable();
            $table->string('semester_code');
            $table->string('semester_name');
            $table->decimal('total_acload', 8, 2)->nullable();
            $table->decimal('credit', 6, 2)->nullable();
            $table->string('in_group')->nullable();
            $table->boolean('at_semester');
            $table->json('subject_details')->nullable();
            $table->json('subject_exam_types')->nullable();
            $table->string('rating_grade_code')->nullable();
            $table->string('rating_grade_name')->nullable();
            $table->string('exam_finish_code')->nullable();
            $table->string('exam_finish_name')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('department_name')->nullable();
            $table->timestamps();

//            $table->foreign('curricula_hemis_id')->references('curricula_hemis_id')->on('curricula')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_subjects');
    }
};
