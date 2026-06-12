<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vedomost dublikatlari: unique kalitda education_year bo'lgani uchun
 * joriy yil aniqlash mantig'i o'zgarganda bir guruh×fan×semestr ikki marta
 * yaratilgan. Semestr kodlari (11-22) reja bo'yicha takrorlanmaydi —
 * guruh bitta semestrni faqat bir marta o'taydi, shuning uchun
 * education_year kalitdan chiqariladi. Avval dublikatlar tozalanadi:
 * eng "progressli" yozuv (fayl yuklangan / status ilgarilagan) qoladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $statusRank = "CASE status
            WHEN 'approved' THEN 5
            WHEN 'reviewing' THEN 4
            WHEN 'received' THEN 3
            WHEN 'rejected' THEN 2
            ELSE 1 END";

        $dups = DB::table('vedomost_submissions')
            ->selectRaw('group_hemis_id, subject_id, semester_code')
            ->groupBy('group_hemis_id', 'subject_id', 'semester_code')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($dups as $d) {
            $ids = DB::table('vedomost_submissions')
                ->where('group_hemis_id', $d->group_hemis_id)
                ->where('subject_id', $d->subject_id)
                ->where('semester_code', $d->semester_code)
                ->orderByRaw("(pdf_path IS NOT NULL) DESC, {$statusRank} DESC, id ASC")
                ->pluck('id');

            DB::table('vedomost_submissions')
                ->whereIn('id', $ids->slice(1)->values()->all())
                ->delete();
        }

        Schema::table('vedomost_submissions', function (Blueprint $table) {
            $table->dropUnique('vedomost_subm_group_subject_sem_year_unique');
            $table->unique(
                ['group_hemis_id', 'subject_id', 'semester_code'],
                'vedomost_subm_group_subject_sem_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('vedomost_submissions', function (Blueprint $table) {
            $table->dropUnique('vedomost_subm_group_subject_sem_unique');
            $table->unique(
                ['group_hemis_id', 'subject_id', 'semester_code', 'education_year'],
                'vedomost_subm_group_subject_sem_year_unique'
            );
        });
    }
};
