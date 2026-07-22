<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yarim-para (0.5) darslarni qo'llab-quvvatlash.
 *
 * Yarim para = 1 soat, bir para = 2 soat. Har dars endi yarim-para birligida
 * uzunlikka ega (len_half): 1 = 0.5 para (1 soat), 2 = 1 para (2 soat),
 * 3 = 1.5 para (3 soat), 4 = 2 para (4 soat). start_half — dars para ichida
 * qaysi yarimdan boshlanishi (0 = para boshi, 1 = para o'rtasi).
 *
 * `pair` maydoni avvalgidek para raqami (1..N) bo'lib qoladi — orqaga mos.
 * Mutlaq yarim-slot indeksi = (pair-1)*2 + start_half.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_cards', function (Blueprint $table) {
            if (!Schema::hasColumn('timetable_cards', 'start_half')) {
                $table->unsignedTinyInteger('start_half')->default(0)->after('pair');
            }
            if (!Schema::hasColumn('timetable_cards', 'len_half')) {
                $table->unsignedTinyInteger('len_half')->default(2)->after('start_half');
            }
        });

        if (Schema::hasTable('timetable_card_overrides')) {
            Schema::table('timetable_card_overrides', function (Blueprint $table) {
                if (!Schema::hasColumn('timetable_card_overrides', 'start_half')) {
                    $table->unsignedTinyInteger('start_half')->default(0)->after('pair');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('timetable_cards', function (Blueprint $table) {
            foreach (['start_half', 'len_half'] as $col) {
                if (Schema::hasColumn('timetable_cards', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        if (Schema::hasTable('timetable_card_overrides') && Schema::hasColumn('timetable_card_overrides', 'start_half')) {
            Schema::table('timetable_card_overrides', function (Blueprint $table) {
                $table->dropColumn('start_half');
            });
        }
    }
};
