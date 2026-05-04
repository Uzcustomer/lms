<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_schedules', 'oski_moodle_synced_at')) {
                $table->timestamp('oski_moodle_synced_at')->nullable();
            }
            if (!Schema::hasColumn('exam_schedules', 'oski_moodle_response')) {
                $table->json('oski_moodle_response')->nullable();
            }
            if (!Schema::hasColumn('exam_schedules', 'oski_moodle_error')) {
                $table->text('oski_moodle_error')->nullable();
            }
            if (!Schema::hasColumn('exam_schedules', 'test_moodle_synced_at')) {
                $table->timestamp('test_moodle_synced_at')->nullable();
            }
            if (!Schema::hasColumn('exam_schedules', 'test_moodle_response')) {
                $table->json('test_moodle_response')->nullable();
            }
            if (!Schema::hasColumn('exam_schedules', 'test_moodle_error')) {
                $table->text('test_moodle_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            foreach ([
                'oski_moodle_synced_at', 'oski_moodle_response', 'oski_moodle_error',
                'test_moodle_synced_at', 'test_moodle_response', 'test_moodle_error',
            ] as $col) {
                if (Schema::hasColumn('exam_schedules', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
