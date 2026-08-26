<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('timetable_cycle_placements')) {
            return;
        }

        Schema::create('timetable_cycle_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('timetable_boards')->cascadeOnDelete();
            $table->string('specialty_name');
            $table->unsignedTinyInteger('course');
            $table->string('group_name');
            $table->string('subject_name');
            $table->unsignedSmallInteger('start_index');
            $table->timestamps();

            $table->unique(
                ['board_id', 'specialty_name', 'course', 'group_name', 'subject_name'],
                'tcp_subject_unique'
            );
            $table->index(['board_id', 'specialty_name', 'course']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_cycle_placements');
    }
};
