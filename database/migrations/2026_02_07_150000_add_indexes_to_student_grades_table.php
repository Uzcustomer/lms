<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->index(['semester_code', 'grade'], 'idx_sg_semester_grade');
            $table->index(['student_hemis_id', 'subject_id', 'semester_code'], 'idx_sg_student_subject_semester');
            $table->index('subject_id', 'idx_sg_subject_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->index('hemis_id', 'idx_students_hemis_id');
            $table->index('group_id', 'idx_students_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->dropIndex('idx_sg_semester_grade');
            $table->dropIndex('idx_sg_student_subject_semester');
            $table->dropIndex('idx_sg_subject_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('idx_students_hemis_id');
            $table->dropIndex('idx_students_group_id');
        });
    }
};
