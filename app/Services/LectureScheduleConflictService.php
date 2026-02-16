<?php

namespace App\Services;

use App\Models\CurriculumSubjectTeacher;
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

        // ── 1. KUN+JUFTLIK BO'YICHA KONFLIKTLAR ──
        $grouped = $items->groupBy(fn($item) => $item->week_day . '_' . $item->lesson_pair_code);

        foreach ($grouped as $key => $group) {
            if ($group->count() < 2) {
                continue;
            }

            // 1a. O'qituvchi konflikti: bitta o'qituvchi bir vaqtda 2+ TURLI auditoriyada
            $this->checkTeacherConflicts($group, $conflicts);

            // 1b. Auditoriya konflikti: bitta xonada bir vaqtda 2+ TURLI o'qituvchi
            $this->checkRoomConflicts($group, $conflicts);

            // 1c. Guruh konflikti: bitta guruhga bir vaqtda 2+ fan
            $this->checkGroupConflicts($group, $conflicts);

            // 1d. Potok/Xona konflikti: bitta xonada bir vaqtda 1 dan ortiq potok
            $this->checkRoomPotokConflicts($group, $conflicts);
        }

        // ── 2. SOAT KONFLIKTI: O'quv reja vs jadval soatlari ──
        $this->checkHoursConflicts($batch, $items, $conflicts);

        // ── 3. DUBLIKAT KONFLIKTI: aynan bir xil yozuvlar ──
        $this->checkDuplicateConflicts($items, $conflicts);

        // ── 4. MA'LUMOT TO'LIQLIGI OGOHLANTIRISHI ──
        $this->checkMissingDataWarnings($items, $conflicts);

        // ── 5. POTOK ICHKI IZCHILLIK: bir potok ichida turli fan/oqituvchi/xona ──
        $this->checkPotokConsistency($items, $conflicts);

        // Batch statistikasini yangilash
        $batch->update([
            'conflicts_count' => count($conflicts),
        ]);

        return $conflicts;
    }

    // ═══════════════════════════════════════════════════════════════
    //  1a. O'QITUVCHI KONFLIKTI
    // ═══════════════════════════════════════════════════════════════

    private function checkTeacherConflicts(Collection $group, array &$conflicts): void
    {
        $byTeacher = $group->filter(fn($i) => $i->employee_name)
            ->groupBy('employee_name');

        foreach ($byTeacher as $teacherName => $teacherLessons) {
            $overlapping = $this->filterOverlappingParity($teacherLessons);
            if ($overlapping->count() > 1) {
                $uniqueRooms = $overlapping->pluck('auditorium_name')->filter()->unique();
                if ($uniqueRooms->count() <= 1 && $overlapping->every(fn($i) => !empty($i->auditorium_name))) {
                    continue; // Bitta xonada — bitta ma'ruza
                }

                $ids = $overlapping->pluck('id')->toArray();
                $groups = $overlapping->pluck('group_name')->unique()->implode(', ');
                $conflicts[] = [
                    'type' => 'teacher',
                    'message' => "{$teacherName} — bir vaqtda {$overlapping->count()} dars: {$groups}",
                    'ids' => $ids,
                    'week_day' => $group->first()->week_day,
                    'pair' => $group->first()->lesson_pair_name,
                ];
                foreach ($overlapping as $item) {
                    $this->markConflict($item, 'teacher', "O'qituvchi {$teacherName} bir vaqtda boshqa guruhda ham: {$groups}");
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  1b. AUDITORIYA KONFLIKTI
    // ═══════════════════════════════════════════════════════════════

    private function checkRoomConflicts(Collection $group, array &$conflicts): void
    {
        $byRoom = $group->filter(fn($i) => $i->auditorium_name)
            ->groupBy('auditorium_name');

        foreach ($byRoom as $roomName => $roomLessons) {
            $overlapping = $this->filterOverlappingParity($roomLessons);
            if ($overlapping->count() > 1) {
                $uniqueTeachers = $overlapping->pluck('employee_name')->filter()->unique();
                if ($uniqueTeachers->count() <= 1) {
                    continue; // Bitta o'qituvchi — bitta ma'ruza
                }

                $ids = $overlapping->pluck('id')->toArray();
                $groups = $overlapping->pluck('group_name')->unique()->implode(', ');
                $conflicts[] = [
                    'type' => 'auditorium',
                    'message' => "{$roomName} — bir vaqtda {$overlapping->count()} guruh: {$groups}",
                    'ids' => $ids,
                    'week_day' => $group->first()->week_day,
                    'pair' => $group->first()->lesson_pair_name,
                ];
                foreach ($overlapping as $item) {
                    $this->markConflict($item, 'auditorium', "{$roomName}-xonada bir vaqtda boshqa guruh ham: {$groups}");
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  1c. GURUH KONFLIKTI
    // ═══════════════════════════════════════════════════════════════

    private function checkGroupConflicts(Collection $group, array &$conflicts): void
    {
        $byGroup = $group->groupBy('group_name');

        foreach ($byGroup as $groupName => $groupLessons) {
            $overlapping = $this->filterOverlappingParity($groupLessons);
            if ($overlapping->count() > 1) {
                $ids = $overlapping->pluck('id')->toArray();
                $subjects = $overlapping->pluck('subject_name')->unique()->implode(', ');
                $conflicts[] = [
                    'type' => 'group',
                    'message' => "{$groupName} — bir vaqtda {$overlapping->count()} fan: {$subjects}",
                    'ids' => $ids,
                    'week_day' => $group->first()->week_day,
                    'pair' => $group->first()->lesson_pair_name,
                ];
                foreach ($overlapping as $item) {
                    $this->markConflict($item, 'group', "{$groupName} guruhga bir vaqtda boshqa fan ham: {$subjects}");
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  1d. POTOK/XONA KONFLIKTI — bitta xonada bittadan ortiq potok
    // ═══════════════════════════════════════════════════════════════

    private function checkRoomPotokConflicts(Collection $group, array &$conflicts): void
    {
        $withRoomAndPotok = $group->filter(fn($i) => $i->auditorium_name && $i->group_source);

        $byRoom = $withRoomAndPotok->groupBy('auditorium_name');

        foreach ($byRoom as $roomName => $roomLessons) {
            $overlapping = $this->filterOverlappingParity($roomLessons);
            if ($overlapping->count() < 2) {
                continue;
            }

            $uniquePotoks = $overlapping->pluck('group_source')->unique();
            if ($uniquePotoks->count() <= 1) {
                continue; // Bitta potok — muammo yo'q
            }

            $ids = $overlapping->pluck('id')->toArray();
            $potoks = $uniquePotoks->implode(', ');
            $conflicts[] = [
                'type' => 'room_potok',
                'message' => "{$roomName} — bir vaqtda {$uniquePotoks->count()} potok: {$potoks}",
                'ids' => $ids,
                'week_day' => $group->first()->week_day,
                'pair' => $group->first()->lesson_pair_name,
            ];
            foreach ($overlapping as $item) {
                $this->markConflict($item, 'room_potok', "{$roomName}-xonada bir vaqtda boshqa potok ham: {$potoks}");
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  2. SOAT KONFLIKTI — O'quv reja soatlari vs jadval soatlari
    // ═══════════════════════════════════════════════════════════════

    private function checkHoursConflicts(LectureScheduleBatch $batch, Collection $items, array &$conflicts): void
    {
        // Guruh bo'yicha guruhlash, so'ng fan+turi bo'yicha
        $byGroup = $items->groupBy('group_name');

        foreach ($byGroup as $groupName => $groupItems) {
            // Har bir fan+turi kombinatsiyasi uchun soatlarni hisoblash
            $bySubjectType = $groupItems->groupBy(function ($item) {
                return $item->subject_name . '||' . ($item->training_type_name ?? '');
            });

            foreach ($bySubjectType as $key => $subjectItems) {
                [$subjectName, $trainingType] = explode('||', $key, 2);
                if (!$trainingType) {
                    continue; // Turi ko'rsatilmagan — tekshirib bo'lmaydi
                }

                // Jadvaldagi darslar sonini hisoblash
                // Har bir unikal kun+juftlik = bitta haftalik dars
                $uniqueSlots = $subjectItems->unique(fn($i) => $i->week_day . '_' . $i->lesson_pair_code);
                $totalScheduleHours = 0;

                foreach ($uniqueSlots as $slot) {
                    $lessonCount = $this->parseLessonCount($slot->weeks);
                    $totalScheduleHours += $lessonCount * 2; // Har bir juftlik = 2 soat
                }

                // O'quv rejadan mos yozuvni topish
                $groupId = $subjectItems->first()->group_id;
                $curriculum = $this->findCurriculumMatch($subjectName, $trainingType, $groupId, $groupName);

                if (!$curriculum || !$curriculum->academic_load) {
                    continue; // O'quv rejada topilmadi — skip
                }

                $planHours = (int) $curriculum->academic_load;

                if ($totalScheduleHours !== $planHours) {
                    $diff = $totalScheduleHours - $planHours;
                    $direction = $diff > 0 ? 'ko\'p' : 'kam';
                    $ids = $subjectItems->pluck('id')->unique()->toArray();

                    $conflicts[] = [
                        'type' => 'hours',
                        'message' => "{$subjectName} ({$trainingType}), {$groupName}: jadvalda {$totalScheduleHours} soat, rejada {$planHours} soat ({$direction}: " . abs($diff) . " soat)",
                        'ids' => $ids,
                        'week_day' => null,
                        'pair' => null,
                    ];
                    foreach ($subjectItems as $item) {
                        $this->markConflict($item, 'hours', "Soat farqi: jadvalda {$totalScheduleHours}, rejada {$planHours} ({$direction}: " . abs($diff) . ")");
                    }
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  3. DUBLIKAT KONFLIKTI
    // ═══════════════════════════════════════════════════════════════

    private function checkDuplicateConflicts(Collection $items, array &$conflicts): void
    {
        $seen = [];
        $duplicateGroups = [];

        foreach ($items as $item) {
            $key = implode('|', [
                $item->week_day,
                $item->lesson_pair_code,
                $item->group_name,
                $item->subject_name,
                $item->week_parity ?? '',
            ]);

            if (isset($seen[$key])) {
                if (!isset($duplicateGroups[$key])) {
                    $duplicateGroups[$key] = [$seen[$key]];
                }
                $duplicateGroups[$key][] = $item;
            } else {
                $seen[$key] = $item;
            }
        }

        foreach ($duplicateGroups as $key => $dupes) {
            $first = $dupes[0];
            $ids = collect($dupes)->pluck('id')->toArray();
            $conflicts[] = [
                'type' => 'duplicate',
                'message' => "Dublikat: {$first->group_name}, {$first->subject_name} ({$first->lesson_pair_name}) — " . count($dupes) . " marta",
                'ids' => $ids,
                'week_day' => $first->week_day,
                'pair' => $first->lesson_pair_name,
            ];
            foreach ($dupes as $item) {
                $this->markConflict($item, 'duplicate', "Dublikat yozuv: {$first->group_name}, {$first->subject_name}");
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  4. MA'LUMOT TO'LIQLIGI OGOHLANTIRISHI
    // ═══════════════════════════════════════════════════════════════

    private function checkMissingDataWarnings(Collection $items, array &$conflicts): void
    {
        $noTeacher = $items->filter(fn($i) => empty($i->employee_name));
        $noRoom = $items->filter(fn($i) => empty($i->auditorium_name));

        // O'qituvchisiz guruhlarni guruhlash
        if ($noTeacher->isNotEmpty()) {
            $bySubject = $noTeacher->groupBy('subject_name');
            foreach ($bySubject as $subjectName => $subjectItems) {
                $groups = $subjectItems->pluck('group_name')->unique()->implode(', ');
                $conflicts[] = [
                    'type' => 'missing_teacher',
                    'message' => "{$subjectName}: o'qituvchi ko'rsatilmagan ({$groups})",
                    'ids' => $subjectItems->pluck('id')->toArray(),
                    'week_day' => null,
                    'pair' => null,
                ];
            }
        }

        // Xonasiz darslarni guruhlash
        if ($noRoom->isNotEmpty()) {
            $bySubject = $noRoom->groupBy('subject_name');
            foreach ($bySubject as $subjectName => $subjectItems) {
                $groups = $subjectItems->pluck('group_name')->unique()->implode(', ');
                $conflicts[] = [
                    'type' => 'missing_room',
                    'message' => "{$subjectName}: auditoriya ko'rsatilmagan ({$groups})",
                    'ids' => $subjectItems->pluck('id')->toArray(),
                    'week_day' => null,
                    'pair' => null,
                ];
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  5. POTOK IZCHILLIK TEKSHIRUVI
    // ═══════════════════════════════════════════════════════════════

    private function checkPotokConsistency(Collection $items, array &$conflicts): void
    {
        $withPotok = $items->filter(fn($i) => $i->group_source);

        // Potok + kun + juftlik + paritet bo'yicha guruhlash
        $grouped = $withPotok->groupBy(function ($item) {
            return $item->group_source . '|' . $item->week_day . '_' . $item->lesson_pair_code . '|' . ($item->week_parity ?? '');
        });

        foreach ($grouped as $key => $potokItems) {
            if ($potokItems->count() < 2) {
                continue;
            }

            // Potok ichida fan nomi bir xil bo'lishi kerak
            $uniqueSubjects = $potokItems->pluck('subject_name')->unique();
            if ($uniqueSubjects->count() > 1) {
                $potokName = $potokItems->first()->group_source;
                $subjects = $uniqueSubjects->implode(', ');
                $ids = $potokItems->pluck('id')->toArray();
                $conflicts[] = [
                    'type' => 'potok_inconsistent',
                    'message' => "Potok {$potokName}: bir vaqtda turli fanlar — {$subjects}",
                    'ids' => $ids,
                    'week_day' => $potokItems->first()->week_day,
                    'pair' => $potokItems->first()->lesson_pair_name,
                ];
                foreach ($potokItems as $item) {
                    $this->markConflict($item, 'potok_inconsistent', "Potok {$potokName} ichida turli fan: {$subjects}");
                }
            }

            // Potok ichida auditoriya bir xil bo'lishi kerak
            $uniqueRooms = $potokItems->pluck('auditorium_name')->filter()->unique();
            if ($uniqueRooms->count() > 1) {
                $potokName = $potokItems->first()->group_source;
                $rooms = $uniqueRooms->implode(', ');
                $ids = $potokItems->pluck('id')->toArray();
                $conflicts[] = [
                    'type' => 'potok_inconsistent',
                    'message' => "Potok {$potokName}: guruhlar turli auditoriyalarda — {$rooms}",
                    'ids' => $ids,
                    'week_day' => $potokItems->first()->week_day,
                    'pair' => $potokItems->first()->lesson_pair_name,
                ];
                foreach ($potokItems as $item) {
                    $this->markConflict($item, 'potok_inconsistent', "Potok {$potokName} ichida turli auditoriya: {$rooms}");
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  HEMIS SOLISHTIRISH
    // ═══════════════════════════════════════════════════════════════

    public function compareWithHemis(LectureScheduleBatch $batch): array
    {
        $items = $batch->items()->get();
        $mismatches = [];
        $matchCount = 0;
        $partialCount = 0;
        $mismatchCount = 0;
        $notFoundCount = 0;

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

    // ═══════════════════════════════════════════════════════════════
    //  YORDAMCHI FUNKSIYALAR
    // ═══════════════════════════════════════════════════════════════

    /**
     * weeks maydonidan darslar sonini hisoblash.
     * "6" -> 6, "1-8" -> 8, "1,3,5,7" -> 4, null -> 15 (default semestr)
     */
    private function parseLessonCount(?string $weeks): int
    {
        if (empty($weeks)) {
            return 15; // Default: butun semestr
        }

        $weeks = trim($weeks);

        // "1-8" oraliq
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $weeks, $m)) {
            return (int) $m[2] - (int) $m[1] + 1;
        }

        // "1,3,5,7" ro'yxat
        if (str_contains($weeks, ',')) {
            return count(explode(',', $weeks));
        }

        // Bitta raqam = darslar soni
        if (is_numeric($weeks)) {
            return (int) $weeks;
        }

        return 15;
    }

    /**
     * O'quv rejadan mos yozuvni topish
     */
    private function findCurriculumMatch(string $subjectName, string $trainingType, ?int $groupId, string $groupName): ?CurriculumSubjectTeacher
    {
        $query = CurriculumSubjectTeacher::where('active', true);

        // Fan nomi bo'yicha (LIKE qidirish)
        $query->where(function ($q) use ($subjectName) {
            $q->where('subject_name', $subjectName)
                ->orWhere('subject_name', 'LIKE', '%' . $subjectName . '%');
        });

        // Training type bo'yicha
        $query->where(function ($q) use ($trainingType) {
            $lower = mb_strtolower($trainingType);
            $q->whereRaw('LOWER(training_type_name) = ?', [$lower])
                ->orWhereRaw('LOWER(training_type_name) LIKE ?', ['%' . $lower . '%']);
        });

        // Guruh bo'yicha (agar ID bor bo'lsa)
        if ($groupId) {
            $match = (clone $query)->where('group_id', $groupId)->first();
            if ($match) {
                return $match;
            }
        }

        return $query->first();
    }

    /**
     * Juft-toq bo'yicha haqiqiy ziddiyatli elementlarni filtrlash.
     */
    private function filterOverlappingParity(Collection $items): Collection
    {
        if ($items->count() < 2) {
            return $items;
        }

        $hasAnyParity = $items->contains(fn($i) => !empty($i->week_parity));
        if (!$hasAnyParity) {
            return $items;
        }

        $conflicting = collect();
        $arr = $items->values();

        for ($i = 0; $i < $arr->count(); $i++) {
            for ($j = $i + 1; $j < $arr->count(); $j++) {
                if ($this->parityOverlaps($arr[$i]->week_parity, $arr[$j]->week_parity)) {
                    $conflicting->push($arr[$i]);
                    $conflicting->push($arr[$j]);
                }
            }
        }

        return $conflicting->unique('id');
    }

    private function parityOverlaps(?string $a, ?string $b): bool
    {
        if (empty($a) || empty($b)) {
            return true;
        }
        return $a === $b;
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

    private function compareItem(LectureSchedule $item, Collection $hemisSchedules): array
    {
        $matching = $hemisSchedules->filter(function ($hs) use ($item) {
            if (!$hs->lesson_date) {
                return false;
            }
            $hsDayOfWeek = $hs->lesson_date->dayOfWeekIso;
            if ($hsDayOfWeek != $item->week_day) {
                return false;
            }

            if (mb_strtolower(trim($hs->group_name)) !== mb_strtolower(trim($item->group_name))) {
                return false;
            }

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

        if (mb_strtolower(trim($hemis->subject_name ?? '')) !== mb_strtolower(trim($item->subject_name))) {
            $diffs[] = [
                'field' => 'fan',
                'uploaded' => $item->subject_name,
                'hemis' => $hemis->subject_name,
            ];
        }

        if ($item->employee_name && $hemis->employee_name) {
            if (mb_strtolower(trim($hemis->employee_name)) !== mb_strtolower(trim($item->employee_name))) {
                $diffs[] = [
                    'field' => 'oqituvchi',
                    'uploaded' => $item->employee_name,
                    'hemis' => $hemis->employee_name,
                ];
            }
        }

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

        $hasCriticalDiff = collect($diffs)->contains(fn($d) => in_array($d['field'], ['fan', 'oqituvchi']));

        return [
            'status' => $hasCriticalDiff ? 'mismatch' : 'partial',
            'id' => $item->id,
            'diff' => $diffs,
            'message' => collect($diffs)->map(fn($d) => "{$d['field']}: yuklangan=\"{$d['uploaded']}\" ↔ hemis=\"{$d['hemis']}\"")->implode('; '),
        ];
    }

    private function findExtraHemisSchedules(Collection $items, Collection $hemisSchedules): array
    {
        $extra = [];

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
}
