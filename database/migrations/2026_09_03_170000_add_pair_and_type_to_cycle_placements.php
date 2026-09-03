<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sikl bloki endi juftlik darajasida: har blok bitta juftlikda, ma'ruza
 * yoki amaliy turida yotadi. Eski (turi/juftligi yo'q) yozuvlar amaliy va
 * 1-juftlik deb belgilanadi.
 *
 * Eski unique indeks (fan bo'yicha bitta yozuv) endi torlik qiladi — bir
 * fanning ma'ruzasi va amaliyoti alohida yoziladi. Indeks bayt chegarasi
 * (3072) tufayli unga ustun qo'shib bo'lmaydi, shuning uchun u o'chirilib,
 * unikallik kodda (updateOrCreate kaliti) ta'minlanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('timetable_cycle_placements')) {
            return;
        }

        Schema::table('timetable_cycle_placements', function (Blueprint $table) {
            if (!Schema::hasColumn('timetable_cycle_placements', 'training_type')) {
                $table->string('training_type', 20)->nullable()->after('subject_name');
            }
            if (!Schema::hasColumn('timetable_cycle_placements', 'pair')) {
                $table->unsignedTinyInteger('pair')->nullable()->after('start_index');
            }
        });

        DB::table('timetable_cycle_placements')->whereNull('training_type')->update(['training_type' => 'practice']);
        DB::table('timetable_cycle_placements')->whereNull('pair')->update(['pair' => 1]);

        $indexes = collect(DB::select("SHOW INDEX FROM timetable_cycle_placements"))->pluck('Key_name')->unique();
        if ($indexes->contains('tcp_subject_unique')) {
            Schema::table('timetable_cycle_placements', function (Blueprint $table) {
                $table->dropUnique('tcp_subject_unique');
            });
        }
        if (!$indexes->contains('tcp_board_idx')) {
            Schema::table('timetable_cycle_placements', function (Blueprint $table) {
                $table->index('board_id', 'tcp_board_idx');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('timetable_cycle_placements')) {
            return;
        }

        Schema::table('timetable_cycle_placements', function (Blueprint $table) {
            foreach (['training_type', 'pair'] as $column) {
                if (Schema::hasColumn('timetable_cycle_placements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
