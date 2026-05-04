<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['student_hemis_id', 'subject_id', 'semester_code'], 'idx_att_student_subject_semester');
            $table->index(['subject_id', 'semester_code'], 'idx_att_subject_semester');
            $table->index('student_hemis_id', 'idx_att_student_hemis');
            $table->index('semester_code', 'idx_att_semester_code');
            $table->index('education_year_code', 'idx_att_education_year_code');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_att_student_subject_semester');
            $table->dropIndex('idx_att_subject_semester');
            $table->dropIndex('idx_att_student_hemis');
            $table->dropIndex('idx_att_semester_code');
            $table->dropIndex('idx_att_education_year_code');
        });
    }
};
