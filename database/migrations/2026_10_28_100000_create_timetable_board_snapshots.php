<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dars jadvali doskasining versiyalari (zaxira nusxalar): kartalar (joylashuv, o'qituvchi, xona),
 * haftalik o'zgartirishlar va sikl joylashuvlari — siqilgan JSON. Har kecha va xavfli amallardan oldin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('timetable_board_snapshots')) {
            return;
        }
        Schema::create('timetable_board_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('timetable_boards')->cascadeOnDelete();
            $table->string('reason', 50);                 // daily | manual | before_generate | before_unplace | ...
            $table->string('created_by_name')->nullable();
            $table->unsignedInteger('cards_count')->default(0);
            $table->unsignedInteger('placed_count')->default(0);
            $table->unsignedInteger('overrides_count')->default(0);
            $table->longText('payload');                  // base64(gzencode(json))
            $table->timestamps();
            $table->index(['board_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_board_snapshots');
    }
};
