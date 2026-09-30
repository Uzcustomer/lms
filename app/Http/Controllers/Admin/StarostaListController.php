<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StarostaListExport;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Starostalar ro'yxati (registrator ofisi + admin).
 *
 * Faol guruhlar va har biriga belgilangan starosta (students.is_starosta)
 * bo'yicha filtrlanadigan ro'yxat + Excel eksport.
 */
class StarostaListController extends Controller
{
    public function index(Request $request)
    {
        $rows = $this->buildRows($request);

        // Filtr dropdownlari — barcha faol guruhlardan
        $departments = Group::where('active', true)
            ->whereNotNull('department_name')->where('department_name', '!=', '')
            ->select('department_hemis_id', 'department_name')
            ->distinct()->orderBy('department_name')->get();

        $specialties = Group::where('active', true)
            ->whereNotNull('specialty_name')->where('specialty_name', '!=', '')
            ->select('specialty_hemis_id', 'specialty_name')
            ->distinct()->orderBy('specialty_name')->get();

        $stats = [
            'total' => $rows->count(),
            'assigned' => $rows->whereNotNull('starosta')->count(),
        ];
        $stats['missing'] = $stats['total'] - $stats['assigned'];

        return view('admin.starosta-list.index', [
            'rows' => $rows,
            'departments' => $departments,
            'specialties' => $specialties,
            'stats' => $stats,
        ]);
    }

    public function export(Request $request)
    {
        $rows = $this->buildRows($request);
        $fileName = 'starostalar_'.date('Y_m_d_H_i').'.xlsx';

        return Excel::download(new StarostaListExport($rows), $fileName);
    }

    /**
     * Filtrlangan "guruh + starosta" qatorlari.
     */
    private function buildRows(Request $request): Collection
    {
        $query = Group::where('active', true);

        if ($request->filled('department')) {
            $query->where('department_hemis_id', $request->department);
        }
        if ($request->filled('specialty')) {
            $query->where('specialty_hemis_id', $request->specialty);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $groups = $query->orderBy('name')
            ->get(['id', 'group_hemis_id', 'name', 'department_name', 'specialty_name']);

        $groupHemisIds = $groups->pluck('group_hemis_id')->filter()->values()->all();

        // Har guruhning starostasi
        $starostas = Student::whereIn('group_id', $groupHemisIds)
            ->where('is_starosta', true)
            ->get(['id', 'full_name', 'student_id_number', 'phone', 'group_id'])
            ->keyBy('group_id');

        // Faol talabalar soni + kurs (level_code guruh ichida bir xil bo'ladi)
        $agg = Student::whereIn('group_id', $groupHemisIds)
            ->where('student_status_code', 11)
            ->selectRaw('group_id, COUNT(*) as cnt, MAX(level_code) as lvl')
            ->groupBy('group_id')
            ->get()->keyBy('group_id');

        $rows = $groups->map(function ($g) use ($starostas, $agg) {
            $st = $starostas->get($g->group_hemis_id);
            $a = $agg->get($g->group_hemis_id);
            $course = ($a && $a->lvl !== null) ? ((int) $a->lvl % 10) : null;

            return (object) [
                'group' => $g->name,
                'department' => $g->department_name,
                'specialty' => $g->specialty_name,
                'course' => $course,
                'student_count' => $a->cnt ?? 0,
                'starosta' => $st?->full_name,
                'starosta_id_number' => $st?->student_id_number,
                'starosta_phone' => $st?->phone,
            ];
        });

        // Kurs filtri (level_code'dan olingan)
        if ($request->filled('course')) {
            $course = (int) $request->course;
            $rows = $rows->filter(fn ($r) => $r->course === $course);
        }

        // Holat filtri
        if ($request->filled('status')) {
            if ($request->status === 'assigned') {
                $rows = $rows->filter(fn ($r) => $r->starosta !== null);
            } elseif ($request->status === 'missing') {
                $rows = $rows->filter(fn ($r) => $r->starosta === null);
            }
        }

        return $rows->values();
    }
}
