<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_distribution_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('source_group_id');
            $table->unsignedBigInteger('target_group_id');
            $table->string('original_group_hemis_id')->nullable();
            $table->string('original_group_name')->nullable();
            $table->string('student_name');
            $table->string('student_id_number')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();

            $table->unique('student_id', 'sd_assignments_student_unique');
            $table->index('source_group_id', 'sd_assignments_source_index');
            $table->index('target_group_id', 'sd_assignments_target_index');
            $table->foreign('student_id', 'sd_assignments_student_fk')
                ->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('source_group_id', 'sd_assignments_source_fk')
                ->references('id')->on('student_distribution_groups')->cascadeOnDelete();
            $table->foreign('target_group_id', 'sd_assignments_target_fk')
                ->references('id')->on('student_distribution_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_distribution_assignments');
    }
};
