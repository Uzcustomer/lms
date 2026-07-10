<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use App\Models\RetakeGroup;
use App\Models\RetakeMustaqilSubmission;
use App\Models\Student;
use App\Services\Retake\RetakeAccess;
use App\Services\Retake\RetakeJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
        $studentSearch = trim((string) request('student_search', ''));

        // Cascading filtrlar (talaba ma'lumotlari + fan) — JN hisoboti uslubida.
        $studentFilters = [
            'education_type' => request('education_type'),
            'department' => request('department'),
            'specialty' => request('specialty'),
            'level_code' => request('level_code'),
            'semester_code' => request('semester_code'),
            'group' => request('group'),
        ];
        $subjectFilter = request('subject');
        $sentStatus = request('sent_status'); // '', 'sent', 'not_sent'
        $perPage = (int) request('per_page', 50);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 50;
        }
        $hasStudentFilter = collect($studentFilters)->filter(fn ($v) => filled($v))->isNotEmpty();

        $studentSub = function ($sub) use ($studentFilters) {
            $sub->select('hemis_id')->from('students');
            if (!empty($studentFilters['education_type'])) $sub->where('education_type_code', $studentFilters['education_type']);
            if (!empty($studentFilters['department'])) $sub->where('department_id', $studentFilters['department']);
            if (!empty($studentFilters['specialty'])) $sub->where('specialty_id', $studentFilters['specialty']);
            if (!empty($studentFilters['level_code'])) $sub->where('level_code', $studentFilters['level_code']);
            if (!empty($studentFilters['semester_code'])) $sub->where('semester_code', $studentFilters['semester_code']);
            if (!empty($studentFilters['group'])) $sub->where('group_id', $studentFilters['group']);
        };

        // O'quv bo'limi tomonidan tasdiqlanib guruhga qo'yilgan BARCHA qayta o'qish
        // guruhlari (sinov bilan yakunlanadigan fanlar ham).
        $groupsQuery = RetakeGroup::query()
            ->whereHas('applications', fn ($q) => $q->where('final_status', RetakeApplication::STATUS_APPROVED))
            ->with('teacher')
            ->withCount(['applications as students_count' => fn ($q) => $q->where('final_status', RetakeApplication::STATUS_APPROVED)])
            ->orderByDesc('start_date');
        if ($subjectFilter) {
            $groupsQuery->where('subject_id', $subjectFilter);
        }
        if ($hasStudentFilter) {
            $groupsQuery->whereHas('applications', function ($q) use ($studentSub) {
                $q->where('final_status', RetakeApplication::STATUS_APPROVED)
                  ->whereIn('student_hemis_id', $studentSub);
            });
        }
        $groups = $groupsQuery->paginate($perPage, ['*'], 'groups_page')->withQueryString();

        $sentApplicationsQuery = RetakeApplication::query()
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
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
        if ($subjectFilter) {
            $sentApplicationsQuery->whereHas('retakeGroup', fn ($q) => $q->where('subject_id', $subjectFilter));
        }
        if ($hasStudentFilter) {
            $sentApplicationsQuery->whereIn('student_hemis_id', $studentSub);
        }
        if ($sentStatus === 'sent') {
            $sentApplicationsQuery->whereNotNull('sent_to_test_markazi_at');
        } elseif ($sentStatus === 'not_sent') {
            $sentApplicationsQuery->whereNull('sent_to_test_markazi_at');
        }

        // "Testga yuborilgan talabalar" jadvalidagi ustun filtrlari (client-side)
        // BUTUN ma'lumot bo'yicha ishlashi uchun bu tabda barcha (yuqoridagi
        // panel filtrlaridan keyingi) qatorlarni bitta sahifada yuklaymiz.
        // Guruhlar tabida esa oddiy sahifalash saqlanadi.
        $studentsPerPage = $activeTab === 'students' ? 100000 : $perPage;

        $sentApplications = $sentApplicationsQuery
            ->orderByDesc('id')
            ->paginate($studentsPerPage, ['*'], 'students_page')
            ->withQueryString();

        $mustaqilMap = RetakeMustaqilSubmission::query()
            ->whereIn('application_id', $sentApplications->getCollection()->pluck('id'))
            ->get()
            ->keyBy('application_id');

        // Urinishlar soni — talaba shu fandan necha marta test topshirgan.
        $attemptsMap = $this->buildAttemptsMap($sentApplications->getCollection());

        // Yakuniy natija — vedomost tekshirish logikasi (JN=50, MT=20, OSKI/Test=15+15 yoki 30).
        $finalResultMap = [];
        foreach ($sentApplications->getCollection() as $app) {
            $at = $app->retakeGroup?->assessment_type;
            $isSinov = in_array($at, ['sinov', 'sinov_fan'], true);
            $mt = ($mustaqilMap[$app->id] ?? null)?->grade;
            // Sinov fanlarda Sinov(test) bahosi = JN (avtomatik).
            $effTest = $isSinov ? $app->joriy_score : $app->test_score;
            $finalResultMap[$app->id] = $this->service->testMarkaziFinalResult(
                $app->joriy_score,
                $mt,
                $app->oske_score,
                $effTest,
                $at,
                $app->group?->student?->level_code,
            );
        }

        // Fan ro'yxati — barcha o'quv reja fanlari emas, faqat joriy filtrlarga
        // mos qayta o'qish arizalarida uchraydigan fanlar. Semestr esa ARIZA
        // semestriga (retake_applications.semester_name) qarab qo'llanadi —
        // talabaning joriy semestriga emas (qayta o'quvchilar odatda yuqori
        // semestrga o'tib ketgan bo'ladi, aks holda ro'yxat bo'sh chiqardi).
        // Qolgan filtrlar (fakultet/yo'nalish/kurs/guruh) talaba atributi bo'yicha.
        $selSemNum = null;
        if (filled($studentFilters['semester_code'])) {
            $semName = Student::where('semester_code', $studentFilters['semester_code'])->value('semester_name');
            if ($semName && preg_match('/(\d+)/', $semName, $m)) {
                $selSemNum = (int) $m[1];
            }
        }
        $subjStudentSub = function ($sub) use ($studentFilters) {
            $sub->select('hemis_id')->from('students');
            if (!empty($studentFilters['education_type'])) $sub->where('education_type_code', $studentFilters['education_type']);
            if (!empty($studentFilters['department'])) $sub->where('department_id', $studentFilters['department']);
            if (!empty($studentFilters['specialty'])) $sub->where('specialty_id', $studentFilters['specialty']);
            if (!empty($studentFilters['level_code'])) $sub->where('level_code', $studentFilters['level_code']);
            if (!empty($studentFilters['group'])) $sub->where('group_id', $studentFilters['group']);
        };
        $hasSubjStudentFilter = collect($studentFilters)
            ->except('semester_code')
            ->filter(fn ($v) => filled($v))
            ->isNotEmpty();

        $subjectQuery = RetakeApplication::query()
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
            ->join('retake_groups as rgs', 'rgs.id', '=', 'retake_applications.retake_group_id')
            ->whereNotNull('rgs.subject_id');
        if ($hasSubjStudentFilter) {
            $subjectQuery->whereIn('retake_applications.student_hemis_id', $subjStudentSub);
        }
        if ($selSemNum) {
            $subjectQuery->where('retake_applications.semester_name', 'like', $selSemNum . '-%');
        }
        $subjects = $subjectQuery
            ->distinct()
            ->orderBy('rgs.subject_name')
            ->pluck('rgs.subject_name', 'rgs.subject_id')
            ->toArray();

        return view('teacher.retake-test-markazi.index', [
            'groups' => $groups,
            'sentApplications' => $sentApplications,
            'mustaqilMap' => $mustaqilMap,
            'finalResultMap' => $finalResultMap,
            'attemptsMap' => $attemptsMap,
            'activeTab' => $activeTab,
            'studentSearch' => $studentSearch,
            'sentStatus' => $sentStatus,
            'educationTypes' => \App\Services\Retake\RetakeFilterCache::educationTypes(),
            'subjects' => $subjects,
        ]);
    }

    public function show(int $groupId)
    {
        $this->authorize();

        $group = RetakeGroup::with('teacher')->findOrFail($groupId);

        // Tasdiqlangan barcha talabalar (test markaziga yuborilgan bo'lishi shart emas).
        $applications = $this->service->applications($group);
        $gradesMap = $this->service->gradesMap($group);
        $mustaqilMap = $this->service->mustaqilMap($group);

        // Urinishlar soni — barcha ariza shu guruhga tegishli, retakeGroup ni
        // shu guruhga bog'lab qo'yamiz (qo'shimcha so'rovsiz).
        $applications->each(fn ($a) => $a->setRelation('retakeGroup', $group));
        $attemptsMap = $this->buildAttemptsMap($applications);

        $isSinov = in_array($group->assessment_type, ['sinov', 'sinov_fan'], true);
        $finalResultMap = [];
        foreach ($applications as $app) {
            $effTest = $isSinov ? $app->joriy_score : $app->test_score;
            $finalResultMap[$app->id] = $this->service->testMarkaziFinalResult(
                $app->joriy_score,
                ($mustaqilMap[$app->id] ?? null)?->grade,
                $app->oske_score,
                $effTest,
                $group->assessment_type,
                $app->group?->student?->level_code,
            );
        }

        return view('teacher.retake-test-markazi.show', [
            'group' => $group,
            'applications' => $applications,
            'gradesMap' => $gradesMap,
            'mustaqilMap' => $mustaqilMap,
            'finalResultMap' => $finalResultMap,
            'attemptsMap' => $attemptsMap,
        ]);
    }

    /**
     * Talaba shu fandan necha marta test topshirganini (urinishlar soni)
     * hisoblaydi: hemis_quiz_results dagi haqiqiy urinishlar, va appelyatsiyada
     * o'chirilgan baholar (removed + 1) — ikkalasidan kattasi. Faqat >=2 bo'lsa
     * jurnalda "(N)" ko'rsatiladi.
     *
     * @param  \Illuminate\Support\Collection  $applications
     * @return array<int, int>
     */
    private function buildAttemptsMap($applications): array
    {
        $removed = $this->service->removedAppealCounts($applications);
        $quiz = $this->service->attemptCounts($applications);

        $map = [];
        foreach ($applications as $app) {
            $map[$app->id] = max($quiz[$app->id] ?? 0, ($removed[$app->id] ?? 0) + 1);
        }

        return $map;
    }

    /**
     * Qo'lda baho kiritish O'CHIRILGAN. OSKE/TEST natijalari faqat
     * diagnostika orqali (Test markazi → "Sistemaga yuklash") tushadi.
     * Bu endpoint endi qabul qilmaydi — eski/tashqi so'rovlarga ham yopiq.
     */
    public function saveScore(Request $request, int $groupId): JsonResponse
    {
        $this->authorize();

        return response()->json([
            'success' => false,
            'message' => "Qo'lda kiritish o'chirilgan. OSKE/TEST natijalari faqat diagnostika orqali (Sistemaga yuklash) tushadi.",
        ], 403);
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

    /**
     * YN qaydnoma (Excel) — vazn taqsimoti bilan, asosiy jurnal logikasidek.
     * RetakeJournalService::buildVedomostExcel orqali yn_qaydnoma shablonidan.
     */
    public function generateYnQaydnoma(Request $request, int $groupId)
    {
        $this->authorize();

        $request->validate([
            'weight_jn'   => 'required|integer|min:0|max:100',
            'weight_mt'   => 'required|integer|min:0|max:100',
            'weight_on'   => 'nullable|integer|min:0|max:100',
            'weight_oski' => 'nullable|integer|min:0|max:100',
            'weight_test' => 'nullable|integer|min:0|max:100',
            'semester'    => 'nullable|integer|min:1|max:20',
        ]);

        $group = RetakeGroup::with('teacher')->findOrFail($groupId);

        $weights = [
            'jn'   => (int) $request->input('weight_jn'),
            'mt'   => (int) $request->input('weight_mt'),
            'on'   => (int) ($request->input('weight_on') ?? 0),
            'oski' => (int) ($request->input('weight_oski') ?? 0),
            'test' => (int) ($request->input('weight_test') ?? 0),
        ];

        if (array_sum($weights) !== 100) {
            return response()->json(['error' => "Vaznlar jami 100 bo'lishi kerak"], 422);
        }

        $semesterNumber = $request->filled('semester') ? (int) $request->input('semester') : null;

        try {
            $built = $this->service->buildVedomostExcel($group, $weights, $semesterNumber);
        } catch (ValidationException $e) {
            return response()->json(['error' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->download($built['path'], $built['filename'])->deleteFileAfterSend(false);
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
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
            ->with(['group.student', 'retakeGroup'])
            ->orderBy('id');

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
            $row->addCell(1400)->addText($app->semester_name ?? $retakeGroup?->semester_name ?? '—', $bodyFont, $cellCenter);
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

    public function exportSentStudentsExcel(Request $request)
    {
        $this->authorize();

        $studentSearch = trim((string) $request->input('student_search', ''));
        $sentStatus = (string) $request->input('sent_status', 'sent');
        $studentFilters = [
            'education_type' => $request->input('education_type'),
            'department' => $request->input('department'),
            'specialty' => $request->input('specialty'),
            'level_code' => $request->input('level_code'),
            'semester_code' => $request->input('semester_code'),
            'group' => $request->input('group'),
        ];
        $subjectFilter = $request->input('subject');
        $hasStudentFilter = collect($studentFilters)->filter(fn ($v) => filled($v))->isNotEmpty();

        $studentSub = function ($sub) use ($studentFilters) {
            $sub->select('hemis_id')->from('students');
            if (!empty($studentFilters['education_type'])) $sub->where('education_type_code', $studentFilters['education_type']);
            if (!empty($studentFilters['department'])) $sub->where('department_id', $studentFilters['department']);
            if (!empty($studentFilters['specialty'])) $sub->where('specialty_id', $studentFilters['specialty']);
            if (!empty($studentFilters['level_code'])) $sub->where('level_code', $studentFilters['level_code']);
            if (!empty($studentFilters['semester_code'])) $sub->where('semester_code', $studentFilters['semester_code']);
            if (!empty($studentFilters['group'])) $sub->where('group_id', $studentFilters['group']);
        };

        $query = RetakeApplication::query()
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
            ->with(['group.student', 'retakeGroup'])
            ->orderByDesc('sent_to_test_markazi_at');

        if ($sentStatus === 'not_sent') {
            $query->whereNull('sent_to_test_markazi_at');
        } elseif ($sentStatus !== '') {
            $query->whereNotNull('sent_to_test_markazi_at');
        }

        if ($studentSearch !== '') {
            $query->where(function ($q) use ($studentSearch) {
                $q->where('student_hemis_id', 'like', "%{$studentSearch}%")
                    ->orWhereHas('group.student', function ($studentQuery) use ($studentSearch) {
                        $studentQuery->where('full_name', 'like', "%{$studentSearch}%")
                            ->orWhere('student_id_number', 'like', "%{$studentSearch}%");
                    });
            });
        }
        if ($subjectFilter) {
            $query->whereHas('retakeGroup', fn ($q) => $q->where('subject_id', $subjectFilter));
        }
        if ($hasStudentFilter) {
            $query->whereIn('student_hemis_id', $studentSub);
        }

        $applications = $query->get()
            ->sortBy(function ($app) {
                $studentName = $app->group?->student?->full_name ?? '';
                $subjectName = $app->retakeGroup?->subject_name ?? $app->subject_name ?? '';
                // Semestr — ARIZANIKI (guruh bir nechta semestrni birlashtiradi).
                $semesterName = $app->semester_name ?? $app->retakeGroup?->semester_name ?? '';

                return mb_strtolower($studentName . '|' . $subjectName . '|' . $semesterName);
            })
            ->values();
        if ($applications->isEmpty()) {
            abort(404, 'Excel uchun testga yuborilgan talabalar topilmadi');
        }

        $mustaqilMap = RetakeMustaqilSubmission::query()
            ->whereIn('application_id', $applications->pluck('id'))
            ->get()
            ->keyBy('application_id');

        $attemptsMap = $this->buildAttemptsMap($applications);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Testga yuborilganlar');

        $headers = [
            'A1' => 'T/R',
            'B1' => 'Full name',
            'C1' => 'Fakultet',
            'D1' => 'Kurs',
            'E1' => 'Semester',
            'F1' => "Qayta o'qish fani",
            'G1' => 'Ariza bergan fani',
            'H1' => 'Fan semestri',
            'I1' => 'Yopilish shakli',
            'J1' => 'Test sanasi',
            'K1' => 'JN',
            'L1' => 'MT',
            'M1' => 'OSKE',
            'N1' => 'TEST',
            'O1' => 'Yakuniy natija',
            'P1' => 'Holat',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $sheet->getStyle('A1:P1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F6E43'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $row = 2;
        foreach ($applications as $index => $app) {
            $student = $app->group?->student;
            $retakeGroup = $app->retakeGroup;
            $mustaqil = $mustaqilMap[$app->id] ?? null;
            $at = $retakeGroup?->assessment_type;
            $assessmentLabel = match ($at) {
                'oske' => 'OSKE',
                'test' => 'TEST',
                'oske_test' => 'OSKE + TEST',
                'sinov', 'sinov_fan' => 'Sinov',
                default => '-',
            };
            $testDate = $retakeGroup?->test_date
                ? $retakeGroup->test_date->format('d.m.Y')
                : '-';

            $needsOske = in_array($at, ['oske', 'oske_test'], true);
            $needsTest = in_array($at, ['test', 'oske_test', 'sinov', 'sinov_fan'], true);
            $isSinov = in_array($at, ['sinov', 'sinov_fan'], true);
            // Sinov fanlarda Sinov(test) bahosi = JN (avtomatik).
            $effTest = $isSinov ? $app->joriy_score : $app->test_score;

            // Katak qiymati: yopilish shaklida bu nazorat KO'ZDA TUTILMAGAN bo'lsa
            // 'X'; baho qo'yilishi kerak-u, hali yo'q bo'lsa '-'; bor bo'lsa qiymat.
            $ctrlCell = fn (bool $needs, $value) => !$needs ? 'X' : ($value !== null ? (float) $value : '-');

            $sheet->setCellValue("A{$row}", $index + 1);
            $sheet->setCellValue("B{$row}", $student?->full_name ?? '—');
            $sheet->setCellValue("C{$row}", $student?->department_name ?? '—');
            $sheet->setCellValue("D{$row}", $student?->level_name ?? '—');
            $sheet->setCellValue("E{$row}", $student?->semester_name ?? '—');
            $sheet->setCellValue("F{$row}", $retakeGroup?->subject_name ?? $app->subject_name ?? '—');
            $sheet->setCellValue("G{$row}", $app->subject_name ?? '—'); // Ariza bergan fani
            $sheet->setCellValue("H{$row}", $app->semester_name ?? $retakeGroup?->semester_name ?? '—');
            $sheet->setCellValue("I{$row}", $assessmentLabel);
            $sheet->setCellValue("J{$row}", $testDate);
            $sheet->setCellValue("K{$row}", $app->joriy_score !== null ? (float) $app->joriy_score : '-');
            $sheet->setCellValue("L{$row}", $mustaqil?->grade !== null ? (float) $mustaqil->grade : '-');
            $sheet->setCellValue("M{$row}", $ctrlCell($needsOske, $app->oske_score));
            $sheet->setCellValue("N{$row}", $ctrlCell($needsTest, $effTest));

            $final = $this->service->testMarkaziFinalResult(
                $app->joriy_score,
                $mustaqil?->grade,
                $app->oske_score,
                $effTest,
                $at,
                $student?->level_code,
            );
            $finalText = match ($final['status']) {
                'no_teacher_grade' => "O'qituvchi bahosini qo'ymagan",
                'absent' => 'Imtihonga kelmagan',
                'failed' => 'Yiqildi',
                'passed' => (string) $final['value'],
                default => '-',
            };
            $attempts = $attemptsMap[$app->id] ?? 1;
            if ($finalText !== '-' && $attempts >= 2) {
                $finalText .= ' (' . $attempts . ')';
            }
            $sheet->setCellValue("O{$row}", $finalText);
            $sheet->setCellValue("P{$row}", $app->sent_to_test_markazi_at ? 'Yuborilgan' : 'Yuborilmagan');

            $sheet->setCellValueExplicit("A{$row}", (string) ($index + 1), DataType::TYPE_NUMERIC);
            $row++;
        }

        $lastRow = $row - 1;
        if ($lastRow >= 2) {
            $sheet->getStyle("A2:P{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E5E7EB'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I2:P{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach (range('A', 'P') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        $tempDir = storage_path('app/public/retake-test-markazi');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $fileName = 'testga_yuborilgan_talabalar_' . now()->format('Ymd_His') . '.xlsx';
        $tempPath = $tempDir . '/' . Str::slug(pathinfo($fileName, PATHINFO_FILENAME)) . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Joriy filtrlar (fakultet, yo'nalish, kurs, semestr, guruh, fan) bo'yicha
     * qayta o'qish VEDOMOST(lar)ini yaratadi. Har bir vedomost — bitta
     * (qayta o'qish guruhi × FAKULTET × YO'NALISH × semestr) kesimi uchun
     * alohida fayl (bir guruh turli fakultet/yo'nalish talabalarini
     * birlashtirishi mumkin, ular ajratiladi). Bir nechta fayl bo'lsa — ZIP.
     */
    public function generateVedomost(Request $request)
    {
        $this->authorize();

        $applications = $this->filteredSentApplications($request);
        if ($applications->isEmpty()) {
            return back()->with('error', "Tanlangan filtrlarga mos qayta o'qish talabalari topilmadi.");
        }

        $groupsById = RetakeGroup::whereIn('id', $applications->pluck('retake_group_id')->filter()->unique())
            ->get()->keyBy('id');

        // (guruh × fakultet × yo'nalish × semestr) bo'yicha ajratamiz.
        $buckets = $applications->groupBy(function ($app) {
            $st = $app->group?->student;
            $dep = $st?->department_name ?: '—';
            $spec = $st?->specialty_name ?: '—';
            $sem = $app->semester_name ?: ($app->retakeGroup?->semester_name ?? '');
            return $app->retake_group_id . '||' . $dep . '||' . $spec . '||' . $sem;
        });

        if ($buckets->count() > 80) {
            return back()->with('error', "Juda ko'p bo'lim topildi ({$buckets->count()} ta). Iltimos avval yuqoridagi filtrlardan (fakultet / yo'nalish / kurs / semestr / guruh / fan) birortasini tanlab, so'ng vedomost yarating.");
        }

        $tmpDir = storage_path('app/public/retake/vedomosts');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        $files = []; // [unikal absPath, yuklab olish nomi]
        foreach ($buckets as $key => $apps) {
            [$rgId, $dep, $spec, $sem] = array_pad(explode('||', $key), 4, '');
            $group = $groupsById[$rgId] ?? null;
            if (!$group) {
                continue;
            }

            try {
                $built = $this->service->buildVedomostExcel($group, null, null, $apps->values());
            } catch (ValidationException $e) {
                continue;
            }

            // buildVedomostExcel bir guruh uchun bir xil yo'lga yozadi — keyingi
            // bo'lim uni qayta yozib yubormasligi uchun darhol unikal nusxaga ko'chiramiz.
            $uniq = $tmpDir . '/' . uniqid('ved_', true) . '.xlsx';
            if (!@copy($built['path'], $uniq)) {
                continue;
            }

            $files[] = [$uniq, $this->vedomostFileName($dep, $spec, $group->subject_name ?? 'fan', $sem)];
        }

        if (empty($files)) {
            return back()->with('error', "Vedomost yaratib bo'lmadi (mos ma'lumot yo'q).");
        }

        // Bitta fayl — to'g'ridan-to'g'ri, aks holda ZIP.
        if (count($files) === 1) {
            return response()->download($files[0][0], $files[0][1])->deleteFileAfterSend(true);
        }

        $zipName = 'qayta_oqish_vedomostlari_' . now()->format('Ymd_His') . '.zip';
        $zipPath = $tmpDir . '/' . $zipName;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', "ZIP faylini yaratib bo'lmadi.");
        }
        $used = [];
        foreach ($files as [$abs, $name]) {
            if (!is_file($abs)) continue;
            $entry = $name;
            $i = 1;
            while (isset($used[$entry])) {
                $entry = pathinfo($name, PATHINFO_FILENAME) . "_{$i}." . pathinfo($name, PATHINFO_EXTENSION);
                $i++;
            }
            $used[$entry] = true;
            $zip->addFile($abs, $entry);
        }
        $zip->close();

        // Unikal nusxalar endi ZIP ichida — vaqtinchalik fayllarni tozalaymiz.
        foreach ($files as [$abs, $name]) {
            @unlink($abs);
        }

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    /**
     * Vedomost fayl nomi — "Fakultet - Yo'nalish - Fan - Semestr.xlsx".
     * Fayl tizimi uchun xavfli belgilar tozalanadi, bo'shliqlar saqlanadi.
     */
    private function vedomostFileName(string $faculty, string $specialty, string $subject, string $semester = ''): string
    {
        $clean = function (string $s): string {
            $s = preg_replace('/[\/\\\\:*?"<>|]+/u', ' ', $s);
            $s = preg_replace('/\s+/u', ' ', $s);
            return trim($s);
        };
        $parts = array_filter(
            [$clean($faculty), $clean($specialty), $clean($subject), $clean($semester)],
            fn ($p) => $p !== '' && $p !== '—'
        );
        $base = implode(' - ', $parts);
        if ($base === '') {
            $base = 'vedomost';
        }
        return mb_substr($base, 0, 120) . '.xlsx';
    }

    /**
     * exportSentStudentsExcel bilan bir xil filtrlar bo'yicha tasdiqlangan
     * qayta o'qish arizalari (fakultet/yo'nalish/kurs/semestr/guruh/fan +
     * ism qidiruv + yuborilganlik holati).
     */
    private function filteredSentApplications(Request $request)
    {
        $studentSearch = trim((string) $request->input('student_search', ''));
        $sentStatus = (string) $request->input('sent_status', '');
        $studentFilters = [
            'education_type' => $request->input('education_type'),
            'department' => $request->input('department'),
            'specialty' => $request->input('specialty'),
            'level_code' => $request->input('level_code'),
            'semester_code' => $request->input('semester_code'),
            'group' => $request->input('group'),
        ];
        $subjectFilter = $request->input('subject');
        $hasStudentFilter = collect($studentFilters)->filter(fn ($v) => filled($v))->isNotEmpty();

        $studentSub = function ($sub) use ($studentFilters) {
            $sub->select('hemis_id')->from('students');
            if (!empty($studentFilters['education_type'])) $sub->where('education_type_code', $studentFilters['education_type']);
            if (!empty($studentFilters['department'])) $sub->where('department_id', $studentFilters['department']);
            if (!empty($studentFilters['specialty'])) $sub->where('specialty_id', $studentFilters['specialty']);
            if (!empty($studentFilters['level_code'])) $sub->where('level_code', $studentFilters['level_code']);
            if (!empty($studentFilters['semester_code'])) $sub->where('semester_code', $studentFilters['semester_code']);
            if (!empty($studentFilters['group'])) $sub->where('group_id', $studentFilters['group']);
        };

        $query = RetakeApplication::query()
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
            ->with(['group.student', 'retakeGroup']);

        if ($sentStatus === 'sent') {
            $query->whereNotNull('sent_to_test_markazi_at');
        } elseif ($sentStatus === 'not_sent') {
            $query->whereNull('sent_to_test_markazi_at');
        }
        if ($studentSearch !== '') {
            $query->where(function ($q) use ($studentSearch) {
                $q->where('student_hemis_id', 'like', "%{$studentSearch}%")
                    ->orWhereHas('group.student', function ($sq) use ($studentSearch) {
                        $sq->where('full_name', 'like', "%{$studentSearch}%")
                            ->orWhere('student_id_number', 'like', "%{$studentSearch}%");
                    });
            });
        }
        if ($subjectFilter) {
            $query->whereHas('retakeGroup', fn ($q) => $q->where('subject_id', $subjectFilter));
        }
        if ($hasStudentFilter) {
            $query->whereIn('student_hemis_id', $studentSub);
        }

        return $query->get();
    }

    private function authorize(): void
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor) abort(403);

        $allowed = $actor->hasAnyRole([
            ProjectRole::SUPERADMIN->value,
            ProjectRole::ADMIN->value,
            ProjectRole::TEST_CENTER->value,
            ProjectRole::REGISTRAR_OFFICE->value,
        ]);
        if (!$allowed) {
            abort(403, 'Sizda test markaziga ruxsat yo\'q');
        }
    }
}
