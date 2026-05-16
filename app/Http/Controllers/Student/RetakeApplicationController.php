<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicRecord;
use App\Models\RetakeApplication;
use App\Models\RetakeApplicationGroup;
use App\Models\RetakeSetting;
use App\Models\Student;
use App\Services\Retake\RetakeApplicationService;
use App\Services\Retake\RetakeDebtService;
use App\Services\Retake\RetakeWindowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class RetakeApplicationController extends Controller
{
    public function __construct(
        private RetakeApplicationService $applicationService,
        private RetakeDebtService $debtService,
        private RetakeWindowService $windowService,
    ) {}

    public function index()
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $window = $this->windowService->activeWindowForStudent($student);

        // Aktiv arizalar (subject_id|semester_id => application)
        $activeApplications = RetakeApplication::query()
            ->forStudent((int) $student->hemis_id)
            ->whereIn('final_status', [
                RetakeApplication::STATUS_PENDING,
                RetakeApplication::STATUS_APPROVED,
            ])
            ->with(['retakeGroup.teacher'])
            ->get()
            ->keyBy(fn (RetakeApplication $a) => $a->subject_id . '|' . $a->semester_id);

        // Qarzdorliklar
        $debts = $this->debtService->debts($student);

        // Tarix (eski + joriy oynalar bo'yicha guruhlar)
        $history = RetakeApplicationGroup::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->with(['applications.retakeGroup.teacher', 'window.session'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $remainingSlots = $this->applicationService->remainingSlots((int) $student->hemis_id, $window?->id);
        $creditPrice = RetakeSetting::creditPrice();
        $receiptMaxMb = RetakeSetting::receiptMaxMb();

        // To'lov yuklash kerak bo'lgan guruhlar (dekan + registrator tasdiqlagan,
        // hali to'lov yuklanmagan yoki rejected bo'lib qayta yuklash kerak).
        $groupsAwaitingPayment = $history->filter(function (RetakeApplicationGroup $g) {
            return $g->requires_payment;
        })->values();

        // To'lov yuklangan, ammo registrator tasdiqi kutilmoqda.
        $groupsPaymentVerifying = $history->filter(function (RetakeApplicationGroup $g) {
            return $g->payment_awaiting_verification;
        })->values();

        return view('student.retake.index', [
            'student' => $student,
            'window' => $window,
            'debts' => $debts,
            'activeApplications' => $activeApplications,
            'history' => $history,
            'remainingSlots' => $remainingSlots,
            'creditPrice' => $creditPrice,
            'receiptMaxMb' => $receiptMaxMb,
            'maxSubjectsPerApplication' => RetakeApplicationService::MAX_SUBJECTS_PER_APPLICATION,
            'groupsAwaitingPayment' => $groupsAwaitingPayment,
            'groupsPaymentVerifying' => $groupsPaymentVerifying,
            'paymentMaxMb' => RetakeApplicationService::PAYMENT_RECEIPT_MAX_MB,
        ]);
    }

    /**
     * Talaba to'lov chekini yuklaydi (dekan + registrator tasdiqidan keyin).
     */
    public function uploadPayment(Request $request, int $groupId)
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $maxMb = RetakeApplicationService::PAYMENT_RECEIPT_MAX_MB;

        $request->validate([
            'payment' => "required|file|mimes:pdf,jpg,jpeg,png|max:" . ($maxMb * 1024),
        ]);

        $group = RetakeApplicationGroup::findOrFail($groupId);

        if ((int) $group->student_hemis_id !== (int) $student->hemis_id) {
            abort(403);
        }

        try {
            $this->applicationService->uploadPayment(
                $student,
                $group,
                $request->file('payment'),
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->route('student.retake.index')
            ->with('success', "Arizangiz o'quv bo'limiga yuborildi");
    }

    public function store(Request $request)
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $maxMb = RetakeSetting::receiptMaxMb();

        $data = $request->validate([
            'subjects' => 'required|array|min:1|max:' . RetakeApplicationService::MAX_SUBJECTS_PER_APPLICATION,
            'subjects.*.subject_id' => 'required|string',
            'subjects.*.semester_id' => 'required|string',
            'receipt' => "required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:" . ($maxMb * 1024),
            'comment' => 'nullable|string|max:' . RetakeApplicationService::MAX_COMMENT_LENGTH,
        ]);

        try {
            $group = $this->applicationService->submit(
                $student,
                $data['subjects'],
                $request->file('receipt'),
                $data['comment'] ?? null,
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('student.retake.index')
            ->with('success', 'Ariza muvaffaqiyatli yuborildi. Tasdiqlanishini kuting.');
    }

    /**
     * Tasdiqlangan arizaning DOCX faylini yuklab olish.
     */
    public function downloadDocx(int $groupId): Response
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $group = RetakeApplicationGroup::findOrFail($groupId);

        if ((int) $group->student_hemis_id !== (int) $student->hemis_id) {
            abort(403);
        }

        if (!$group->docx_path || !Storage::disk('public')->exists($group->docx_path)) {
            abort(404, 'DOCX fayl hali generatsiya qilinmagan');
        }

        return Storage::disk('public')->download(
            $group->docx_path,
            "qayta_oqish_arizasi_{$group->id}.docx"
        );
    }

    /**
     * Ruxsatnoma PDF faylini yuklab olish (uzbek yoki english versiyasi).
     */
    public function downloadCertificate(int $groupId, Request $request): Response
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $group = RetakeApplicationGroup::findOrFail($groupId);

        if ((int) $group->student_hemis_id !== (int) $student->hemis_id) {
            abort(403);
        }

        $lang = $request->input('lang', app()->getLocale() === 'en' ? 'en' : 'uz');
        if (!in_array($lang, ['uz', 'en'], true)) {
            $lang = 'uz';
        }

        // Uzbekcha versiya — generatsiya qilingan fayldan beriladi (mavjud bo'lsa).
        if ($lang === 'uz') {
            if (!$group->pdf_certificate_path || !Storage::disk('public')->exists($group->pdf_certificate_path)) {
                abort(404, 'Ruxsatnoma hali generatsiya qilinmagan');
            }
            return Storage::disk('public')->download(
                $group->pdf_certificate_path,
                "ruxsatnoma_{$group->id}.pdf"
            );
        }

        // Inglizcha versiya — talab paytida generatsiya qilinadi.
        $approved = $group->applications()
            ->with('retakeGroup.teacher')
            ->where('final_status', 'approved')
            ->get();
        if ($approved->isEmpty()) {
            abort(404, 'Tasdiqlangan fanlar yo\'q');
        }

        $relPath = app(\App\Services\Retake\RetakeDocumentService::class)
            ->generatePdfCertificate($group->load('student'), $approved, 'en');

        return Storage::disk('public')->download(
            $relPath,
            "permit_{$group->id}.pdf"
        );
    }
}
