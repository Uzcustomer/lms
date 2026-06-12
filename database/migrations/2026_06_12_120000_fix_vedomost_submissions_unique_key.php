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

        // Yuklangan fayl / tekshiruv natijasini yo'qotmaslik uchun ko'chiriladigan ustunlar
        $carry = [
            'pdf_path', 'excel_path', 'uploaded_by', 'uploaded_by_name', 'uploaded_at',
            'status', 'reviewed_by', 'reviewed_by_name', 'reviewed_at', 'rejection_reason',
            'prorektor_notified_at',
            'ai_check_status', 'ai_verdict', 'ai_summary', 'ai_result', 'ai_error', 'ai_checked_at',
        ];
        // Faqat haqiqatan mavjud ustunlarni ko'chiramiz (AI ustunlari keyingi migration)
        $carry = array_values(array_filter(
            $carry,
            fn($c) => Schema::hasColumn('vedomost_submissions', $c)
        ));

        foreach ($dups as $d) {
            $rows = DB::table('vedomost_submissions')
                ->where('group_hemis_id', $d->group_hemis_id)
                ->where('subject_id', $d->subject_id)
                ->where('semester_code', $d->semester_code)
                ->orderByRaw("(pdf_path IS NOT NULL) DESC, {$statusRank} DESC, id ASC")
                ->get();

            $keeper = $rows->first();
            $rest = $rows->slice(1);

            // Qoldiriladigan yozuvda fayl bo'lmasa, o'chiriladiganlardan
            // (faylga ega birinchi nusxadan) fayl/status ma'lumotini ko'chiramiz.
            if (!$keeper->pdf_path) {
                $source = $rest->first(fn($r) => $r->pdf_path !== null);
                if ($source) {
                    $update = [];
                    foreach ($carry as $col) {
                        $update[$col] = $source->{$col};
                    }
                    DB::table('vedomost_submissions')->where('id', $keeper->id)->update($update);
                }
            }

            DB::table('vedomost_submissions')
                ->whereIn('id', $rest->pluck('id')->all())
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
