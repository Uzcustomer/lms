<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dars jadvali qoidalari (aSc Timetables "Взаимосвязи" uslubida).
 *
 * Har qoida: qaysi fanlarga (subjects), qaysi yo'nalish+kurslarga (scopes)
 * tegishli, sharti (condition) va shart parametrlari (params) bilan.
 * weight — qoidaning og'irligi (majburiy / normal / yengil), active — yoqilganmi,
 * position — ro'yxatdagi tartib (yuqoriga/pastga ko'chirish uchun).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('timetable_rules')) {
            return;
        }
        Schema::create('timetable_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('timetable_boards')->cascadeOnDelete();
            // Shart turi (kod) — frontend ro'yxatidagi radio tanlovlar
            $table->string('condition', 60);
            // Qamrov: fanlar ro'yxati (bo'sh = barcha fanlar)
            $table->json('subjects')->nullable();
            // Qamrov: "yo'nalish|kurs" ro'yxati (bo'sh = barchasi)
            $table->json('scopes')->nullable();
            // Shartga xos qo'shimcha parametrlar (masalan tartib, kunlar soni)
            $table->json('params')->nullable();
            // majburiy | normal | yengil
            $table->string('weight', 20)->default('normal');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['board_id', 'active']);
            $table->index(['board_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_rules');
    }
};
