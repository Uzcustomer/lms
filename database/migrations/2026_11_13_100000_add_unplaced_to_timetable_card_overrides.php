<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Haftalik yozuv "joylanmagan (o'tiladi)": shablonda joyi bor dars shu haftada joydan olingan, lekin bekor qilinmagan — "Joylashmagan
 * kartalar" panelida turadi va qayta joylanadi (masalan "Yaxlitlab bo'lmadi — joylanmaganlarga o'tkazildi"). cancelled = true bilan birga:
 * bandlik va jadvalda joyi yo'q; unplaced = true — "o'tilmaydi" emas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('timetable_card_overrides', 'unplaced')) {
            Schema::table('timetable_card_overrides', function (Blueprint $table) {
                $table->boolean('unplaced')->default(false)->after('cancelled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('timetable_card_overrides', 'unplaced')) {
            Schema::table('timetable_card_overrides', function (Blueprint $table) {
                $table->dropColumn('unplaced');
            });
        }
    }
};
