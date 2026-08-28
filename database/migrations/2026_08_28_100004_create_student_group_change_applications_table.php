<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_group_change_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('source_group_id');
            $table->unsignedBigInteger('target_group_id');
            $table->string('student_name');
            $table->string('student_id_number')->nullable();
            $table->string('faculty_name');
            $table->string('specialty_name');
            $table->unsignedTinyInteger('course');
            $table->string('source_group_name');
            $table->string('target_group_name');
            $table->text('reason');
            $table->string('status', 24)->default('pending');
            $table->text('review_note')->nullable();
            $table->unsignedBigInteger('reviewed_by_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id', 'sgca_student_fk')
                ->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('source_group_id', 'sgca_source_group_fk')
                ->references('id')->on('student_distribution_groups')->cascadeOnDelete();
            $table->foreign('target_group_id', 'sgca_target_group_fk')
                ->references('id')->on('student_distribution_groups')->cascadeOnDelete();
            $table->index(['student_id', 'status'], 'sgca_student_status_index');
            $table->index(['status', 'created_at'], 'sgca_status_created_index');
            $table->index('target_group_id', 'sgca_target_group_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_group_change_applications');
    }
};
