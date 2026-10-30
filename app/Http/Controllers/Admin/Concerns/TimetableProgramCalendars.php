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

    /** cardCurriculumId: "fakultet|guruhlar" => o'quv reja (calendarGroups kabi so'rov davomida o'zgarmaydi). */
    private array $calendarCurriculumMemo = [];

    /** gridFor natijalari (calendarGridCache bilan birga tozalanadi). */
    private array $calendarGridForMemo = [];

    /** Sana => [ISO hafta kuni, o'sha haftaning dushanbasi] — to'qnashuv tekshiruvida har qo'shni karta uchun qayta parse qilinmasin. */
    private array $calendarDayMemo = [];

    /** Doska => cycleBusyByDate (sikl darslari sana bo'yicha) — weeklyCycleWarnings har tekshiruvda qayta yuklamasin. */
    private array $cycleBusyMemo = [];

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
                        $this->calendarGroups[$key][] = [(int) $g->curriculum_hemis_id, (bool) ($g->active ?? true)];
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
                return 0;
            } // Ambiguous groups must never inherit an arbitrary program.
        }

        return 0;
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

        return $this->calendarCurriculumMemo[$faculty.'|'.json_encode($groups)] ??= (function () use ($groups, $faculty): int {
            $ids = [];
            foreach ($groups as $group) {
                $id = $this->calendarCurriculum((string) $group, $faculty);
                if (! $id) {
                    return 0;
                }
                $ids[$id] = true;
            }

            return count($ids) === 1 ? (int) array_key_first($ids) : 0;
        })();
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
        [$dow, $monday] = $this->calendarDayMemo[$date] ??= (function () use ($date): array {
            $day = \Illuminate\Support\Carbon::parse($date);

            return [$day->dayOfWeekIso, $day->startOfWeek()->toDateString()];
        })();
        if ($dow > $grid['days']) {
            return null;
        }

        return $this->calendarWeekCache[$key][$monday] ?? null;
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
