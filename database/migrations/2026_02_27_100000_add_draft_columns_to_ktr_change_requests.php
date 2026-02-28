<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ktr_change_requests')) {
            return;
        }

        Schema::table('ktr_change_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('ktr_change_requests', 'draft_week_count')) {
                $table->unsignedSmallInteger('draft_week_count')->nullable()->after('status');
            }
            if (!Schema::hasColumn('ktr_change_requests', 'draft_plan_data')) {
                $table->json('draft_plan_data')->nullable()->after('draft_week_count');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ktr_change_requests')) {
            return;
        }

        Schema::table('ktr_change_requests', function (Blueprint $table) {
            $table->dropColumn(['draft_week_count', 'draft_plan_data']);
        });
    }
};
