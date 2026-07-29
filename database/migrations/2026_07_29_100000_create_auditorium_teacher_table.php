<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auditorium_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('timetable_boards')->cascadeOnDelete();
            $table->foreignId('auditorium_id')->constrained('auditoriums')->cascadeOnDelete();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->boolean('is_general')->default(false);
            $table->timestamps();

            $table->unique(['board_id', 'auditorium_id']);
            $table->index(['board_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorium_teacher');
    }
};
