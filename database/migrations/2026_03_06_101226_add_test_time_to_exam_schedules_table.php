<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('exam_schedules', 'test_time')) {
            Schema::table('exam_schedules', function (Blueprint $table) {
                $table->time('test_time')->nullable()->after('test_na');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exam_schedules', 'test_time')) {
            Schema::table('exam_schedules', function (Blueprint $table) {
                $table->dropColumn('test_time');
            });
        }
    }
};
