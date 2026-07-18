<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasOverrideCount = Schema::hasColumn('retake_application_windows', 'override_count');
        $hasOverrideLastAt = Schema::hasColumn('retake_application_windows', 'override_last_at');
        $hasOverrideLastByName = Schema::hasColumn('retake_application_windows', 'override_last_by_name');

        Schema::table('retake_application_windows', function (Blueprint $table) use ($hasOverrideCount, $hasOverrideLastAt, $hasOverrideLastByName) {
            if (!$hasOverrideCount) {
                $table->unsignedSmallInteger('override_count')->default(0)->after('application_reopen_until');
            }
            if (!$hasOverrideLastAt) {
                $table->timestamp('override_last_at')->nullable()->after('override_count');
            }
            if (!$hasOverrideLastByName) {
                $table->string('override_last_by_name')->nullable()->after('override_last_at');
            }
        });
    }

    public function down(): void
    {
        $hasOverrideCount = Schema::hasColumn('retake_application_windows', 'override_count');
        $hasOverrideLastAt = Schema::hasColumn('retake_application_windows', 'override_last_at');
        $hasOverrideLastByName = Schema::hasColumn('retake_application_windows', 'override_last_by_name');

        Schema::table('retake_application_windows', function (Blueprint $table) use ($hasOverrideCount, $hasOverrideLastAt, $hasOverrideLastByName) {
            if ($hasOverrideLastByName) {
                $table->dropColumn('override_last_by_name');
            }
            if ($hasOverrideLastAt) {
                $table->dropColumn('override_last_at');
            }
            if ($hasOverrideCount) {
                $table->dropColumn('override_count');
            }
        });
    }
};
