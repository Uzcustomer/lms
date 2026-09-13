<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fan uchun "klinik" belgisi qo'lda: NULL — nomdagi kalit so'zlardan avtomatik,
 * 1 — klinik (jadvalda ma'ruza+amaliy bitta umumiy kartada), 0 — klinik emas.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('subject_kafedra_overrides')) {
            return;
        }

        Schema::table('subject_kafedra_overrides', function (Blueprint $table) {
            if (!Schema::hasColumn('subject_kafedra_overrides', 'is_clinical')) {
                $table->unsignedTinyInteger('is_clinical')->nullable()->after('practice_group_size');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('subject_kafedra_overrides')) {
            return;
        }

        Schema::table('subject_kafedra_overrides', function (Blueprint $table) {
            if (Schema::hasColumn('subject_kafedra_overrides', 'is_clinical')) {
                $table->dropColumn('is_clinical');
            }
        });
    }
};
