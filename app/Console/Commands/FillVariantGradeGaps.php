<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Variantli fanlar ((a)/(b)/(c)) bo'yicha bo'shliqlarni to'ldirish.
 *
 * Talaba semestr davomida bir variantdan boshqasiga o'tkazilsa (masalan (a) → (b)),
 * yangi variant jurnalida o'tkazilishdan oldingi darslar bo'sh qoladi va JN pasayadi,
 * vaholanki o'sha kuni talaba eski variantda baholangan. Bu buyruq shunday kunlarda
 * eski variantdagi bahoni yangi variant darsiga (dars jadvalidagi o'sha kun va juftlik)
 * ko'chiradi; izohda qaysi variant va o'qituvchidan ko'chirilgani yoziladi.
 *
 * Talabaning hozirgi varianti HEMIS biriktiruvidan (student_subjects) olinadi — avval
 * import:student-subjects bilan yangilang. --guess bilan biriktiruv bo'lmagan talabalar
 * uchun eng oxirgi baho qo'yilgan variant hozirgi deb olinadi.
 */
class FillVariantGradeGaps extends Command
{
    protected $signature = 'grades:fill-variant-gaps
        {--since= : Shu sanadan (Y-m-d), sukut — 90 kun oldin}
        {--group= : Faqat shu guruh (nomi, masalan "p/p24-01a (rus)")}
        {--student= : Faqat shu talaba (ID raqami yoki hemis_id)}
        {--dry-run : Hech narsa yozmasdan faqat ko\'rsatish}
        {--guess : HEMIS biriktiruvi bo\'lmasa — eng oxirgi baho qo\'yilgan variantni hozirgi deb olish}';

    protected $description = "Variant almashgan talabalarning yangi variantdagi bo'sh kunlariga eski variant bahosini ko'chirish";

    private const VARIANT_RE = '/\s*\(\s*[a-zA-Zа-яА-ЯёЁ]\s*\)\s*$/u';

    /**
     * Talabaning shu fan bo'yicha hozirgi varianti: [subject_id, shu variantdagi oxirgi baho sanasi].
     * 1) HEMIS biriktiruvi (student_subjects) — bir nechta bo'lsa eng keyingi biriktirilgani;
     * 2) bo'lmasa — variantlar ichida eng oxirgi bahosi qo'yilgani (teng bo'lsa — aniqlanmaydi).
     */
    private function currentVariant(int $studentHemis, string $semester, string $base): array
    {
        $isSibling = fn ($name) => (string) $name !== trim((string) preg_replace(self::VARIANT_RE, '', (string) $name))
            && trim((string) preg_replace(self::VARIANT_RE, '', (string) $name)) === $base;

        $latestBySubject = DB::table('student_grades')
            ->where('student_hemis_id', $studentHemis)->where('semester_code', $semester)->whereNull('deleted_at')
            ->where('training_type_code', '!=', '11')->where('subject_name', 'like', $base . '%')
            ->groupBy('subject_id', 'subject_name')
            ->selectRaw('subject_id, subject_name, MAX(lesson_date) as last_date')
            ->get()->filter(fn ($r) => $isSibling($r->subject_name))
            ->groupBy('subject_id')->map(fn ($rows) => $rows->max('last_date'));

        $hemis = DB::table('student_subjects')->where('student_hemis_id', $studentHemis)
            ->where('subject_name', 'like', $base . '%')
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('student_subjects', 'hemis_created_at'), fn ($q) => $q->orderByDesc('hemis_created_at'))
            ->get(['subject_id', 'subject_name'])->filter(fn ($r) => $isSibling($r->subject_name))->first();
        if ($hemis && $hemis->subject_id) {
            return [(int) $hemis->subject_id, $latestBySubject->get($hemis->subject_id)];
        }
        // HEMIS biriktiruvi yo'q — sukut bo'yicha tegilmaydi (--guess bilan taxmin qilinadi)
        if (!$this->option('guess') || $latestBySubject->isEmpty()) {
            return [0, null];
        }
        $sorted = $latestBySubject->sortDesc();
        $top = $sorted->keys()->first();
        if ($sorted->count() > 1 && $sorted->values()[0] === $sorted->values()[1]) {
            return [0, null];   // teng — qaysi variant ekani noma'lum, tegilmaydi
        }

        return [(int) $top, $sorted->first()];
    }

    public function handle(): int
    {
        $since = $this->option('since') ?: now('Asia/Tashkent')->subDays(90)->toDateString();
        $until = now('Asia/Tashkent')->toDateString() . ' 23:59:59';
        $dry = (bool) $this->option('dry-run');

        $studentHemis = null;
        if ($s = $this->option('student')) {
            $studentHemis = DB::table('students')->where('student_id_number', $s)->orWhere('hemis_id', $s)->value('hemis_id');
            if (!$studentHemis) {
                $this->error("Talaba topilmadi: {$s}");
                return 1;
            }
        }

        // 1) Variantli fanlarning (amaliy) darslari va shu guruh talabalari, shu darsda bahosi yo'q
        $q = DB::table('schedules as sch')
            ->join('students as st', 'st.group_id', '=', 'sch.group_id')
            ->whereNull('sch.deleted_at')
            ->whereBetween('sch.lesson_date', [$since . ' 00:00:00', $until])
            ->where('sch.training_type_code', '!=', '11')
            // Nomi "(a)" / "(Б)" kabi bitta harfli qavs bilan tugaydigan fanlar
            ->where(fn ($w) => $w->where('sch.subject_name', 'like', '%(_)')->orWhere('sch.subject_name', 'like', '%(_) ')
                ->orWhere('sch.subject_name', 'like', '%( _ )'))
            ->where(fn ($w) => $w->whereNull('st.student_status_code')->orWhere('st.student_status_code', '!=', '60'))
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))->from('student_grades as g')
                    ->whereColumn('g.student_hemis_id', 'st.hemis_id')
                    ->whereColumn('g.subject_id', 'sch.subject_id')
                    ->whereNull('g.deleted_at')
                    ->whereRaw('DATE(g.lesson_date) = DATE(sch.lesson_date)')
                    ->whereColumn('g.lesson_pair_code', 'sch.lesson_pair_code');
            })
            ->when($this->option('group'), fn ($w, $g) => $w->where('sch.group_name', $g))
            ->when($studentHemis, fn ($w) => $w->where('st.hemis_id', $studentHemis))
            ->orderBy('sch.lesson_date')
            ->select(['sch.*', 'st.id as st_id', 'st.hemis_id as st_hemis', 'st.full_name as st_name']);

        $candidates = $q->get();
        $this->info("Tekshiriladigan bo'sh kataklar: {$candidates->count()} ({$since} dan)");

        $rows = [];
        $report = [];
        $belongCache = [];
        foreach ($candidates as $c) {
            $date = substr((string) $c->lesson_date, 0, 10);
            $base = trim((string) preg_replace(self::VARIANT_RE, '', (string) $c->subject_name));

            // 2) Talabaning HOZIRGI varianti shu darsning fanimi — faqat shunga ko'chiriladi
            //    (aks holda doim (b) da bo'lgan talabaga (a) ga ham ko'chib ketardi)
            $bk = $c->st_hemis . '|' . $c->semester_code . '|' . $base;
            $belongCache[$bk] ??= $this->currentVariant((int) $c->st_hemis, (string) $c->semester_code, $base);
            [$currentSubject, $latest] = $belongCache[$bk];
            if ((int) $currentSubject !== (int) $c->subject_id || ($latest !== null && substr((string) $latest, 0, 10) <= $date)) {
                continue;
            }

            // 3) O'sha kuni shu fanning boshqa variantidagi baho
            $siblings = DB::table('student_grades')
                ->where('student_hemis_id', $c->st_hemis)
                ->whereNull('deleted_at')
                ->whereRaw('DATE(lesson_date) = ?', [$date])
                ->where('subject_id', '!=', $c->subject_id)
                ->where('subject_name', 'like', $base . '%')
                ->where('training_type_code', '!=', '11')
                ->get(['subject_name', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason', 'employee_name', 'is_final'])
                ->filter(fn ($g) => trim((string) preg_replace(self::VARIANT_RE, '', (string) $g->subject_name)) === $base
                    && $g->subject_name !== $c->subject_name);
            if ($siblings->isEmpty()) {
                continue;
            }
            $src = $siblings->firstWhere('lesson_pair_code', $c->lesson_pair_code) ?? $siblings->first();
            $value = $src->status === 'retake' && $src->retake_grade !== null ? (float) $src->retake_grade : $src->grade;
            $absent = $src->status === 'pending' && $src->reason === 'absent';
            if ($value === null && !$absent) {
                continue;
            }

            $now = now();
            $rows[] = [
                'hemis_id' => 88888888,
                'student_id' => $c->st_id,
                'student_hemis_id' => $c->st_hemis,
                'subject_id' => $c->subject_id,
                'subject_name' => $c->subject_name,
                'subject_code' => $c->subject_code,
                'semester_code' => $c->semester_code,
                'semester_name' => $c->semester_name,
                'subject_schedule_id' => $c->schedule_hemis_id,
                'training_type_code' => $c->training_type_code,
                'training_type_name' => $c->training_type_name,
                'employee_id' => $c->employee_id,
                'employee_name' => $c->employee_name,
                'lesson_pair_code' => $c->lesson_pair_code,
                'lesson_pair_name' => $c->lesson_pair_name,
                'lesson_pair_start_time' => $c->lesson_pair_start_time,
                'lesson_pair_end_time' => $c->lesson_pair_end_time,
                'lesson_date' => $c->lesson_date,
                'grade' => $absent ? null : (float) $value,
                'status' => $absent ? 'pending' : 'recorded',
                'reason' => $absent ? 'absent' : null,
                'grade_comment' => 'Variant almashgan: ' . $src->subject_name . ' (' . ($src->employee_name ?: '—') . ') bahosi ko\'chirildi',
                'is_final' => (bool) $src->is_final,
                'education_year_code' => $c->education_year_code,
                'education_year_name' => $c->education_year_name,
                'created_at_api' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $report[] = [$c->st_name, $c->group_name, $c->subject_name, $date . ' (' . $c->lesson_pair_name . ')',
                $src->subject_name, $absent ? 'NB' : (float) $value];
        }

        if (!$rows) {
            $this->info("Ko'chiriladigan baho topilmadi.");
            return 0;
        }

        $this->table(['Talaba', 'Guruh', 'Fan (yangi variant)', 'Sana', 'Qayerdan', 'Baho'], array_slice($report, 0, 300));
        if (count($report) > 300) {
            $this->line('… yana ' . (count($report) - 300) . ' ta');
        }

        if ($dry) {
            $this->warn('[SINOV] ' . count($rows) . " ta baho ko'chiriladi — hech narsa yozilmadi. Yozish uchun --dry-run siz ishga tushiring.");
            return 0;
        }

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('student_grades')->insert($chunk);
            }
        });
        $this->info(count($rows) . " ta baho ko'chirildi.");

        return 0;
    }
}
