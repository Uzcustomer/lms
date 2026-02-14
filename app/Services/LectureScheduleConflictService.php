<?php

namespace App\Services;

use App\Models\LectureSchedule;
use App\Models\LectureScheduleBatch;
use App\Models\Schedule;
use Illuminate\Support\Collection;

class LectureScheduleConflictService
{
    /**
     * Barcha konflikt belgilarini tozalab, qaytadan aniqlash
     */
    public function resetAndRedetect(LectureScheduleBatch $batch): array
    {
        // Avval barcha conflict belgilarini tozalash
        $batch->items()->update([
            'has_conflict' => false,
            'conflict_details' => null,
        ]);

        return $this->detectInternalConflicts($batch);
    }

    /**
     * Ichki konfliktlarni aniqlash (yuklangan jadval ichida)
     */
    public function detectInternalConflicts(LectureScheduleBatch $batch): array
    {
        $items = $batch->items()->get();
        $conflicts = [];

        // Har bir juftlik-kun kombinatsiyasi bo'yicha guruhlash
        $grouped = $items->groupBy(fn($item) => $item->week_day . '_' . $item->lesson_pair_code);

        foreach ($grouped as $key => $group) {
            if ($group->count() < 2) {
                continue;
            }

            // 1. O'qituvchi konflikti: bitta o'qituvchiga bir vaqtda 2 dars
            $byTeacher = $group->filter(fn($i) => $i->employee_name)
                ->groupBy('employee_name');

            foreach ($byTeacher as $teacherName => $teacherLessons) {
                if ($teacherLessons->count() > 1) {
                    $ids = $teacherLessons->pluck('id')->toArray();
                    $groups = $teacherLessons->pluck('group_name')->implode(', ');
                    $conflicts[] = [
                        'type' => 'teacher',
                        'message' => "{$teacherName} — bir vaqtda {$teacherLessons->count()} dars: {$groups}",
                        'ids' => $ids,
                        'week_day' => $group->first()->week_day,
                        'pair' => $group->first()->lesson_pair_name,
                    ];
                    // Har bir ziddiyatli qatorga belgi qo'yish
                    foreach ($teacherLessons as $item) {
                        $this->markConflict($item, 'teacher', "O'qituvchi {$teacherName} bir vaqtda boshqa guruhda ham: {$groups}");
                    }
                }
            }

            // 2. Auditoriya konflikti: bitta xonaga bir vaqtda 2 guruh
            $byRoom = $group->filter(fn($i) => $i->auditorium_name)
                ->groupBy('auditorium_name');

            foreach ($byRoom as $roomName => $roomLessons) {
                if ($roomLessons->count() > 1) {
                    $ids = $roomLessons->pluck('id')->toArray();
                    $groups = $roomLessons->pluck('group_name')->implode(', ');
                    $conflicts[] = [
                        'type' => 'auditorium',
                        'message' => "{$roomName}-xona — bir vaqtda {$roomLessons->count()} guruh: {$groups}",
                        'ids' => $ids,
                        'week_day' => $group->first()->week_day,
                        'pair' => $group->first()->lesson_pair_name,
                    ];
                    foreach ($roomLessons as $item) {
                        $this->markConflict($item, 'auditorium', "{$roomName}-xonada bir vaqtda boshqa guruh ham: {$groups}");
                    }
                }
            }

            // 3. Guruh konflikti: bitta guruhga bir vaqtda 2 fan
            $byGroup = $group->groupBy('group_name');

            foreach ($byGroup as $groupName => $groupLessons) {
                if ($groupLessons->count() > 1) {
                    $ids = $groupLessons->pluck('id')->toArray();
                    $subjects = $groupLessons->pluck('subject_name')->implode(', ');
                    $conflicts[] = [
                        'type' => 'group',
                        'message' => "{$groupName} — bir vaqtda {$groupLessons->count()} fan: {$subjects}",
                        'ids' => $ids,
                        'week_day' => $group->first()->week_day,
                        'pair' => $group->first()->lesson_pair_name,
                    ];
                    foreach ($groupLessons as $item) {
                        $this->markConflict($item, 'group', "{$groupName} guruhga bir vaqtda boshqa fan ham: {$subjects}");
                    }
                }
            }
        }

        // Batch statistikasini yangilash
        $batch->update([
            'conflicts_count' => count($conflicts),
        ]);

        return $conflicts;
    }

    /**
     * Hemis jadvali bilan solishtirish
     */
    public function compareWithHemis(LectureScheduleBatch $batch): array
    {
        $items = $batch->items()->get();
        $mismatches = [];
        $matchCount = 0;
        $partialCount = 0;
        $mismatchCount = 0;
        $notFoundCount = 0;

        // Hemis dagi jadvallarni olish (joriy semestr uchun)
        $hemisSchedules = $this->getHemisSchedules($batch);

        foreach ($items as $item) {
            $result = $this->compareItem($item, $hemisSchedules);

            $item->update([
                'hemis_status' => $result['status'],
                'hemis_diff' => $result['diff'],
            ]);

            switch ($result['status']) {
                case 'match':
                    $matchCount++;
                    break;
                case 'partial':
                    $partialCount++;
                    $mismatches[] = $result;
                    break;
                case 'mismatch':
                    $mismatchCount++;
                    $mismatches[] = $result;
                    break;
                case 'not_found':
                    $notFoundCount++;
                    $mismatches[] = $result;
                    break;
            }
        }

        // Hemis da bor lekin yuklangan jadvalda yo'q darslar
        $extraHemis = $this->findExtraHemisSchedules($items, $hemisSchedules);

        $batch->update([
            'hemis_mismatches_count' => $mismatchCount + $notFoundCount,
        ]);

        return [
            'total' => $items->count(),
            'match' => $matchCount,
            'partial' => $partialCount,
            'mismatch' => $mismatchCount,
            'not_found' => $notFoundCount,
            'extra_hemis' => count($extraHemis),
            'mismatches' => $mismatches,
            'extra_hemis_items' => $extraHemis,
        ];
    }

    /**
     * Hemis jadvallarini bazadan olish
     */
    private function getHemisSchedules(LectureScheduleBatch $batch): Collection
    {
        $query = Schedule::query();

        if ($batch->semester_code) {
            $query->where('semester_code', $batch->semester_code);
        }

        if ($batch->education_year) {
            $query->where('education_year_code', $batch->education_year);
        }

        return $query->get();
    }

    /**
     * Bitta qatorni Hemis bilan solishtirish
     */
    private function compareItem(LectureSchedule $item, Collection $hemisSchedules): array
    {
        // Hemis dan shu guruh + hafta kuni + juftlik bo'yicha qidirish
        $matching = $hemisSchedules->filter(function ($hs) use ($item) {
            // Hafta kunini solishtirish
            if (!$hs->lesson_date) {
                return false;
            }
            $hsDayOfWeek = $hs->lesson_date->dayOfWeekIso; // 1=Monday ... 7=Sunday
            if ($hsDayOfWeek != $item->week_day) {
                return false;
            }

            // Guruh nomini solishtirish
            if (mb_strtolower(trim($hs->group_name)) !== mb_strtolower(trim($item->group_name))) {
                return false;
            }

            // Juftlik kodini solishtirish
            $hsPairCode = preg_replace('/[^0-9]/', '', $hs->lesson_pair_code ?? '');
            $itemPairCode = preg_replace('/[^0-9]/', '', $item->lesson_pair_code ?? '');
            if ($hsPairCode !== $itemPairCode) {
                return false;
            }

            return true;
        });

        if ($matching->isEmpty()) {
            return [
                'status' => 'not_found',
                'id' => $item->id,
                'diff' => ['Hemis da topilmadi'],
                'message' => "{$item->group_name}, {$item->week_day_name}, {$item->lesson_pair_name} — Hemis da topilmadi",
            ];
        }

        $hemis = $matching->first();
        $diffs = [];

        // Fan nomini solishtirish
        if (mb_strtolower(trim($hemis->subject_name ?? '')) !== mb_strtolower(trim($item->subject_name))) {
            $diffs[] = [
                'field' => 'fan',
                'uploaded' => $item->subject_name,
                'hemis' => $hemis->subject_name,
            ];
        }

        // O'qituvchini solishtirish
        if ($item->employee_name && $hemis->employee_name) {
            if (mb_strtolower(trim($hemis->employee_name)) !== mb_strtolower(trim($item->employee_name))) {
                $diffs[] = [
                    'field' => 'oqituvchi',
                    'uploaded' => $item->employee_name,
                    'hemis' => $hemis->employee_name,
                ];
            }
        }

        // Auditoriyani solishtirish
        if ($item->auditorium_name && $hemis->auditorium_name) {
            if (mb_strtolower(trim($hemis->auditorium_name)) !== mb_strtolower(trim($item->auditorium_name))) {
                $diffs[] = [
                    'field' => 'auditoriya',
                    'uploaded' => $item->auditorium_name,
                    'hemis' => $hemis->auditorium_name,
                ];
            }
        }

        if (empty($diffs)) {
            return [
                'status' => 'match',
                'id' => $item->id,
                'diff' => [],
                'message' => '',
            ];
        }

        // Fan yoki o'qituvchi farq bo'lsa — mismatch, faqat auditoriya bo'lsa — partial
        $hasCriticalDiff = collect($diffs)->contains(fn($d) => in_array($d['field'], ['fan', 'oqituvchi']));

        return [
            'status' => $hasCriticalDiff ? 'mismatch' : 'partial',
            'id' => $item->id,
            'diff' => $diffs,
            'message' => collect($diffs)->map(fn($d) => "{$d['field']}: yuklangan=\"{$d['uploaded']}\" ↔ hemis=\"{$d['hemis']}\"")->implode('; '),
        ];
    }

    /**
     * Hemis da bor lekin yuklangan jadvalda yo'q darslar
     */
    private function findExtraHemisSchedules(Collection $items, Collection $hemisSchedules): array
    {
        $extra = [];

        // Unique hemis schedule kunlarini olish
        $uniqueHemis = $hemisSchedules->unique(function ($hs) {
            if (!$hs->lesson_date) return null;
            return $hs->lesson_date->dayOfWeekIso . '_' . $hs->group_name . '_' . preg_replace('/[^0-9]/', '', $hs->lesson_pair_code ?? '');
        })->filter();

        foreach ($uniqueHemis as $hs) {
            if (!$hs->lesson_date) continue;

            $hsDow = $hs->lesson_date->dayOfWeekIso;
            $hsPair = preg_replace('/[^0-9]/', '', $hs->lesson_pair_code ?? '');

            $found = $items->first(function ($item) use ($hsDow, $hsPair, $hs) {
                $itemPair = preg_replace('/[^0-9]/', '', $item->lesson_pair_code ?? '');
                return $item->week_day == $hsDow
                    && mb_strtolower(trim($item->group_name)) === mb_strtolower(trim($hs->group_name))
                    && $itemPair === $hsPair;
            });

            if (!$found) {
                $extra[] = [
                    'week_day' => $hsDow,
                    'week_day_name' => LectureSchedule::WEEK_DAYS[$hsDow] ?? '',
                    'pair' => $hs->lesson_pair_name,
                    'group' => $hs->group_name,
                    'subject' => $hs->subject_name,
                    'employee' => $hs->employee_name,
                    'auditorium' => $hs->auditorium_name,
                ];
            }
        }

        return $extra;
    }

    private function markConflict(LectureSchedule $item, string $type, string $message): void
    {
        $existing = $item->conflict_details ?? [];
        $existing[] = ['type' => $type, 'message' => $message];

        $item->update([
            'has_conflict' => true,
            'conflict_details' => $existing,
        ]);
    }
}
