<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Diagnostika "Tartibga solish" sana oralig'i bo'yicha qidiradi — date_finish da
 * indeks yo'q edi, har safar butun jadval ko'rib chiqilardi. (talaba, fan) indeksi
 * dublikatlarni olib tashlash (eng yuqori baho) ichki so'roviga.
 */
return new class extends Migration
{
    private const TABLE = 'hemis_quiz_results';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            if (!Schema::hasIndex(self::TABLE, 'hqr_active_date_finish_idx')) {
                $table->index(['is_active', 'date_finish'], 'hqr_active_date_finish_idx');
            }
            if (!Schema::hasIndex(self::TABLE, 'hqr_student_fan_idx')) {
                $table->index(['student_id', 'fan_id'], 'hqr_student_fan_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            if (Schema::hasIndex(self::TABLE, 'hqr_active_date_finish_idx')) {
                $table->dropIndex('hqr_active_date_finish_idx');
            }
            if (Schema::hasIndex(self::TABLE, 'hqr_student_fan_idx')) {
                $table->dropIndex('hqr_student_fan_idx');
            }
        });
    }
};
