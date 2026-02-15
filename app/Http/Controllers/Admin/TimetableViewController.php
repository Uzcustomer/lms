<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TimetableViewController extends Controller
{
    /**
     * Dars jadvali ko'rish sahifasi
     */
    public function index()
    {
        // Mavjud haftalarni olish
        $weeks = Schedule::selectRaw('MIN(lesson_date) as week_start, MAX(lesson_date) as week_end, week_number')
            ->whereNotNull('lesson_date')
            ->whereNotNull('week_number')
            ->groupBy('week_number')
            ->orderByDesc('week_start')
            ->limit(30)
            ->get()
            ->map(fn($w) => [
                'week_number' => $w->week_number,
                'week_start' => Carbon::parse($w->week_start)->format('Y-m-d'),
                'week_end' => Carbon::parse($w->week_end)->format('Y-m-d'),
                'label' => $w->week_number . '-hafta (' . Carbon::parse($w->week_start)->format('d.m') . ' - ' . Carbon::parse($w->week_end)->format('d.m') . ')',
            ]);

        // O'qituvchilar ro'yxati
        $teachers = Schedule::whereNotNull('employee_name')
            ->where('employee_name', '!=', '')
            ->distinct()
            ->orderBy('employee_name')
            ->pluck('employee_name');

        // Auditoriyalar ro'yxati
        $auditoriums = Schedule::whereNotNull('auditorium_name')
            ->where('auditorium_name', '!=', '')
            ->distinct()
            ->orderBy('auditorium_name')
            ->pluck('auditorium_name');

        // Fanlar ro'yxati
        $subjects = Schedule::whereNotNull('subject_name')
            ->where('subject_name', '!=', '')
            ->distinct()
            ->orderBy('subject_name')
            ->pluck('subject_name');

        // Guruhlar ro'yxati
        $groups = Schedule::whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name');

        return view('admin.timetable-view.index', compact(
            'weeks', 'teachers', 'auditoriums', 'subjects', 'groups'
        ));
    }

    /**
     * AJAX: Jadval ma'lumotlarini olish (filtrlangan)
     */
    public function data(Request $request)
    {
        $filterType = $request->input('filter_type', 'teacher'); // teacher, auditorium, subject, group
        $filterValue = $request->input('filter_value', '');
        $weekNumber = $request->input('week_number');

        if (!$filterValue && !$weekNumber) {
            return response()->json([
                'items' => [],
                'pairs' => [],
                'days' => [],
                'filter_type' => $filterType,
                'group_labels' => [],
            ]);
        }

        $query = Schedule::query();

        // Hafta filtri
        if ($weekNumber) {
            $query->where('week_number', $weekNumber);
        }

        // Asosiy filtr
        switch ($filterType) {
            case 'teacher':
                if ($filterValue) {
                    $query->where('employee_name', $filterValue);
                }
                break;
            case 'auditorium':
                if ($filterValue) {
                    $query->where('auditorium_name', $filterValue);
                }
                break;
            case 'subject':
                if ($filterValue) {
                    $query->where('subject_name', $filterValue);
                }
                break;
            case 'group':
                if ($filterValue) {
                    $query->where('group_name', $filterValue);
                }
                break;
        }

        $items = $query->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'items' => [],
                'pairs' => [],
                'days' => $this->getWeekDays(),
                'filter_type' => $filterType,
                'group_labels' => [],
            ]);
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

        // Kunlar bo'yicha guruhlashtirish (lesson_date dan hafta kunini olish)
        $grid = [];
        foreach ($items as $item) {
            $dayOfWeek = Carbon::parse($item->lesson_date)->dayOfWeekIso; // 1=Monday ... 7=Sunday
            if ($dayOfWeek > 6) continue; // Yakshanba ni o'tkazib yuborish

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
     * AJAX: Filtr qiymatlarini olish (dinamik)
     */
    public function filterOptions(Request $request)
    {
        $filterType = $request->input('filter_type', 'teacher');
        $weekNumber = $request->input('week_number');

        $query = Schedule::query();

        if ($weekNumber) {
            $query->where('week_number', $weekNumber);
        }

        $options = [];

        switch ($filterType) {
            case 'teacher':
                $options = $query->whereNotNull('employee_name')
                    ->where('employee_name', '!=', '')
                    ->distinct()
                    ->orderBy('employee_name')
                    ->pluck('employee_name')
                    ->values();
                break;
            case 'auditorium':
                $options = $query->whereNotNull('auditorium_name')
                    ->where('auditorium_name', '!=', '')
                    ->distinct()
                    ->orderBy('auditorium_name')
                    ->pluck('auditorium_name')
                    ->values();
                break;
            case 'subject':
                $options = $query->whereNotNull('subject_name')
                    ->where('subject_name', '!=', '')
                    ->distinct()
                    ->orderBy('subject_name')
                    ->pluck('subject_name')
                    ->values();
                break;
            case 'group':
                $options = $query->whereNotNull('group_name')
                    ->where('group_name', '!=', '')
                    ->distinct()
                    ->orderBy('group_name')
                    ->pluck('group_name')
                    ->values();
                break;
        }

        return response()->json(['options' => $options]);
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
