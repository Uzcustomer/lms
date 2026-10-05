<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * student_grades (independent_id, student_hemis_id) indeksi.
 *
 * Mustaqil ta'lim bahosi shu ikki ustun bo'yicha qidiriladi: "Faollik va
 * mustaqil ta'lim" hisoboti, jurnaldagi MT ustuni, MT baholash va talaba
 * sahifasi. independent_id bo'yicha indeks yo'q edi; 17 mln+ qatorli
 * jadvalda bu to'liq skan va timeout degani.
 *
 * ALGORITHM=INPLACE, LOCK=NONE — onlayn, jadvalni bloklamaydi.
 */
return new class extends Migration
{
    private const TABLE = 'student_grades';

    private const NAME = 'idx_sg_independent_student';

    public function up(): void
    {
        try {
            if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'independent_id')) {
                Log::info('[Migration] student_grades.independent_id yo\'q, '.self::NAME.' o\'tkazib yuboriladi.');

                return;
            }
            if (! empty(DB::select('SHOW INDEX FROM '.self::TABLE.' WHERE Key_name = ?', [self::NAME]))) {
                Log::info('[Migration] Index '.self::NAME.' allaqachon mavjud.');

                return;
            }
            DB::statement('ALTER TABLE '.self::TABLE.' ADD INDEX '.self::NAME.' (independent_id, student_hemis_id) ALGORITHM=INPLACE, LOCK=NONE');
        } catch (\Throwable $e) {
            // SQLite (testlar) ALGORITHM/LOCK ni tushunmaydi — oddiy yo'l bilan
            try {
                Schema::table(self::TABLE, fn ($t) => $t->index(['independent_id', 'student_hemis_id'], self::NAME));
            } catch (\Throwable $e2) {
                Log::warning('[Migration] '.self::NAME.' yaratilmadi: '.$e2->getMessage());
            }
        }
    }

    public function down(): void
    {
        try {
            if (! empty(DB::select('SHOW INDEX FROM '.self::TABLE.' WHERE Key_name = ?', [self::NAME]))) {
                DB::statement('ALTER TABLE '.self::TABLE.' DROP INDEX '.self::NAME);
            }
        } catch (\Throwable $e) {
            try {
                Schema::table(self::TABLE, fn ($t) => $t->dropIndex(self::NAME));
            } catch (\Throwable $e2) {
                Log::warning('[Migration] '.self::NAME.' o\'chirilmadi: '.$e2->getMessage());
            }
        }
    }
};
