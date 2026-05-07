<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            if (!Schema::hasColumn('student_photos', 'descriptor_confirmed_at')) {
                $table->timestamp('descriptor_confirmed_at')->nullable()->after('moodle_response');
            }
            if (!Schema::hasColumn('student_photos', 'descriptor_confirmed_notified_at')) {
                $table->timestamp('descriptor_confirmed_notified_at')->nullable()->after('descriptor_confirmed_at');
            }
        });

        // Backfill: existing approved rows are treated as already-confirmed
        // so the tutor UI does not flip them all to "Moodle'da ishlanmoqda".
        // The notify timestamp stays null so we never re-send a stale message.
        DB::table('student_photos')
            ->where('status', 'approved')
            ->whereNull('descriptor_confirmed_at')
            ->update(['descriptor_confirmed_at' => DB::raw('COALESCE(reviewed_at, updated_at)')]);
    }

    public function down(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            foreach (['descriptor_confirmed_at', 'descriptor_confirmed_notified_at'] as $col) {
                if (Schema::hasColumn('student_photos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
