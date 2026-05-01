<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\ExamAppeal;
use App\Models\ExamAppealComment;
use App\Models\StudentGrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ExamAppealApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        if (!Schema::hasTable('exam_appeals')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $appeals = ExamAppeal::where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => $this->formatAppealSummary($a));

        return response()->json([
            'success' => true,
            'data' => $appeals,
        ]);
    }

    public function availableGrades(Request $request): JsonResponse
    {
        $student = $request->user();
        $since = Carbon::now()->subDay();

        $curriculum = Curriculum::where('curricula_hemis_id', $student->curriculum_id)->first();
        $educationYearCode = $curriculum?->education_year_code;

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

                $hoursLeft = null;
                if ($gradeTime) {
                    $deadline = Carbon::parse($gradeTime)->addDay();
                    $hoursLeft = $deadline->isFuture() ? $deadline->diffInHours(Carbon::now()) : 0;
                }

                return [
                    'id' => $g->id,
                    'subject_name' => $g->subject_name,
                    'training_type_code' => $g->training_type_code,
                    'training_type_name' => $g->training_type_name,
                    'grade' => (float) $g->grade,
                    'employee_name' => $g->employee_name,
                    'lesson_date' => $g->lesson_date ? Carbon::parse($g->lesson_date)->format('d.m.Y') : null,
                    'graded_at' => $gradeTime ? Carbon::parse($gradeTime)->format('d.m.Y H:i') : null,
                    'can_appeal' => $canAppeal,
                    'hours_left' => $hoursLeft,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $grades,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'student_grade_id' => ['required', 'exists:student_grades,id'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'file' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'student_grade_id.required' => 'Bahoni tanlang.',
            'reason.required' => 'Apellyatsiya sababini yozing.',
            'reason.min' => "Sabab kamida 20 ta belgidan iborat bo'lishi kerak.",
            'reason.max' => "Sabab 2000 ta belgidan oshmasligi kerak.",
            'file.max' => "Fayl hajmi 5MB dan oshmasligi kerak.",
            'file.mimes' => 'Faqat PDF, JPG, PNG formatlar qabul qilinadi.',
        ]);

        $student = $request->user();
        $since = Carbon::now()->subDay();
        $grade = StudentGrade::where('id', $request->student_grade_id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $gradeTime = $grade->created_at_api ?? $grade->updated_at ?? $grade->created_at;
        if (!$gradeTime || !Carbon::parse($gradeTime)->gte($since)) {
            return response()->json([
                'success' => false,
                'message' => "Bu bahoga apellyatsiya berish muddati tugagan. Baho qo'yilganidan 24 soat ichida apellyatsiya topshirish mumkin.",
            ], 422);
        }

        $existing = ExamAppeal::where('student_id', $student->id)
            ->where('student_grade_id', $grade->id)
            ->whereIn('status', ['pending', 'reviewing'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Bu baho uchun allaqachon apellyatsiya topshirgansiz.',
            ], 422);
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

        return response()->json([
            'success' => true,
            'message' => 'Apellyatsiya muvaffaqiyatli topshirildi!',
            'data' => $this->formatAppealDetail($appeal->fresh()),
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $student = $request->user();
        $eagerLoad = Schema::hasTable('exam_appeal_comments') ? ['comments'] : [];

        $appeal = ExamAppeal::with($eagerLoad)
            ->where('id', $id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $this->formatAppealDetail($appeal),
        ]);
    }

    public function addComment(Request $request, $id): JsonResponse
    {
        $request->validate([
            'comment' => ['required', 'string', 'min:3', 'max:1000'],
            'file' => ['nullable', 'file', 'max:2048', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip'],
        ], [
            'comment.required' => 'Izoh yozing.',
            'comment.min' => "Izoh kamida 3 ta belgidan iborat bo'lishi kerak.",
        ]);

        $student = $request->user();
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

        $comment = ExamAppealComment::create($data);

        return response()->json([
            'success' => true,
            'message' => "Izoh qo'shildi.",
            'data' => $this->formatComment($comment),
        ]);
    }

    public function download(Request $request, $id)
    {
        $student = $request->user();
        $appeal = ExamAppeal::where('id', $id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        if (!$appeal->file_path || !Storage::disk('public')->exists($appeal->file_path)) {
            abort(404, 'Fayl topilmadi.');
        }

        return Storage::disk('public')->download($appeal->file_path, $appeal->file_original_name);
    }

    private function formatAppealSummary(ExamAppeal $appeal): array
    {
        return [
            'id' => $appeal->id,
            'subject_name' => $appeal->subject_name,
            'training_type_name' => $appeal->training_type_name,
            'training_type_code' => $appeal->training_type_code,
            'current_grade' => (float) $appeal->current_grade,
            'new_grade' => $appeal->new_grade !== null ? (float) $appeal->new_grade : null,
            'employee_name' => $appeal->employee_name,
            'status' => $appeal->status,
            'status_label' => ExamAppeal::STATUSES[$appeal->status] ?? $appeal->status,
            'exam_date' => $appeal->exam_date ? Carbon::parse($appeal->exam_date)->format('d.m.Y') : null,
            'created_at' => $appeal->created_at?->format('d.m.Y H:i'),
        ];
    }

    private function formatAppealDetail(ExamAppeal $appeal): array
    {
        $base = $this->formatAppealSummary($appeal);
        $base['reason'] = $appeal->reason;
        $base['file_original_name'] = $appeal->file_original_name;
        $base['has_file'] = !empty($appeal->file_path);
        $base['review_comment'] = $appeal->review_comment;
        $base['reviewed_by_name'] = $appeal->reviewed_by_name;
        $base['reviewed_at'] = $appeal->reviewed_at ? Carbon::parse($appeal->reviewed_at)->format('d.m.Y H:i') : null;

        $comments = [];
        if (Schema::hasTable('exam_appeal_comments') && $appeal->relationLoaded('comments')) {
            $comments = $appeal->comments
                ->sortBy('created_at')
                ->values()
                ->map(fn($c) => $this->formatComment($c))
                ->toArray();
        }
        $base['comments'] = $comments;

        return $base;
    }

    private function formatComment(ExamAppealComment $comment): array
    {
        return [
            'id' => $comment->id,
            'user_type' => $comment->user_type,
            'user_name' => $comment->user_name,
            'comment' => $comment->comment,
            'file_original_name' => $comment->file_original_name,
            'has_file' => !empty($comment->file_path),
            'created_at' => $comment->created_at?->format('d.m.Y H:i'),
        ];
    }
}
