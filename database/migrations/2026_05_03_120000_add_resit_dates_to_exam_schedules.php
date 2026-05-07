<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 12a (1-urinish) va 12b (2-urinish) qayta topshirish sanalari uchun
 * exam_schedules jadvaliga ustunlar qo'shish.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('exam_schedules')) {
            return;
        }

        Schema::table('exam_schedules', function (Blueprint $table) {
            // 12a (1-urinish) qayta topshirish sanalari
            if (!Schema::hasColumn('exam_schedules', 'oski_resit_date')) {
                $table->date('oski_resit_date')->nullable()->after('oski_time');
            }
            if (!Schema::hasColumn('exam_schedules', 'oski_resit_time')) {
                $table->time('oski_resit_time')->nullable()->after('oski_resit_date');
            }
            if (!Schema::hasColumn('exam_schedules', 'test_resit_date')) {
                $table->date('test_resit_date')->nullable()->after('test_time');
            }
            if (!Schema::hasColumn('exam_schedules', 'test_resit_time')) {
                $table->time('test_resit_time')->nullable()->after('test_resit_date');
            }
            // 12b (2-urinish) qayta topshirish sanalari
            if (!Schema::hasColumn('exam_schedules', 'oski_resit2_date')) {
                $table->date('oski_resit2_date')->nullable()->after('oski_resit_time');
            }
            if (!Schema::hasColumn('exam_schedules', 'oski_resit2_time')) {
                $table->time('oski_resit2_time')->nullable()->after('oski_resit2_date');
            }
            if (!Schema::hasColumn('exam_schedules', 'test_resit2_date')) {
                $table->date('test_resit2_date')->nullable()->after('test_resit_time');
            }
            if (!Schema::hasColumn('exam_schedules', 'test_resit2_time')) {
                $table->time('test_resit2_time')->nullable()->after('test_resit2_date');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('exam_schedules')) {
            return;
        }

        Schema::table('exam_schedules', function (Blueprint $table) {
            foreach ([
                'test_resit2_time', 'test_resit2_date',
                'oski_resit2_time', 'oski_resit2_date',
                'test_resit_time', 'test_resit_date',
                'oski_resit_time', 'oski_resit_date',
            ] as $col) {
                if (Schema::hasColumn('exam_schedules', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
