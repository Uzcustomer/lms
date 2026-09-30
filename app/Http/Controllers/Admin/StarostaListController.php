<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StarostaListExport;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Starostalar ro'yxati (registrator ofisi + admin).
 *
 * Talabalar tabining aynan filtri/uslubi, lekin faqat starosta qilib
 * belgilangan talabalar (students.is_starosta). Ta'lim turi standart — bakalavr.
 */
class StarostaListController extends Controller
{
    /** Bakalavr ta'lim turi kodi (standart filtr) */
    private const BAKALAVR_CODE = '11';

    public function index(Request $request)
    {
        $students = $this->buildQuery($request)
            ->paginate((int) $request->get('per_page', 50))
            ->appends($request->query());

        $educationTypes = Student::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->distinct()
            ->orderBy('education_type_name')
            ->get();

        // Ko'rinishda tanlangan ta'lim turi (standart — bakalavr)
        $selectedEducationType = $request->input('education_type', self::BAKALAVR_CODE);

        return view('admin.starosta-list.index', compact('students', 'educationTypes', 'selectedEducationType'));
    }

    public function export(Request $request)
    {
        $students = $this->buildQuery($request)->get();
        $fileName = 'starostalar_'.date('Y_m_d_H_i').'.xlsx';

        return Excel::download(new StarostaListExport($students), $fileName);
    }

    /**
     * Faqat starostalar (is_starosta) bo'yicha filtrlangan so'rov.
     */
    private function buildQuery(Request $request)
    {
        $query = Student::query()
            ->where('is_starosta', true)
            ->where('student_status_code', 11); // faqat faol talabalar

        // Ta'lim turi — standart bakalavr; "Barchasi" tanlanса bo'sh qiymat keladi
        $educationType = $request->input('education_type', self::BAKALAVR_CODE);
        if ($educationType !== '' && $educationType !== null) {
            $query->where('education_type_code', $educationType);
        }

        if ($request->filled('department')) {
            $query->where('department_id', $request->department);
        }
        if ($request->filled('specialty')) {
            $query->where('specialty_id', $request->specialty);
        }
        if ($request->filled('level_code')) {
            $query->where('level_code', $request->level_code);
        }
        if ($request->filled('group')) {
            $query->where('group_id', $request->group);
        }

        return $query->orderBy('group_name')->orderBy('full_name');
    }
}
