<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-attempt Moodle push result columns for resit attempts (2 and 3),
     * mirroring the existing attempt-1 oski_moodle_* / test_moodle_* columns.
     */
    private const COLUMNS = [
        'oski_resit_moodle_synced_at',  'oski_resit_moodle_response',  'oski_resit_moodle_error',
        'oski_resit2_moodle_synced_at', 'oski_resit2_moodle_response', 'oski_resit2_moodle_error',
        'test_resit_moodle_synced_at',  'test_resit_moodle_response',  'test_resit_moodle_error',
        'test_resit2_moodle_synced_at', 'test_resit2_moodle_response', 'test_resit2_moodle_error',
    ];

    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            foreach (self::COLUMNS as $col) {
                if (Schema::hasColumn('exam_schedules', $col)) {
                    continue;
                }
                if (str_ends_with($col, '_synced_at')) {
                    $table->timestamp($col)->nullable();
                } elseif (str_ends_with($col, '_response')) {
                    $table->json($col)->nullable();
                } else {
                    $table->text($col)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            foreach (self::COLUMNS as $col) {
                if (Schema::hasColumn('exam_schedules', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
