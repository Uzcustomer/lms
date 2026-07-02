<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuizGradeAppeal;
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
