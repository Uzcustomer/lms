<?php

namespace App\Console\Commands;

use App\Models\HemisQuizResult;
use App\Models\RetakeApplication;
use App\Models\RetakeGroup;
use App\Models\Student;
use App\Services\Retake\RetakeJournalService;
use App\Services\Retake\RetakeSessionCode;
use App\Services\VedomostMergeService;
use Illuminate\Console\Command;

/**
 * Bir guruhda BIR talabaning turli semestr arizalari bo'lganda (mas. 3-sem va
 * 4-sem), eski (semestr-ko'r) moslashtirish tufayli OSKE/TEST ballari noto'g'ri
 * semestr arizasiga tushgan bo'lishi mumkin. Bu buyruq quiz natijalarini semestr
 * bo'yicha to'g'ri arizalarga qayta taqsimlaydi.
 *
 * XAVFSIZLIK: faqat AYNAN semestrga mos quiz natijasi topilganda qiymat o'rnatiladi.
 * Arizadagi mavjud qiymat esa boshqa semestr quizidan kelgani (kontaminatsiya)
 * ANIQLANSAGINA tozalanadi. Aks holda tegilmaydi — ma'lumot yo'qotilmaydi.
 */
class ReconcileRetakeScores extends Command
{
    protected $signature = 'retake:reconcile-scores {--apply : Haqiqatan yozadi (aks holda dry-run)} {--group= : Faqat shu retake_group id}';

    protected $description = "Turli semestr arizalariga noto'g'ri tushgan qayta o'qish OSKE/TEST ballarini semestr bo'yicha qayta taqsimlaydi";

    public function handle(RetakeJournalService $svc, VedomostMergeService $merge): int
    {
        $apply = (bool) $this->option('apply');
        $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
        $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
        $cutoff = config('retake.tokenless_open_cutoff');

        $normSubj = fn (?string $s) => $s === null ? '' : trim(preg_replace('/\s+/u', ' ', mb_strtolower($merge->rootSubjectName($s))));
        $semNum = fn ($s) => preg_match('/(\d+)/', (string) $s, $m) ? (int) $m[1] : null;

        $groups = RetakeGroup::query()
            ->whereIn('assessment_type', ['oske', 'test', 'oske_test'])
            ->when($this->option('group'), fn ($q, $g) => $q->where('id', $g))
            ->cursor();

        $changed = 0;
        $affectedStudents = 0;
        $affectedGroups = 0;

        foreach ($groups as $group) {
            $apps = RetakeApplication::query()
                ->where('retake_group_id', $group->id)
                ->where('final_status', RetakeApplication::STATUS_APPROVED)
                ->get();

            // Bir talabada turli semestrli >1 ariza bo'lganlarni ajratamiz.
            $byStudent = $apps->groupBy('student_hemis_id')->filter(
                fn ($set) => $set->count() > 1
                    && $set->pluck('semester_name')->map($semNum)->filter()->unique()->count() > 1
            );
            if ($byStudent->isEmpty()) {
                continue;
            }
            $affectedGroups++;

            $needsOske = in_array($group->assessment_type, ['oske', 'oske_test'], true);
            $needsTest = in_array($group->assessment_type, ['test', 'oske_test'], true);
            $subjNorm = $normSubj($group->subject_name);
            $session = $group->resolveSession();
            $sessionCode = RetakeSessionCode::fromSession($session);
            $sessionOpen = $session !== null && !$session->is_closed;

            $hemisIds = $byStudent->keys()->map(fn ($v) => (string) $v)->all();
            $sidMap = Student::whereIn('hemis_id', $hemisIds)->pluck('student_id_number', 'hemis_id');
            $sidToHemis = [];
            foreach ($sidMap as $hid => $sid) {
                if ($sid !== null && $sid !== '') $sidToHemis[(string) $sid] = (string) $hid;
            }
            if (empty($sidToHemis)) {
                continue;
            }

            // Quiz natijalari: [hemis][semNum][oske|test] => eng yuqori baho.
            $best = [];
            $rows = HemisQuizResult::query()
                ->where('is_active', 1)
                ->whereIn('student_id', array_keys($sidToHemis))
                ->get(['student_id', 'quiz_type', 'attempt_name', 'shakl', 'grade', 'date_finish', 'fan_id', 'fan_name', 'semester']);
            foreach ($rows as $r) {
                $same = ((string) $r->fan_id === (string) $group->subject_id)
                    || ($subjNorm !== '' && $normSubj($r->fan_name) === $subjNorm);
                if (!$same || !RetakeSessionCode::isRetakeQuiz($r->attempt_name, $r->shakl) || $r->grade === null) {
                    continue;
                }
                $rowCode = RetakeSessionCode::fromQuizName($r->attempt_name, $r->shakl);
                if ($rowCode !== null) {
                    if ($rowCode !== $sessionCode) continue;
                } else {
                    if (!$sessionOpen) continue;
                    if ($cutoff !== null && $r->date_finish !== null && substr((string) $r->date_finish, 0, 10) < $cutoff) continue;
                }
                // FAQAT OSKI / YN test turlari — mavzu va boshqa quizlar hisobga olinmaydi.
                $isOske = in_array($r->quiz_type, $oskiTypes, true);
                $isTest = in_array($r->quiz_type, $testTypes, true);
                if (!$isOske && !$isTest) {
                    continue;
                }
                $hid = $sidToHemis[(string) $r->student_id] ?? null;
                if ($hid === null) continue;
                $sn = $semNum($r->semester) ?? 0;
                $kind = $isOske ? 'oske' : 'test';
                $g = (float) $r->grade;
                if (!isset($best[$hid][$sn][$kind]) || $g > $best[$hid][$sn][$kind]) {
                    $best[$hid][$sn][$kind] = $g;
                }
            }

            // Har talaba+ariza bo'yicha to'g'ri qiymatni aniqlaymiz.
            foreach ($byStudent as $hid => $set) {
                $hid = (string) $hid;
                $studentTouched = false;
                foreach ($set as $app) {
                    $appSem = $semNum($app->semester_name);
                    $newOske = $app->oske_score !== null ? (float) $app->oske_score : null;
                    $newTest = $app->test_score !== null ? (float) $app->test_score : null;

                    foreach (['oske' => $needsOske, 'test' => $needsTest] as $kind => $need) {
                        if (!$need) continue;
                        $col = $kind === 'oske' ? 'oske_score' : 'test_score';
                        $cur = $app->$col !== null ? (float) $app->$col : null;
                        $correct = ($appSem !== null && isset($best[$hid][$appSem][$kind]))
                            ? $best[$hid][$appSem][$kind]
                            : null;

                        if ($correct !== null) {
                            // Aniq semestr quizi bor — to'g'ri qiymatni o'rnatamiz.
                            $target = round($correct);
                        } elseif ($cur !== null && $this->belongsToOtherSem($best[$hid] ?? [], $appSem, $kind, $cur)) {
                            // Mavjud qiymat BOSHQA semestr quizidan kelgan (kontaminatsiya) — tozalaymiz.
                            $target = null;
                        } else {
                            continue; // mos quiz yo'q, kontaminatsiya ham aniq emas — tegmaymiz.
                        }

                        if ($target !== $cur) {
                            if ($kind === 'oske') $newOske = $target; else $newTest = $target;
                            $studentTouched = true;
                            $this->line(sprintf(
                                '  #%d %s [%s] %s: %s -> %s',
                                $app->id, $group->subject_name, $app->semester_name, strtoupper($kind),
                                $cur === null ? '—' : $cur, $target === null ? '—' : $target
                            ));
                        }
                    }

                    if ($apply && ($newOske !== ($app->oske_score !== null ? (float) $app->oske_score : null)
                                || $newTest !== ($app->test_score !== null ? (float) $app->test_score : null))) {
                        $svc->saveOskeTestScore($app, $needsOske ? $newOske : null, $needsTest ? $newTest : null, null);
                        $changed++;
                    } elseif (!$apply) {
                        // dry-run — o'zgarishlar yuqorida chiqarildi
                        $changed += 0;
                    }
                }
                if ($studentTouched) $affectedStudents++;
            }
        }

        $mode = $apply ? 'QO\'LLANDI' : 'DRY-RUN (o\'zgartirilmadi)';
        $this->info("[$mode] Ta'sirlangan guruhlar: {$affectedGroups}, talabalar: {$affectedStudents}" . ($apply ? ", yozilgan arizalar: {$changed}" : ''));
        if (!$apply) {
            $this->comment('Haqiqatan yozish uchun: php artisan retake:reconcile-scores --apply');
        }

        return self::SUCCESS;
    }

    /**
     * Berilgan qiymat ($val) shu talabaning BOSHQA (appSem'dan farqli) semestr
     * quizida bormi — ya'ni mavjud qiymat kontaminatsiya (boshqa semestrdan) mi?
     */
    private function belongsToOtherSem(array $byHidBest, ?int $appSem, string $kind, float $val): bool
    {
        foreach ($byHidBest as $sem => $kinds) {
            if ((int) $sem === (int) $appSem) continue;
            if (isset($kinds[$kind]) && (float) round($kinds[$kind]) === (float) round($val)) {
                return true;
            }
        }
        return false;
    }
}
