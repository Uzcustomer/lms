<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retake\SubmitRetakeApplicationRequest;
use App\Models\RetakeApplication;
use App\Services\Retake\RetakeApplicationService;
use App\Services\Retake\RetakePeriodService;
use App\Services\Retake\StudentDebtService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RetakeController extends Controller
{
    public function __construct(
        private readonly StudentDebtService $debtService,
        private readonly RetakePeriodService $periodService,
        private readonly RetakeApplicationService $applicationService,
    ) {
    }

    /**
     * Talaba qayta o'qish sahifasi: qarzdor fanlar + ariza yuborish formasi.
     */
    public function index(): View
    {
        $student = Auth::guard('student')->user();
        abort_unless($student !== null, 403);

        $debts = $this->debtService->getDebtSubjects($student);

        $activePeriod = $this->periodService->findActiveForStudent($student);
        $latestPeriod = $activePeriod ?? $this->periodService->findLatestForStudent($student);

        $applications = RetakeApplication::query()
            ->where('student_id', $student->id)
            ->with(['retakeGroup.teacher'])
            ->orderByDesc('submitted_at')
            ->limit(50)
            ->get();

        return view('student.retake.index', [
            'student' => $student,
            'debts' => $debts,
            'activePeriod' => $activePeriod,
            'latestPeriod' => $latestPeriod,
            'applications' => $applications,
            'maxSubjects' => RetakeApplicationService::MAX_SUBJECTS,
        ]);
    }

    /**
     * Yangi ariza yuborish (web form).
     */
    public function store(SubmitRetakeApplicationRequest $request): RedirectResponse
    {
        $student = Auth::guard('student')->user();
        abort_unless($student !== null, 403);

        try {
            $applications = $this->applicationService->submit(
                $student,
                $request->input('subjects'),
                $request->file('receipt'),
                $request->input('student_note'),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('student.retake.index')
            ->with('success', "Ariza muvaffaqiyatli yuborildi. {$applications->count()} ta fan bo'yicha.");
    }

    /**
     * Avto-generatsiya qilingan ariza DOCX (8-bosqichda implementatsiya).
     */
    public function downloadDocument(int $id): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $student = Auth::guard('student')->user();
        $application = RetakeApplication::where('student_id', $student->id)->findOrFail($id);

        if ($application->generated_doc_path === null
            || ! Storage::disk('local')->exists($application->generated_doc_path)) {
            return back()->with('error', 'Ariza hujjati hali generatsiya qilinmagan.');
        }

        return Storage::disk('local')->download(
            $application->generated_doc_path,
            "ariza-{$application->application_group_id}.docx",
        );
    }

    /**
     * Tasdiqnoma PDF (8-bosqichda generatsiya qilinadi).
     */
    public function downloadTasdiqnoma(int $id): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $student = Auth::guard('student')->user();
        $application = RetakeApplication::where('student_id', $student->id)->findOrFail($id);

        if ($application->tasdiqnoma_pdf_path === null
            || ! Storage::disk('local')->exists($application->tasdiqnoma_pdf_path)) {
            return back()->with('error', 'Tasdiqnoma hali generatsiya qilinmagan.');
        }

        return Storage::disk('local')->download(
            $application->tasdiqnoma_pdf_path,
            "tasdiqnoma-{$application->id}.pdf",
        );
    }
}
