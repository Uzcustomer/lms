<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fan tugaganmi — YN qaydnoma (vedomost) shundan keyin yaratiladi: yopilish shakli talab qilgan
 * imtihonlar (OSKI / test) sanasi bugungacha o'tgan; imtihonsiz fan (sinov, normativ yoki imtihon
 * jadvali yo'q) — guruhning shu fandagi oxirgi darsi o'tgan. 12a / 12b (va qo'shimchalari) shakllari —
 * shu urinish (2- / 3-) topshirilgach: jurnalda shu urinishning OSKI / test natijasi bor yoki uning
 * imtihon sanasi (oski_resit_date / test_resit_date, …resit2…) o'tgan.
 */
class YnqReadiness
{
    /**
     * @param  Collection<int, array{0: string, 1: string, 2: string}>|array  $refs  [guruh, fan, semestr]
     * @param  string  $ynqForm  qaydnoma shakli (YnqForm) — 12a / 12b da o'sha urinish imtihonlari sanasi
     * @return array<string, array{finished: bool, reason: ?string}>  kalit — "guruh|fan|semestr"
     */
    public static function forMany(Collection|array $refs, string $ynqForm = YnqForm::MAIN): array
    {
        // Urinish: 12 / 12q — asosiy imtihonlar, 12a / 12aq — 1-qayta, 12b / 12bq — 2-qayta topshirish
        $attempt = match (YnqForm::normalize($ynqForm)) {
            '12a', '12aq' => ['oski_resit_date', 'test_resit_date', 2],
            '12b', '12bq' => ['oski_resit2_date', 'test_resit2_date', 3],
            default => ['oski_date', 'test_date', 1],
        };
        [$oskiCol, $testCol, $attemptNo] = $attempt;
        $retake = $attemptNo > 1;
        $refs = collect($refs)->map(fn ($r) => array_map('strval', array_values((array) $r)))->unique(fn ($r) => implode('|', $r))->values();
        if ($refs->isEmpty()) {
            return [];
        }
        $today = Carbon::today()->toDateString();
        $groups = $refs->pluck(0)->unique()->values();
        $subjects = $refs->pluck(1)->unique()->values();

        $exams = Schema::hasTable('exam_schedules') && Schema::hasColumns('exam_schedules', [$oskiCol, $testCol])
            ? DB::table('exam_schedules')->whereNull('student_hemis_id')->whereIn('group_hemis_id', $groups)->whereIn('subject_id', $subjects)
                ->get(['group_hemis_id', 'subject_id', 'semester_code', "{$oskiCol} as oski_date", "{$testCol} as test_date", 'oski_na', 'test_na'])
                ->keyBy(fn ($e) => $e->group_hemis_id . '|' . $e->subject_id . '|' . $e->semester_code)
            : collect();
        $closing = DB::table('groups as g')
            ->join('curriculum_subjects as cs', 'cs.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
            ->whereIn('g.group_hemis_id', $groups)->whereIn('cs.subject_id', $subjects)
            ->get(['g.group_hemis_id', 'cs.subject_id', 'cs.semester_code', 'cs.closing_form'])
            ->keyBy(fn ($c) => $c->group_hemis_id . '|' . $c->subject_id . '|' . $c->semester_code);
        $lastLessons = DB::table('schedules')->whereNull('deleted_at')->whereNotNull('lesson_date')
            ->whereIn('group_id', $groups)->whereIn('subject_id', $subjects)
            ->groupBy('group_id', 'subject_id', 'semester_code')
            ->selectRaw('group_id, subject_id, semester_code, MAX(lesson_date) as last_lesson')
            ->get()
            ->keyBy(fn ($s) => $s->group_id . '|' . $s->subject_id . '|' . $s->semester_code);

        // Qayta topshirish: shu urinishning OSKI / test natijasi jurnalda bor guruhlar
        $attempted = $retake && Schema::hasColumn('student_grades', 'attempt')
            ? DB::table('student_grades as sg')->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->whereNull('sg.deleted_at')->where('sg.attempt', $attemptNo)->whereIn('sg.training_type_code', [101, 102, 103])
                ->where(fn ($q) => $q->whereNotNull('sg.grade')->orWhereNotNull('sg.retake_grade'))
                ->whereIn('st.group_id', $groups)->whereIn('sg.subject_id', $subjects)
                ->distinct()->get(['st.group_id', 'sg.subject_id', 'sg.semester_code'])
                ->mapWithKeys(fn ($r) => [$r->group_id . '|' . $r->subject_id . '|' . $r->semester_code => true])
            : collect();

        $result = [];
        foreach ($refs as [$group, $subject, $semester]) {
            $key = "{$group}|{$subject}|{$semester}";
            $exam = $exams->get($key);
            $form = $closing->get($key)->closing_form ?? null;
            $oski = $exam && !$exam->oski_na ? $exam->oski_date : null;
            $test = $exam && !$exam->test_na ? $exam->test_date : null;
            if ($retake) {
                // Belgilangan imtihonlar (faqat yiqilgan turi o'tkazilishi mumkin) o'tgan yoki natijasi bor
                $dates = array_filter([$oski, $test]);
                $passed = $dates !== [] && collect($dates)->every(fn ($d) => substr((string) $d, 0, 10) <= $today);
                $done = $passed || $attempted->has($key);
                $upcoming = collect($dates)->filter(fn ($d) => substr((string) $d, 0, 10) > $today)->sort()->first();
                $result[$key] = ['finished' => $done, 'reason' => $done ? null : ($upcoming
                    ? "{$attemptNo}-urinish imtihoni " . Carbon::parse($upcoming)->format('d.m.Y') . ' da'
                    : "{$attemptNo}-urinish hali topshirilmagan")];
                continue;
            }
            $required = match ($form) {
                'oski' => ['OSKI' => $oski],
                'test' => ['test' => $test],
                'oski_test' => ['OSKI' => $oski, 'test' => $test],
                default => array_filter(['OSKI' => $oski, 'test' => $test]),
            };

            if ($required !== []) {
                $pending = array_keys(array_filter($required, fn ($d) => !$d || substr((string) $d, 0, 10) > $today));
                $result[$key] = ['finished' => $pending === [], 'reason' => $pending === [] ? null
                    : (collect($required)->only($pending)->filter()->isEmpty()
                        ? implode(' va ', $pending) . ' sanasi belgilanmagan'
                        : implode(' va ', $pending) . ' hali o\'tmagan')];
                continue;
            }
            // Imtihonsiz fan — oxirgi dars o'tgan bo'lsa
            $last = $lastLessons->get($key)?->last_lesson;
            $done = $last && substr((string) $last, 0, 10) < $today;
            $result[$key] = ['finished' => $done, 'reason' => $done ? null : ($last ? 'darslar hali tugamagan' : 'dars jadvali yo\'q')];
        }

        return $result;
    }

    /** @return array{finished: bool, reason: ?string} */
    public static function for(string $groupHemisId, string $subjectId, string $semesterCode, string $ynqForm = YnqForm::MAIN): array
    {
        return self::forMany([[$groupHemisId, $subjectId, $semesterCode]], $ynqForm)["{$groupHemisId}|{$subjectId}|{$semesterCode}"];
    }
}
