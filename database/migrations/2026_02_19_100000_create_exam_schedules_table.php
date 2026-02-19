<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('department_hemis_id')->comment('Fakultet HEMIS ID');
            $table->string('specialty_hemis_id')->comment("Yo'nalish HEMIS ID");
            $table->string('curriculum_hemis_id')->comment("O'quv reja HEMIS ID");
            $table->string('semester_code')->comment('Semestr kodi');
            $table->string('group_hemis_id')->comment('Guruh HEMIS ID');
            $table->string('subject_id')->comment('Fan ID');
            $table->string('subject_name')->comment('Fan nomi');
            $table->date('oski_date')->nullable()->comment('OSKI imtihon sanasi');
            $table->date('test_date')->nullable()->comment('Test imtihon sanasi');
            $table->string('education_year')->nullable()->comment("O'quv yili");
            $table->unsignedBigInteger('created_by')->nullable()->comment('Kim yaratgan');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Kim yangilagan');
            $table->timestamps();

            $table->index('department_hemis_id');
            $table->index('semester_code');
            $table->index('group_hemis_id');
            $table->index('subject_id');
            $table->index(['group_hemis_id', 'subject_id', 'semester_code'], 'exam_schedule_group_subject_semester');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_schedules');
    }
};
