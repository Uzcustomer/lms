<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbsenceExcuse;
use App\Models\Group;
use App\Models\Subject;
use App\Models\Teacher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Yakuniy qilingan shakldan keyin kelgan tuzatishlar (sababli ma'lumotnomalar) —
 * "tuzatish dalolatnomasi" sifatida ro'yxat va PDF.
 */
class YnFormCorrectionController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('yn_form_corrections')) {
            return back()->with('error', 'yn_form_corrections jadvali yo\'q. Migratsiya kerak.');
        }

        $query = DB::table('yn_form_corrections as c')
            ->leftJoin('students as s', 's.hemis_id', '=', 'c.student_hemis_id')
            ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'c.group_hemis_id')
            ->leftJoin('curriculum_subjects as cs', function ($j) {
                $j->on('cs.subject_id', '=', 'c.subject_id')
                  ->on('cs.semester_code', '=', 'c.semester_code');
            })
            ->leftJoin('absence_excuses as ae', 'ae.id', '=', 'c.absence_excuse_id')
            ->select([
                'c.*',
                's.full_name as student_name',
                'g.name as group_name',
                'cs.subject_name as subject_name',
                'ae.start_date as excuse_start_date',
                'ae.end_date as excuse_end_date',
                'ae.reason as excuse_reason',
                'ae.doc_number as excuse_doc_number',
            ])
            ->orderByDesc('c.performed_at');

        // Filtrlar
        if ($request->filled('correction_type')) {
            $query->where('c.correction_type', $request->correction_type);
        }
        if ($request->filled('attempt')) {
            $query->where('c.attempt', $request->attempt);
        }
        if ($request->filled('subject_id')) {
            $query->where('c.subject_id', $request->subject_id);
        }
        if ($request->filled('group_hemis_id')) {
            $query->where('c.group_hemis_id', $request->group_hemis_id);
        }
        if ($request->filled('student_hemis_id')) {
            $query->where('c.student_hemis_id', $request->student_hemis_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('s.full_name', 'like', "%{$search}%")
                  ->orWhere('c.student_hemis_id', 'like', "%{$search}%")
                  ->orWhere('cs.subject_name', 'like', "%{$search}%")
                  ->orWhere('g.name', 'like', "%{$search}%");
            });
        }

        $corrections = $query->paginate(30)->withQueryString();

        $stats = [
            'late_sababli' => DB::table('yn_form_corrections')->where('correction_type', 'late_sababli')->count(),
            'finalized'    => DB::table('yn_form_corrections')->where('correction_type', 'finalized')->count(),
            'total'        => DB::table('yn_form_corrections')->count(),
        ];

        return view('admin.yn-form-corrections.index', compact('corrections', 'stats'));
    }

    /**
     * Tuzatish dalolatnomasi PDF ko'rinishida (faqat late_sababli uchun).
     */
    public function pdf($id)
    {
        if (!Schema::hasTable('yn_form_corrections')) {
            abort(404);
        }

        $correction = DB::table('yn_form_corrections as c')
            ->leftJoin('students as s', 's.hemis_id', '=', 'c.student_hemis_id')
            ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'c.group_hemis_id')
            ->leftJoin('curriculum_subjects as cs', function ($j) {
                $j->on('cs.subject_id', '=', 'c.subject_id')
                  ->on('cs.semester_code', '=', 'c.semester_code');
            })
            ->leftJoin('absence_excuses as ae', 'ae.id', '=', 'c.absence_excuse_id')
            ->where('c.id', $id)
            ->select([
                'c.*',
                's.full_name as student_name',
                's.short_name as student_short_name',
                'g.name as group_name',
                'cs.subject_name as subject_name',
                'cs.subject_code as subject_code',
                'ae.start_date as excuse_start_date',
                'ae.end_date as excuse_end_date',
                'ae.reason as excuse_reason',
                'ae.doc_number as excuse_doc_number',
                'ae.reviewed_at as excuse_reviewed_at',
                'ae.reviewed_by_name as excuse_reviewed_by_name',
            ])
            ->first();

        if (!$correction) {
            abort(404, 'Tuzatish topilmadi');
        }

        if ($correction->correction_type !== 'late_sababli') {
            return back()->with('error', 'Faqat kechikkan sababli (late_sababli) tuzatishlar uchun PDF mavjud.');
        }

        // Asl baholar (sababli arizadan oldin saqlangan)
        $originalGrades = [];
        if ($correction->student_hemis_id && $correction->subject_id) {
            $originalGrades = DB::table('student_grades')
                ->where('student_hemis_id', $correction->student_hemis_id)
                ->where('subject_id', $correction->subject_id)
                ->where('semester_code', $correction->semester_code)
                ->whereIn('training_type_code', [101, 102]) // OSKI / Test
                ->whereNull('deleted_at')
                ->select('training_type_code', 'training_type_name', 'grade', 'retake_grade', 'retake_was_sababli', 'lesson_date')
                ->get();
        }

        $formName = match ((int) ($correction->attempt ?? 1)) {
            2 => '12a-shakl',
            3 => '12b-shakl',
            default => '12-shakl',
        };

        $pdf = Pdf::loadView('pdf.yn-tuzatish-dalolatnomasi', [
            'correction' => $correction,
            'originalGrades' => $originalGrades,
            'formName' => $formName,
            'generatedAt' => now(),
        ]);

        $fileName = 'tuzatish-dalolatnomasi-' . $correction->id . '.pdf';
        return $pdf->stream($fileName);
    }
}
