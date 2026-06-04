<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * student_admission_data uchun yetishmagan ustunlarni qo'shish:
 *   - doimiy_manzil, tolov_shakli, milliy_sertifikat — original migration'da
 *     mavjud bo'lishi kerak edi, lekin productionda topilmadi (qism-qism rollback
 *     bo'lganida tushib qolgan). hasColumn bilan idempotent qo'shamiz.
 *   - full_name — applicant.full ni qidiruv va ko'rsatish uchun saqlaymiz.
 *     "FAMILIYA ISM OTASINING_ISMI" tartibida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            if (!Schema::hasColumn('student_admission_data', 'doimiy_manzil')) {
                $table->string('doimiy_manzil')->nullable();
            }
            if (!Schema::hasColumn('student_admission_data', 'tolov_shakli')) {
                $table->text('tolov_shakli')->nullable();
            }
            if (!Schema::hasColumn('student_admission_data', 'milliy_sertifikat')) {
                $table->string('milliy_sertifikat')->nullable();
            }
            if (!Schema::hasColumn('student_admission_data', 'full_name')) {
                $table->string('full_name')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            foreach (['doimiy_manzil', 'tolov_shakli', 'milliy_sertifikat', 'full_name'] as $col) {
                if (Schema::hasColumn('student_admission_data', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
