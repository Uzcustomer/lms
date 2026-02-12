<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HemisQuizResult;
use App\Imports\QuizResultImport;
use App\Exports\QuizResultExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class QuizResultController extends Controller
{
    public function index(Request $request)
    {
        $query = HemisQuizResult::where('is_active', 1);

        if ($request->filled('faculty')) {
            $query->where('faculty', $request->faculty);
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        if ($request->filled('fan_name')) {
            $query->where('fan_name', 'LIKE', '%' . $request->fan_name . '%');
        }

        if ($request->filled('student_name')) {
            $query->where('student_name', 'LIKE', '%' . $request->student_name . '%');
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('quiz_type')) {
            $query->where('quiz_type', $request->quiz_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date_finish', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date_finish', '<=', $request->date_to);
        }

        $perPage = $request->input('per_page', 50);
        $results = $query->orderByDesc('date_finish')->paginate($perPage)->withQueryString();

        // Filtrlar uchun unique qiymatlarni olish
        $faculties = HemisQuizResult::where('is_active', 1)
            ->whereNotNull('faculty')
            ->distinct()
            ->pluck('faculty')
            ->sort();

        $semesters = HemisQuizResult::where('is_active', 1)
            ->whereNotNull('semester')
            ->distinct()
            ->pluck('semester')
            ->sort();

        $quizTypes = HemisQuizResult::where('is_active', 1)
            ->whereNotNull('quiz_type')
            ->distinct()
            ->pluck('quiz_type')
            ->sort();

        return view('admin.quiz_results.index', compact(
            'results', 'faculties', 'semesters', 'quizTypes'
        ));
    }

    public function exportExcel(Request $request)
    {
        return (new QuizResultExport($request))->export();
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        $import = new QuizResultImport();
        Excel::import($import, $request->file('file'));

        $successCount = $import->successCount;
        $errors = $import->errors;

        if (count($errors) > 0) {
            return redirect()->route('admin.quiz-results.index')
                ->with('success', "$successCount ta natija student_grades ga muvaffaqiyatli yuklandi.")
                ->with('import_errors', $errors)
                ->with('error_count', count($errors));
        }

        return redirect()->route('admin.quiz-results.index')
            ->with('success', "$successCount ta natija student_grades ga muvaffaqiyatli yuklandi.");
    }
}
