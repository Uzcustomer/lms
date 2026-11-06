<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sikl fanida alohida ma'ruza bloki: "ma'ruza alohida" belgisi va kunlik ma'ruza soatlari ro'yxati (SOATDA, masalan [8,8,6,6,6] —
 * 5 ma'ruza kuni). Fakultet / o'quv reja bo'yicha ham (aniqlovchili yozuvda); qidiruv — sikl kunlari soni bilan bir xil:
 * (fakultet + o'quv reja) → (fakultet) → (umumiy). lecture_separate NULL — yuqori darajadan meros; 0 — o'chirilgan (shu darajada).
 * Belgisi yo'q fanlar hozirgidek (ma'ruza amaliy blok ichida). Mavjud yozuvlar o'zgarmaydi.
 */
return new class extends Migration
{
    private const TABLE = 'timetable_subject_settings';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }
        Schema::table(self::TABLE, function (Blueprint $table) {
            if (!Schema::hasColumn(self::TABLE, 'lecture_separate')) {
                $table->boolean('lecture_separate')->nullable()->after('cycle_days');
            }
            if (!Schema::hasColumn(self::TABLE, 'lecture_hours')) {
                $table->json('lecture_hours')->nullable()->after('lecture_separate');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }
        Schema::table(self::TABLE, function (Blueprint $table) {
            foreach (['lecture_hours', 'lecture_separate'] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
