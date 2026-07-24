<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fan bo'yicha jadval rejimi (1-3 kurs hafta almashinuvi / 4-6 kurs sikl).
 *
 * Har fan (doska + yo'nalish + kurs + fan nomi bo'yicha) uchun alohida rejim
 * saqlanadi. Doskaga bog'langan — reja jadvalini (manual_curriculum_subjects)
 * o'zgartirmaymiz. Rejimlar:
 *  - normal   → har hafta bir xil (sukut);
 *  - alternate→ hafta almashinuvi: bitta katakni bir necha fan navbat bilan
 *               bo'lishadi (rotation_group + occurrences: necha marta keladi);
 *  - cycle    → sikl (blok): guruhlar rotatsiyasi bo'yicha uzluksiz blok
 *               (cycle_weeks: sikl uzunligi haftada).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('timetable_subject_settings')) {
            return;
        }
        Schema::create('timetable_subject_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('timetable_boards')->cascadeOnDelete();
            $table->string('specialty_name');
            $table->unsignedTinyInteger('course');
            $table->string('subject_name');
            // normal | alternate | cycle
            $table->string('mode', 20)->default('normal');
            // alternate: bir slotni bo'lishadigan fanlar guruhi (bir xil belgi + bir slot)
            $table->string('rotation_group')->nullable();
            // alternate: fan necha marta (hafta) keladi; bo'sh bo'lsa soatdan hisoblanadi
            $table->unsignedSmallInteger('occurrences')->nullable();
            // cycle: sikl (blok) uzunligi haftada
            $table->unsignedTinyInteger('cycle_weeks')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['board_id', 'specialty_name', 'course', 'subject_name'], 'tss_unique');
            $table->index(['board_id', 'course']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_subject_settings');
    }
};
