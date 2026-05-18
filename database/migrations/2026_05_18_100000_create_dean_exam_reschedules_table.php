<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dean_exam_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_id')
                ->constrained('exam_schedules')
                ->cascadeOnDelete();
            $table->string('yn_type', 10);
            $table->date('used_date');
            $table->string('original_time', 5)->nullable();
            $table->string('new_time', 5);
            $table->unsignedSmallInteger('student_count');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by')->comment('Dekanat user id');
            $table->timestamps();

            // Bir guruhning bir YN'ini bir kun ichida faqat bir marta
            // ko'chirish mumkin.
            $table->unique(
                ['exam_schedule_id', 'yn_type', 'used_date'],
                'dean_reschedule_one_per_group_per_day'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dean_exam_reschedules');
    }
};
