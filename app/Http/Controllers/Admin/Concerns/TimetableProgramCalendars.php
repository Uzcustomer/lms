<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\TimetableBoard;
use App\Models\TimetableGridSetting;
use App\Services\Timetable\ProgramCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait TimetableProgramCalendars
{
    private ?array $calendarGroups = null;

    private array $calendarGridCache = [];

    private array $calendarWeekCache = [];

    private ?array $calendarPrograms = null;

    private ?array $calendarStudentCurricula = null;

    private function calendarCurriculum(string $group, ?string $faculty): int
    {
        if ($this->calendarGroups === null) {
            $this->calendarGroups = [];
            if (Schema::hasTable('groups') && Schema::hasColumn('groups', 'curriculum_hemis_id')) {
                $rows = DB::table('groups')->whereNotNull('curriculum_hemis_id')->get();
                foreach ($rows as $g) {
                    $fac = mb_strtolower(trim((string) ($g->department_name ?? '')));
                    $names = [(string) $g->name, preg_replace('/\s*\(.*$/u', '', (string) $g->name)];
                    foreach (array_unique($names) as $name) {
                        $key = $fac.'|'.ProgramCalendar::groupKey($name);
                        $this->calendarGroups[$key][] = [(int) $g->curriculum_hemis_id, (bool) ($g->active ?? true), (int) ($g->group_hemis_id ?? 0)];
                    }
                }
            }
        }
        foreach (array_unique([$group, preg_replace('/\s*\(.*$/u', '', $group)]) as $name) {
            $key = mb_strtolower(trim((string) $faculty)).'|'.ProgramCalendar::groupKey($name);
            $rows = $this->calendarGroups[$key] ?? [];
            $active = array_filter($rows, fn ($r) => $r[1]);
            $ids = array_unique(array_column($active ?: $rows, 0));
            if (count($ids) === 1) {
                return (int) reset($ids);
            }
            if (count($ids) > 1) {
                // Bir xil nomli bir nechta faol guruh (HEMIS'da eskisi nofaol qilinmagan): guruhdagi faol
                // talabalar qaysi rejada o'qisa — o'sha. Ular ham bo'linsa, noaniq guruh tasodifiy reja olmaydi.
                return $this->studentBackedCurriculum($active ?: $rows, $ids);
            }
        }

        return 0;
    }

    /**
     * Faol (o'qiyotgan, status 11) talabalar: HEMIS guruh id => [o'quv reja id => talabalar soni].
     * Talaba yozuvidagi o'quv reja — uning joriy semestridagi rejasi.
     */
    private function activeStudentCurricula(): array
    {
        if ($this->calendarStudentCurricula === null) {
            $this->calendarStudentCurricula = [];
            if (Schema::hasTable('students') && Schema::hasColumn('students', 'curriculum_id')) {
                foreach (DB::table('students')->where('student_status_code', 11)
                    ->whereNotNull('group_id')->whereNotNull('curriculum_id')
                    ->selectRaw('group_id, curriculum_id, COUNT(*) as n')->groupBy('group_id', 'curriculum_id')->get() as $r) {
                    $this->calendarStudentCurricula[(int) $r->group_id][(int) $r->curriculum_id] = (int) $r->n;
                }
            }
        }

        return $this->calendarStudentCurricula;
    }

    /** Nomzod guruhlarning faol talabalari ko'pchiligi o'qiydigan reja (nomzodlar orasidan); aniq ustun bo'lmasa — 0. */
    private function studentBackedCurriculum(array $rows, array $candidates): int
    {
        $students = $this->activeStudentCurricula();
        $votes = [];
        foreach (array_unique(array_filter(array_column($rows, 2))) as $groupId) {
            foreach ($students[$groupId] ?? [] as $cur => $n) {
                if (in_array($cur, $candidates, true)) {
                    $votes[$cur] = ($votes[$cur] ?? 0) + $n;
                }
            }
        }
        arsort($votes);
        $top = array_slice($votes, 0, 2, true);
        if (! $top || (count($top) === 2 && reset($top) === end($top))) {
            return 0;
        }

        return (int) array_key_first($top);
    }

    /**
     * Bir xil nomli guruhlar ichida ustunlik tartibi (oxirgisi ustun): faol, so'ng faol talabalari shu
     * guruhning rejasida o'qiydigani; teng bo'lsa — kelgan (id) tartibi saqlanadi.
     */
    private function preferStudentBackedGroups(iterable $groups): array
    {
        $students = $this->activeStudentCurricula();
        $list = is_array($groups) ? array_values($groups) : collect($groups)->values()->all();
        usort($list, fn ($a, $b) => [(int) ($a->active ?? 0), (int) isset($students[(int) ($a->group_hemis_id ?? 0)][(int) $a->curriculum_hemis_id])]
            <=> [(int) ($b->active ?? 0), (int) isset($students[(int) ($b->group_hemis_id ?? 0)][(int) $b->curriculum_hemis_id])]);

        return $list;
    }

    private function cardCurriculumId($card): int
    {
        $groups = is_array($card) ? ($card['group_names'] ?? []) : ($card->group_names ?? []);
        if (is_string($groups)) {
            $groups = json_decode($groups, true) ?: [];
        }
        if (! $groups) {
            $groups = [is_array($card) ? ($card['group_name'] ?? '') : ($card->group_name ?? '')];
        }
        $faculty = is_array($card) ? ($card['faculty_name'] ?? null) : ($card->faculty_name ?? null);
        $ids = [];
        foreach ($groups as $group) {
            $id = $this->calendarCurriculum((string) $group, $faculty);
            if (! $id) {
                return 0;
            }
            $ids[$id] = true;
        }

        return count($ids) === 1 ? (int) array_key_first($ids) : 0;
    }

    private function calendarSetting(TimetableBoard $board, ?string $faculty, string $specialty, int $course, int $curriculum = 0): ?object
    {
        return ProgramCalendar::select($this->calendarGridCache[$board->id] ??= TimetableGridSetting::where('board_id', $board->id)->get(), $faculty, $specialty, $course, $curriculum);
    }

    private function cardGrid(TimetableBoard $board, $card): array
    {
        return $this->gridFor($board, $card->faculty_name, (string) $card->specialty_name, (int) $card->course, $this->cardCurriculumId($card));
    }

    /** Only load the groups/resources which can collide, rather than every weekly override on the board. */
    private function calendarConflictState($card, ?string $proposedRoom = null): array
    {
        $ownOverrides = Schema::hasTable('timetable_card_overrides')
            ? \App\Models\TimetableCardOverride::where('card_id', $card->id)->get() : collect();
        $rooms = array_values(array_unique(array_filter(array_merge([$card->auditorium_code, $proposedRoom], $ownOverrides->pluck('auditorium_code')->all()))));
        $overrideIds = $rooms && Schema::hasColumn('timetable_card_overrides', 'auditorium_code')
            ? \App\Models\TimetableCardOverride::whereIn('auditorium_code', $rooms)->pluck('card_id')->all() : [];
        $candidates = \App\Models\TimetableCard::where('board_id', $card->board_id)
            ->where(function ($q) use ($card, $rooms, $overrideIds) {
                $q->where('id', $card->id)->orWhere(fn ($g) => $g->where('faculty_name', $card->faculty_name)
                    ->where('specialty_name', $card->specialty_name)->where('course', $card->course));
                if ($card->teacher_id) {
                    $q->orWhere('teacher_id', $card->teacher_id);
                }
                if ($rooms) {
                    $q->orWhereIn('auditorium_code', $rooms);
                }
                if ($overrideIds) {
                    $q->orWhereIn('id', $overrideIds);
                }
            })->get()->filter(fn ($o) => (int) $o->id === (int) $card->id
                || ($card->teacher_id && (int) $o->teacher_id === (int) $card->teacher_id)
                || in_array($o->auditorium_code, $rooms, true) || in_array($o->id, $overrideIds)
                || ($this->groupScopeKey($o) === $this->groupScopeKey($card) && array_intersect($o->occupiedGroups(), $card->occupiedGroups())));
        $state = ['cards' => $candidates->keyBy('id')->all(), 'ov' => []];
        if (Schema::hasTable('timetable_card_overrides')) {
            foreach (\App\Models\TimetableCardOverride::whereIn('card_id', $candidates->pluck('id'))->get() as $override) {
                $state['ov'][(int) $override->card_id][(int) $override->week] = $override;
            }
        }

        return $state;
    }

    private function calendarWeekAt(TimetableBoard $board, $card, string $date): ?int
    {
        $grid = $this->cardGrid($board, $card);
        $key = $board->id.'|'.$card->faculty_name.'|'.$card->specialty_name.'|'.$card->course
            .'|'.$this->cardCurriculumId($card);
        if (! isset($this->calendarWeekCache[$key])) {
            $start = $grid['start_date'] ? \Illuminate\Support\Carbon::parse($grid['start_date']) : $this->weeklySemesterStart($board);
            $this->calendarWeekCache[$key] = [];
            foreach ($this->weekMondays($board, $start, $grid['weeks'], $grid['weekly_breaks']) as $i => $monday) {
                $this->calendarWeekCache[$key][$monday->toDateString()] = $i + 1;
            }
        }
        $day = \Illuminate\Support\Carbon::parse($date);
        if ($day->dayOfWeekIso > $grid['days']) {
            return null;
        }

        return $this->calendarWeekCache[$key][$day->startOfWeek()->toDateString()] ?? null;
    }

    private function sameCycleCapacityPool($a, $b): bool
    {
        return mb_strtolower(trim((string) $a->faculty_name)) === mb_strtolower(trim((string) $b->faculty_name))
            && $this->cardCurriculumId($a) === $this->cardCurriculumId($b);
    }

    private function calendarProgram(int $id): array
    {
        if ($this->calendarPrograms === null) {
            $this->calendarPrograms = [];
            if (Schema::hasTable('curricula')) {
                foreach (DB::table('curricula')->get() as $p) {
                    $this->calendarPrograms[(int) $p->curricula_hemis_id] = [
                        'curriculum_hemis_id' => (int) $p->curricula_hemis_id,
                        'program_name' => $p->name,
                        'education_form_name' => $p->education_form_name ?? '',
                    ];
                }
            }
        }

        return $this->calendarPrograms[$id] ?? ['curriculum_hemis_id' => $id, 'program_name' => '', 'education_form_name' => ''];
    }

    /** A lecture cannot share one calendar across different HEMIS curricula. */
    private function programOqims(array $oqims, ?string $faculty): array
    {
        $out = [];
        foreach ($oqims as $oq) {
            $buckets = [];
            foreach ($oq['rows'] ?? [] as $row) {
                $buckets[$this->calendarCurriculum((string) ($row['name'] ?? ''), $faculty)][] = $row;
            }
            foreach ($buckets as $id => $rows) {
                $out[] = array_replace($oq, ['rows' => $rows, 'curriculum_hemis_id' => (int) $id,
                    'total' => array_sum(array_column($rows, 'count'))]);
            }
        }

        return $out;
    }
}
