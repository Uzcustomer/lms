<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use App\Models\RetakeGroup;
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
     * Talabaning o'zi a'zo bo'lgan retake guruhlari ro'yxati.
     */
    public function index()
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $groupIds = RetakeApplication::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
            ->pluck('retake_group_id')
            ->unique();

        $groups = RetakeGroup::query()
            ->with('teacher')
            ->whereIn('id', $groupIds)
            ->orderByDesc('start_date')
            ->get();

        return view('student.retake-journal.index', [
            'groups' => $groups,
        ]);
    }

    /**
     * Talaba uchun bitta guruhdagi jurnal — faqat o'qish.
     */
    public function show(int $groupId)
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $group = RetakeGroup::with('teacher')->findOrFail($groupId);

        // Talaba shu guruhda bo'lsa-yo'qmi
        $myApp = RetakeApplication::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('retake_group_id', $group->id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->first();
        if (!$myApp) {
            abort(403, 'Siz bu guruhga biriktirilmagansiz');
        }

        // Mustaqil ta'lim submission
        $mustaqil = \App\Models\RetakeMustaqilSubmission::query()
            ->where('retake_group_id', $group->id)
            ->where('application_id', $myApp->id)
            ->first();

        return view('student.retake-journal.show', [
            'group' => $group,
            'application' => $myApp,
            'mustaqil' => $mustaqil,
            'isEditable' => $this->service->isEditable($group),
        ]);
    }

    /**
     * Mustaqil ta'lim faylini yuklash.
     */
    public function uploadMustaqil(Request $request, int $groupId)
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

        $group = RetakeGroup::findOrFail($groupId);

        try {
            $this->service->submitMustaqil(
                $group,
                $student,
                $request->file('file'),
                $request->input('comment'),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[RetakeJournal] Mustaqil upload failed', [
                'group_id' => $groupId,
                'student_hemis_id' => $student->hemis_id,
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return redirect()->back()->withErrors([
                'upload' => 'Yuklashda kutilmagan xato: ' . $e->getMessage(),
            ]);
        }

        return redirect()->route('student.retake-journal.show', $groupId)
            ->with('success', __('Mustaqil ta\'lim fayli yuklandi'));
    }

    /**
     * O'z faylni yuklab olish.
     */
    public function downloadMustaqil(int $groupId)
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $group = RetakeGroup::findOrFail($groupId);

        $myApp = RetakeApplication::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('retake_group_id', $group->id)
            ->first();
        if (!$myApp) abort(403);

        $submission = \App\Models\RetakeMustaqilSubmission::query()
            ->where('retake_group_id', $group->id)
            ->where('application_id', $myApp->id)
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
