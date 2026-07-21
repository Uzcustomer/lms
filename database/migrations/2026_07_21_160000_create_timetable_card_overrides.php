<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hafta bo'yicha dars istisnolari (individual haftalar uchun).
 *
 * Har karta sukut bo'yicha barcha haftalarda o'z (day, pair) da takrorlanadi
 * (shablon). Ushbu jadval faqat farq qiladigan haftalar uchun override saqlaydi:
 *  - cancelled = true  → shu haftada dars bo'lmaydi;
 *  - day/pair to'ldirilgan → shu haftada boshqa katakka ko'chirilgan.
 * Override bo'lmasa — shablon (kartaning asosiy day/pair) amal qiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_card_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('timetable_cards')->cascadeOnDelete();
            $table->unsignedTinyInteger('week');
            $table->unsignedTinyInteger('day')->nullable();
            $table->unsignedTinyInteger('pair')->nullable();
            $table->boolean('cancelled')->default(false);
            $table->timestamps();

            $table->unique(['card_id', 'week']);
            $table->index(['card_id', 'week', 'day', 'pair']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_card_overrides');
    }
};
