<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Talabalarni taqsimlash moduli jadvallarini o'chiradi.
 *
 * Modul mantig'i noto'g'ri tuzilgani uchun butunlay olib tashlandi va
 * qaytadan quriladi. Bu migratsiyaning down() metodi jadvallarni tiklamaydi —
 * yangi tuzilma boshqacha bo'lishi kutilmoqda, shuning uchun eski sxemani
 * qayta yaratishning ma'nosi yo'q.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tashqi kalitlar bir-biriga bog'langani uchun tartib muhim:
        // avval bog'liq jadvallar, keyin asosiy jadval.
        $tables = [
            'student_distribution_assignments',
            'student_group_change_applications',
            'student_group_change_permissions',
            'student_distribution_groups',
        ];

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                Schema::dropIfExists($table);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // O'chirilgan migratsiya fayllarining yozuvlarini ham tozalaymiz,
        // aks holda `migrate:status` mavjud bo'lmagan fayllarni ko'rsatib turadi.
        DB::table('migrations')
            ->where('migration', 'like', '2026_08_28_1000%')
            ->where(function ($query) {
                $query->where('migration', 'like', '%student_distribution%')
                    ->orWhere('migration', 'like', '%student_group_change%');
            })
            ->delete();
    }

    public function down(): void
    {
        // Ataylab bo'sh: modul qaytadan quriladi, eski sxema tiklanmaydi.
    }
};
