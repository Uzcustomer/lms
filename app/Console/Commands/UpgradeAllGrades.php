<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use App\Models\MarkingSystemScore;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Barcha qoidalar bitta buyruqda (grades:upgrade-cycle, -nb-oski, -mt va imtihon qoidasi). Har guruh/fan uchun
 * chegara sana (sukut bo'yicha 26.10.2026) bo'yicha fan "davom etgan" hisoblanadi, agar:
 *   - darslari chegara sanada yoki keyin tugagan bo'lsa (sikl kesib o'tgan), yoki
 *   - darslari oldin tugagan, lekin yakuniy imtihoni — Test bo'lsa Test, faqat OSKI bo'lsa OSKI —
 *     chegara sanada yoki keyin bo'lsa.
 * Davom etgan fanda chegara sanadan oldingi:
 *   - kunlik baholar (NB / past baho ustidagi otrabotka ham),
 *   - MT — fayli chegara sanadan oldin yuklangan bo'lsa,
 *   - OSKI — chegara sanadan oldin topshirilgan bo'lsa (Test bahosiga hech qachon tegilmaydi)
 * grades:admin-upgrade formulasi bo'yicha oshiriladi (60 dan pasti — yo'q). Avval oshirilganlar
 * "allaqachon" bo'lib qoladi. Davom etmagan fanda oshirib yuborilgan baholar --revert-finished bilan qaytadi.
 * Sukut bo'yicha — 1-son, 2-son davolash va Pediatriya (Pediatriya, Stomatologiya, Tibbiy profilaktika)
 * fakultetlarining 4-6 kurslari; faqat hisobot, yozish — --force.
 */
class UpgradeAllGrades extends UpgradeCycleGrades
{
    protected $signature = 'grades:upgrade-all
        {groups?* : Faqat shu guruhlar, masalan d1/d22-01 (kichik guruhlari a, b, c olinadi)}
        {--faculty=* : Fakultet nomidan qism (sukut: 1-son davolash, 2-son davolash, Pediatriya)}
        {--specialty=* : Yo\'nalish nomidan qism (sukut: Davolash, Pediatriya, Stomatologiya, Tibbiy profilaktika)}
        {--courses=4,5,6 : Qaysi kurslar}
        {--cutoff=2026-10-26 : Chegara sana}
        {--since=2026-09-01 : O\'quv yili boshi — undan oldingi darslar/imtihonlarga tegilmaydi}
        {--force : Haqiqatan yozish (bo\'lmasa faqat hisobot)}
        {--revert : Oshirilgan baholarni eski holiga qaytarish}
        {--revert-finished : Davom etmagan fanlarda oshirib yuborilgan baholarni qaytarish}';

    protected $description = "Chegara sana qoidalari bo'yicha kunlik, NB otrabotka, MT va OSKI baholarini oshirish — hammasi bitta buyruqda (admin)";

    private const DEFAULT_FACULTIES = ['1-son davolash', '2-son davolash', 'Pediatriya'];
    private const DEFAULT_SPECIALTIES = ['Davolash', 'Pediatriya', 'Stomatologiya', 'Tibbiy profilaktika'];
    private const MT = 99;
    private const OSKI = 101;

    public function handle(): int
    {
        if (!Schema::hasColumn('student_grades', 'upgraded_by_admin')) {
            $this->error("Avval migratsiya: php artisan migrate");

            return self::FAILURE;
        }
        if (!$this->argument('groups') && !array_filter($this->option('faculty'))) {
            $this->input->setOption('faculty', self::DEFAULT_FACULTIES);
            if (!array_filter($this->option('specialty'))) {
                $this->input->setOption('specialty', self::DEFAULT_SPECIALTIES);
            }
        }

        $cutoff = Carbon::parse($this->option('cutoff'))->startOfDay();
        $since = Carbon::parse($this->option('since'))->startOfDay();
        $force = (bool) $this->option('force');
        $revert = (bool) $this->option('revert');
        $revertFinished = (bool) $this->option('revert-finished');
        $limit = MarkingSystemScore::lowGradeLimit();
        $deadlineDays = Deadline::pluck('deadline_days', 'level_code');
        $excluded = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);
        $cut = $cutoff->format('d.m');

        $table = [];
        $totals = ['kunlik' => 0, 'MT' => 0, 'OSKI' => 0, 'allaqachon' => 0, 'wrong' => 0];
        $groups = $this->selectedGroups($table, 11);
        if ($groups === null) {
            return self::FAILURE;
        }
        $courseOf = $this->courses($groups->pluck('group_hemis_id'));
        $from = UpgradeNbOskiGrades::gradesTable();
        $columns = ['id', 'student_hemis_id', 'subject_id', 'semester_code', 'training_type_code', 'lesson_date', 'grade', 'retake_grade',
            'reason', 'status', 'upgraded_by_admin', 'admin_original_grade'];

        $this->output->progressStart($groups->count());
        foreach ($groups as $group) {
            $this->output->progressAdvance();
            $students = DB::table('students')->where('group_id', $group->group_hemis_id)->pluck('level_code', 'hemis_id');
            if ($students->isEmpty()) {
                continue;
            }
            $hemisIds = $students->keys()->map(fn ($id) => (string) $id)->all();

            $cycles = DB::table('schedules')
                ->whereNull('deleted_at')
                ->where('group_id', $group->group_hemis_id)
                ->groupBy('subject_id', 'subject_name', 'semester_code')
                ->havingRaw('MAX(lesson_date) >= ?', [$since])
                ->orderByRaw('MIN(lesson_date)')
                ->get([DB::raw('subject_id, subject_name, semester_code, MIN(lesson_date) AS cycle_start, MAX(lesson_date) AS cycle_end')]);
            if ($cycles->isEmpty()) {
                continue;
            }
            $ends = UpgradeNbOskiGrades::subjectEnds($group->group_hemis_id, $since);

            // Guruhning chegara sanagacha bo'lgan kunlik/OSKI baholari va MT — bitta so'rovda
            $grades = DB::table($from)
                ->whereIn('student_hemis_id', $hemisIds)
                ->whereNull('deleted_at')
                ->where(fn ($q) => $q
                    ->where(fn ($q) => $q->where('lesson_date', '>=', $since)->where('lesson_date', '<', $cutoff))
                    ->orWhere(fn ($q) => $q->where('training_type_code', self::MT)->whereNull('lesson_date')))
                ->where(fn ($q) => $q->whereNotNull('grade')->orWhereNotNull('retake_grade'))
                ->get($columns)
                ->groupBy(fn ($r) => (string) $r->subject_id);

            foreach ($cycles as $c) {
                $subjectRows = $grades[(string) $c->subject_id] ?? collect();
                // Kunlik baholar — faqat sikl ichidagi (jadvaldagi birinchi dars kunidan) darslar
                $cycleStart = Carbon::parse($c->cycle_start)->startOfDay()->toDateTimeString();
                $daily = $subjectRows->filter(fn ($r) => $r->lesson_date !== null && (string) $r->lesson_date >= $cycleStart
                    && (string) $r->semester_code === (string) $c->semester_code
                    && !in_array((int) $r->training_type_code, $excluded, true));
                $oski = $subjectRows->filter(fn ($r) => (int) $r->training_type_code === self::OSKI && $r->lesson_date !== null);
                $mt = $subjectRows->filter(fn ($r) => (int) $r->training_type_code === self::MT && $r->lesson_date === null
                    && (string) $r->semester_code === (string) $c->semester_code);
                if ($daily->isEmpty() && $oski->isEmpty() && $mt->isEmpty()) {
                    continue;
                }

                $lessonsEnd = Carbon::parse($c->cycle_end)->startOfDay();
                $dates = $ends[(string) $c->subject_id]['dates'] ?? [];
                $exam = $dates['Test'] ?? $dates['OSKI'] ?? null;
                $examLabel = isset($dates['Test']) ? 'Test ' . $dates['Test']->format('d.m') : (isset($dates['OSKI']) ? 'OSKI ' . $dates['OSKI']->format('d.m') : '—');
                $row = [$group->name, $courseOf[$group->group_hemis_id] ?? '', trim($c->subject_name),
                    Carbon::parse($c->cycle_start)->format('d.m') . ' – ' . $lessonsEnd->format('d.m'), $examLabel];

                $continued = $lessonsEnd->gte($cutoff) || ($exam !== null && $exam->gte($cutoff));
                if (!$continued) {
                    // Davom etmagan fan — tegilmaydi; oshirib yuborilgan bo'lsa ko'rsatiladi/qaytariladi
                    $upgraded = $daily->merge($oski)->merge($mt)->filter(fn ($r) => $r->upgraded_by_admin);
                    if ($upgraded->isEmpty()) {
                        continue;
                    }
                    $totals['wrong'] += $upgraded->count();
                    $none = [];
                    if ($revertFinished) {
                        [$upgradedMt, $upgradedOther] = $upgraded->partition(fn ($r) => (int) $r->training_type_code === self::MT);
                        AdminUpgradeGrades::applyRows($upgradedOther, $students, $limit, $deadlineDays, true, $force, $group->name, $none);
                        AdminUpgradeGrades::applyRows($upgradedMt, $students, $limit, $deadlineDays, true, $force, $group->name, $none, false);
                    }
                    $table[] = array_merge($row, ['', '', '', '', '', "FAN DAVOM ETMAGAN — noto'g'ri oshirilgan: {$upgraded->count()}"
                        . ($revertFinished ? ($force ? ', qaytarildi' : ', qaytariladi') : '')]);
                    continue;
                }

                $none = [];
                $d = AdminUpgradeGrades::applyRows($daily, $students, $limit, $deadlineDays, $revert, $force, $group->name, $none);
                $o = AdminUpgradeGrades::applyRows($oski, $students, $limit, $deadlineDays, $revert, $force, $group->name, $none);
                $uploaded = $mt->isEmpty() ? [] : UpgradeMtGrades::uploadDates($group->group_hemis_id, $c, $hemisIds);
                $mtBefore = $mt->filter(fn ($r) => isset($uploaded[$r->student_hemis_id]) && $uploaded[$r->student_hemis_id]->lt($cutoff));
                $m = AdminUpgradeGrades::applyRows($mtBefore, $students, $limit, $deadlineDays, $revert, $force, $group->name, $none, false);

                $totals['kunlik'] += $d['changed'];
                $totals['OSKI'] += $o['changed'];
                $totals['MT'] += $m['changed'];
                $same = $d['same'] + $o['same'] + $m['same'];
                $totals['allaqachon'] += $same;
                if ($d['changed'] + $o['changed'] + $m['changed'] === 0) {
                    continue;   // hech narsa o'zgarmaydi — jadvalni to'ldirmaymiz
                }
                $mtSkipped = $mt->count() - $mtBefore->count();
                $table[] = array_merge($row, [
                    $d['changed'] . ' / ' . $daily->count(),
                    $d['retake'],
                    $m['changed'] . ($mtSkipped ? " ({$cut} dan keyin/faylsiz: {$mtSkipped})" : ''),
                    $o['changed'],
                    $d['below'] + $o['below'] + $m['below'],
                    $same ? "allaqachon: {$same}" : '',
                ]);
            }
        }
        $this->output->progressFinish();

        $verb = $revert ? 'Qaytariladi' : 'Oshiriladi';
        $this->table(['Guruh', 'Kurs', 'Fan', 'Darslar', 'Yakuniy imtihon', "Kunlik: {$verb} / jami ({$cut} gacha)", 'shundan NB/otrabotka',
            "MT: {$verb}", "OSKI: {$verb}", '<60', 'Izoh'], $table);
        $this->line(sprintf('Kunlik: %d, MT: %d, OSKI: %d — jami %d ta baho %s. Allaqachon oshirilgan: %d.',
            $totals['kunlik'], $totals['MT'], $totals['OSKI'], $totals['kunlik'] + $totals['MT'] + $totals['OSKI'],
            $force ? ($revert ? 'qaytarildi' : 'oshirildi') : ($revert ? 'qaytariladi' : 'oshiriladi'), $totals['allaqachon']));
        if ($totals['wrong']) {
            $this->warn("Davom etmagan fanlarda noto'g'ri oshirilgan baholar: {$totals['wrong']} ta."
                . ($revertFinished ? '' : ' Qaytarish uchun: --revert-finished --force'));
        }
        if (!$force) {
            $this->comment('Bu faqat hisobot. Yozish uchun --force qo\'shing.');
        }

        return self::SUCCESS;
    }
}
