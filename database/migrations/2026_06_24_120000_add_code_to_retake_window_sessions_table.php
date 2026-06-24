<?php

use App\Services\Retake\RetakeSessionCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_window_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('retake_window_sessions', 'code')) {
                // Kanonik sessiya kodi: YYYY-YYYY-fasl (masalan 2025-2026-yozgi).
                // Moodle quiz nomidagi suffiks bilan bir xil bo'lishi shart.
                $table->string('code')->nullable()->after('name')->index();
            }
        });

        // Mavjud sessiyalar uchun kodni nom matnidan chiqarib to'ldiramiz.
        if (Schema::hasColumn('retake_window_sessions', 'code')) {
            DB::table('retake_window_sessions')
                ->whereNull('code')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->each(function ($row) {
                    $code = RetakeSessionCode::normalize($row->name);
                    if ($code !== null) {
                        DB::table('retake_window_sessions')
                            ->where('id', $row->id)
                            ->update(['code' => $code]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('retake_window_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('retake_window_sessions', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};
