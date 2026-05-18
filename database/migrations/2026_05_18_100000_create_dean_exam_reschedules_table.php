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
            $table->foreignId('computer_assignment_id')
                ->constrained('computer_assignments')
                ->cascadeOnDelete();
            $table->string('student_hemis_id', 50)->index();
            $table->string('yn_type', 10);
            $table->date('used_date');
            $table->dateTime('original_start');
            $table->dateTime('original_end');
            $table->unsignedSmallInteger('original_computer')->nullable();
            $table->dateTime('new_start');
            $table->dateTime('new_end');
            $table->unsignedSmallInteger('new_computer');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by')->comment('Dekanat user id');
            $table->timestamps();

            // Bir talabaga bir kun ichida faqat bir marta dekanat reschedule qila oladi.
            $table->unique(['student_hemis_id', 'used_date'], 'dean_reschedule_one_per_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dean_exam_reschedules');
    }
};
