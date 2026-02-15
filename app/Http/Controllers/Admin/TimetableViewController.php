<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurriculumWeek;
use App\Models\Schedule;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TimetableViewController extends Controller
{
    /**
     * Dars jadvali ko'rish sahifasi
     */
    public function index()
    {
        // Barcha semestrlarni olish (haftalari bor, unikal code bo'yicha)
        $allSemesters = Semester::has('curriculumWeeks')
            ->orderByDesc('current')
            ->orderByDesc('education_year')
            ->orderBy('name')
            ->get()
            ->unique('code')
            ->map(fn($s) => [
                'semester_hemis_id' => $s->semester_hemis_id,
                'code' => $s->code,
                'name' => $s->name,
                'education_year' => $s->education_year,
                'current' => $s->current,
            ])
            ->values();

        // Joriy semestr (default tanlangan)
        $currentSemester = $allSemesters->firstWhere('current', true);

        // Joriy semestr haftalari
        $currentWeeks = [];
        $currentWeekId = null;
        if ($currentSemester) {
            $weeks = $this->getSemesterWeeks($currentSemester['semester_hemis_id']);
            $currentWeeks = $weeks['weeks'];
            $currentWeekId = $weeks['current_week_id'];
        }

        return view('admin.timetable-view.index', compact(
            'allSemesters', 'currentSemester', 'currentWeeks', 'currentWeekId'
        ));
    }

    /**
     * AJAX: Semestr haftalari
     */
    public function weeks(Request $request)
    {
        $semesterHemisId = $request->input('semester_hemis_id');

        if (!$semesterHemisId) {
            return response()->json(['weeks' => [], 'current_week_id' => null]);
        }

        return response()->json($this->getSemesterWeeks($semesterHemisId));
    }

    /**
     * AJAX: Jadval ma'lumotlarini olish (filtrlangan)
     */
    public function data(Request $request)
    {
        $filterType = $request->input('filter_type', 'teacher');
        $filterValue = $request->input('filter_value', '');
        $weekId = $request->input('week_id');

        $emptyResponse = [
            'items' => [],
            'pairs' => [],
            'days' => $this->getWeekDays(),
            'filter_type' => $filterType,
        ];

        if (!$filterValue) {
            return response()->json($emptyResponse);
        }

        // Hafta sana oralig'ini aniqlash
        $weekStart = null;
        $weekEnd = null;
        if ($weekId) {
            $week = CurriculumWeek::where('curriculum_week_hemis_id', $weekId)->first();
            if ($week) {
                $weekStart = $week->start_date;
                $weekEnd = $week->end_date;
            }
        }

        $query = Schedule::query();

        // Hafta bo'yicha filtr (sana oralig'i)
        if ($weekStart && $weekEnd) {
            $query->whereBetween('lesson_date', [$weekStart, $weekEnd]);
        }

        // Asosiy filtr
        switch ($filterType) {
            case 'teacher':
                $query->where('employee_name', $filterValue);
                break;
            case 'auditorium':
                $query->where('auditorium_name', $filterValue);
                break;
            case 'subject':
                $query->where('subject_name', $filterValue);
                break;
            case 'group':
                $query->where('group_name', $filterValue);
                break;
        }

        $items = $query->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        if ($items->isEmpty()) {
            return response()->json($emptyResponse);
        }

        // Juftliklar
        $pairs = $items->unique('lesson_pair_code')
            ->sortBy('lesson_pair_code')
            ->map(fn($i) => [
                'code' => $i->lesson_pair_code,
                'name' => $i->lesson_pair_name ?? ($i->lesson_pair_code . '-juftlik'),
                'start' => $i->lesson_pair_start_time,
                'end' => $i->lesson_pair_end_time,
            ])
            ->values();

        // Kunlar bo'yicha guruhlashtirish
        $grid = [];
        foreach ($items as $item) {
            $dayOfWeek = Carbon::parse($item->lesson_date)->dayOfWeekIso; // 1=Dushanba ... 7=Yakshanba
            if ($dayOfWeek > 6) continue;

            $key = $dayOfWeek . '_' . $item->lesson_pair_code;
            if (!isset($grid[$key])) {
                $grid[$key] = [];
            }

            $grid[$key][] = [
                'id' => $item->id,
                'day_of_week' => $dayOfWeek,
                'lesson_pair_code' => $item->lesson_pair_code,
                'group_name' => $item->group_name,
                'subject_name' => $item->subject_name,
                'employee_name' => $item->employee_name,
                'auditorium_name' => $item->auditorium_name,
                'training_type_name' => $item->training_type_name,
                'lesson_date' => Carbon::parse($item->lesson_date)->format('d.m'),
                'faculty_name' => $item->faculty_name,
                'department_name' => $item->department_name,
            ];
        }

        return response()->json([
            'items' => $grid,
            'pairs' => $pairs,
            'days' => $this->getWeekDays(),
            'filter_type' => $filterType,
        ]);
    }

    /**
     * AJAX: Filtr qiymatlarini olish (dinamik - hafta bo'yicha)
     */
    public function filterOptions(Request $request)
    {
        $filterType = $request->input('filter_type', 'teacher');
        $weekId = $request->input('week_id');

        $query = Schedule::query();

        // Hafta bo'yicha filtr
        if ($weekId) {
            $week = CurriculumWeek::where('curriculum_week_hemis_id', $weekId)->first();
            if ($week) {
                $query->whereBetween('lesson_date', [$week->start_date, $week->end_date]);
            }
        }

        $column = match ($filterType) {
            'teacher' => 'employee_name',
            'auditorium' => 'auditorium_name',
            'subject' => 'subject_name',
            'group' => 'group_name',
            default => 'employee_name',
        };

        $options = (clone $query)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->values();

        return response()->json(['options' => $options]);
    }

    /**
     * Semestr haftalari va joriy haftani aniqlash
     */
    private function getSemesterWeeks(int $semesterHemisId): array
    {
        $weeks = CurriculumWeek::where('semester_hemis_id', $semesterHemisId)
            ->orderBy('start_date')
            ->get();

        $currentDate = Carbon::now();
        $currentWeekId = null;

        $mapped = $weeks->values()->map(function ($week, $index) use ($currentDate, &$currentWeekId) {
            $start = $week->start_date;
            $end = $week->end_date;

            // Joriy haftani aniqlash
            if ($currentDate->between($start, $end)) {
                $currentWeekId = $week->curriculum_week_hemis_id;
            }

            return [
                'id' => $week->curriculum_week_hemis_id,
                'number' => $index + 1,
                'label' => ($index + 1) . '-hafta (' . $start->format('d.m.Y') . ' - ' . $end->format('d.m.Y') . ')',
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'current' => $week->current,
            ];
        });

        // Agar joriy hafta topilmasa, eng yaqin kelgusini tanlash
        if (!$currentWeekId && $mapped->isNotEmpty()) {
            $futureWeek = $mapped->first(fn($w) => Carbon::parse($w['start_date'])->isAfter($currentDate));
            $currentWeekId = $futureWeek['id'] ?? $mapped->last()['id'];
        }

        return [
            'weeks' => $mapped->values()->toArray(),
            'current_week_id' => $currentWeekId,
        ];
    }

    private function getWeekDays(): array
    {
        return [
            1 => 'Dushanba',
            2 => 'Seshanba',
            3 => 'Chorshanba',
            4 => 'Payshanba',
            5 => 'Juma',
            6 => 'Shanba',
        ];
    }
}
