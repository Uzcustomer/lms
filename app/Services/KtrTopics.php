<?php

namespace App\Services;

use App\Models\CurriculumSubject;
use App\Models\CurriculumWeek;
use App\Models\KtrPlan;
use Illuminate\Support\Facades\DB;

/**
 * KTR (kalendar-tematik reja) dan fan mavzulari: har hafta bo'yicha
 * mashg'ulot turi (ma'ruza, amaliy, laboratoriya, ...), soati va mavzusi.
 * Ma'lumot ktr_plans.plan_data dan olinadi: {hours: {hafta: {kod: soat}},
 * topics: {hafta: {kod: mavzu}}}; tur nomlari curriculum_subjects.subject_details
 * dan. Mustaqil ta'lim ko'rsatilmaydi (KTR Word eksporti bilan bir xil).
 */
class KtrTopics
{
    private const TYPE_ORDER = ['maruza', 'amaliy', 'laboratoriya', 'klinik', 'seminar', 'mustaqil'];

    /**
     * @return array{has_plan: bool, types: array<string,string>, weeks: list<array{week:int, dates:string, items: list<array{code:string, type:string, hours:int, topic:string}>}>, topics_count: int}
     */
    public static function forSubject(CurriculumSubject $cs): array
    {
        $types = self::trainingTypes($cs);
        $plan = KtrPlan::where('curriculum_subject_id', $cs->id)->first();
        if (!$plan || !is_array($plan->plan_data)) {
            return ['has_plan' => false, 'types' => $types, 'weeks' => [], 'topics_count' => 0];
        }

        $planData = $plan->plan_data;
        $hours = $planData['hours'] ?? $planData;
        $topics = $planData['topics'] ?? [];
        $dates = self::weekDates($cs);

        $weeks = [];
        $count = 0;
        for ($w = 1; $w <= (int) $plan->week_count; $w++) {
            $items = [];
            foreach ($types as $code => $name) {
                $h = (int) ($hours[$w][$code] ?? $hours[(string) $w][$code] ?? 0);
                $topic = trim((string) ($topics[$w][$code] ?? $topics[(string) $w][$code] ?? ''));
                if ($h <= 0 && $topic === '') {
                    continue;
                }
                $items[] = ['code' => (string) $code, 'type' => $name, 'hours' => $h, 'topic' => $topic];
                if ($topic !== '') {
                    $count++;
                }
            }
            if ($items) {
                $weeks[] = ['week' => $w, 'dates' => $dates[$w] ?? '', 'items' => $items];
            }
        }

        return ['has_plan' => true, 'types' => $types, 'weeks' => $weeks, 'topics_count' => $count];
    }

    /** Mashg'ulot turlari (mustaqil ta'limsiz), KTR tartibida: kod => nom */
    private static function trainingTypes(CurriculumSubject $cs): array
    {
        $details = is_string($cs->subject_details) ? json_decode($cs->subject_details, true) : $cs->subject_details;
        $types = [];
        foreach ((array) $details as $detail) {
            $code = (string) ($detail['trainingType']['code'] ?? '');
            $name = (string) ($detail['trainingType']['name'] ?? '');
            if ($code !== '' && $name !== '' && !preg_match('/mustaqil/i', self::normalize($name))) {
                $types[$code] = $name;
            }
        }

        uksort($types, function ($a, $b) use ($types) {
            return self::position($types[$a]) <=> self::position($types[$b]);
        });

        return $types;
    }

    private static function position(string $name): int
    {
        $normalized = self::normalize($name);
        foreach (self::TYPE_ORDER as $i => $keyword) {
            if (str_contains($normalized, $keyword)) {
                return $i;
            }
        }

        return count(self::TYPE_ORDER);
    }

    private static function normalize(string $value): string
    {
        return preg_replace('/[^a-z\x{0400}-\x{04FF}]/u', '', mb_strtolower($value));
    }

    /** Hafta sanalari (curriculum_weeks): hafta raqami => "dd.mm-dd.mm" */
    private static function weekDates(CurriculumSubject $cs): array
    {
        $semesterHemisId = DB::table('semesters')
            ->where('curriculum_hemis_id', $cs->curricula_hemis_id)
            ->where('code', $cs->semester_code)
            ->value('semester_hemis_id');
        if (!$semesterHemisId) {
            return [];
        }

        $dates = [];
        foreach (CurriculumWeek::where('semester_hemis_id', $semesterHemisId)->orderBy('start_date')->get() as $i => $week) {
            $start = $week->start_date ? $week->start_date->format('d.m') : '';
            $end = $week->end_date ? $week->end_date->format('d.m') : '';
            $dates[$i + 1] = $start && $end ? "{$start}–{$end}" : '';
        }

        return $dates;
    }
}
