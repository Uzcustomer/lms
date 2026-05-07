<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Soft delete imkoniyati — guruh va sessiyalar to'liq o'chirilmaydi,
        // arxivga ("Tarix") joylanadi.
        Schema::table('retake_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('retake_groups', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at');
            }
        });

        Schema::table('retake_window_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('retake_window_sessions', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at');
            }
        });

        Schema::table('retake_application_windows', function (Blueprint $table) {
            if (!Schema::hasColumn('retake_application_windows', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('retake_groups', function (Blueprint $table) {
            if (Schema::hasColumn('retake_groups', 'deleted_at')) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            }
        });

        Schema::table('retake_window_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('retake_window_sessions', 'deleted_at')) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            }
        });

        Schema::table('retake_application_windows', function (Blueprint $table) {
            if (Schema::hasColumn('retake_application_windows', 'deleted_at')) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            }
        });
    }
};
