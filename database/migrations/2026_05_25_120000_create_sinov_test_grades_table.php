<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sinov_test_grades')) {
            return;
        }

        Schema::create('sinov_test_grades', function (Blueprint $table) {
            $table->id();
            $table->string('subject_id');
            $table->string('semester_code');
            $table->string('group_hemis_id');
            $table->string('student_hemis_id');
            $table->decimal('default_grade', 5, 2)->nullable();
            $table->decimal('override_grade', 5, 2)->nullable();
            $table->boolean('is_locked')->default(false);
            $table->unsignedBigInteger('overridden_by_user_id')->nullable();
            $table->timestamp('overridden_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['subject_id', 'semester_code', 'group_hemis_id', 'student_hemis_id'],
                'sinov_test_grades_unique'
            );
            $table->index(['group_hemis_id', 'subject_id', 'semester_code'], 'sinov_test_grades_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sinov_test_grades');
    }
};
