<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use App\Models\RetakeGroup;
use App\Models\RetakeMustaqilSubmission;
use App\Services\Retake\RetakeAccess;
use App\Services\Retake\RetakeJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use Illuminate\Support\Str;

/**
 * Test markazi paneli — qayta o'qish guruhlaridan kelgan vedomostlar:
 * OSKE va TEST natijalarini kiritish.
 */
class RetakeTestMarkaziController extends Controller
{
    public function __construct(
        private RetakeJournalService $service,
    ) {}

    public function index()
    {
        $this->authorize();

        $activeTab = request('tab') === 'students' ? 'students' : 'groups';

        // Yuborilgan guruhlar — eng yangi avval
        $groups = RetakeGroup::query()
            ->whereNotNull('sent_to_test_markazi_at')
            ->whereIn('assessment_type', ['oske', 'test', 'oske_test'])
            ->with('teacher')
            ->withCount('applications as students_count')
            ->orderByDesc('sent_to_test_markazi_at')
            ->paginate(30, ['*'], 'groups_page')
            ->withQueryString();

        $studentSearch = trim((string) request('student_search', ''));

        $sentApplicationsQuery = RetakeApplication::query()
            ->whereNotNull('sent_to_test_markazi_at')
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->with(['group.student', 'retakeGroup']);

        if ($studentSearch !== '') {
            $sentApplicationsQuery->where(function ($query) use ($studentSearch) {
                $query->where('student_hemis_id', 'like', "%{$studentSearch}%")
                    ->orWhereHas('group.student', function ($studentQuery) use ($studentSearch) {
                        $studentQuery->where('full_name', 'like', "%{$studentSearch}%")
                            ->orWhere('student_id_number', 'like', "%{$studentSearch}%");
                    });
            });
        }

        $sentApplications = $sentApplicationsQuery
            ->orderByDesc('sent_to_test_markazi_at')
            ->paginate(50, ['*'], 'students_page')
            ->withQueryString();

        $mustaqilMap = RetakeMustaqilSubmission::query()
            ->whereIn('application_id', $sentApplications->getCollection()->pluck('id'))
            ->get()
            ->keyBy('application_id');

        return view('teacher.retake-test-markazi.index', [
            'groups' => $groups,
            'sentApplications' => $sentApplications,
            'mustaqilMap' => $mustaqilMap,
            'activeTab' => $activeTab,
            'studentSearch' => $studentSearch,
        ]);
    }

    public function show(int $groupId)
    {
        $this->authorize();

        $group = RetakeGroup::with('teacher')->findOrFail($groupId);
        if (!$group->sent_to_test_markazi_at) {
            abort(404, 'Bu guruh test markaziga yuborilmagan');
        }

        $applications = $this->service->sentApplications($group);
        $gradesMap = $this->service->gradesMap($group);
        $mustaqilMap = $this->service->mustaqilMap($group);

        return view('teacher.retake-test-markazi.show', [
            'group' => $group,
            'applications' => $applications,
            'gradesMap' => $gradesMap,
            'mustaqilMap' => $mustaqilMap,
        ]);
    }

    public function saveScore(Request $request, int $groupId): JsonResponse
    {
        $this->authorize();

        $data = $request->validate([
            'application_id' => 'required|integer',
            'oske_score' => 'nullable|numeric|min:0|max:100',
            'test_score' => 'nullable|numeric|min:0|max:100',
        ]);

        $group = RetakeGroup::findOrFail($groupId);
        if (!$group->sent_to_test_markazi_at) {
            return response()->json(['success' => false, 'message' => 'Guruh test markaziga yuborilmagan'], 403);
        }

        $app = RetakeApplication::query()
            ->where('id', $data['application_id'])
            ->where('retake_group_id', $group->id)
            ->firstOrFail();

        $actor = RetakeAccess::currentStaff();

        try {
            $this->service->saveOskeTestScore(
                $app,
                $data['oske_score'] !== null && $data['oske_score'] !== '' ? (float) $data['oske_score'] : null,
                $data['test_score'] !== null && $data['test_score'] !== '' ? (float) $data['test_score'] : null,
                $actor,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        $fresh = $app->refresh();
        return response()->json([
            'success' => true,
            'oske_score' => $fresh->oske_score,
            'test_score' => $fresh->test_score,
            'final_grade' => $fresh->final_grade_value,
        ]);
    }

    /**
     * Diagnostika orqali — Moodle qayta o'qish quiz natijalarini (OSKE/TEST)
     * shu guruh sessiyasiga mos kelganlarini avtomatik yuklaydi. Boshqa
     * fasl/o'quv yili natijalari rad etiladi.
     */
    public function loadFromDiagnostika(int $groupId): JsonResponse
    {
        $this->authorize();

        $group = RetakeGroup::findOrFail($groupId);
        if (!$group->sent_to_test_markazi_at) {
            return response()->json(['success' => false, 'message' => 'Guruh test markaziga yuborilmagan'], 403);
        }

        $actor = RetakeAccess::currentStaff();

        try {
            $stats = $this->service->fetchRetakeResultsFromQuiz($group, $actor);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        $parts = [];
        if ($stats['fetched_oske'] > 0) $parts[] = "OSKE: {$stats['fetched_oske']} ta";
        if ($stats['fetched_test'] > 0) $parts[] = "TEST: {$stats['fetched_test']} ta";
        $loaded = empty($parts) ? 'Yangi natija topilmadi' : ('Yuklandi — ' . implode(', ', $parts));
        if ($stats['rejected_other_session'] > 0) {
            $loaded .= ". Boshqa sessiyaga tegishli {$stats['rejected_other_session']} natija rad etildi";
        }

        return response()->json([
            'success' => true,
            'message' => $loaded,
            'stats' => $stats,
        ]);
    }

    public function generateYnOldiWord(Request $request)
    {
        $this->authorize();

        $data = $request->validate([
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['required', 'integer'],
        ]);

        $groups = RetakeGroup::query()
            ->whereIn('id', $data['group_ids'])
            ->whereNotNull('sent_to_test_markazi_at')
            ->whereIn('assessment_type', ['oske', 'test', 'oske_test'])
            ->with('teacher')
            ->orderBy('name')
            ->get();

        if ($groups->isEmpty()) {
            abort(404, 'Tanlangan guruhlar topilmadi');
        }

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(11);

        $sectionStyle = [
            'marginTop' => 900,
            'marginBottom' => 900,
            'marginLeft' => 1200,
            'marginRight' => 1200,
        ];

        $fileName = 'YN_oldi_test_markazi_' . now()->format('Ymd_His') . '.docx';
        $tempDir = storage_path('app/public/retake-test-markazi');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        foreach ($groups as $group) {
            $applications = $this->service->sentApplications($group);
            $mustaqilMap = $this->service->mustaqilMap($group);

            $section = $phpWord->addSection($sectionStyle);
            $section->addText('YN oldi qaydnoma - Test markazi', ['bold' => true, 'size' => 15], ['alignment' => Jc::CENTER, 'spaceAfter' => 120]);
            $section->addText('Guruh: ' . $group->name, ['bold' => true, 'size' => 12], ['spaceAfter' => 60]);
            $section->addText('Fan: ' . ($group->subject_name ?? '—'), ['size' => 11], ['spaceAfter' => 30]);
            $section->addText('Tur: ' . ($group->assessment_type ?? '—'), ['size' => 11], ['spaceAfter' => 30]);
            $section->addText("Qoidalar: JN >= 60 va MT >= 60 bo'lsa testga ruxsat, aks holda ruxsat yo'q.", ['size' => 11], ['spaceAfter' => 160]);

            $tableStyle = [
                'borderSize' => 6,
                'borderColor' => '999999',
                'cellMargin' => 60,
            ];
            $phpWord->addTableStyle('RetakeYnTable_' . $group->id, $tableStyle);
            $table = $section->addTable('RetakeYnTable_' . $group->id);

            $headerFont = ['bold' => true, 'size' => 10];
            $bodyFont = ['size' => 10];
            $bodyFontRed = ['size' => 10, 'color' => 'C00000'];
            $cellCenter = ['alignment' => Jc::CENTER];
            $cellLeft = ['alignment' => Jc::START];

            $headerBg = ['bgColor' => 'D9E2F3', 'valign' => 'center'];
            $row = $table->addRow();
            $row->addCell(500, $headerBg)->addText('#', $headerFont, $cellCenter);
            $row->addCell(5200, $headerBg)->addText('Talaba F.I.Sh.', $headerFont, $cellCenter);
            $row->addCell(1200, $headerBg)->addText('JN', $headerFont, $cellCenter);
            $row->addCell(1200, $headerBg)->addText('MT', $headerFont, $cellCenter);
            $row->addCell(1800, $headerBg)->addText('Testga ruxsat', $headerFont, $cellCenter);

            foreach ($applications as $idx => $app) {
                $student = $app->group->student ?? null;
                $jn = $app->joriy_score !== null ? round((float) $app->joriy_score, 2) : null;
                $mt = ($mustaqilMap[$app->id] ?? null)?->grade;
                $mt = $mt !== null ? round((float) $mt, 2) : null;
                $allowed = $jn !== null && $mt !== null && $jn >= 60 && $mt >= 60;

                $row = $table->addRow();
                $row->addCell(500)->addText((string) ($idx + 1), $bodyFont, $cellCenter);
                $row->addCell(5200)->addText($student?->full_name ?? '—', $bodyFont, $cellLeft);
                $row->addCell(1200)->addText($jn !== null ? rtrim(rtrim(number_format($jn, 2, '.', ''), '0'), '.') : '—', $jn !== null && $jn < 60 ? $bodyFontRed : $bodyFont, $cellCenter);
                $row->addCell(1200)->addText($mt !== null ? rtrim(rtrim(number_format($mt, 2, '.', ''), '0'), '.') : '—', $mt !== null && $mt < 60 ? $bodyFontRed : $bodyFont, $cellCenter);
                $row->addCell(1800)->addText(
                    $allowed ? 'Testga ruxsat' : 'Ruxsat yo\'q',
                    $allowed ? ['size' => 10, 'bold' => true, 'color' => '0F9D58'] : ['size' => 10, 'bold' => true, 'color' => 'C00000'],
                    $cellCenter
                );
            }

            if ($group !== $groups->last()) {
                $section->addTextBreak(1);
            }
        }

        $tempPath = $tempDir . '/' . Str::slug(pathinfo($fileName, PATHINFO_FILENAME)) . '.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    public function generateDailyAllowedStudentsWord(Request $request)
    {
        $this->authorize();

        $studentSearch = trim((string) $request->input('student_search', ''));

        $query = RetakeApplication::query()
            ->whereNotNull('sent_to_test_markazi_at')
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->with(['group.student', 'retakeGroup'])
            ->orderBy('sent_to_test_markazi_at');

        if ($studentSearch !== '') {
            $query->where(function ($q) use ($studentSearch) {
                $q->where('student_hemis_id', 'like', "%{$studentSearch}%")
                    ->orWhereHas('group.student', function ($studentQuery) use ($studentSearch) {
                        $studentQuery->where('full_name', 'like', "%{$studentSearch}%")
                            ->orWhere('student_id_number', 'like', "%{$studentSearch}%");
                    });
            });
        }

        $applications = $query->get();
        if ($applications->isEmpty()) {
            abort(404, 'Word uchun ruxsat etilgan talabalar topilmadi');
        }

        $mustaqilMap = RetakeMustaqilSubmission::query()
            ->whereIn('application_id', $applications->pluck('id'))
            ->get()
            ->keyBy('application_id');

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 900,
            'marginBottom' => 900,
            'marginLeft' => 1000,
            'marginRight' => 1000,
        ]);

        $section->addText('Testga ruxsat etilgan talabalar', ['bold' => true, 'size' => 15], ['alignment' => Jc::CENTER, 'spaceAfter' => 120]);
        $section->addText('Sana: ' . now()->format('d.m.Y H:i'), ['size' => 11], ['spaceAfter' => 160]);

        $phpWord->addTableStyle('DailyAllowedStudentsTable', [
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 60,
        ]);

        $headerFont = ['bold' => true, 'size' => 9];
        $bodyFont = ['size' => 9];
        $cellCenter = ['alignment' => Jc::CENTER];
        $cellLeft = ['alignment' => Jc::START];
        $headerBg = ['bgColor' => 'D9E2F3', 'valign' => 'center'];

        $table = $section->addTable('DailyAllowedStudentsTable');
        $row = $table->addRow();
        $row->addCell(500, $headerBg)->addText('#', $headerFont, $cellCenter);
        $row->addCell(3600, $headerBg)->addText('F.I.Sh.', $headerFont, $cellCenter);
        $row->addCell(1800, $headerBg)->addText('Fan', $headerFont, $cellCenter);
        $row->addCell(1400, $headerBg)->addText('Semester', $headerFont, $cellCenter);
        $row->addCell(900, $headerBg)->addText('JN', $headerFont, $cellCenter);
        $row->addCell(900, $headerBg)->addText('MT', $headerFont, $cellCenter);
        $row->addCell(1700, $headerBg)->addText('Status', $headerFont, $cellCenter);

        foreach ($applications as $idx => $app) {
            $student = $app->group?->student;
            $retakeGroup = $app->retakeGroup;
            $mustaqil = $mustaqilMap[$app->id] ?? null;

            $row = $table->addRow();
            $row->addCell(500)->addText((string) ($idx + 1), $bodyFont, $cellCenter);
            $row->addCell(3600)->addText($student?->full_name ?? '—', $bodyFont, $cellLeft);
            $row->addCell(1800)->addText($retakeGroup?->subject_name ?? $app->subject_name ?? '—', $bodyFont, $cellLeft);
            $row->addCell(1400)->addText($retakeGroup?->semester_name ?? $app->semester_name ?? '—', $bodyFont, $cellCenter);
            $row->addCell(900)->addText($app->joriy_score !== null ? rtrim(rtrim(number_format($app->joriy_score, 2, '.', ''), '0'), '.') : '—', $bodyFont, $cellCenter);
            $row->addCell(900)->addText($mustaqil?->grade !== null ? rtrim(rtrim(number_format($mustaqil->grade, 2, '.', ''), '0'), '.') : '—', $bodyFont, $cellCenter);
            $row->addCell(1700)->addText('Ruxsat', ['size' => 9, 'bold' => true, 'color' => '0F9D58'], $cellCenter);
        }

        $fileName = 'testga_ruxsat_etilgan_talabalar_' . now()->format('Ymd_His') . '.docx';
        $tempDir = storage_path('app/public/retake-test-markazi');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempPath = $tempDir . '/' . Str::slug(pathinfo($fileName, PATHINFO_FILENAME)) . '.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    private function authorize(): void
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor) abort(403);

        $allowed = $actor->hasAnyRole([
            ProjectRole::SUPERADMIN->value,
            ProjectRole::ADMIN->value,
            ProjectRole::TEST_CENTER->value,
        ]);
        if (!$allowed) {
            abort(403, 'Sizda test markaziga ruxsat yo\'q');
        }
    }
}
