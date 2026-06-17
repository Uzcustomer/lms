<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use App\Models\Student;
use App\Services\Retake\RetakeJournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RetakeJournalController extends Controller
{
    public function __construct(
        private RetakeJournalService $service,
    ) {}

    /**
     * Talabaning JURNAL kartochkalari ro'yxati.
     * Har bir ariza = alohida kartochka (talaba bitta guruhda 2+ semestrdan
     * arizasi bo'lsa, har biri uchun alohida jurnal ko'rinadi).
     */
    public function index()
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $applications = RetakeApplication::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
            ->with(['retakeGroup.teacher'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($a) => $a->retakeGroup !== null)
            ->values();

        return view('student.retake-journal.index', [
            'applications' => $applications,
        ]);
    }

    /**
     * Talaba uchun bitta ARIZA jurnalini ko'rish (faqat o'qish + mustaqil yuklash).
     */
    public function show(int $applicationId)
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $app = RetakeApplication::query()
            ->where('id', $applicationId)
            ->where('student_hemis_id', $student->hemis_id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
            ->with(['retakeGroup.teacher'])
            ->first();
        if (!$app || !$app->retakeGroup) {
            abort(403, 'Bu jurnal sizga tegishli emas yoki guruh topilmadi');
        }

        $group = $app->retakeGroup;

        $mustaqil = \App\Models\RetakeMustaqilSubmission::query()
            ->where('retake_group_id', $group->id)
            ->where('application_id', $app->id)
            ->first();

        return view('student.retake-journal.show', [
            'group' => $group,
            'application' => $app,
            'student' => $student,
            'mustaqil' => $mustaqil,
            'isEditable' => $this->service->isEditable($group),
        ]);
    }

    /**
     * Mustaqil ta'lim faylini yuklash — aniq ariza uchun.
     */
    public function uploadMustaqil(Request $request, int $applicationId)
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();
        if (!$student) {
            return redirect()->route('student.login')->withErrors(['auth' => 'Avtorizatsiya talab qilinadi']);
        }
        if (!$student->hemis_id) {
            return redirect()->back()->withErrors(['student' => 'Talaba ma\'lumotlari to\'liq emas (HEMIS ID yo\'q)']);
        }

        try {
            $request->validate([
                'file' => 'required|file|max:' . (\App\Models\RetakeMustaqilSubmission::MAX_FILE_MB * 1024) . '|mimes:pdf,doc,docx,jpg,jpeg,png,zip,rar',
                'comment' => 'nullable|string|max:1000',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        $app = RetakeApplication::query()
            ->where('id', $applicationId)
            ->where('student_hemis_id', $student->hemis_id)
            ->whereNotNull('retake_group_id')
            ->with('retakeGroup')
            ->first();
        if (!$app || !$app->retakeGroup) {
            abort(403);
        }

        try {
            $this->service->submitMustaqil(
                $app->retakeGroup,
                $student,
                $request->file('file'),
                $request->input('comment'),
                $app->id,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[RetakeJournal] Mustaqil upload failed', [
                'application_id' => $applicationId,
                'student_hemis_id' => $student->hemis_id,
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return redirect()->back()->withErrors([
                'upload' => 'Yuklashda kutilmagan xato: ' . $e->getMessage(),
            ]);
        }

        return redirect()->route('student.retake-journal.show', $applicationId)
            ->with('success', __('Mustaqil ta\'lim fayli yuklandi'));
    }

    /**
     * O'z faylini yuklab olish — aniq ariza uchun.
     */
    public function downloadMustaqil(int $applicationId)
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $app = RetakeApplication::query()
            ->where('id', $applicationId)
            ->where('student_hemis_id', $student->hemis_id)
            ->whereNotNull('retake_group_id')
            ->first();
        if (!$app) abort(403);

        $submission = \App\Models\RetakeMustaqilSubmission::query()
            ->where('retake_group_id', $app->retake_group_id)
            ->where('application_id', $app->id)
            ->firstOrFail();

        if (!$submission->file_path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($submission->file_path)) {
            abort(404);
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->download(
            $submission->file_path,
            $submission->original_filename ?: 'mustaqil.pdf'
        );
    }
}
