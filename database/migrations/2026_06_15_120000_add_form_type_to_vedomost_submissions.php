<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vedomost shakli (form_type) — 12 / 12a / 12b.
 *
 *  - 12  : 1-urinish (joriy) qaydnomasi — har guruh uchun alohida varaq (mavjud).
 *  - 12a : 2-urinish (1-qayta topshirish) — yo'nalish bo'yicha BARCHA guruhlar
 *          bitta umumiy varaqda. 1-urinish sanasi o'tib, yiqilganlar bo'lsa ochiladi.
 *  - 12b : 3-urinish (2-qayta topshirish) — xuddi 12a kabi umumiy varaq. 2-urinish
 *          sanasi o'tib, yiqilganlar bo'lsa ochiladi.
 *
 * Eski yozuvlar default '12' bo'ladi. Unique kalit form_type bilan kengaytiriladi,
 * shunda bitta guruh×fan×semestr uchun 12, 12a, 12b alohida qator bo'la oladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vedomost_submissions')) {
            return;
        }

        if (!Schema::hasColumn('vedomost_submissions', 'form_type')) {
            Schema::table('vedomost_submissions', function (Blueprint $table) {
                $table->string('form_type', 5)->default('12')->after('closing_form')->index();
            });
        }

        // Eski unique kalit (form_type'siz) — yangisiga almashtiramiz.
        try {
            Schema::table('vedomost_submissions', function (Blueprint $table) {
                $table->dropUnique('vedomost_subm_group_subject_sem_unique');
            });
        } catch (\Throwable $e) {
            // kalit bo'lmasa — e'tiborsiz qoldiramiz
        }

        Schema::table('vedomost_submissions', function (Blueprint $table) {
            $table->unique(
                ['group_hemis_id', 'subject_id', 'semester_code', 'form_type'],
                'vedomost_subm_group_subject_sem_form_unique'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vedomost_submissions')) {
            return;
        }

        try {
            Schema::table('vedomost_submissions', function (Blueprint $table) {
                $table->dropUnique('vedomost_subm_group_subject_sem_form_unique');
            });
        } catch (\Throwable $e) {
            // e'tiborsiz
        }

        Schema::table('vedomost_submissions', function (Blueprint $table) {
            $table->unique(
                ['group_hemis_id', 'subject_id', 'semester_code'],
                'vedomost_subm_group_subject_sem_unique'
            );
            if (Schema::hasColumn('vedomost_submissions', 'form_type')) {
                $table->dropColumn('form_type');
            }
        });
    }
};
