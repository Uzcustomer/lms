<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HemisQuizResult;
use App\Models\QuizGradeAppeal;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Sistemaga adashib yuklangan test natijalarini o'quv prorektori (va superadmin)
 * asoslovchi hujjat yuklab tuzatishi (almashtirish / o'chirish) uchun.
 * Faqat KO'RSATISH emas — bevosita amal qiladi va har bir amal audit qilinadi.
 */
class QuizGradeAppealController extends Controller
{
    private const ALLOWED_ROLES = ['superadmin', 'oquv_prorektori'];

    /**
     * Faqat o'quv prorektori (yoki superadmin) — session active_role bo'yicha.
     * VedomostRejectionInboxController bilan bir xil uslub.
     */
    private function checkAccess(): void
    {
        $user = auth()->user() ?? auth()->guard('teacher')->user();
        if (!$user) {
            abort(403);
        }
        if (!in_array(session('active_role', ''), self::ALLOWED_ROLES, true)) {
            abort(403, "Bu bo'limni faqat o'quv prorektori ko'ra oladi.");
        }
    }

    /**
     * Joriy foydalanuvchi apelyatsiya qila oladimi (menyu/tugma ko'rsatish uchun).
     */
    public static function canAppeal(): bool
    {
        return in_array(session('active_role', ''), self::ALLOWED_ROLES, true);
    }

    private function routePrefix(): string
    {
        return auth()->guard('teacher')->check() ? 'teacher' : 'admin';
    }

    /**
     * Apelyatsiyalar tarixi.
     */
    public function index(Request $request)
    {
        $this->checkAccess();
        $routePrefix = $this->routePrefix();

        $appeals = Schema::hasTable('quiz_grade_appeals')
            ? QuizGradeAppeal::orderByDesc('created_at')->paginate(50)
            : null;

        return view('admin.quiz-grade-appeals.index', compact('appeals', 'routePrefix'));
    }

    /**
     * FISH / HEMIS ID / Talaba ID bo'yicha talabani qidirib, uning sistemaga
     * yuklangan (reason='quiz_result') barcha test baholarini qaytaradi —
     * prorektor to'g'ridan-to'g'ri shu ro'yxatdan apelyatsiya qila oladi.
     */
    public function searchGrades(Request $request)
    {
        $this->checkAccess();

        $request->validate(['q' => 'required|string|min:2']);
        $q = trim($request->q);

        $students = Student::where('full_name', 'LIKE', '%' . $q . '%')
            ->orWhere('student_id_number', 'LIKE', '%' . $q . '%')
            ->orWhere('hemis_id', 'LIKE', '%' . $q . '%')
            ->limit(20)
            ->get(['id', 'hemis_id', 'student_id_number', 'full_name', 'department_name', 'specialty_name', 'group_name']);

        if ($students->isEmpty()) {
            return response()->json(['success' => true, 'rows' => [], 'students_found' => 0]);
        }

        $studentLookup = [];
        $ids = [];
        foreach ($students as $s) {
            if ($s->hemis_id) {
                $studentLookup[$s->hemis_id] = $s;
                $ids[] = $s->hemis_id;
            }
            if ($s->student_id_number) {
                $studentLookup[$s->student_id_number] = $s;
                $ids[] = $s->student_id_number;
            }
        }

        $grades = StudentGrade::where('reason', 'quiz_result')
            ->whereNotNull('quiz_result_id')
            ->whereIn('student_hemis_id', array_unique($ids))
            ->orderByDesc('lesson_date')
            ->limit(500)
            ->get();

        $quizResults = HemisQuizResult::whereIn('id', $grades->pluck('quiz_result_id')->unique())
            ->get()
            ->keyBy('id');

        $rows = $grades->map(function ($g) use ($quizResults, $studentLookup) {
            $student = $studentLookup[$g->student_hemis_id] ?? null;
            $quiz = $quizResults[$g->quiz_result_id] ?? null;

            return [
                'id'           => $g->id,
                'student_hemis_id' => $g->student_hemis_id,
                'student_name' => $student->full_name ?? '-',
                'faculty'      => $student->department_name ?? '-',
                'direction'    => $student->specialty_name ?? '-',
                'group'        => $student->group_name ?? '-',
                'fan_name'     => $g->subject_name,
                'quiz_type'    => $quiz->quiz_type ?? '-',
                'shakl'        => $quiz->shakl ?? '-',
                'attempt_name' => $quiz->attempt_name ?? '-',
                'grade'        => $g->grade,
                'date'         => $quiz && $quiz->date_finish ? $quiz->date_finish->format('d.m.Y') : '-',
            ];
        })->values();

        return response()->json([
            'success'        => true,
            'rows'           => $rows,
            'students_found' => $students->count(),
        ]);
    }

    /**
     * Bahoni almashtirish yoki o'chirish — asoslovchi hujjat majburiy.
     */
    public function store(Request $request)
    {
        $this->checkAccess();

        $data = $request->validate([
            'student_grade_id' => 'required|integer|exists:student_grades,id',
            'action'           => 'required|in:replace,delete',
            'new_grade'        => 'required_if:action,replace|nullable|numeric|min:0|max:100',
            'reason'           => 'required|string|min:5|max:2000',
            'document'         => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png',
        ], [
            'new_grade.required_if' => 'Almashtirish uchun yangi baho kiritilishi shart.',
            'reason.required'       => 'Sabab (asoslash) kiritilishi shart.',
            'reason.min'            => 'Sabab kamida 5 belgidan iborat bo\'lsin.',
            'document.required'     => 'Asoslovchi hujjatni yuklash shart.',
            'document.mimes'        => 'Hujjat PDF, JPG yoki PNG bo\'lishi kerak.',
            'document.max'          => 'Hujjat hajmi 5MB dan oshmasin.',
        ]);

        $grade = StudentGrade::findOrFail($data['student_grade_id']);
        $user = auth()->user() ?? auth()->guard('teacher')->user();

        $file = $request->file('document');
        $path = $file->store('quiz-grade-appeals/' . $grade->id, 'public');

        $oldGrade = $grade->grade;
        $isReplace = $data['action'] === QuizGradeAppeal::ACTION_REPLACE;

        $appeal = QuizGradeAppeal::create([
            'student_grade_id'       => $grade->id,
            'quiz_result_id'         => $grade->quiz_result_id,
            'student_hemis_id'       => $grade->student_hemis_id,
            'student_name'           => optional($grade->student)->full_name,
            'subject_id'             => $grade->subject_id,
            'subject_name'           => $grade->subject_name,
            'action'                 => $data['action'],
            'old_grade'              => $oldGrade,
            'new_grade'              => $isReplace ? $data['new_grade'] : null,
            'reason'                 => $data['reason'],
            'document_path'          => $path,
            'document_original_name' => $file->getClientOriginalName(),
            'performed_by_guard'     => auth()->guard('teacher')->check() ? 'teacher' : 'web',
            'performed_by_id'        => $user?->id,
            'performed_by_name'      => $user->full_name ?? $user->name ?? null,
            'performed_by_role'      => session('active_role'),
        ]);

        // Bahoni qo'llash — Eloquent orqali (LogsActivity trait avtomatik audit qiladi)
        if ($isReplace) {
            $grade->grade = $data['new_grade'];
            $grade->save();
        } else {
            $grade->delete(); // soft delete
        }

        ActivityLogService::log(
            'update',
            'student_grade',
            $isReplace
                ? "Apelyatsiya (prorektor): test bahosi {$oldGrade} -> {$data['new_grade']} almashtirildi"
                : "Apelyatsiya (prorektor): test bahosi ({$oldGrade}) o'chirildi",
            $appeal
        );

        return response()->json([
            'success' => true,
            'message' => $isReplace ? 'Baho almashtirildi.' : 'Baho o\'chirildi.',
        ]);
    }

    /**
     * Asoslovchi hujjatni yuklab olish.
     */
    public function download($id)
    {
        $this->checkAccess();

        $appeal = QuizGradeAppeal::findOrFail($id);
        if (!$appeal->document_path || !Storage::disk('public')->exists($appeal->document_path)) {
            abort(404, 'Fayl topilmadi.');
        }

        return Storage::disk('public')->download($appeal->document_path, $appeal->document_original_name);
    }
}
