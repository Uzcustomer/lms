<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dars ochish endi o'quv prorektori tasdig'idan o'tadi.
 *
 * Registrator so'rov yuboradi (status = pending), prorektor ko'rib chiqadi:
 * tasdiqlasa ochiladi (active) va o'qituvchining baho qo'yish muddati shu
 * paytdan hisoblanadi; rad etsa sababi saqlanadi (rejected). Kutilayotgan
 * so'rovda muddat hali yo'q, shuning uchun deadline bo'sh bo'la oladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lesson_openings')) {
            return;
        }

        // deadline NOT NULL edi — kutilayotgan so'rovda u hali aniqlanmagan.
        DB::statement('ALTER TABLE lesson_openings MODIFY deadline DATETIME NULL');

        Schema::table('lesson_openings', function (Blueprint $table) {
            if (!Schema::hasColumn('lesson_openings', 'reviewed_by_id')) {
                $table->unsignedBigInteger('reviewed_by_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('lesson_openings', 'reviewed_by_name')) {
                $table->string('reviewed_by_name')->nullable()->after('reviewed_by_id');
            }
            if (!Schema::hasColumn('lesson_openings', 'reviewed_by_guard')) {
                $table->string('reviewed_by_guard')->nullable()->after('reviewed_by_name');
            }
            if (!Schema::hasColumn('lesson_openings', 'reviewed_at')) {
                $table->dateTime('reviewed_at')->nullable()->after('reviewed_by_guard');
            }
            if (!Schema::hasColumn('lesson_openings', 'review_comment')) {
                $table->text('review_comment')->nullable()->after('reviewed_at');
            }
            if (!Schema::hasColumn('lesson_openings', 'request_note')) {
                $table->text('request_note')->nullable()->after('file_original_name');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('lesson_openings')) {
            return;
        }

        Schema::table('lesson_openings', function (Blueprint $table) {
            foreach (['reviewed_by_id', 'reviewed_by_name', 'reviewed_by_guard', 'reviewed_at', 'review_comment', 'request_note'] as $column) {
                if (Schema::hasColumn('lesson_openings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // Kutilayotgan yoki rad etilgan so'rovlarda deadline bo'sh — ularni
        // o'chirmasdan NOT NULL ga qaytarib bo'lmaydi.
        if (!DB::table('lesson_openings')->whereNull('deadline')->exists()) {
            DB::statement('ALTER TABLE lesson_openings MODIFY deadline DATETIME NOT NULL');
        }
    }
};
