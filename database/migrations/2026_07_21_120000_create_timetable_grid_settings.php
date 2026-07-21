<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panjara sozlamalari har yo'nalish + kurs uchun alohida: ba'zi fakultet/
 * yo'nalish/kurslarda hafta davomiyligi, kunlar yoki kuniga para soni
 * boshqacha bo'ladi. Yozuv bo'lmasa — doskaning umumiy sozlamasi ishlatiladi.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('timetable_grid_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('timetable_boards')->cascadeOnDelete();
            $table->string('specialty_name');
            $table->unsignedTinyInteger('course');
            $table->unsignedTinyInteger('days');
            $table->unsignedTinyInteger('pairs_per_day');
            $table->unsignedTinyInteger('weeks');
            $table->timestamps();

            $table->unique(['board_id', 'specialty_name', 'course'], 'ttgs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_grid_settings');
    }
};
