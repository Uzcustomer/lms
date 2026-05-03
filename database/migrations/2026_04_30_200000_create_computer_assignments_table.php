<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('computer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_id')
                ->constrained('exam_schedules')
                ->cascadeOnDelete();
            $table->string('student_id_number', 50)->index();
            $table->string('student_hemis_id', 50)->index();
            $table->string('yn_type', 10); // oski|test
            $table->unsignedSmallInteger('computer_number');
            $table->dateTime('planned_start');
            $table->dateTime('planned_end');
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_end')->nullable();
            $table->string('status', 20)->default('scheduled');
            // Values: scheduled | in_progress | finished | abandoned | moved
            $table->unsignedBigInteger('moodle_attempt_id')->nullable();
            $table->json('history')->nullable(); // audit of changes (computer/time moves)
            $table->timestamps();

            $table->index(['exam_schedule_id', 'yn_type']);
            $table->index(['computer_number', 'planned_start']);
            $table->index(['planned_start', 'planned_end']);
            $table->unique(
                ['exam_schedule_id', 'student_id_number', 'yn_type'],
                'comp_assign_unique_per_schedule'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('computer_assignments');
    }
};
