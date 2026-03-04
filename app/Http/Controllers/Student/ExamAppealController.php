<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\ExamAppeal;
use App\Models\ExamAppealComment;
use App\Models\StudentGrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class ExamAppealController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user();

        $appeals = collect();
        if (Schema::hasTable('exam_appeals')) {
            $appeals = ExamAppeal::where('student_id', $student->id)
                ->orderByDesc('created_at')
                ->paginate(15);
        }

        return view('student.appeals.index', compact('appeals'));
    }

    public function create()
    {
        $student = Auth::guard('student')->user();
        $since = Carbon::now()->subDay();

        $curriculum = Curriculum::where('curricula_hemis_id', $student->curriculum_id)->first();
        $educationYearCode = $curriculum?->education_year_code;

        // Faqat OSKI (101), ON (100), Test (102), YN test larni ko'rsatish
        $grades = StudentGrade::where('student_id', $student->id)
            ->where('status', 'recorded')
            ->whereNotNull('grade')
            ->whereIn('training_type_code', [100, 101, 102])
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhereNull('education_year_code');
            }))
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($g) use ($since) {
                $gradeTime = $g->created_at_api ?? $g->updated_at ?? $g->created_at;
                $canAppeal = $gradeTime && Carbon::parse($gradeTime)->gte($since);

                return [
                    'id' => $g->id,
                    'subject_name' => $g->subject_name,
                    'training_type_code' => $g->training_type_code,
                    'training_type_name' => $g->training_type_name,
                    'grade' => $g->grade,
                    'employee_name' => $g->employee_name,
                    'lesson_date' => $g->lesson_date ? Carbon::parse($g->lesson_date)->format('d.m.Y') : null,
                    'graded_at' => $gradeTime ? Carbon::parse($gradeTime)->format('d.m.Y H:i') : null,
                    'can_appeal' => $canAppeal,
                ];
            });

        return view('student.appeals.create', compact('grades'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_grade_id' => ['required', 'exists:student_grades,id'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'file' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'student_grade_id.required' => 'Bahoni tanlang.',
            'reason.required' => 'Apellyatsiya sababini yozing.',
            'reason.min' => 'Sabab kamida 20 ta belgidan iborat bo\'lishi kerak.',
            'reason.max' => 'Sabab 2000 ta belgidan oshmasligi kerak.',
            'file.max' => 'Fayl hajmi 5MB dan oshmasligi kerak.',
            'file.mimes' => 'Faqat PDF, JPG, PNG formatlar qabul qilinadi.',
        ]);

        $student = Auth::guard('student')->user();
        $since = Carbon::now()->subDay();
        $grade = StudentGrade::where('id', $request->student_grade_id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        // 24 soat tekshiruvi
        $gradeTime = $grade->created_at_api ?? $grade->updated_at ?? $grade->created_at;
        if (!$gradeTime || !Carbon::parse($gradeTime)->gte($since)) {
            return back()->withErrors([
                'student_grade_id' => 'Bu bahoga apellyatsiya berish muddati tugagan. Baho qo\'yilganidan 24 soat ichida apellyatsiya topshirish mumkin.',
            ])->withInput();
        }

        // Bir xil baho uchun takroriy apellyatsiya tekshiruvi
        $existing = ExamAppeal::where('student_id', $student->id)
            ->where('student_grade_id', $grade->id)
            ->whereIn('status', ['pending', 'reviewing'])
            ->first();

        if ($existing) {
            return back()->withErrors(['student_grade_id' => 'Bu baho uchun allaqachon apellyatsiya topshirgansiz.'])->withInput();
        }

        $filePath = null;
        $fileOriginalName = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileOriginalName = $file->getClientOriginalName();
            $filePath = $file->store('appeals/' . $student->id, 'public');
        }

        $appeal = ExamAppeal::create([
            'student_id' => $student->id,
            'student_grade_id' => $grade->id,
            'subject_name' => $grade->subject_name,
            'subject_id' => $grade->subject_id,
            'training_type_code' => $grade->training_type_code,
            'training_type_name' => $grade->training_type_name,
            'current_grade' => $grade->grade,
            'employee_name' => $grade->employee_name,
            'exam_date' => $grade->lesson_date,
            'reason' => $request->reason,
            'file_path' => $filePath,
            'file_original_name' => $fileOriginalName,
        ]);

        return redirect()->route('student.appeals.show', $appeal->id)
            ->with('success', 'Apellyatsiya muvaffaqiyatli topshirildi!');
    }

    public function show($id)
    {
        $student = Auth::guard('student')->user();
        $eagerLoad = Schema::hasTable('exam_appeal_comments') ? ['comments'] : [];
        $appeal = ExamAppeal::with($eagerLoad)
            ->where('id', $id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        return view('student.appeals.show', compact('appeal'));
    }

    public function addComment(Request $request, $id)
    {
        $request->validate([
            'comment' => ['required', 'string', 'min:3', 'max:1000'],
            'file' => ['nullable', 'file', 'max:2048', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip'],
        ], [
            'comment.required' => 'Izoh yozing.',
            'comment.min' => 'Izoh kamida 3 ta belgidan iborat bo\'lishi kerak.',
        ]);

        $student = Auth::guard('student')->user();
        $appeal = ExamAppeal::where('id', $id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $data = [
            'exam_appeal_id' => $appeal->id,
            'user_type' => 'student',
            'user_id' => $student->id,
            'user_name' => $student->full_name ?? 'Talaba',
            'comment' => $request->comment,
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $data['file_path'] = $file->store('appeal-comments', 'public');
            $data['file_original_name'] = $file->getClientOriginalName();
        }

        ExamAppealComment::create($data);

        return redirect()->route('student.appeals.show', $appeal->id)
            ->with('success', 'Izoh qo\'shildi.');
    }

    public function download($id)
    {
        $student = Auth::guard('student')->user();
        $appeal = ExamAppeal::where('id', $id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        if (!$appeal->file_path || !Storage::disk('public')->exists($appeal->file_path)) {
            abort(404, 'Fayl topilmadi.');
        }

        return Storage::disk('public')->download($appeal->file_path, $appeal->file_original_name);
    }
}
