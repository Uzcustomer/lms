<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_change_requests', function (Blueprint $table) {
            // NULL preserves the existing registrar-only workflow.
            $table->string('approval_stage', 20)->nullable()->index();
            $table->unsignedBigInteger('department_hemis_id')->nullable()->index();
            $table->unsignedBigInteger('head_reviewer_id')->nullable();
            $table->string('head_reviewer_name')->nullable();
            $table->timestamp('head_reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('timetable_change_requests', function (Blueprint $table) {
            $table->dropIndex(['approval_stage']);
            $table->dropIndex(['department_hemis_id']);
            $table->dropColumn(['approval_stage', 'department_hemis_id', 'head_reviewer_id', 'head_reviewer_name', 'head_reviewed_at']);
        });
    }
};
