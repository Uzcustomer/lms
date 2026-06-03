<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_assignments', function (Blueprint $table) {
            $table->dateTime('reveal_at')->nullable()->after('planned_end');
            $table->boolean('reveal_notified')->default(false)->after('reveal_at');
            $table->boolean('approach_notified')->default(false)->after('reveal_notified');
            $table->boolean('ready_notified')->default(false)->after('approach_notified');
            $table->boolean('is_reserve')->default(false)->after('ready_notified');
            $table->unsignedSmallInteger('moved_from_computer')->nullable()->after('is_reserve');
            $table->string('moved_reason', 50)->nullable()->after('moved_from_computer');

            $table->index('reveal_at');
            $table->index(['status', 'planned_start']);
        });

        Schema::table('computers', function (Blueprint $table) {
            $table->boolean('is_reserve_pool')->default(false)->after('active');
            $table->index('is_reserve_pool');
        });

        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->string('test_assignment_mode', 20)->default('manual')->after('test_time');
            $table->string('oski_assignment_mode', 20)->default('manual')->after('oski_time');
        });
    }

    public function down(): void
    {
        Schema::table('computer_assignments', function (Blueprint $table) {
            $table->dropIndex(['reveal_at']);
            $table->dropIndex(['status', 'planned_start']);
            $table->dropColumn([
                'reveal_at',
                'reveal_notified',
                'approach_notified',
                'ready_notified',
                'is_reserve',
                'moved_from_computer',
                'moved_reason',
            ]);
        });

        Schema::table('computers', function (Blueprint $table) {
            $table->dropIndex(['is_reserve_pool']);
            $table->dropColumn('is_reserve_pool');
        });

        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropColumn(['test_assignment_mode', 'oski_assignment_mode']);
        });
    }
};
