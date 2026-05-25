<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual imtihon sanasi uchun:
 *  - exam_schedules.individual_note: shaxsiy sana belgilashda admin
 *    yozadigan izoh (sabab — pullik xizmat, sport, tibbiy va h.k.)
 *  - exam_schedules.override_warning: agar baholar yo'q yoki pullik bo'lishiga
 *    qaramay admin majburan sana qo'ygan bo'lsa true
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('exam_schedules', 'individual_note')) {
            Schema::table('exam_schedules', function (Blueprint $table) {
                $table->text('individual_note')->nullable()->after('test_resit2_time');
            });
        }
        if (!Schema::hasColumn('exam_schedules', 'override_warning')) {
            Schema::table('exam_schedules', function (Blueprint $table) {
                $table->boolean('override_warning')->default(false)->after('individual_note');
            });
        }
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('exam_schedules', 'override_warning')) {
                $table->dropColumn('override_warning');
            }
            if (Schema::hasColumn('exam_schedules', 'individual_note')) {
                $table->dropColumn('individual_note');
            }
        });
    }
};
