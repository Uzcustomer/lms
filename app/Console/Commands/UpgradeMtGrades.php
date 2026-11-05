<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use App\Models\MarkingSystemScore;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sikli (guruh dars jadvalidagi oxirgi dars) chegara sanada (sukut bo'yicha 26.10.2026) yoki undan
 * keyin tugagan fanlarda — talaba MT faylini chegara sanadan OLDIN yuklagan bo'lsa (independent_submissions
 * .submitted_at, oxirgi yuklash), MT bahosi (student_grades, training_type_code = 99) grades:admin-upgrade
 * formulasi bo'yicha oshiriladi (60 dan pasti — yo'q). Chegara sanada/keyin yuklangan yoki fayli yo'q
 * MT ga tegilmaydi. Guruhlar grades:upgrade-cycle dagidek tanlanadi.
 * Sukut bo'yicha faqat hisobot, yozish — --force; --revert — shu baholarni qaytaradi.
 */
class UpgradeMtGrades extends UpgradeCycleGrades
{
    protected $signature = 'grades:upgrade-mt
        {groups?* : Guruhlar, masalan d1/d22-01 (kichik guruhlari a, b, c olinadi)}
        {--faculty=* : Fakultet nomidan qism, masalan "1-son davolash" (bir nechta bo\'lishi mumkin)}
        {--specialty=* : --faculty ichida faqat shu yo\'nalishlar (nomidan qism)}
        {--courses=4,5,6 : --faculty da qaysi kurslar}
        {--cutoff=2026-10-26 : Sikl shu sanada/keyin tugagan, MT esa shu sanadan oldin yuklangan bo\'lsa oshiriladi}
        {--force : Haqiqatan yozish (bo\'lmasa faqat hisobot)}
        {--revert : Oshirilgan baholarni eski holiga qaytarish}';

    protected $description = "Chegara sanadan keyin tugagan sikllarda undan oldin yuklangan MT baholarini oshirish (admin)";

    private const MT = 99;

    public function handle(): int
    {
        if (!Schema::hasColumn('student_grades', 'upgraded_by_admin')) {
            $this->error("Avval migratsiya: php artisan migrate");

            return self::FAILURE;
        }

        $cutoff = Carbon::parse($this->option('cutoff'))->startOfDay();
        $force = (bool) $this->option('force');
        $revert = (bool) $this->option('revert');
        $limit = MarkingSystemScore::lowGradeLimit();
        $deadlineDays = Deadline::pluck('deadline_days', 'level_code');
        $cut = $cutoff->format('d.m');

        $table = [];
        $samples = [];
        $total = 0;
        $groups = $this->selectedGroups($table, 10);
        if ($groups === null) {
            return self::FAILURE;
        }
        $brief = (bool) array_filter($this->option('faculty'));
        $courseOf = $this->courses($groups->pluck('group_hemis_id'));

        $this->output->progressStart($groups->count());
        foreach ($groups as $group) {
            $this->output->progressAdvance();
            $students = DB::table('students')->where('group_id', $group->group_hemis_id)->pluck('level_code', 'hemis_id');
            if ($students->isEmpty()) {
                continue;
            }
            $hemisIds = $students->keys()->map(fn ($id) => (string) $id)->all();

            // Fan sikllari — chegara sanada yoki keyin tugaganlar
            $cycles = DB::table('schedules')
                ->whereNull('deleted_at')
                ->where('group_id', $group->group_hemis_id)
                ->groupBy('subject_id', 'subject_name', 'semester_code')
                ->havingRaw('MAX(lesson_date) >= ?', [$cutoff])
                ->orderByRaw('MIN(lesson_date)')
                ->get([DB::raw('subject_id, subject_name, semester_code, MIN(lesson_date) AS cycle_start, MAX(lesson_date) AS cycle_end')]);

            foreach ($cycles as $c) {
                $rows = DB::table('student_grades')
                    ->whereNull('deleted_at')
                    ->whereIn('student_hemis_id', $hemisIds)
                    ->where('subject_id', (string) $c->subject_id)
                    ->where('semester_code', (string) $c->semester_code)
                    ->where('training_type_code', self::MT)
                    ->whereNull('lesson_date')
                    ->where(fn ($q) => $q->whereNotNull('grade')->orWhereNotNull('retake_grade'))
                    ->get(['id', 'student_hemis_id', 'lesson_date', 'grade', 'retake_grade', 'reason', 'status',
                        'upgraded_by_admin', 'admin_original_grade']);
                if ($rows->isEmpty()) {
                    continue;
                }

                $uploaded = self::uploadDates($group->group_hemis_id, $c, $hemisIds);
                $before = $rows->filter(fn ($r) => isset($uploaded[$r->student_hemis_id]) && $uploaded[$r->student_hemis_id]->lt($cutoff));
                $after = $rows->filter(fn ($r) => isset($uploaded[$r->student_hemis_id]) && $uploaded[$r->student_hemis_id]->gte($cutoff))->count();
                $noFile = $rows->count() - $before->count() - $after;
                if ($before->isEmpty() && $brief) {
                    continue;
                }

                $sampleCount = count($samples);
                $r = AdminUpgradeGrades::applyRows($before, $students, $limit, $deadlineDays, $revert, $force, $group->name, $samples, false);
                array_splice($samples, $brief ? 0 : $sampleCount + 3);
                $total += $r['changed'];
                $dates = $before->map(fn ($row) => $uploaded[$row->student_hemis_id]->format('d.m'))->unique()->sort()->values();
                $table[] = [$group->name, $courseOf[$group->group_hemis_id] ?? '', $c->subject_name,
                    Carbon::parse($c->cycle_start)->format('d.m') . ' – ' . Carbon::parse($c->cycle_end)->format('d.m.Y'),
                    $dates->count() > 3 ? $dates->first() . ' – ' . $dates->last() : $dates->implode(', '),
                    $before->count(), $r['changed'], $r['below'],
                    trim(($after ? "{$cut} dan keyin yuklangan: {$after}" : '') . ($noFile ? ($after ? '; ' : '') . "fayl yo'q: {$noFile}" : '')),
                    $r['same'] ? "allaqachon: {$r['same']}" : ''];
            }
        }
        $this->output->progressFinish();

        $this->table(['Guruh', 'Kurs', 'Fan', 'Sikl', "MT yuklangan ({$cut} gacha)", 'MT baholar', $revert ? 'Qaytariladi' : 'Oshiriladi',
            '<60', 'Tegilmaydi', 'Izoh'], $table);
        if ($samples) {
            $this->line('Namunalar:');
            $this->table(['Guruh', 'Talaba', '', $revert ? 'Hozir' : 'Eski', $revert ? 'Qaytadi' : 'Yangi'], $samples);
        }

        if ($force) {
            $this->info('Jami ' . ($revert ? 'qaytarildi' : 'oshirildi') . ": {$total} ta MT bahosi.");
        } else {
            $this->info('[hisobot] Jami ' . ($revert ? 'qaytariladi' : 'oshiriladi') . ": {$total} ta MT bahosi.");
            $this->comment('Bu faqat hisobot. Yozish uchun --force qo\'shing.');
        }

        return self::SUCCESS;
    }

    /**
     * Talabalar MT faylini oxirgi marta qachon yuklagani (JournalController MT baholashdagidek:
     * guruh + semestr + fan bo'yicha topshiriqlar). @return array<string, Carbon> hemis_id => submitted_at
     */
    public static function uploadDates($groupHemisId, object $cycle, array $hemisIds): array
    {
        $csHemisIds = DB::table('curriculum_subjects')
            ->where('subject_id', $cycle->subject_id)
            ->where('semester_code', $cycle->semester_code)
            ->pluck('curriculum_subject_hemis_id')
            ->all();
        $independentIds = DB::table('independents')
            ->where('group_hemis_id', $groupHemisId)
            ->where('semester_code', $cycle->semester_code)
            ->where(fn ($q) => $q->whereIn('subject_hemis_id', $csHemisIds ?: [0])
                ->orWhereRaw('LOWER(subject_name) = ?', [mb_strtolower(trim($cycle->subject_name))]))
            ->pluck('id');
        if ($independentIds->isEmpty()) {
            return [];
        }

        return DB::table('independent_submissions')
            ->whereIn('independent_id', $independentIds)
            ->whereIn('student_hemis_id', $hemisIds)
            ->groupBy('student_hemis_id')
            ->get(['student_hemis_id', DB::raw('MAX(submitted_at) AS uploaded_at')])
            ->mapWithKeys(fn ($s) => [(string) $s->student_hemis_id => Carbon::parse($s->uploaded_at)])
            ->all();
    }
}
