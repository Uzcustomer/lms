<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\ExamCapacityOverride;
use App\Models\ExamSchedule;
use App\Models\YnSubmission;
use App\Services\JnMtCalculator;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\MarkingSystemScore;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Specialty;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\ContractList;
use App\Models\Setting;
use App\Models\DocumentVerification;
use App\Models\AbsenceExcuseMakeup;
use App\Models\YnStudentGrade;
use App\Models\StudentNotification;
use App\Services\AutoAssignService;
use App\Services\ExamCapacityService;
use App\Services\ExamDateRoleService;
use App\Services\TelegramService;
use App\Jobs\BookMoodleGroupExam;
use App\Jobs\AssignComputersJob;
use App\Enums\ProjectRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

class AcademicScheduleController extends Controller
{
    private function routePrefix(): string
    {
        return auth()->guard('teacher')->check() ? 'teacher' : 'admin';
    }

    /**
     * Test-center sahifasini faqat ko'rish uchun ochadigan rollar.
     * Bu rollar Saqlash/Yangilash kabi yozish operatsiyalarini bajara olmaydi.
     */
    private function isTestCenterReadOnly(): bool
    {
        $user = auth()->user() ?? auth('teacher')->user();
        if (!$user) {
            return true;
        }
        $activeRole = session('active_role', $user->getRoleNames()->first());
        if (in_array($activeRole, ExamDateRoleService::adminRoles(), true)) {
            return false;
        }
        return in_array($activeRole, [
            ProjectRole::REGISTRAR_OFFICE->value,
            ProjectRole::DEAN->value,
        ], true);
    }

    /**
     * Foydalanuvchi hozir aynan "Test markazi" rolida ishlayaptimi?
     * (admin rollari testCenterAccess testidan o'tadi, lekin bu yerda
     * "false" qaytaradi — admin uchun cheklov yo'q.)
     */
    private function isActingAsTestCenter(): bool
    {
        $user = auth()->user() ?? auth('teacher')->user();
        if (!$user) {
            return false;
        }
        $activeRole = session('active_role', $user->getRoleNames()->first());
        return $activeRole === ProjectRole::TEST_CENTER->value;
    }

    /**
     * Test markazi roli uchun: imtihon vaqtini kamida 1 kun oldin
     * belgilash/o'zgartirish mumkin. O'sha kun yoki o'tgan sanalarga
     * tegish taqiqlanadi (admin uchun cheklov yo'q).
     *
     * @return string|null  Xatolik matni (cheklov bor) yoki null (ruxsat).
     */
    private function testCenterDateTooSoon($relatedDate): ?string
    {
        if (!$this->isActingAsTestCenter()) {
            return null;
        }
        if (empty($relatedDate)) {
            return null;
        }
        $dateStr = $relatedDate instanceof \Carbon\Carbon
            ? $relatedDate->format('Y-m-d')
            : \Carbon\Carbon::parse($relatedDate)->format('Y-m-d');
        $today = now()->format('Y-m-d');
        if ($dateStr <= $today) {
            return "Test markazi rolida imtihon vaqtini kamida bir kun oldin belgilash kerak. O'sha kuni (yoki o'tgan sanalar uchun) vaqtni o'zgartirib bo'lmaydi.";
        }
        return null;
    }

    /**
     * Test markazi sahifasi (YN jadvali) ko'rishi/ishlatishi mumkin
     * bo'lgan rollar. O'quv bo'limi, o'quv bo'limi boshlig'i va o'quv
     * prorektori bu sahifaga muhtoj emas — ular faqat "YN kunini
     * belgilash" sahifasidan foydalanadi (route: academic-schedule.index).
     * Boshqa kichik rollar ham bu yerga ruxsat berilmaydi.
     *
     * Sidebar bunga ko'p o'rinda mos keladi (test-center linki faqat
     * test_markazi va registrator_ofisi/dekan ostida ko'rinadi), lekin
     * URL bilan to'g'ridan-to'g'ri kirish ham bo'sh bo'lmasligi uchun
     * bu kontrolni ham qo'shamiz.
     */
    private function testCenterAllowedRoles(): array
    {
        return array_merge(
            ExamDateRoleService::adminRoles(),
            [
                ProjectRole::TEST_CENTER->value,
                ProjectRole::REGISTRAR_OFFICE->value,
                ProjectRole::DEAN->value,
            ]
        );
    }

    /**
     * Joriy foydalanuvchi rolini tekshiradi va Test markazi sahifasi
     * uchun ruxsat etilmagan rollarni 403 / redirect qiladi.
     */
    private function ensureTestCenterAccess(): ?\Symfony\Component\HttpFoundation\Response
    {
        $user = auth()->user() ?? auth('teacher')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Avtorizatsiya kerak.'], 401);
        }
        $activeRole = session('active_role', $user->getRoleNames()->first());
        if (!in_array($activeRole, $this->testCenterAllowedRoles(), true)) {
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu sahifa sizning rolingiz uchun ochiq emas.',
                ], 403);
            }
            return redirect()->route(
                $this->routePrefix() . '.academic-schedule.index'
            )->with('error', 'Test markazi sahifasi sizning rolingiz uchun ochiq emas.');
        }
        return null;
    }

    /**
     * O'quv bo'limi uchun: YN kunini belgilash sahifasi
     */
    public function index(Request $request)
    {
        // Sahifaga kirish huquqini tekshirish: registrator/dekanat/o'quv bo'limi/admin
        // — barchasi ko'rishi mumkin. Sana qo'yish huquqi alohida tekshiriladi.
        $user = auth()->user() ?? auth('teacher')->user();
        $activeRole = session('active_role', $user?->getRoleNames()->first());
        $adminRoles = ExamDateRoleService::adminRoles();
        $isAdmin = $user && in_array($activeRole, $adminRoles, true);
        if (!ExamDateRoleService::canViewPage($activeRole)) {
            abort(403, 'Bu sahifaga kirish uchun ruxsat yo\'q.');
        }

        // 1-urinish sanasini belgilash uchun ushbu rolga ruxsat etilgan kurs darajalari
        $attempt1Levels = $isAdmin
            ? array_keys(ExamDateRoleService::getMapping())
            : ExamDateRoleService::levelsForRole($activeRole);
        // 2+ urinish (12a/12b resit) sanalarini faqat registrator_ofisi (yoki admin) qo'yadi
        $canEditResit = ExamDateRoleService::canEditResit($activeRole);

        $currentSemesters = Semester::where('current', true)->get();
        $currentEducationYear = $currentSemesters->first()?->education_year;

        // Filtrlar
        $selectedEducationType = $request->get('education_type');
        $selectedDepartment = $request->get('department_id');
        $selectedSpecialty = $request->get('specialty_id');
        $selectedLevelCode = $request->get('level_code');

        $selectedSemester = $request->get('semester_code');
        $selectedGroup = $request->get('group_id');
        $selectedSubject = $request->get('subject_id');
        $selectedStatus = $request->get('status');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $currentSemesterToggle = $request->get('current_semester', '1');
        // Agar foydalanuvchi faqat "gacha" sanani bersa va "Joriy semestr" toggle yoqilgan bo'lsa,
        // "dan" sanani joriy semestr boshlanish sanasidan boshlaymiz —
        // shunda eski semestrlardagi tugagan darslar ro'yxatga tushib qolmaydi.
        if (empty($dateFrom) && !empty($dateTo) && $currentSemesterToggle === '1') {
            try {
                $currentSemesters = Semester::where('current', true)->get();
                $semHemisIds = $currentSemesters->pluck('semester_hemis_id')->filter()->toArray();
                $semCodes = $currentSemesters->pluck('code')->filter()->toArray();

                $earliestStartDate = null;
                // 1) HEMIS API curriculum_weeks (eng aniq manba)
                if (!empty($semHemisIds)) {
                    $earliestStartDate = DB::table('curriculum_weeks')
                        ->whereIn('semester_hemis_id', $semHemisIds)
                        ->min('start_date');
                }
                // 2) schedules.lesson_date — joriy semestr kodlari bo'yicha
                if (!$earliestStartDate && !empty($semCodes)) {
                    $earliestStartDate = DB::table('schedules')
                        ->whereIn('semester_code', $semCodes)
                        ->whereNotNull('lesson_date')
                        ->whereNull('deleted_at')
                        ->min('lesson_date');
                }
                // 3) date_to ga ko'ra mantiqiy minimum: gacha sananing 6 oy oldingisi
                if (!$earliestStartDate) {
                    $earliestStartDate = \Carbon\Carbon::parse($dateTo)->subMonths(6)->format('Y-m-d');
                }

                if ($earliestStartDate) {
                    $dateFrom = substr($earliestStartDate, 0, 10);
                }
            } catch (\Throwable $e) {
                // semestr boshlanish sanasini topa olmasak filtrsiz qoldiramiz
            }
        }
        $oskiDateFrom = $request->get('oski_date_from');
        $oskiDateTo = $request->get('oski_date_to');
        $testDateFrom = $request->get('test_date_from');
        $testDateTo = $request->get('test_date_to');
        $selectedClosingForm = $request->get('closing_form'); // '', 'unset', 'oski', 'test', 'oski_test', 'normativ', 'sinov', 'none'
        $showStudents = $request->get('show_students') === '1';
        $urinishFilter = $request->get('urinish'); // '1', '2', '3' yoki null (barchasi)
        $isSearched = $request->has('searched');

        // Fanlar ro'yxati va jadval ma'lumotlari
        $scheduleData = collect();
        if ($isSearched) {
            $scheduleData = $this->loadScheduleData(
                $currentSemesters, $selectedDepartment, $selectedSpecialty,
                $selectedSemester, $selectedGroup, $selectedEducationType,
                $selectedLevelCode, $selectedSubject, $selectedStatus,
                $currentSemesterToggle, false, $dateFrom, $dateTo
            );


            // OSKI sanasi bo'yicha filtr
            if ($oskiDateFrom || $oskiDateTo) {
                $scheduleData = $scheduleData->map(function ($items) use ($oskiDateFrom, $oskiDateTo) {
                    return $items->filter(function ($item) use ($oskiDateFrom, $oskiDateTo) {
                        $d = $item['oski_date'];
                        if (!$d) return false;
                        if ($oskiDateFrom && $d < $oskiDateFrom) return false;
                        if ($oskiDateTo && $d > $oskiDateTo) return false;
                        return true;
                    });
                })->filter(fn($items) => $items->isNotEmpty());
            }

            // Test sanasi bo'yicha filtr
            if ($testDateFrom || $testDateTo) {
                $scheduleData = $scheduleData->map(function ($items) use ($testDateFrom, $testDateTo) {
                    return $items->filter(function ($item) use ($testDateFrom, $testDateTo) {
                        $d = $item['test_date'];
                        if (!$d) return false;
                        if ($testDateFrom && $d < $testDateFrom) return false;
                        if ($testDateTo && $d > $testDateTo) return false;
                        return true;
                    });
                })->filter(fn($items) => $items->isNotEmpty());
            }

            // Yopilish shakli bo'yicha filtr
            if (!empty($selectedClosingForm)) {
                $scheduleData = $scheduleData->map(function ($items) use ($selectedClosingForm) {
                    return $items->filter(function ($item) use ($selectedClosingForm) {
                        $cf = $item['closing_form'] ?? null;
                        if ($selectedClosingForm === 'unset') {
                            return $cf === null;
                        }
                        return $cf === $selectedClosingForm;
                    });
                })->filter(fn($items) => $items->isNotEmpty());
            }
        }

        $routePrefix = $this->routePrefix();
        $canDelete = $isAdmin;
        // Edit huquqi: admin yoki sozlamalarda biror kurs uchun 1-urinish ruxsati,
        // yoki 2+ urinish uchun registrator_ofisi
        $canEdit = $canDelete || !empty($attempt1Levels) || $canEditResit;

        // Talabani ko'rsatish toggle yoqilgan bo'lsa — har guruh+fan uchun
        // talabalar ro'yxati va ularning shaxsiy resit/resit2 sanalarini olish
        if ($showStudents && $isSearched) {
            $scheduleData = $this->attachStudentsToSchedule($scheduleData);
        }

        // Har bir item ni urinish bo'yicha kengaytirib, alohida virtual qatorlarga aylantirish.
        // Bu YN kunini belgilash sahifasida 1-urinish / 2-urinish / 3-urinish ro'yxatda alohida qatorlarda paydo bo'ladi.
        if ($isSearched) {
            $scheduleData = $this->expandByUrinish($scheduleData, $urinishFilter);
        }

        // Test markazi yuklanganligi statistikasi — bo'sh kunlarni tanlash uchun
        $testCenterLoad = [];
        $testCenterCapacity = 0;
        if ($isSearched) {
            $loadFrom = $oskiDateFrom ?: ($testDateFrom ?: $dateFrom);
            $loadTo = $oskiDateTo ?: ($testDateTo ?: $dateTo);
            // Filter to'liq belgilanmagan bo'lsa, bugun + 60 kun ko'rsatamiz
            if (!$loadFrom) $loadFrom = now()->toDateString();
            if (!$loadTo) $loadTo = now()->addDays(60)->toDateString();
            $loadInfo = $this->computeTestCenterLoad($loadFrom, $loadTo);
            $testCenterLoad = $loadInfo['days'];
            $testCenterCapacity = $loadInfo['capacity'];
        }

        return view('admin.academic-schedule.index', compact(
            'scheduleData',
            'selectedEducationType',
            'selectedDepartment',
            'selectedSpecialty',
            'selectedLevelCode',
            'selectedSemester',
            'selectedGroup',
            'selectedSubject',
            'selectedStatus',
            'dateFrom',
            'dateTo',
            'oskiDateFrom',
            'oskiDateTo',
            'testDateFrom',
            'testDateTo',
            'selectedClosingForm',
            'currentSemesterToggle',
            'showStudents',
            'urinishFilter',
            'isSearched',
            'currentEducationYear',
            'routePrefix',
            'canDelete',
            'canEdit',
            'attempt1Levels',
            'canEditResit',
            'isAdmin',
            'activeRole',
            'testCenterLoad',
            'testCenterCapacity',
        ));
    }

    /**
     * Berilgan sana oraliq uchun test markazi yuklanganligini hisoblaydi.
     * Har bir kun uchun: rejalashtirilgan OSKI/Test sonlari va guruhlar.
     * Bo'sh kunlarni tanlashga yordam berish uchun.
     */
    private function computeTestCenterLoad(string $dateFrom, string $dateTo): array
    {
        try {
            $from = \Carbon\Carbon::parse($dateFrom)->format('Y-m-d');
            $to = \Carbon\Carbon::parse($dateTo)->format('Y-m-d');
        } catch (\Throwable $e) {
            return ['days' => [], 'capacity' => 0];
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        // Juda uzoq oraliq bo'lsa, 120 kun bilan cheklaymiz
        $maxTo = \Carbon\Carbon::parse($from)->addDays(120)->format('Y-m-d');
        if ($to > $maxTo) $to = $maxTo;

        $capacity = (int) (ExamCapacityService::getSettings()['computer_count'] ?? 0);

        // Asosiy guruh sanalarini olish
        $rows = ExamSchedule::where(function ($q) use ($from, $to) {
                $q->where(function ($q2) use ($from, $to) {
                    $q2->whereNotNull('oski_date')
                       ->where('oski_na', false)
                       ->whereBetween('oski_date', [$from, $to]);
                })->orWhere(function ($q2) use ($from, $to) {
                    $q2->whereNotNull('test_date')
                       ->where('test_na', false)
                       ->whereBetween('test_date', [$from, $to]);
                })->orWhere(function ($q2) use ($from, $to) {
                    $q2->whereNotNull('oski_resit_date')
                       ->whereBetween('oski_resit_date', [$from, $to]);
                })->orWhere(function ($q2) use ($from, $to) {
                    $q2->whereNotNull('test_resit_date')
                       ->whereBetween('test_resit_date', [$from, $to]);
                })->orWhere(function ($q2) use ($from, $to) {
                    $q2->whereNotNull('oski_resit2_date')
                       ->whereBetween('oski_resit2_date', [$from, $to]);
                })->orWhere(function ($q2) use ($from, $to) {
                    $q2->whereNotNull('test_resit2_date')
                       ->whereBetween('test_resit2_date', [$from, $to]);
                });
            })
            ->get();

        // Guruhdagi talabalar soni — yuklanish indikatorida talaba sonini ham ko'rsatish uchun
        $groupHemisIds = $rows->pluck('group_hemis_id')->filter()->unique()->values()->toArray();
        $groupSizes = [];
        if (!empty($groupHemisIds)) {
            try {
                $groupSizes = DB::table('students')
                    ->whereIn('group_id', $groupHemisIds)
                    ->where('student_status_code', 11)
                    ->groupBy('group_id')
                    ->select('group_id', DB::raw('COUNT(*) as cnt'))
                    ->pluck('cnt', 'group_id')
                    ->toArray();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $byDate = [];
        $addEvent = function (?string $date, string $type, $row, ?int $stuCount) use (&$byDate, $from, $to) {
            if (!$date) return;
            if ($date < $from || $date > $to) return;
            $byDate[$date]['oski'] = $byDate[$date]['oski'] ?? 0;
            $byDate[$date]['test'] = $byDate[$date]['test'] ?? 0;
            $byDate[$date]['groups'] = $byDate[$date]['groups'] ?? [];
            $byDate[$date]['student_count'] = $byDate[$date]['student_count'] ?? 0;
            $byDate[$date][$type]++;
            $byDate[$date]['groups'][$row->group_hemis_id] = true;
            $byDate[$date]['student_count'] += (int) ($stuCount ?? 0);
        };

        foreach ($rows as $r) {
            $stuCount = $groupSizes[$r->group_hemis_id] ?? 0;
            $oskiD = $r->oski_date?->format('Y-m-d');
            if ($oskiD && !$r->oski_na) $addEvent($oskiD, 'oski', $r, $stuCount);
            $testD = $r->test_date?->format('Y-m-d');
            if ($testD && !$r->test_na) $addEvent($testD, 'test', $r, $stuCount);
            $addEvent($r->oski_resit_date?->format('Y-m-d'), 'oski', $r, null);
            $addEvent($r->test_resit_date?->format('Y-m-d'), 'test', $r, null);
            $addEvent($r->oski_resit2_date?->format('Y-m-d'), 'oski', $r, null);
            $addEvent($r->test_resit2_date?->format('Y-m-d'), 'test', $r, null);
        }

        // Sanalar oraliqdagi har kun uchun (bo'sh bo'lsa ham) yozuv qo'shish
        $days = [];
        $cursor = \Carbon\Carbon::parse($from);
        $end = \Carbon\Carbon::parse($to);
        while ($cursor->lte($end)) {
            $d = $cursor->format('Y-m-d');
            $info = $byDate[$d] ?? ['oski' => 0, 'test' => 0, 'groups' => [], 'student_count' => 0];
            $days[] = [
                'date' => $d,
                'weekday' => $cursor->isoFormat('dd'),
                'is_weekend' => in_array($cursor->dayOfWeek, [0, 6], true),
                'oski_count' => $info['oski'],
                'test_count' => $info['test'],
                'group_count' => count($info['groups']),
                'student_count' => $info['student_count'],
                'total' => $info['oski'] + $info['test'],
            ];
            $cursor->addDay();
        }

        return ['days' => $days, 'capacity' => $capacity];
    }

    /**
     * Har guruh+fan uchun talabalar ro'yxatini va ularning shaxsiy
     * resit/resit2 sanalarini olib biriktirish.
     */
    private function attachStudentsToSchedule($scheduleData)
    {
        $hasStudentCol = \Illuminate\Support\Facades\Schema::hasColumn('exam_schedules', 'student_hemis_id');
        if (!$hasStudentCol) {
            return $scheduleData;
        }

        $allGroupHemisIds = $scheduleData->flatMap(fn($items) => $items->pluck('group')->pluck('group_hemis_id'))
            ->unique()->values()->toArray();
        if (empty($allGroupHemisIds)) return $scheduleData;

        // Sahifada ko'rinayotgan fanlar va semestrlar — per-student
        // yozuvlarni shu kombinatsiya bo'yicha cheklash uchun. Aks holda
        // boshqa fan/semestr yozuvlari ham xotiraga olinadi va kalit
        // (group|subject|semester|student) bo'yicha mos kelmasa, qator
        // sahifada ko'rinmay qolishi mumkin.
        $allSubjectIds = $scheduleData->flatMap(fn($items) => $items->pluck('subject')->pluck('subject_id'))
            ->filter()->unique()->values()->toArray();
        $allSemesterCodes = $scheduleData->flatMap(fn($items) => $items->pluck('subject')->pluck('semester_code'))
            ->filter()->unique()->values()->toArray();

        $studentsByGroup = DB::table('students')
            ->whereIn('group_id', $allGroupHemisIds)
            ->where('student_status_code', 11)
            ->select('hemis_id', 'student_id_number', 'full_name', 'group_id')
            ->orderBy('full_name')
            ->get()
            ->groupBy('group_id');

        // Per-student exam_schedules yozuvlarini olish (student_hemis_id NOT NULL).
        // Subject va semester filtrlari muhim — keng so'rov sahifa pastdagi
        // talaba qatorida noto'g'ri yozuvni tanlashga olib kelishi mumkin edi.
        $perStudentMap = [];
        $perStudentQuery = ExamSchedule::whereNotNull('student_hemis_id')
            ->whereIn('group_hemis_id', $allGroupHemisIds);
        if (!empty($allSubjectIds)) {
            $perStudentQuery->whereIn('subject_id', $allSubjectIds);
        }
        if (!empty($allSemesterCodes)) {
            $perStudentQuery->whereIn('semester_code', $allSemesterCodes);
        }
        $perStudentRows = $perStudentQuery->get();
        foreach ($perStudentRows as $row) {
            $key = $row->group_hemis_id . '|' . $row->subject_id . '|' . $row->semester_code . '|' . $row->student_hemis_id;
            $perStudentMap[$key] = $row;
        }

        // Per-student urinish-status: jn/mt/oski/test/davomat asosida — 2/3-urinish ro'yxatda
        // kim "yiqilgan" va kim "pullik" ekanligini bilish uchun.
        $studentStatus = $this->computeStudentAttemptStatuses($scheduleData);

        // O'tgan semestrlardagi qarz fanlari ro'yxatini hisoblash
        // (>4 qarzdorlar menyusidagi logika asosida — academic_records'da yo'q fanlar)
        $allHemisIds = $studentsByGroup->flatten()->pluck('hemis_id')->unique()->values()->toArray();
        $pastDebtsMap = $this->computeStudentPastSemesterDebts($allHemisIds);

        // Aniq signal: qaysi talabaga student_grades'da attempt=2 yoki attempt=3 yozuvi bor.
        // Bu jurnaldan qo'lda 12a/12b shakliga o'tkazilgan, lekin bahosi hali NULL bo'lgan
        // talabalarni topish uchun. failed_attempt1 V<60 ga asoslangan, lekin OSKI/Test
        // baholari bo'sh bo'lsa V hisoblanmaydi.
        $hasGradeAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');
        $explicitAttemptByStudent = []; // [hemis|subject|sem] => ['attempt2' => bool, 'attempt3' => bool]
        if ($hasGradeAttemptCol && !empty($allHemisIds)) {
            try {
                $rows = DB::table('student_grades')
                    ->whereIn('student_hemis_id', $allHemisIds)
                    ->whereIn('training_type_code', [101, 102])
                    ->whereIn('attempt', [2, 3])
                    ->whereNull('deleted_at')
                    ->select('student_hemis_id', 'subject_id', 'semester_code', 'attempt')
                    ->distinct()
                    ->get();
                foreach ($rows as $r) {
                    $k = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                    if ((int) $r->attempt === 2) $explicitAttemptByStudent[$k]['attempt2'] = true;
                    if ((int) $r->attempt === 3) $explicitAttemptByStudent[$k]['attempt3'] = true;
                }
            } catch (\Throwable $e) {
                \Log::warning('explicit attempt lookup failed: ' . $e->getMessage());
            }
        }

        // "Qatnashmagan" mantiq:
        //   YN sanasi o'tib, guruhdagi BIROR talabaga OSKI/Test bahosi tushgan bo'lsa,
        //   demak shu kuni imtihon o'tgan. Bahosi tushmagan talaba — qatnashmagan,
        //   shu sababli avtomatik 2-urinishga o'tkaziladi.
        // Bu yerda: (group|subject|sem) bo'yicha attempt=1 baholari mavjud talabalar ro'yxati.
        $attempt1OskiByKey = []; // group|subj|sem => [hemis_id => true]
        $attempt1TestByKey = [];
        if (!empty($allHemisIds)) {
            try {
                $q = DB::table('student_grades as sg')
                    ->whereIn('sg.student_hemis_id', $allHemisIds)
                    ->whereIn('sg.training_type_code', [101, 102])
                    ->whereNotNull('sg.grade')
                    ->whereNull('sg.deleted_at');
                if ($hasGradeAttemptCol) {
                    $q->where(function ($qq) {
                        $qq->where('sg.attempt', 1)->orWhereNull('sg.attempt');
                    });
                }
                $rows = $q->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                    ->select('st.group_id', 'sg.subject_id', 'sg.semester_code', 'sg.training_type_code', 'sg.student_hemis_id')
                    ->distinct()
                    ->get();
                foreach ($rows as $r) {
                    $k = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code;
                    if ((int) $r->training_type_code === 101) {
                        $attempt1OskiByKey[$k][$r->student_hemis_id] = true;
                    } elseif ((int) $r->training_type_code === 102) {
                        $attempt1TestByKey[$k][$r->student_hemis_id] = true;
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('attempt1 grade lookup failed: ' . $e->getMessage());
            }
        }
        $today = now()->toDateString();

        // Birinchi pass: har bir talabaning joriy semestrdagi qarz fanlari ro'yxatini
        // butun scheduleData bo'ylab yig'amiz. Aks holda har row faqat o'z fanini
        // hisoblaydi va talabaning bir nechta fandan qarzdorligi to'g'ri ko'rsatilmaydi.
        // currentDebtsByStudent[hemis_id] = [subject_id|semester_code => ['subject_name', 'semester_name']]
        $currentDebtsByStudent = [];
        foreach ($scheduleData as $itemsBatch) {
            foreach ($itemsBatch as $itm) {
                $itGHid = $itm['group']->group_hemis_id ?? null;
                $itSubj = $itm['subject']->subject_id ?? null;
                $itSem = $itm['subject']->semester_code ?? null;
                if (!$itGHid || !$itSubj || !$itSem) continue;
                $itStatusKey = $itGHid . '|' . $itSubj . '|' . $itSem;
                $itStatusByStudent = $studentStatus[$itStatusKey] ?? [];
                $itGroupKey = $itGHid . '|' . $itSubj . '|' . $itSem;
                $itOskiMap = $attempt1OskiByKey[$itGroupKey] ?? [];
                $itTestMap = $attempt1TestByKey[$itGroupKey] ?? [];
                $itOskiDate = $itm['oski_date'] ?? null;
                $itTestDate = $itm['test_date'] ?? null;
                $itLessonEnd = $itm['lesson_end_date'] ?? null;
                $itEffOski = $itOskiDate ?: $itLessonEnd;
                $itEffTest = $itTestDate ?: $itLessonEnd;
                $itOskiPassed = $itEffOski && $itEffOski < $today && !empty($itOskiMap);
                $itTestPassed = $itEffTest && $itEffTest < $today && !empty($itTestMap);
                $itStuList = $studentsByGroup->get($itGHid, collect());
                foreach ($itStuList as $st) {
                    $stat = $itStatusByStudent[$st->hemis_id] ?? ['failed1' => false];
                    $missedO = $itOskiPassed && empty($itOskiMap[$st->hemis_id]);
                    $missedT = $itTestPassed && empty($itTestMap[$st->hemis_id]);
                    $explicitK = $st->hemis_id . '|' . $itSubj . '|' . $itSem;
                    $hasA2 = !empty($explicitAttemptByStudent[$explicitK]['attempt2']);
                    $eff1 = $stat['failed1'] || $missedO || $missedT || $hasA2;
                    if ($eff1) {
                        $debtKey = $itSubj . '|' . $itSem;
                        if (!isset($currentDebtsByStudent[$st->hemis_id][$debtKey])) {
                            $currentDebtsByStudent[$st->hemis_id][$debtKey] = [
                                'subject_name' => $itm['subject']->subject_name ?? '',
                                'semester_name' => $itm['subject']->semester_name ?? '',
                                'semester_code' => (int) $itSem,
                            ];
                        }
                    }
                }
            }
        }

        return $scheduleData->map(function ($items) use ($studentsByGroup, $perStudentMap, $studentStatus, $pastDebtsMap, $explicitAttemptByStudent, $attempt1OskiByKey, $attempt1TestByKey, $today, $currentDebtsByStudent) {
            return $items->map(function ($item) use ($studentsByGroup, $perStudentMap, $studentStatus, $pastDebtsMap, $explicitAttemptByStudent, $attempt1OskiByKey, $attempt1TestByKey, $today, $currentDebtsByStudent) {
                $gHid = $item['group']->group_hemis_id;
                $subjectId = $item['subject']->subject_id ?? null;
                $semCode = $item['subject']->semester_code ?? null;
                $statusKey = $gHid . '|' . $subjectId . '|' . $semCode;
                $statusByStudent = $studentStatus[$statusKey] ?? [];
                $studentList = $studentsByGroup->get($gHid, collect());

                // Guruhning OSKI/Test sanasi (asosiy schedule, student_hemis_id NULL).
                // Sinov (test) / Normativ kabi closing_formlar uchun sana saqlanmaydi —
                // bu holatda dars tugash sanasini ($item['lesson_end_date']) proxy sifatida olamiz.
                $groupKeyMain = $gHid . '|' . $subjectId . '|' . $semCode;
                $oskiGradeMap = $attempt1OskiByKey[$groupKeyMain] ?? [];
                $testGradeMap = $attempt1TestByKey[$groupKeyMain] ?? [];
                $groupOskiDate = $item['oski_date'] ?? null;
                $groupTestDate = $item['test_date'] ?? null;
                $lessonEnd = $item['lesson_end_date'] ?? null;
                $effOskiDate = $groupOskiDate ?: $lessonEnd;
                $effTestDate = $groupTestDate ?: $lessonEnd;
                $oskiPassed = $effOskiDate && $effOskiDate < $today && !empty($oskiGradeMap);
                $testPassed = $effTestDate && $effTestDate < $today && !empty($testGradeMap);

                $rows = [];
                foreach ($studentList as $stu) {
                    $key = $gHid . '|' . $subjectId . '|' . $semCode . '|' . $stu->hemis_id;
                    $perRow = $perStudentMap[$key] ?? null;
                    $stat = $statusByStudent[$stu->hemis_id] ?? ['failed1' => false, 'failed2' => false, 'pullik' => false, 'held_back' => false];
                    // Talaba qatnashmaganmi: guruhda imtihon o'tgan (boshqalar baho olgan)
                    // lekin shu talabaga baho tushmagan — qatnashmagan, 2-urinishga o'tkaziladi
                    $missedOski = $oskiPassed && empty($oskiGradeMap[$stu->hemis_id]);
                    $missedTest = $testPassed && empty($testGradeMap[$stu->hemis_id]);
                    $didNotAttend = $missedOski || $missedTest;
                    $explicitKey = $stu->hemis_id . '|' . $subjectId . '|' . $semCode;
                    $hasAttempt2 = !empty($explicitAttemptByStudent[$explicitKey]['attempt2']);
                    $hasAttempt3 = !empty($explicitAttemptByStudent[$explicitKey]['attempt3']);
                    // [DEBUG] Vaqtinchalik — BAHOROV (5634) ning Bolalar xirurgiyasi (260) status
                    if ((int) $stu->hemis_id === 5634 && (int) $subjectId === 260) {
                        \Log::info('DBG_BAHOROV', [
                            'gHid' => $gHid, 'subj' => $subjectId, 'sem' => $semCode,
                            'statusByStudent_keys' => array_keys($statusByStudent),
                            'stat_for_5634' => $stat,
                            'is_pullik_will_be' => $stat['pullik'],
                        ]);
                    }
                    $pastDebts = $pastDebtsMap[$stu->hemis_id] ?? [];
                    // Yagona "1-urinishdan o'tmadi" signali — quyidagilardan biri:
                    //   - V<60 (failed_attempt1)
                    //   - Pullik
                    //   - Imtihon kuni qatnashmagan (boshqalar baho olgan, talabaga yo'q)
                    //   - student_grades.attempt=2 yozuvi mavjud (qo'lda 12a ga o'tkazilgan)
                    $effectiveFailed1 = $stat['failed1'] || $didNotAttend || $hasAttempt2;
                    $effectiveFailed2 = $stat['failed2'] || $hasAttempt3;
                    // Joriy semestrdagi BARCHA qarz fanlari (barcha rowlar bo'yicha)
                    $currentDebts = array_values($currentDebtsByStudent[$stu->hemis_id] ?? []);
                    usort($currentDebts, fn($a, $b) => $a['semester_code'] <=> $b['semester_code']);
                    $rows[] = [
                        'hemis_id' => $stu->hemis_id,
                        'student_id_number' => $stu->student_id_number ?? null,
                        'full_name' => $stu->full_name,
                        'oski_resit_date' => $perRow?->oski_resit_date?->format('Y-m-d'),
                        'oski_resit_time' => $perRow?->oski_resit_time,
                        'oski_resit2_date' => $perRow?->oski_resit2_date?->format('Y-m-d'),
                        'oski_resit2_time' => $perRow?->oski_resit2_time,
                        'test_resit_date' => $perRow?->test_resit_date?->format('Y-m-d'),
                        'test_resit_time' => $perRow?->test_resit_time,
                        'test_resit2_date' => $perRow?->test_resit2_date?->format('Y-m-d'),
                        'test_resit2_time' => $perRow?->test_resit2_time,
                        'failed_attempt1' => $effectiveFailed1,
                        'failed_attempt2' => $effectiveFailed2,
                        'did_not_attend' => $didNotAttend,
                        'has_attempt2_grade' => $hasAttempt2,
                        'has_attempt3_grade' => $hasAttempt3,
                        'is_pullik' => $stat['pullik'],
                        'is_held_back' => $stat['held_back'] ?? false,
                        'past_debts' => $pastDebts,
                        'current_semester_debts' => $currentDebts,
                    ];
                }
                $item['students'] = $rows;
                return $item;
            });
        });
    }

    /**
     * Talabalarning o'tgan semestrlardagi qarz fanlari ro'yxati.
     * "Qarz" = curriculum_subjects'da bor lekin academic_records'da yozuvi yo'q fanlar.
     * Joriy semestr KIRITILMAYDI — joriy semestrdagi "qarz" status jurnal asosida
     * (failed_attempt1 flagi orqali) alohida hisoblanadi.
     *
     * @param array $studentHemisIds
     * @return array [hemis_id => [['subject_name' => ..., 'semester_name' => ..., 'semester_code' => ...], ...]]
     */
    private function computeStudentPastSemesterDebts(array $studentHemisIds): array
    {
        if (empty($studentHemisIds)) return [];

        $result = [];

        // Talaba ma'lumotlari (curriculum + joriy semestr)
        $students = DB::table('students')
            ->whereIn('hemis_id', $studentHemisIds)
            ->whereNotNull('curriculum_id')
            ->select('hemis_id', 'curriculum_id', 'semester_code', 'group_name')
            ->get();
        if ($students->isEmpty()) return [];

        // Har bir talabaning academic_records yozuvlari (qarz emasligini bilish uchun)
        // va tarixiy curriculum_id (transferdan keyingi rejani topish uchun)
        $arRecords = [];
        foreach (array_chunk($studentHemisIds, 1000) as $chunk) {
            $arRecords = array_merge($arRecords, DB::table('academic_records')
                ->whereIn('student_id', $chunk)
                ->select('student_id', 'subject_id', 'semester_id', 'curriculum_id')
                ->get()
                ->all());
        }
        $arExistsLookup = []; // hemis_id|subject_id|semester_id => true
        $studentSemCurr = []; // hemis_id => [semester_id => curriculum_id]
        foreach ($arRecords as $ar) {
            $arExistsLookup[$ar->student_id . '|' . $ar->subject_id . '|' . $ar->semester_id] = true;
            if (!isset($studentSemCurr[$ar->student_id][$ar->semester_id]) && $ar->curriculum_id) {
                $studentSemCurr[$ar->student_id][$ar->semester_id] = $ar->curriculum_id;
            }
        }
        unset($arRecords);

        // (curriculum_id, semester_code) juftliklari
        $curriculumPairs = []; // 'curr_id|sem' => true
        foreach ($students as $st) {
            $studentSemCode = $st->semester_code ? (int) $st->semester_code : null;
            foreach ($studentSemCurr[$st->hemis_id] ?? [] as $semCode => $currId) {
                if (!$studentSemCode || (int) $semCode < $studentSemCode) {
                    $curriculumPairs[$currId . '|' . $semCode] = true;
                }
            }
        }
        if (empty($curriculumPairs)) return [];

        $allCurriculumIds = collect(array_keys($curriculumPairs))
            ->map(fn($k) => explode('|', $k)[0])->unique()->values()->all();
        $allSemCodes = collect(array_keys($curriculumPairs))
            ->map(fn($k) => explode('|', $k)[1])->unique()->values()->all();

        $currSubjectsQuery = DB::table('curriculum_subjects as cs')
            ->whereIn('cs.curricula_hemis_id', $allCurriculumIds ?: [0])
            ->whereIn('cs.semester_code', $allSemCodes ?: [0])
            ->where('cs.is_active', 1)
            ->where(function ($q) {
                $q->whereNull('cs.in_group')->orWhere('cs.in_group', '');
            })
            ->select(
                'cs.curricula_hemis_id', 'cs.curriculum_subject_hemis_id',
                'cs.semester_code', 'cs.semester_name',
                'cs.subject_id', 'cs.subject_name', 'cs.subject_type_code'
            )
            ->distinct();

        $excludedPatterns = config('app.excluded_rating_subject_patterns', []);
        foreach ($excludedPatterns as $pattern) {
            $currSubjectsQuery->where('cs.subject_name', 'NOT LIKE', "%{$pattern}%");
        }
        $currSubjects = $currSubjectsQuery->get();
        $subjectsByPair = $currSubjects->groupBy(fn($s) => $s->curricula_hemis_id . '|' . $s->semester_code);

        // Tanlov fanlari (subject_type_code=12) — talaba tanlagan fan id ni olish
        $tanlovCsHemisIds = $currSubjects->where('subject_type_code', '12')
            ->pluck('curriculum_subject_hemis_id')->filter()->unique()->values()->toArray();
        $tanlovPicksMap = [];
        if (!empty($tanlovCsHemisIds)) {
            $tanlovPicks = DB::table('student_subjects')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->whereIn('curriculum_subject_hemis_id', $tanlovCsHemisIds)
                ->select('student_hemis_id', 'curriculum_subject_hemis_id', 'subject_id', 'subject_name')
                ->get();
            foreach ($tanlovPicks as $tp) {
                $tanlovPicksMap[$tp->student_hemis_id . '|' . $tp->curriculum_subject_hemis_id] = [
                    'subject_id'   => $tp->subject_id,
                    'subject_name' => $tp->subject_name,
                ];
            }
        }

        // Har bir talaba uchun qarz fanlarini yig'ish
        foreach ($students as $st) {
            $studentSemCode = $st->semester_code ? (int) $st->semester_code : null;
            $debts = [];
            foreach ($studentSemCurr[$st->hemis_id] ?? [] as $semCode => $currId) {
                if ($studentSemCode && (int) $semCode >= $studentSemCode) continue;

                $subjectsForSem = $subjectsByPair->get($currId . '|' . $semCode, collect());
                $subjectsForSem = $this->filterSubjectsByGroupSuffixSimple($subjectsForSem, $st->group_name ?? '');

                foreach ($subjectsForSem as $sub) {
                    $effectiveSubjectId = $sub->subject_id;
                    $effectiveSubjectName = $sub->subject_name;
                    if ((string) $sub->subject_type_code === '12') {
                        $picked = $tanlovPicksMap[$st->hemis_id . '|' . $sub->curriculum_subject_hemis_id] ?? null;
                        if ($picked) {
                            $effectiveSubjectId = $picked['subject_id'];
                            $effectiveSubjectName = $picked['subject_name'];
                        } else {
                            continue;
                        }
                    }

                    $arKey = $st->hemis_id . '|' . $effectiveSubjectId . '|' . $sub->semester_code;
                    if (isset($arExistsLookup[$arKey])) continue;

                    $debts[] = [
                        'subject_name' => $effectiveSubjectName,
                        'semester_name' => $sub->semester_name,
                        'semester_code' => (int) $sub->semester_code,
                    ];
                }
            }

            // Semestr bo'yicha tartiblash
            usort($debts, fn($a, $b) => $a['semester_code'] <=> $b['semester_code']);
            $result[$st->hemis_id] = $debts;
        }

        return $result;
    }

    /**
     * Guruh suffiksiga qarab fan variantlarini filtrlash (ReportController logikasi nusxasi).
     */
    private function filterSubjectsByGroupSuffixSimple($records, string $groupName)
    {
        if (empty($groupName)) return $records;
        $groupSuffix = '';
        if (preg_match('/(\d+)([a-zA-Z])$/', trim($groupName), $m)) {
            $groupSuffix = mb_strtolower($m[2]);
        }
        if (empty($groupSuffix)) return $records;

        return $records->filter(function ($record) use ($groupSuffix) {
            $name = $record->subject_name ?? '';
            if (preg_match('/\(([a-zA-Zа-яА-Я])\)\s*$/u', $name, $m)) {
                return mb_strtolower($m[1]) === $groupSuffix;
            }
            return true;
        })->values();
    }

    /**
     * Har bir guruh+fan+talaba uchun urinish statusini hisoblash.
     * Qaytaradi: [groupHid|subjId|semCode => [studentHid => ['failed1'=>bool, 'failed2'=>bool, 'pullik'=>bool]]]
     */
    private function computeStudentAttemptStatuses($scheduleData): array
    {
        $result = [];
        $hasAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');
        $minLimit = 60;

        // Har item uchun (group, subject, semester) uchligini yig'amiz
        $triples = [];
        $scheduleData->each(function ($items) use (&$triples) {
            foreach ($items as $it) {
                $g = $it['group']->group_hemis_id ?? null;
                $s = $it['subject']->subject_id ?? null;
                $sem = $it['subject']->semester_code ?? null;
                if ($g && $s && $sem) {
                    $triples[$g . '|' . $s . '|' . $sem] = [$g, $s, $sem];
                }
            }
        });

        if (empty($triples)) return $result;

        $allGroupHids = array_unique(array_column($triples, 0));
        $allSubjectIds = array_unique(array_column($triples, 1));
        $allSemCodes = array_unique(array_column($triples, 2));

        // Ko'rinadigan triples (status qaytarish uchun) va kengaytirilgan triples
        // (4+ qarz qoidasi uchun, butun o'quv yili bo'yicha sanash kerak).
        $visibleTriples = $triples;

        // semester_code → education_year xaritasi
        $semYearMap = [];
        try {
            $rows = DB::table('semesters')
                ->whereIn('semester_code', $allSemCodes)
                ->select('semester_code', 'education_year')
                ->get();
            foreach ($rows as $r) $semYearMap[$r->semester_code] = $r->education_year;
        } catch (\Throwable $e) {}
        $relevantYears = array_values(array_unique(array_filter($semYearMap)));

        // O'quv yiliga kiruvchi BARCHA semesterlarni olish (boshqa semestrlardagi
        // qarzlarni hisoblash uchun)
        $yearSemCodes = $allSemCodes;
        if (!empty($relevantYears)) {
            try {
                $extraSems = DB::table('semesters')
                    ->whereIn('education_year', $relevantYears)
                    ->pluck('semester_code')
                    ->toArray();
                foreach ($extraSems as $code) {
                    if (!isset($semYearMap[$code])) {
                        // map qaytadan to'ldirish
                        $semYearMap[$code] = $relevantYears[0] ?? null;
                    }
                }
                $yearSemCodes = array_values(array_unique(array_merge($yearSemCodes, $extraSems)));
                // Aniq xaritalash uchun yana bir marta o'qiymiz
                $rows = DB::table('semesters')
                    ->whereIn('semester_code', $yearSemCodes)
                    ->select('semester_code', 'education_year')
                    ->get();
                foreach ($rows as $r) $semYearMap[$r->semester_code] = $r->education_year;
            } catch (\Throwable $e) {}
        }

        // Eslatma: o'quv yiliga to'liq kengaytirish (yashirin semestrlar uchun)
        // og'ir SQL so'rovlariga aylanadi va sahifa 504 berib qoladi. Hozircha
        // ko'rinadigan triples ustida ishlaymiz — agar foydalanuvchi yiliga
        // tegishli har ikki semestrni filtr orqali yuklasa, hisob to'g'ri bo'ladi.

        // Talabalarning hemis_id va group_id xaritasi (faqat faol talabalar)
        $studentGroup = DB::table('students')
            ->whereIn('group_id', $allGroupHids)
            ->where('student_status_code', 11)
            ->pluck('group_id', 'hemis_id')
            ->toArray();
        $allStudentHids = array_keys($studentGroup);
        if (empty($allStudentHids)) return $result;

        // exam_schedules dan oski_na/test_na va sanalarini olish (asosiy schedule, student_hemis_id NULL).
        // Sanalar 1-urinish/2-urinish "muddati tugaganmi" tekshiruvi uchun kerak.
        $naMap = []; // group|subj|sem => ['oski_na'=>bool, 'test_na'=>bool, 'oski_date', 'test_date', 'oski_resit_date', 'test_resit_date']
        try {
            $rows = DB::table('exam_schedules')
                ->whereNull('student_hemis_id')
                ->whereIn('group_hemis_id', $allGroupHids)
                ->whereIn('subject_id', $allSubjectIds)
                ->whereIn('semester_code', $allSemCodes)
                ->select('group_hemis_id', 'subject_id', 'semester_code',
                    'oski_na', 'test_na',
                    'oski_date', 'test_date',
                    'oski_resit_date', 'test_resit_date')
                ->get();
            foreach ($rows as $r) {
                $k = $r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                $naMap[$k] = [
                    'oski_na' => (bool) $r->oski_na,
                    'test_na' => (bool) $r->test_na,
                    'oski_date' => $r->oski_date,
                    'test_date' => $r->test_date,
                    'oski_resit_date' => $r->oski_resit_date,
                    'test_resit_date' => $r->test_resit_date,
                ];
            }
        } catch (\Throwable $e) {}

        // 1) JN/MT olish — snapshot va tirik AVG ni birlashtirib ishlatamiz.
        $jnMtMap = []; // hemis_id|subj|sem => [jn, mt]

        // 1a) Snapshot (yn_student_grades) — defolt
        try {
            $hasYnSubEduYearCol = \Illuminate\Support\Facades\Schema::hasColumn('yn_submissions', 'education_year');
            $ynQuery = DB::table('yn_student_grades as ysg')
                ->join('yn_submissions as yns', 'yns.id', '=', 'ysg.yn_submission_id')
                ->whereIn('yns.subject_id', $allSubjectIds)
                ->whereIn('yns.semester_code', $allSemCodes)
                ->whereIn('yns.group_hemis_id', $allGroupHids);
            if ($hasYnSubEduYearCol && !empty($relevantYears)) {
                $ynQuery->whereIn('yns.education_year', $relevantYears);
            }
            // Muhim: bu yerda attempt=1 bilan cheklamaymiz.
            // Sababli/tuzatishlardan keyin (2/3-urinish) yangilangan JN/MT ham
            // aynan shu snapshotlarda turadi va pullik holatini to'g'ri aniqlash
            // uchun eng so'nggi yozuvni olish kerak.
            $ynRows = $ynQuery
                ->orderBy('ysg.created_at', 'desc')
                ->select('ysg.student_hemis_id', 'yns.subject_id', 'yns.semester_code', 'ysg.jn', 'ysg.mt')
                ->get();
            foreach ($ynRows as $r) {
                $k = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                if (!isset($jnMtMap[$k])) {
                    // Default 0 in snapshot = "not yet graded", treat as null
                    $jnInt = (int) $r->jn;
                    $mtInt = (int) $r->mt;
                    $jnMtMap[$k] = [
                        'jn' => $jnInt > 0 ? $jnInt : null,
                        'mt' => $mtInt > 0 ? $mtInt : null,
                    ];
                }
            }
        } catch (\Throwable $e) {}

        // 1b) Tirik manbalar — snapshot yo'q (jarayonda) bo'lganlar uchun.
        // Tartib: snapshot (1a) eng birinchi va eng kuchli — YN topshirilganida
        // jurnal qulflagan qiymat kanonik. Snapshot null bo'lsa, tirik qiymatlar
        // bilan to'ldiramiz.
        //
        // JN: training_type_code NOT IN [11,99,100,101,102,103] uchun AVG.
        //   (11 = ma'ruza ham JN ga kirmaydi — jurnaldagi mantiq bilan mos.)
        // MT: jurnaldagi MT jadvalida yagona baho — training_type_code=99
        //   AND lesson_date IS NULL. Per-day MT (lesson_date bor) ishlatilmaydi.
        try {
            $jnAvg = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $allStudentHids)
                ->whereIn('subject_id', $allSubjectIds)
                ->whereIn('semester_code', $allSemCodes)
                ->whereNotIn('training_type_code', [11, 99, 100, 101, 102, 103])
                ->whereRaw('COALESCE(retake_grade, grade) IS NOT NULL')
                ->selectRaw('student_hemis_id, subject_id, semester_code,
                    AVG(COALESCE(retake_grade, grade)) as avg_grade')
                ->groupBy('student_hemis_id', 'subject_id', 'semester_code')
                ->get();
            foreach ($jnAvg as $r) {
                $k = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                if (!isset($jnMtMap[$k])) $jnMtMap[$k] = ['jn' => null, 'mt' => null];
                // Snapshot ustun: faqat snapshot null bo'lsa to'ldiramiz
                if ($jnMtMap[$k]['jn'] === null) {
                    $jnMtMap[$k]['jn'] = (int) round((float) $r->avg_grade, 0, PHP_ROUND_HALF_UP);
                }
            }

            // Manual MT (lesson_date NULL) — jurnaldagi MT jadvalining yagona bahosi
            $manualMt = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $allStudentHids)
                ->whereIn('subject_id', $allSubjectIds)
                ->whereIn('semester_code', $allSemCodes)
                ->where('training_type_code', 99)
                ->whereNull('lesson_date')
                // Bir nechta MT yozuvi bo'lsa eng oxirgisini (retake/tuzatishdan keyingi)
                // ustuvor olish kerak. Aks holda eski past MT tasodifan olinib,
                // talaba noto'g'ri "pullik" ko'rinib qolishi mumkin.
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->select('student_hemis_id', 'subject_id', 'semester_code', 'grade', 'retake_grade')
                ->get();
            foreach ($manualMt as $r) {
                $effective = $r->retake_grade ?? $r->grade;
                if ($effective === null) continue;
                $k = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                if (!isset($jnMtMap[$k])) $jnMtMap[$k] = ['jn' => null, 'mt' => null];
                if ($jnMtMap[$k]['mt'] === null) {
                    $jnMtMap[$k]['mt'] = (int) round((float) $effective, 0, PHP_ROUND_HALF_UP);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Live JN/MT aggregate failed: ' . $e->getMessage());
        }

        // 2) OSKI / Test attempt=1 baholari (101, 102, va legacy 103 quiz)
        // Legacy code 103 quiz grades: resolve to OSKI(101) or Test(102) via quiz_result
        $examMap = []; // hemis_id|subj|sem|type => avg grade
        $examLists = []; // hemis_id|subj|sem|type => [grades] (for averaging)
        try {
            $rows = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $allStudentHids)
                ->whereIn('subject_id', $allSubjectIds)
                ->whereIn('semester_code', $allSemCodes)
                ->whereIn('training_type_code', [101, 102, 103])
                ->when($hasAttemptCol, fn($q) => $q->where(fn($qq) => $qq->where('attempt', 1)->orWhereNull('attempt')))
                ->select('student_hemis_id', 'subject_id', 'semester_code', 'training_type_code', 'grade', 'retake_grade', 'quiz_result_id')
                ->get();

            $quizIds = $rows->where('training_type_code', 103)->pluck('quiz_result_id')->filter()->unique()->values()->all();
            $quizTypeMap = [];
            if (!empty($quizIds)) {
                $quizTypeMap = DB::table('hemis_quiz_results')->whereIn('id', $quizIds)->pluck('quiz_type', 'id')->toArray();
            }
            $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
            $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];

            foreach ($rows as $r) {
                $typeCode = (int) $r->training_type_code;
                if ($typeCode === 103) {
                    if (!$r->quiz_result_id) continue;
                    $quizType = $quizTypeMap[$r->quiz_result_id] ?? null;
                    if (in_array($quizType, $oskiTypes, true)) {
                        $typeCode = 101;
                    } elseif (in_array($quizType, $testTypes, true)) {
                        $typeCode = 102;
                    } else {
                        continue;
                    }
                }
                $effective = $r->retake_grade ?? $r->grade;
                if ($effective === null) continue;
                $k = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code . '|' . $typeCode;
                $examLists[$k][] = (float) $effective;
            }
            foreach ($examLists as $k => $list) {
                $examMap[$k] = count($list) ? array_sum($list) / count($list) : null;
            }
        } catch (\Throwable $e) {}

        // 2b) OSKI / Test attempt=2 baholari (12a) — failed_attempt2 ni aniqlash uchun
        $examMap2 = [];
        $examLists2 = [];
        try {
            if ($hasAttemptCol) {
                $rows = DB::table('student_grades')
                    ->whereNull('deleted_at')
                    ->whereIn('student_hemis_id', $allStudentHids)
                    ->whereIn('subject_id', $allSubjectIds)
                    ->whereIn('semester_code', $allSemCodes)
                    ->whereIn('training_type_code', [101, 102, 103])
                    ->where('attempt', 2)
                    ->select('student_hemis_id', 'subject_id', 'semester_code', 'training_type_code', 'grade', 'retake_grade', 'quiz_result_id')
                    ->get();

                $quizIds2 = $rows->where('training_type_code', 103)->pluck('quiz_result_id')->filter()->unique()->values()->all();
                $quizTypeMap2 = [];
                if (!empty($quizIds2)) {
                    $quizTypeMap2 = DB::table('hemis_quiz_results')->whereIn('id', $quizIds2)->pluck('quiz_type', 'id')->toArray();
                }
                $oskiTypes2 = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
                $testTypes2 = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];

                foreach ($rows as $r) {
                    $typeCode = (int) $r->training_type_code;
                    if ($typeCode === 103) {
                        if (!$r->quiz_result_id) continue;
                        $quizType = $quizTypeMap2[$r->quiz_result_id] ?? null;
                        if (in_array($quizType, $oskiTypes2, true)) {
                            $typeCode = 101;
                        } elseif (in_array($quizType, $testTypes2, true)) {
                            $typeCode = 102;
                        } else {
                            continue;
                        }
                    }
                    $effective = $r->retake_grade ?? $r->grade;
                    if ($effective === null) continue;
                    $k = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code . '|' . $typeCode;
                    $examLists2[$k][] = (float) $effective;
                }
                foreach ($examLists2 as $k => $list) {
                    $examMap2[$k] = count($list) ? array_sum($list) / count($list) : null;
                }
            }
        } catch (\Throwable $e) {}

        // 3) Davomat — har talaba/fan/semestr uchun absent_off summasi
        $davomatMap = []; // hemis_id|subj|sem => total_absent_off
        try {
            $rows = DB::table('attendances')
                ->whereIn('student_hemis_id', $allStudentHids)
                ->whereIn('subject_id', $allSubjectIds)
                ->whereIn('semester_code', $allSemCodes)
                ->whereNotIn('training_type_code', [99, 100, 101, 102])
                ->selectRaw('student_hemis_id, subject_id, semester_code, SUM(absent_off) as total_off')
                ->groupBy('student_hemis_id', 'subject_id', 'semester_code')
                ->get();
            foreach ($rows as $r) {
                $k = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                $davomatMap[$k] = (float) $r->total_off;
            }
        } catch (\Throwable $e) {}

        // 4) Auditoriya soatlari — fan bo'yicha
        $audHoursMap = []; // subj|sem => hours
        try {
            $subjectRows = DB::table('curriculum_subjects')
                ->whereIn('subject_id', $allSubjectIds)
                ->whereIn('semester_code', $allSemCodes)
                ->select('subject_id', 'semester_code', 'subject_details', 'total_acload')
                ->get();
            $nonAud = ['17'];
            foreach ($subjectRows as $sr) {
                $details = is_string($sr->subject_details) ? json_decode($sr->subject_details, true) : $sr->subject_details;
                $aud = 0;
                if (is_array($details)) {
                    foreach ($details as $d) {
                        $tc = (string) ($d['trainingType']['code'] ?? '');
                        if ($tc !== '' && !in_array($tc, $nonAud, true)) {
                            $aud += (float) ($d['academic_load'] ?? 0);
                        }
                    }
                }
                if ($aud <= 0) $aud = (float) ($sr->total_acload ?? 0);
                $audHoursMap[$sr->subject_id . '|' . $sr->semester_code] = $aud;
            }
        } catch (\Throwable $e) {}

        // Guruh bo'yicha triplesni indekslab olamiz (O(N×M) ni O(N+M) ga aylantirish uchun)
        $triplesByGroup = [];
        foreach ($triples as $triple) {
            $triplesByGroup[$triple[0]][] = $triple;
        }

        // Endi har talaba uchun statusni hisoblaymiz
        foreach ($studentGroup as $hid => $gHid) {
            $myTriples = $triplesByGroup[$gHid] ?? [];
            foreach ($myTriples as $triple) {
                [$g, $s, $sem] = $triple;

                $jnMtKey = $hid . '|' . $s . '|' . $sem;
                // Snapshot bo'lmasa null — "yiqilgan" deb hisoblamaymiz, fallback yo'q
                $jn = $jnMtMap[$jnMtKey]['jn'] ?? null;
                $mt = $jnMtMap[$jnMtKey]['mt'] ?? null;

                $oski = $examMap[$hid . '|' . $s . '|' . $sem . '|101'] ?? null;
                $test = $examMap[$hid . '|' . $s . '|' . $sem . '|102'] ?? null;
                $oski2 = $examMap2[$hid . '|' . $s . '|' . $sem . '|101'] ?? null;
                $test2 = $examMap2[$hid . '|' . $s . '|' . $sem . '|102'] ?? null;

                $absentOff = $davomatMap[$hid . '|' . $s . '|' . $sem] ?? 0;
                $audHours = $audHoursMap[$s . '|' . $sem] ?? 0;
                $davomatPct = $audHours > 0 ? round(($absentOff / $audHours) * 100, 2) : 0;

                // Agar talaba haqida hech qanday ma'lumot yo'q bo'lsa (boshqa fanga
                // o'tkazilgan, chet ellik almashinuv va h.k.), urinish ro'yxatiga kiritmaslik
                $hasAnyData = ($jn !== null) || ($mt !== null)
                    || ($oski !== null) || ($test !== null)
                    || ($oski2 !== null) || ($test2 !== null)
                    || ($absentOff > 0);
                if (!$hasAnyData) {
                    continue;
                }

                // OSKI/Test fan uchun talab qilinadimi? (exam_schedules.oski_na/test_na)
                $naKey = $g . '|' . $s . '|' . $sem;
                $oskiRequired = !($naMap[$naKey]['oski_na'] ?? false);
                $testRequired = !($naMap[$naKey]['test_na'] ?? false);

                // 1/2-urinish sanalari — muddati tugaganmi tekshirish uchun
                $oskiDate = $naMap[$naKey]['oski_date'] ?? null;
                $testDate = $naMap[$naKey]['test_date'] ?? null;
                $oskiResitDate = $naMap[$naKey]['oski_resit_date'] ?? null;
                $testResitDate = $naMap[$naKey]['test_resit_date'] ?? null;
                $today = now()->format('Y-m-d');

                // Pullik faqat haqiqatda past bo'lsa: null/yo'q ma'lumotni "past" deb sanamaymiz
                $jnLow = ($jn !== null) && ($jn < $minLimit);
                $mtLow = ($mt !== null) && ($mt < $minLimit);
                $isPullik = $jnLow || $mtLow || ($davomatPct >= 25);

                // "Tasdiqlangan yiqilish" mantiqi:
                //  - Imtihon sanasi belgilanmagan yoki hali kelmagan bo'lsa → imtihon
                //    o'tmagan, mavjud baho (hatto 0 ham) placeholder hisoblanadi —
                //    "yiqilgan" deb erta xulosa qilmaymiz
                //  - Sana o'tgan + baho kiritilgan va past bo'lsa → yiqilgan
                //  - Sana o'tgan + baho yo'q bo'lsa → kelmagan = yiqilgan
                $confirmFailed = function (bool $required, $grade, $date) use ($today, $minLimit) {
                    if (!$required) return false;
                    if ($date === null || ((string) $date) > $today) return false; // imtihon hali bo'lmagan
                    if ($grade !== null) return ((float) $grade) < $minLimit;
                    return true; // sana o'tdi, baho yo'q → kelmadi
                };

                // Yiqilgan attempt=1: pullik yoki tasdiqlangan OSKI/Test yiqilishi
                $oskiNum = $oski !== null ? (float) $oski : null;
                $testNum = $test !== null ? (float) $test : null;
                $oskiFailed1 = $confirmFailed($oskiRequired, $oskiNum, $oskiDate);
                $testFailed1 = $confirmFailed($testRequired, $testNum, $testDate);
                $failed1 = $isPullik || $oskiFailed1 || $testFailed1;

                $oski2Num = $oski2 !== null ? (float) $oski2 : null;
                $test2Num = $test2 !== null ? (float) $test2 : null;
                $oskiFailed2 = $confirmFailed($oskiRequired, $oski2Num, $oskiResitDate);
                $testFailed2 = $confirmFailed($testRequired, $test2Num, $testResitDate);
                $failed2 = $isPullik || $oskiFailed2 || $testFailed2;

                $key = $g . '|' . $s . '|' . $sem;
                if (!isset($result[$key])) $result[$key] = [];
                $result[$key][$hid] = [
                    'failed1' => $failed1,
                    'failed2' => $failed2,
                    'pullik' => $isPullik,
                    'held_back' => false,
                ];
            }
        }

        // 4+ ta fandan qarz bo'lsa — qayta o'qishga (2-urinishga) ruxsat berilmaydi.
        // Talaba guruh almashtirishi mumkin va qoida o'quv yili bo'yicha amal qiladi,
        // shuning uchun bucket = hemis_id | education_year. Sanash butun yil bo'yicha
        // (ko'rinmaydigan semestrlar ham) — failed1 fanlar sonini sanab, chegaradan
        // oshganlarni held_back deb belgilaymiz. Lekin natija ko'rinadigan triples
        // uchungina qaytariladi.
        $debtThreshold = 4;
        $debtCount = []; // hemis_id|education_year => count
        foreach ($result as $key => $studs) {
            [$g, $s, $sem] = explode('|', $key);
            $year = $semYearMap[$sem] ?? null;
            if (!$year) continue;
            foreach ($studs as $hid => $stat) {
                if (!empty($stat['failed1'])) {
                    $bucket = $hid . '|' . $year;
                    $debtCount[$bucket] = ($debtCount[$bucket] ?? 0) + 1;
                }
            }
        }

        // Faqat ko'rinadigan triples uchun result qaytaramiz, lekin held_back
        // bayrog'ini butun yil bo'yicha hisoblangan debtCount asosida belgilaymiz
        $visibleResult = [];
        foreach ($visibleTriples as $key => $triple) {
            if (!isset($result[$key])) continue;
            $sem = $triple[2];
            $year = $semYearMap[$sem] ?? null;
            $studs = $result[$key];
            foreach ($studs as $hid => $stat) {
                $bucket = $hid . '|' . ($year ?? '');
                $stat['held_back'] = $year && (($debtCount[$bucket] ?? 0) >= $debtThreshold);
                $visibleResult[$key][$hid] = $stat;
            }
        }

        return $visibleResult;
    }

    /**
     * Test markazi uchun: Yakuniy nazoratlar jadvali (faqat ko'rish)
     * Filtrlari by default bugungi sanaga o'rnatiladi va avtomatik qidiriladi
     * OSKI/Test alohida ustun emas — YN turi va YN sanasi sifatida ko'rsatiladi
     */
    /**
     * Test-center uchun umumiy ma'lumot yuklash (testCenterView va export uchun)
     * Qaytaradi: ['scheduleData' => Collection, 'currentEducationYear' => int|null]
     */
    private function buildTestCenterData(Request $request): array
    {
        $today = now()->format('Y-m-d');

        $selectedEducationType = $request->get('education_type');
        $selectedDepartment = $request->get('department_id');
        $selectedSpecialty = $request->get('specialty_id');
        $selectedLevelCode = $request->get('level_code');
        $selectedSemester = $request->get('semester_code');
        $selectedGroup = $request->get('group_id');
        $selectedSubject = $request->get('subject_id');
        $selectedStatus = $request->get('status');
        $dateFrom = $request->get('date_from', $today);
        $dateTo = $request->get('date_to', $today);
        $currentSemesterToggle = $request->get('current_semester', '1');
        $urinishFilter = $request->get('urinish'); // '1' | '2' | '3' | null
        $showStudents = $request->get('show_students') === '1';

        $currentSemesters = Semester::where('current', true)->get();
        $currentEducationYear = $currentSemesters->first()?->education_year;

        $scheduleData = $this->loadScheduleData(
            $currentSemesters, $selectedDepartment, $selectedSpecialty,
            $selectedSemester, $selectedGroup, $selectedEducationType,
            $selectedLevelCode, $selectedSubject, $selectedStatus,
            $currentSemesterToggle, true, $dateFrom, $dateTo, true
        );

        // Talabalarni ko'rsatish toggle yoqilgan bo'lsa — har item ga "students" massivi qo'shiladi
        if ($showStudents) {
            $scheduleData = $this->attachStudentsToSchedule($scheduleData);
        }

        $semesterMap = $currentSemesters->keyBy('code');
        $curriculumHemisIds = collect();
        foreach ($scheduleData as $groupHemisId => $items) {
            foreach ($items as $item) {
                $curriculumHemisIds->push($item['group']->curriculum_hemis_id);
            }
        }
        $curriculumFormMap = Curriculum::whereIn('curricula_hemis_id', $curriculumHemisIds->unique())
            ->pluck('education_form_name', 'curricula_hemis_id');

        $transformedData = collect();
        foreach ($scheduleData as $groupHemisId => $items) {
            foreach ($items as $item) {
                $oskiNa = $item['oski_na'] ?? false;
                $testNa = $item['test_na'] ?? false;

                $sem = $semesterMap->get($item['subject']->semester_code);
                $extraFields = [
                    'subject_code' => $item['subject']->subject_id ?? '',
                    'level_name' => $sem?->level_name ?? '',
                    'semester_name' => $item['subject']->semester_name ?? ($sem?->name ?? ''),
                    'education_form_name' => $curriculumFormMap->get($item['group']->curriculum_hemis_id) ?? '',
                ];

                // Har bir YN turi (OSKI, Test) uchun 1-, 2-, 3-urinish sanalarini alohida qatorlar sifatida chiqaramiz.
                // Faqat 1-urinish uchun N/A bayrog'i ishlatiladi (resit larda N/A tushunchasi yo'q).
                $attemptDefs = [
                    ['yn_type' => 'OSKI', 'attempt' => 1, 'date' => $item['oski_date'] ?? null,         'date_carbon' => $item['oski_date_carbon'] ?? null,         'time' => $item['oski_time'] ?? null,         'na' => $oskiNa],
                    ['yn_type' => 'OSKI', 'attempt' => 2, 'date' => $item['oski_resit_date'] ?? null,   'date_carbon' => $item['oski_resit_date_carbon'] ?? null,   'time' => $item['oski_resit_time'] ?? null,   'na' => false],
                    ['yn_type' => 'OSKI', 'attempt' => 3, 'date' => $item['oski_resit2_date'] ?? null,  'date_carbon' => $item['oski_resit2_date_carbon'] ?? null,  'time' => $item['oski_resit2_time'] ?? null,  'na' => false],
                    ['yn_type' => 'Test', 'attempt' => 1, 'date' => $item['test_date'] ?? null,         'date_carbon' => $item['test_date_carbon'] ?? null,         'time' => $item['test_time'] ?? null,         'na' => $testNa],
                    ['yn_type' => 'Test', 'attempt' => 2, 'date' => $item['test_resit_date'] ?? null,   'date_carbon' => $item['test_resit_date_carbon'] ?? null,   'time' => $item['test_resit_time'] ?? null,   'na' => false],
                    ['yn_type' => 'Test', 'attempt' => 3, 'date' => $item['test_resit2_date'] ?? null,  'date_carbon' => $item['test_resit2_date_carbon'] ?? null,  'time' => $item['test_resit2_time'] ?? null,  'na' => false],
                ];

                foreach ($attemptDefs as $def) {
                    // Urinish filtri qo'llanilsa — faqat tanlangan urinish qatorlari chiqadi
                    if ($urinishFilter !== null && $urinishFilter !== '' && (int) $urinishFilter !== $def['attempt']) {
                        continue;
                    }

                    $d = $def['date'];
                    $inRange = $d && (!$dateFrom || $d >= $dateFrom) && (!$dateTo || $d <= $dateTo);
                    // 1-urinish N/A bayrog'i tanlangan bo'lsa, sana bo'lmasa ham (sanasi kelajakda belgilanadigan) ko'rsatiladi
                    $naVisible = $def['attempt'] === 1 && $def['na'] && !$dateFrom && !$dateTo;
                    if (!$inRange && !$naVisible) {
                        continue;
                    }

                    $ynItem = array_merge($item, $extraFields);
                    $ynItem['yn_type'] = $def['yn_type'];
                    $ynItem['attempt'] = $def['attempt'];
                    $ynItem['yn_date'] = $d;
                    $ynItem['yn_date_carbon'] = $def['date_carbon'];
                    $ynItem['yn_na'] = $def['attempt'] === 1 ? $def['na'] : false;
                    $ynItem['test_time'] = $def['time'];
                    // Moodle quiz-resolution status for this attempt (read from
                    // the last push result Mark already persisted on the row).
                    $ynItem['moodle_status'] = $this->computeMoodleStatus(
                        $item, $def['yn_type'], $def['attempt'], $ynItem['yn_na']);
                    // Urinishga mos talabalar — show_students yoqilganda ko'rsatish uchun
                    if (!empty($item['students'])) {
                        $ynItem['students'] = $this->filterStudentsForAttempt($item['students'], $def['attempt']);
                    }
                    $transformedData->push($ynItem);
                }
            }
        }

        $groupHemisIds = $transformedData->pluck('group')->pluck('group_hemis_id')->unique()->toArray();
        $studentCounts = DB::table('students')
            ->whereIn('group_id', $groupHemisIds)
            ->where('student_status_code', 11)
            ->groupBy('group_id')
            ->select('group_id', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'group_id');

        $subjectIds = $transformedData->pluck('subject')->pluck('subject_id')->unique()->toArray();
        $ynSubmissions = [];
        if (!empty($groupHemisIds) && !empty($subjectIds)) {
            $ynSubmissionRows = DB::table('yn_submissions')
                ->whereIn('group_hemis_id', $groupHemisIds)
                ->whereIn('subject_id', $subjectIds)
                ->get();
            foreach ($ynSubmissionRows as $row) {
                $ynSubmissions[$row->group_hemis_id . '|' . $row->subject_id . '|' . $row->semester_code] = $row->submitted_at;
            }
        }

        $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
        $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];

        $quizCounts = [];
        if (!empty($groupHemisIds) && !empty($subjectIds)) {
            try {
                $quizRows = DB::table('hemis_quiz_results as hqr')
                    ->join('students as st', 'st.student_id_number', '=', 'hqr.student_id')
                    ->whereIn('st.group_id', $groupHemisIds)
                    ->whereIn('hqr.fan_id', $subjectIds)
                    ->where('hqr.is_active', 1)
                    ->groupBy('st.group_id', 'hqr.fan_id', 'hqr.quiz_type')
                    ->select('st.group_id', 'hqr.fan_id', 'hqr.quiz_type', DB::raw('COUNT(DISTINCT hqr.student_id) as cnt'))
                    ->get();

                foreach ($quizRows as $row) {
                    if (in_array($row->quiz_type, $testTypes)) {
                        $key = $row->group_id . '|' . $row->fan_id . '|Test';
                    } elseif (in_array($row->quiz_type, $oskiTypes)) {
                        $key = $row->group_id . '|' . $row->fan_id . '|OSKI';
                    } else {
                        continue;
                    }
                    $quizCounts[$key] = ($quizCounts[$key] ?? 0) + $row->cnt;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('buildTestCenterData: hemis_quiz_results so\'rovi xatolik berdi: ' . $e->getMessage());
            }
        }

        $transformedData = $transformedData->map(function ($item) use ($studentCounts, $quizCounts, $ynSubmissions) {
            $item['student_count'] = $studentCounts[$item['group']->group_hemis_id] ?? 0;
            $quizKey = $item['group']->group_hemis_id . '|' . ($item['subject']->subject_id ?? '') . '|' . ($item['yn_type'] ?? '');
            $item['quiz_count'] = $quizCounts[$quizKey] ?? 0;
            $ynKey = $item['group']->group_hemis_id . '|' . ($item['subject']->subject_id ?? '') . '|' . ($item['subject']->semester_code ?? '');
            $item['yn_submitted'] = isset($ynSubmissions[$ynKey]);
            return $item;
        });

        $excuseCounts = [];
        if (!empty($groupHemisIds) && !empty($subjectIds)) {
            $excuseRows = AbsenceExcuseMakeup::join('absence_excuses as ae', 'ae.id', '=', 'absence_excuse_makeups.absence_excuse_id')
                ->where('ae.status', 'approved')
                ->whereIn('absence_excuse_makeups.subject_id', $subjectIds)
                ->whereIn('absence_excuse_makeups.assessment_type', ['jn', 'mt'])
                ->join('students as st', 'st.id', '=', 'absence_excuse_makeups.student_id')
                ->whereIn('st.group_id', $groupHemisIds)
                ->groupBy('st.group_id', 'absence_excuse_makeups.subject_id')
                ->select('st.group_id', 'absence_excuse_makeups.subject_id', DB::raw('COUNT(DISTINCT ae.student_hemis_id) as cnt'))
                ->get();

            foreach ($excuseRows as $row) {
                $excuseCounts[$row->group_id . '|' . $row->subject_id] = $row->cnt;
            }
        }

        $transformedData = $transformedData->map(function ($item) use ($excuseCounts) {
            $excuseKey = $item['group']->group_hemis_id . '|' . ($item['subject']->subject_id ?? '');
            $item['excuse_student_count'] = $excuseCounts[$excuseKey] ?? 0;
            return $item;
        });

        $scheduleData = $transformedData->groupBy(fn($item) => $item['group']->group_hemis_id);

        return [
            'scheduleData' => $scheduleData,
            'currentEducationYear' => $currentEducationYear,
            'urinishFilter' => $urinishFilter,
            'showStudents' => $showStudents,
        ];
    }

    /**
     * Whether this schedule's quiz resolves on Moodle, derived from the last
     * push result Mark persisted on the ExamSchedule row. Covers all three
     * attempts (each is a separate Moodle quiz / column set); attempt-1 rows
     * flagged N/A return 'na'.
     *
     * @return string ok | notfound | error | pending | na
     */
    private function computeMoodleStatus(array $item, string $ynType, int $attempt, bool $na): string
    {
        if ($na || !in_array($attempt, [1, 2, 3], true)) {
            return 'na';
        }

        // Attempt-specific column prefix: 1 = oski/test, 2 = *_resit, 3 = *_resit2.
        $base = $ynType === 'OSKI' ? 'oski' : 'test';
        $suffix = $attempt === 2 ? '_resit' : ($attempt === 3 ? '_resit2' : '');
        $prefix = $base . $suffix;
        $error = $item[$prefix . '_moodle_error'] ?? null;
        $syncedAt = $item[$prefix . '_moodle_synced_at'] ?? null;

        if (!empty($error)) {
            $err = is_string($error) ? $error : json_encode($error);
            if (str_contains($err, 'coursenotfound') || str_contains($err, 'quiznotfound')) {
                return 'notfound';
            }
            return 'error';
        }
        if (!empty($syncedAt)) {
            return 'ok';
        }
        return 'pending';
    }

    /**
     * AJAX/form: re-push a single (schedule, yn_type, attempt) to Moodle
     * synchronously so the proctor sees the fresh quiz-resolution status
     * without waiting for the queue. The push is idempotent on the Moodle side.
     */
    public function recheckMoodle(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        if ($this->isTestCenterReadOnly()) {
            return back()->with('error', 'Sizda bu amalni bajarish huquqi yo\'q.');
        }

        $scheduleId = (int) $request->input('schedule_id');
        $ynType = strtolower((string) $request->input('yn_type'));
        $ynType = $ynType === 'oski' ? 'oski' : 'test';
        $attempt = (int) $request->input('attempt', 1);
        if (!in_array($attempt, [1, 2, 3], true)) {
            $attempt = 1;
        }

        $schedule = ExamSchedule::find($scheduleId);
        if (!$schedule) {
            return back()->with('error', 'Imtihon jadvali yozuvi topilmadi.');
        }

        // Attempt-specific column prefix.
        $suffix = $attempt === 2 ? '_resit' : ($attempt === 3 ? '_resit2' : '');
        $prefix = $ynType . $suffix;
        $unscheduled = empty($schedule->{$prefix . '_time'});

        try {
            app(\App\Services\MoodleExamBookingService::class)
                ->book($schedule, $ynType, $unscheduled, $attempt);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('recheckMoodle xatolik: ' . $e->getMessage());
            return back()->with('error', 'Moodle tekshiruvida xatolik: ' . $e->getMessage());
        }

        $fresh = $schedule->fresh();
        $error = $fresh?->{$prefix . '_moodle_error'};
        $synced = $fresh?->{$prefix . '_moodle_synced_at'};

        if (empty($error) && !empty($synced)) {
            return back()->with('success', 'Moodle bilan tekshirildi: quiz topildi.');
        }
        return back()->with('error', 'Moodle bilan tekshirildi: quiz topilmadi yoki xato — '
            . substr((string) $error, 0, 200));
    }

    /**
     * AJAX: re-push every selected (schedule, yn_type, attempt) to Moodle.
     * Unlike recheckMoodle() this is queue-based — a bulk selection can be
     * large, so each row is dispatched as a BookMoodleGroupExam job and the
     * proctor refreshes once the queue has drained.
     */
    public function bulkRecheckMoodle(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        if ($this->isTestCenterReadOnly()) {
            return response()->json(['error' => 'Sizda bu amalni bajarish huquqi yo\'q.'], 403);
        }

        $items = $request->input('items', []);
        if (!is_array($items) || empty($items)) {
            return response()->json(['error' => 'Hech qanday qator tanlanmadi.'], 422);
        }

        $dispatched = 0;
        $seen = [];
        foreach ($items as $it) {
            $scheduleId = (int) ($it['schedule_id'] ?? 0);
            $ynType = strtolower((string) ($it['yn_type'] ?? ''));
            $ynType = $ynType === 'oski' ? 'oski' : 'test';
            $attempt = (int) ($it['attempt'] ?? 1);
            if ($scheduleId <= 0 || !in_array($attempt, [1, 2, 3], true)) {
                continue;
            }
            // De-duplicate identical (schedule, yn, attempt) triples.
            $key = $scheduleId . '|' . $ynType . '|' . $attempt;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $schedule = ExamSchedule::find($scheduleId);
            if (!$schedule) {
                continue;
            }
            $suffix = $attempt === 2 ? '_resit' : ($attempt === 3 ? '_resit2' : '');
            $prefix = $ynType . $suffix;
            // Nothing to push without a date for that attempt.
            if (empty($schedule->{$prefix . '_date'})) {
                continue;
            }
            $unscheduled = empty($schedule->{$prefix . '_time'});
            \App\Jobs\BookMoodleGroupExam::dispatch($schedule->id, $ynType, $unscheduled, $attempt);
            $dispatched++;
        }

        return response()->json([
            'dispatched' => $dispatched,
            'message' => $dispatched . ' ta yozuv Moodle tekshiruviga navbatga qo\'shildi. '
                . 'Bir necha daqiqadan so\'ng sahifani yangilab, "Moodle holati" ustunini ko\'ring.',
        ]);
    }

    /**
     * Tanlangan urinish uchun talabalar ro'yxatini filtrlash:
     *  - 1-urinish: barcha talabalar
     *  - 2-urinish: 1-urinishdan o'tmaganlar (failed_attempt1) YOKI shaxsiy
     *               resit sanasi belgilangan talabalar
     *  - 3-urinish: 2-urinishdan o'tmaganlar (failed_attempt2) YOKI shaxsiy
     *               resit2 sanasi belgilangan talabalar
     *
     * Test Markazi uchun bu filtr biroz yumshoqroq: agar baholar hali
     * to'liq import qilinmagan bo'lsa ham, akademik bo'lim shaxsiy resit
     * sanasini belgilagan talabalar ko'rinishi kerak. Agar hech bir talaba
     * topilmasa — guruhdagi barchasi ko'rsatiladi (informational fallback).
     */
    private function filterStudentsForAttempt(array $students, int $attempt): array
    {
        if ($attempt === 1) {
            return $students;
        }

        if ($attempt === 2) {
            $filtered = array_values(array_filter($students, function ($s) {
                return !empty($s['failed_attempt1'])
                    || !empty($s['oski_resit_date'])
                    || !empty($s['test_resit_date']);
            }));
        } else { // 3
            $filtered = array_values(array_filter($students, function ($s) {
                return !empty($s['failed_attempt2'])
                    || !empty($s['oski_resit2_date'])
                    || !empty($s['test_resit2_date']);
            }));
        }

        // Hech bir talaba topilmasa — guruhdagi barchasi ko'rsatiladi (Test Markazi uchun
        // ro'yxat bo'sh ko'rinmasligi muhim)
        return !empty($filtered) ? $filtered : $students;
    }

    public function testCenterView(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }

        \Illuminate\Support\Facades\Log::info('testCenterView: metod boshlandi', [
            'url' => $request->fullUrl(),
            'guard' => auth()->getDefaultDriver(),
            'user_id' => auth()->id(),
            'user_class' => auth()->user() ? get_class(auth()->user()) : 'null',
        ]);

        $readOnly = $this->isTestCenterReadOnly();
        $isTestCenter = $this->isActingAsTestCenter();
        $today = now()->format('Y-m-d');

        $selectedEducationType = $request->get('education_type');
        $selectedDepartment = $request->get('department_id');
        $selectedSpecialty = $request->get('specialty_id');
        $selectedLevelCode = $request->get('level_code');
        $selectedSemester = $request->get('semester_code');
        $selectedGroup = $request->get('group_id');
        $selectedSubject = $request->get('subject_id');
        $selectedStatus = $request->get('status');
        $dateFrom = $request->get('date_from', $today);
        $dateTo = $request->get('date_to', $today);
        $currentSemesterToggle = $request->get('current_semester', '1');
        $isSearched = true;
        $routePrefix = $this->routePrefix();

        $urinishFilter = $request->get('urinish');
        $showStudents = $request->get('show_students') === '1';

        try {
            $result = $this->buildTestCenterData($request);
            $scheduleData = $result['scheduleData'];
            $currentEducationYear = $result['currentEducationYear'];
            $urinishFilter = $result['urinishFilter'] ?? $urinishFilter;
            $showStudents = $result['showStudents'] ?? $showStudents;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('testCenterView xatolik: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $scheduleData = collect();
            $currentEducationYear = null;
        }

        try {
            return view('admin.academic-schedule.test-center', compact(
                'scheduleData',
                'selectedEducationType',
                'selectedDepartment',
                'selectedSpecialty',
                'selectedLevelCode',
                'selectedSemester',
                'selectedGroup',
                'selectedSubject',
                'selectedStatus',
                'dateFrom',
                'dateTo',
                'currentSemesterToggle',
                'urinishFilter',
                'showStudents',
                'isSearched',
                'currentEducationYear',
                'routePrefix',
                'readOnly',
                'isTestCenter',
            ))->render();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('testCenterView VIEW RENDER xatolik: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Sahifani yuklashda xatolik yuz berdi: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Quiz natijalarini yangilash (topshirgan talabalar sonini qayta hisoblash)
     */
    public function refreshQuizCounts(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        $items = $request->input('items', []);
        if (empty($items)) {
            return response()->json(['counts' => []]);
        }

        $groupHemisIds = collect($items)->pluck('group_id')->unique()->toArray();
        $subjectIds = collect($items)->pluck('subject_id')->unique()->toArray();

        // Talabalar soni
        $studentCounts = DB::table('students')
            ->whereIn('group_id', $groupHemisIds)
            ->where('student_status_code', 11)
            ->groupBy('group_id')
            ->select('group_id', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'group_id');

        // Quiz natijalar soni
        $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
        $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];

        $quizCounts = [];
        if (!empty($groupHemisIds) && !empty($subjectIds)) {
            try {
                $quizRows = DB::table('hemis_quiz_results as hqr')
                    ->join('students as st', 'st.student_id_number', '=', 'hqr.student_id')
                    ->whereIn('st.group_id', $groupHemisIds)
                    ->whereIn('hqr.fan_id', $subjectIds)
                    ->where('hqr.is_active', 1)
                    ->groupBy('st.group_id', 'hqr.fan_id', 'hqr.quiz_type')
                    ->select('st.group_id', 'hqr.fan_id', 'hqr.quiz_type', DB::raw('COUNT(DISTINCT hqr.student_id) as cnt'))
                    ->get();

                foreach ($quizRows as $row) {
                    if (in_array($row->quiz_type, $testTypes)) {
                        $key = $row->group_id . '|' . $row->fan_id . '|Test';
                    } elseif (in_array($row->quiz_type, $oskiTypes)) {
                        $key = $row->group_id . '|' . $row->fan_id . '|OSKI';
                    } else {
                        continue;
                    }
                    $quizCounts[$key] = ($quizCounts[$key] ?? 0) + $row->cnt;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('refreshQuizCounts: hemis_quiz_results so\'rovi xatolik berdi: ' . $e->getMessage());
            }
        }

        $result = [];
        foreach ($items as $item) {
            $key = $item['group_id'] . '|' . $item['subject_id'] . '|' . $item['yn_type'];
            $sc = $studentCounts[$item['group_id']] ?? 0;
            $qc = $quizCounts[$key] ?? 0;
            $result[] = [
                'group_id' => $item['group_id'],
                'subject_id' => $item['subject_id'],
                'yn_type' => $item['yn_type'],
                'student_count' => $sc,
                'quiz_count' => $qc,
            ];
        }

        return response()->json(['counts' => $result]);
    }

    /**
     * Umumiy: jadval ma'lumotlarini yuklash (index va test-center uchun)
     */
    private function loadScheduleData(
        $currentSemesters, $selectedDepartment, $selectedSpecialty,
        $selectedSemester, $selectedGroup, $selectedEducationType,
        $selectedLevelCode, $selectedSubject, $selectedStatus,
        $currentSemesterToggle, $includeCarbon = false,
        $dateFrom = null, $dateTo = null, $filterByYnDate = false
    ) {
        $currentSemesterOnly = $currentSemesterToggle === '1';
        $currentEducationYear = $currentSemesters->first()?->education_year;

        // Semester filter closure
        $semesterFilter = function ($query) use ($currentSemesterOnly, $currentEducationYear) {
            if ($currentSemesterOnly) {
                $query->where('current', true);
            } else {
                $query->where('education_year', $currentEducationYear);
            }
            return $query;
        };

        // Semestr kodlarini aniqlash
        $semesterCodes = collect();
        if ($selectedSemester) {
            $semesterCodes = collect([$selectedSemester]);
        } elseif ($currentSemesterOnly) {
            $semesterCodes = $currentSemesters->pluck('code')->unique();
        }

        if ($selectedLevelCode) {
            $levelSemCodes = $semesterFilter(Semester::query())
                ->where('level_code', $selectedLevelCode)
                ->pluck('code')->unique();
            $semesterCodes = $semesterCodes->isEmpty()
                ? $levelSemCodes
                : $semesterCodes->intersect($levelSemCodes);
        }

        // O'quv rejalarini olish
        $curriculumQuery = Curriculum::where(function($q) use ($currentSemesterOnly, $currentEducationYear) {
            if ($currentSemesterOnly) {
                $q->where('current', true)
                  ->orWhereIn('curricula_hemis_id', Semester::where('current', true)->select('curriculum_hemis_id'));
            } else {
                $q->whereIn('curricula_hemis_id', Semester::where('education_year', $currentEducationYear)->select('curriculum_hemis_id'));
            }
        });
        if ($selectedDepartment) $curriculumQuery->where('department_hemis_id', $selectedDepartment);
        if ($selectedSpecialty) $curriculumQuery->where('specialty_hemis_id', $selectedSpecialty);
        if ($selectedEducationType) $curriculumQuery->where('education_type_code', $selectedEducationType);
        // Kurs/semestr tanlangan bo'lsa — faqat o'sha level/code joriy bo'lgan curriculum'larni qoldirish.
        // Aks holda d2/20 (hozir 6-kurs) kabi eski rejalar 5-kurs filtridan ham chiqib qolyapti
        // (chunki ularda istalgan boshqa current semester bor).
        if ($selectedLevelCode || $selectedSemester) {
            $curriculumQuery->whereIn('curricula_hemis_id', function ($sub) use ($currentSemesterOnly, $currentEducationYear, $selectedLevelCode, $selectedSemester) {
                $sub->select('curriculum_hemis_id')->from('semesters');
                if ($currentSemesterOnly) {
                    $sub->where('current', true);
                } else {
                    $sub->where('education_year', $currentEducationYear);
                }
                if ($selectedLevelCode) $sub->where('level_code', $selectedLevelCode);
                if ($selectedSemester) $sub->where('code', $selectedSemester);
            });
        }
        $curriculumIds = $curriculumQuery->pluck('curricula_hemis_id');

        if ($curriculumIds->isEmpty()) return collect();

        // Fanlar
        $subjectQuery = CurriculumSubject::whereIn('curricula_hemis_id', $curriculumIds)
            ->where('is_active', true);
        if ($currentSemesterOnly && !$selectedSemester) {
            // Har bir curriculum uchun alohida joriy semestr kodi bo'yicha aniq filtr
            $semQuery = $semesterFilter(Semester::query())->whereIn('curriculum_hemis_id', $curriculumIds);
            if ($selectedLevelCode) {
                $semQuery->where('level_code', $selectedLevelCode);
            }
            $curriculumSemCodes = $semQuery->get()
                ->groupBy('curriculum_hemis_id')
                ->map(fn($sems) => $sems->pluck('code')->unique()->values()->toArray());

            if ($curriculumSemCodes->isNotEmpty()) {
                $subjectQuery->where(function ($q) use ($curriculumSemCodes) {
                    foreach ($curriculumSemCodes as $curriculumId => $codes) {
                        $q->orWhere(function ($sub) use ($curriculumId, $codes) {
                            $sub->where('curricula_hemis_id', $curriculumId)
                                ->whereIn('semester_code', $codes);
                        });
                    }
                });
            }
        } elseif ($semesterCodes->isNotEmpty()) {
            $subjectQuery->whereIn('semester_code', $semesterCodes);
        }
        if ($selectedSubject) {
            $subjectQuery->where('subject_id', $selectedSubject);
        }
        $subjects = $subjectQuery->get();

        if ($subjects->isEmpty()) return collect();

        // Guruhlar
        $groupQuery = Group::where('active', true)
            ->whereIn('curriculum_hemis_id', $curriculumIds);
        if ($selectedDepartment) $groupQuery->where('department_hemis_id', $selectedDepartment);
        if ($selectedSpecialty) $groupQuery->where('specialty_hemis_id', $selectedSpecialty);
        if ($selectedGroup) $groupQuery->where('group_hemis_id', $selectedGroup);
        $filteredGroups = $groupQuery->orderBy('name')->get();

        if ($filteredGroups->isEmpty()) return collect();

        // semester_code → level_code map (kurs darajasini aniqlash uchun)
        $semesterLevelMap = $semesterFilter(Semester::query())
            ->whereIn('curriculum_hemis_id', $curriculumIds)
            ->select('code', 'level_code', 'curriculum_hemis_id')
            ->get()
            ->mapWithKeys(fn($s) => [$s->curriculum_hemis_id . '_' . $s->code => (string) $s->level_code]);

        // Mavjud jadvallar
        $scheduleQuery = ExamSchedule::query();
        if ($selectedDepartment) $scheduleQuery->where('department_hemis_id', $selectedDepartment);
        if ($selectedSpecialty) $scheduleQuery->where('specialty_hemis_id', $selectedSpecialty);
        if ($selectedGroup) $scheduleQuery->where('group_hemis_id', $selectedGroup);
        if ($semesterCodes->isNotEmpty()) $scheduleQuery->whereIn('semester_code', $semesterCodes);
        $existingSchedules = $scheduleQuery->get()
            ->keyBy(fn($item) => $item->group_hemis_id . '_' . $item->subject_id . '_' . $item->semester_code);

        // Dars jadvalidan boshlanish/tugash sanalarini olish (schedules jadvalidan)
        $lessonDatesRaw = DB::table('schedules')
            ->select('group_id', 'subject_id', 'subject_name', DB::raw('MIN(lesson_date) as lesson_start'), DB::raw('MAX(lesson_date) as lesson_end'))
            ->whereIn('group_id', $filteredGroups->pluck('group_hemis_id'))
            ->whereNull('deleted_at')
            ->groupBy('group_id', 'subject_id', 'subject_name')
            ->get();

        // Ikki xil key bilan map qilish (HEMIS subject_id yoki curriculum_subject_hemis_id bo'lishi mumkin)
        $lessonDatesMap = [];
        foreach ($lessonDatesRaw as $row) {
            $lessonDatesMap[$row->group_id . '_' . $row->subject_id] = $row;
        }

        // Ma'lumotlarni yig'ish
        $scheduleData = collect();
        foreach ($filteredGroups as $group) {
            $groupSubjects = $subjects->filter(fn($s) => $s->curricula_hemis_id === $group->curriculum_hemis_id);

            foreach ($groupSubjects as $subject) {
                $key = $group->group_hemis_id . '_' . $subject->subject_id . '_' . $subject->semester_code;
                $existing = $existingSchedules->get($key);

                // Dars sanalarini schedules jadvalidan olish
                // HEMIS subject_id yoki curriculum_subject_hemis_id bo'lishi mumkin
                $lessonInfo = $lessonDatesMap[$group->group_hemis_id . '_' . $subject->curriculum_subject_hemis_id]
                           ?? $lessonDatesMap[$group->group_hemis_id . '_' . $subject->subject_id]
                           ?? null;

                $item = [
                    'group' => $group,
                    'subject' => $subject,
                    'closing_form' => $subject->closing_form,
                    'level_code' => $semesterLevelMap[$group->curriculum_hemis_id . '_' . $subject->semester_code] ?? null,
                    'specialty_name' => $group->specialty_name,
                    'lesson_start_date' => $lessonInfo?->lesson_start ? substr($lessonInfo->lesson_start, 0, 10) : null,
                    'lesson_end_date' => $lessonInfo?->lesson_end ? substr($lessonInfo->lesson_end, 0, 10) : null,
                    'oski_date' => $existing?->oski_date?->format('Y-m-d'),
                    'oski_na' => (bool) $existing?->oski_na,
                    'oski_time' => $existing?->oski_time,
                    'oski_resit_date' => $existing?->oski_resit_date?->format('Y-m-d'),
                    'oski_resit_time' => $existing?->oski_resit_time,
                    'oski_resit2_date' => $existing?->oski_resit2_date?->format('Y-m-d'),
                    'oski_resit2_time' => $existing?->oski_resit2_time,
                    'test_date' => $existing?->test_date?->format('Y-m-d'),
                    'test_na' => (bool) $existing?->test_na,
                    'test_time' => $existing?->test_time,
                    'test_resit_date' => $existing?->test_resit_date?->format('Y-m-d'),
                    'test_resit_time' => $existing?->test_resit_time,
                    'test_resit2_date' => $existing?->test_resit2_date?->format('Y-m-d'),
                    'test_resit2_time' => $existing?->test_resit2_time,
                    'schedule_id' => $existing?->id,
                    'oski_moodle_synced_at' => $existing?->oski_moodle_synced_at,
                    'oski_moodle_error' => $existing?->oski_moodle_error,
                    'test_moodle_synced_at' => $existing?->test_moodle_synced_at,
                    'test_moodle_error' => $existing?->test_moodle_error,
                    'oski_resit_moodle_synced_at' => $existing?->oski_resit_moodle_synced_at,
                    'oski_resit_moodle_error' => $existing?->oski_resit_moodle_error,
                    'oski_resit2_moodle_synced_at' => $existing?->oski_resit2_moodle_synced_at,
                    'oski_resit2_moodle_error' => $existing?->oski_resit2_moodle_error,
                    'test_resit_moodle_synced_at' => $existing?->test_resit_moodle_synced_at,
                    'test_resit_moodle_error' => $existing?->test_resit_moodle_error,
                    'test_resit2_moodle_synced_at' => $existing?->test_resit2_moodle_synced_at,
                    'test_resit2_moodle_error' => $existing?->test_resit2_moodle_error,
                ];

                if ($includeCarbon) {
                    $item['lesson_start_date_carbon'] = $lessonInfo?->lesson_start ? \Carbon\Carbon::parse($lessonInfo->lesson_start) : null;
                    $item['lesson_end_date_carbon'] = $lessonInfo?->lesson_end ? \Carbon\Carbon::parse($lessonInfo->lesson_end) : null;
                    $item['oski_date_carbon'] = $existing?->oski_date;
                    $item['test_date_carbon'] = $existing?->test_date;
                    $item['oski_resit_date_carbon'] = $existing?->oski_resit_date;
                    $item['oski_resit2_date_carbon'] = $existing?->oski_resit2_date;
                    $item['test_resit_date_carbon'] = $existing?->test_resit_date;
                    $item['test_resit2_date_carbon'] = $existing?->test_resit2_date;
                }

                $scheduleData->push($item);
            }
        }

        // Holat filtri
        if ($selectedStatus === 'belgilangan') {
            $scheduleData = $scheduleData->filter(fn($item) => $item['oski_date'] || $item['test_date']);
        } elseif ($selectedStatus === 'belgilanmagan') {
            $scheduleData = $scheduleData->filter(fn($item) => !$item['oski_date'] && !$item['test_date']);
        }

        // Sana oralig'i filtri
        if ($dateFrom || $dateTo) {
            if ($filterByYnDate) {
                // YN sanasi bo'yicha filtr — 1-, 2- va 3-urinish sanalarining istalganidan biri oraliqqa kirsa, ko'rsatiladi
                $scheduleData = $scheduleData->filter(function ($item) use ($dateFrom, $dateTo) {
                    $candidates = [
                        $item['oski_date'] ?? null,
                        $item['oski_resit_date'] ?? null,
                        $item['oski_resit2_date'] ?? null,
                        $item['test_date'] ?? null,
                        $item['test_resit_date'] ?? null,
                        $item['test_resit2_date'] ?? null,
                    ];
                    foreach ($candidates as $d) {
                        if ($d && (!$dateFrom || $d >= $dateFrom) && (!$dateTo || $d <= $dateTo)) {
                            return true;
                        }
                    }
                    return false;
                });
            } else {
                // Dars tugash sanasi bo'yicha filtr
                $scheduleData = $scheduleData->filter(function ($item) use ($dateFrom, $dateTo) {
                    $lessonEnd = $item['lesson_end_date'];
                    if (!$lessonEnd) return false;

                    if ($dateFrom && $lessonEnd < $dateFrom) return false;
                    if ($dateTo && $lessonEnd > $dateTo) return false;

                    return true;
                });
            }
        }

        return $scheduleData->groupBy(fn($item) => $item['group']->group_hemis_id);
    }

    /**
     * Imtihon sanalarini saqlash
     */
    public function store(Request $request)
    {
        $schedules = $request->input('schedules');

        if (!is_array($schedules) || empty($schedules)) {
            return redirect()->back()->with('error', 'Saqlash uchun ma\'lumot topilmadi.');
        }

        // Faqat to'liq ma'lumotga ega elementlarni filtrlash
        // (max_input_vars limiti tufayli ba'zi maydonlar tushib qolishi mumkin)
        $validSchedules = [];
        foreach ($schedules as $key => $schedule) {
            if (!empty($schedule['group_hemis_id']) && !empty($schedule['subject_id']) && !empty($schedule['semester_code'])) {
                $validSchedules[$key] = $schedule;
            }
        }

        if (empty($validSchedules)) {
            return redirect()->back()->with('error', 'Ma\'lumotlar to\'liq emas. Sahifani yangilab, qaytadan urinib ko\'ring.');
        }

        $currentSemester = Semester::where('current', true)->first();
        $educationYear = $currentSemester?->education_year;
        $userId = auth()->id();
        $today = \Carbon\Carbon::today();

        // Mavjud yozuvlarni oldindan yuklash (allaqachon saqlangan sanalarni validatsiyadan o'tkazib yuborish uchun)
        $existingForValidation = ExamSchedule::where(function ($q) use ($validSchedules) {
            foreach ($validSchedules as $s) {
                $q->orWhere(function ($sub) use ($s) {
                    $sub->where('group_hemis_id', $s['group_hemis_id'])
                        ->where('subject_id', $s['subject_id'])
                        ->where('semester_code', $s['semester_code']);
                });
            }
        })->get()->keyBy(fn($r) => $r->group_hemis_id . '_' . $r->subject_id . '_' . $r->semester_code);

        // Cheklov 2: Faqat YANGI sanalar kamida ertadan bo'lishi kerak (allaqachon saqlangan sanalar tekshirilmaydi)
        // Admin roli uchun bugungi kunni ham belgilash mumkin
        $user = auth()->user() ?? auth('teacher')->user();
        $activeRole = session('active_role', $user?->getRoleNames()->first());
        $isAdmin = $user && in_array($activeRole, ExamDateRoleService::adminRoles(), true);
        if (!ExamDateRoleService::canEditAnything($activeRole)) {
            abort(403, 'Bu amalni bajarish uchun ruxsat yo\'q.');
        }
        // Saqlangan sanani o'zgartirish — admin yoki sozlamalarda ruxsat etilgan rol
        $canEditSaved = $isAdmin || ExamDateRoleService::roleHasAnyAccess($activeRole);
        $minDate = $isAdmin ? $today : $today->copy()->addDay();

        // Per-row permission validatsiyasi uchun (curriculum_hemis_id, semester_code) → level_code map
        $groupIds = collect($validSchedules)->pluck('group_hemis_id')->filter()->unique()->values();
        $groupCurriculumMap = Group::whereIn('group_hemis_id', $groupIds)
            ->pluck('curriculum_hemis_id', 'group_hemis_id');
        $curriculumIds = $groupCurriculumMap->unique()->values();
        $semesterCodes = collect($validSchedules)->pluck('semester_code')->filter()->unique()->values();

        $semesterLevelMap = collect();
        if ($curriculumIds->isNotEmpty() && $semesterCodes->isNotEmpty()) {
            $semesterLevelMap = Semester::whereIn('curriculum_hemis_id', $curriculumIds)
                ->whereIn('code', $semesterCodes)
                ->get(['curriculum_hemis_id', 'code', 'level_code'])
                ->mapWithKeys(fn($s) => [$s->curriculum_hemis_id . '_' . $s->code => (string) $s->level_code]);
        }

        // Har bir yozuvga urinish + kurs darajasi bo'yicha alohida ruxsat tekshiruvi.
        //  - 1-urinish: sozlamalardagi rollar mapping bo'yicha (level_code orqali).
        //  - 2+ urinish: faqat registrator_ofisi (yoki admin) qo'yadi.
        if (!$isAdmin) {
            foreach ($validSchedules as $schedule) {
                $curriculumId = $groupCurriculumMap[$schedule['group_hemis_id']] ?? null;
                $lvl = $curriculumId
                    ? (string) ($semesterLevelMap[$curriculumId . '_' . $schedule['semester_code']] ?? '')
                    : '';
                $rowAttempt = (int) ($schedule['urinish'] ?? 1);
                if (!ExamDateRoleService::canEditAttempt($activeRole, $lvl, $rowAttempt)) {
                    $msg = $rowAttempt >= 2
                        ? '2-urinish va keyingi urinishlar uchun YN sanasini faqat registrator ofisi belgilay oladi.'
                        : 'Sizning rolingizga ushbu kurs darajasi uchun 1-urinish YN sanasini belgilashga ruxsat yo\'q. Sozlamalardan tekshiring.';
                    return redirect()->back()->with('error', $msg);
                }
            }
        }

        foreach ($validSchedules as $schedule) {
            $existingRec = $existingForValidation->get(
                $schedule['group_hemis_id'] . '_' . $schedule['subject_id'] . '_' . $schedule['semester_code']
            );

            // 2- va 3-urinish (resit) sanalari uchun "ertadan" cheklovi yumshatilgan —
            // bugungi kunni ham belgilash mumkin (registrator ofisi shoshilinch
            // hollarda o'sha kun davomida resit tashkil qilishi kerak bo'lishi mumkin).
            $rowUrinishVal = (int) ($schedule['urinish'] ?? 1);
            $rowMinDate = ($rowUrinishVal >= 2) ? $today : $minDate;

            if (!empty($schedule['oski_date'])) {
                $alreadySaved = $existingRec && $existingRec->oski_date
                    && $existingRec->oski_date->format('Y-m-d') === $schedule['oski_date'];
                if (!$alreadySaved) {
                    $oskiDate = \Carbon\Carbon::parse($schedule['oski_date']);
                    if ($oskiDate->lt($rowMinDate)) {
                        return redirect()->back()->with('error', $isAdmin
                            ? 'OSKI sanasi o\'tgan kunni qo\'yib bo\'lmaydi.'
                            : ($rowUrinishVal >= 2
                                ? 'OSKI sanasi o\'tgan kunni qo\'yib bo\'lmaydi (resit uchun bugun ham bo\'ladi).'
                                : 'OSKI sanasi kamida ertadan bo\'lishi kerak. Bugun yoki o\'tgan kunni qo\'yib bo\'lmaydi.'));
                    }
                }
            }
            if (!empty($schedule['test_date'])) {
                $alreadySaved = $existingRec && $existingRec->test_date
                    && $existingRec->test_date->format('Y-m-d') === $schedule['test_date'];
                if (!$alreadySaved) {
                    $testDate = \Carbon\Carbon::parse($schedule['test_date']);
                    if ($testDate->lt($rowMinDate)) {
                        return redirect()->back()->with('error', $isAdmin
                            ? 'Test sanasi o\'tgan kunni qo\'yib bo\'lmaydi.'
                            : ($rowUrinishVal >= 2
                                ? 'Test sanasi o\'tgan kunni qo\'yib bo\'lmaydi (resit uchun bugun ham bo\'ladi).'
                                : 'Test sanasi kamida ertadan bo\'lishi kerak. Bugun yoki o\'tgan kunni qo\'yib bo\'lmaydi.'));
                    }
                }
            }
        }

        // Kunlik sig'im validatsiyasi (har bir yangi/o'zgargan sana uchun)
        // Eslatma: har bir sananing o'z sig'imi pastda alohida hisoblanadi
        $defaultDailyCapacity = ExamCapacityService::dailyCapacity();
        if ($defaultDailyCapacity > 0) {
            // Aggregat: $dateStudents[$date] = qancha qo'shilayotgan talaba (ushbu so'rov ichida)
            $pendingByDate = [];
            $groupCountCache = [];

            $countOf = function ($groupId) use (&$groupCountCache) {
                if (!isset($groupCountCache[$groupId])) {
                    $groupCountCache[$groupId] = ExamCapacityService::groupStudentCount($groupId);
                }
                return $groupCountCache[$groupId];
            };

            foreach ($validSchedules as $schedule) {
                $existingRec = $existingForValidation->get(
                    $schedule['group_hemis_id'] . '_' . $schedule['subject_id'] . '_' . $schedule['semester_code']
                );

                $oskiNa = !empty($schedule['oski_na']);
                $testNa = !empty($schedule['test_na']);

                // OSKI: agar yangi yoki o'zgargan sana bo'lsa
                if (!empty($schedule['oski_date']) && !$oskiNa) {
                    $oldOski = $existingRec?->oski_date?->format('Y-m-d');
                    $newOski = $schedule['oski_date'];
                    if ($oldOski !== $newOski) {
                        $pendingByDate[$newOski]['students'] = ($pendingByDate[$newOski]['students'] ?? 0) + $countOf($schedule['group_hemis_id']);
                        $pendingByDate[$newOski]['exclude'][] = [
                            'group_hemis_id' => $schedule['group_hemis_id'],
                            'subject_id' => $schedule['subject_id'],
                            'semester_code' => $schedule['semester_code'],
                            'yn_type' => 'oski',
                        ];
                    }
                }
                // Test: agar yangi yoki o'zgargan sana bo'lsa
                if (!empty($schedule['test_date']) && !$testNa) {
                    $oldTest = $existingRec?->test_date?->format('Y-m-d');
                    $newTest = $schedule['test_date'];
                    if ($oldTest !== $newTest) {
                        $pendingByDate[$newTest]['students'] = ($pendingByDate[$newTest]['students'] ?? 0) + $countOf($schedule['group_hemis_id']);
                        $pendingByDate[$newTest]['exclude'][] = [
                            'group_hemis_id' => $schedule['group_hemis_id'],
                            'subject_id' => $schedule['subject_id'],
                            'semester_code' => $schedule['semester_code'],
                            'yn_type' => 'test',
                        ];
                    }
                }
            }

            foreach ($pendingByDate as $date => $info) {
                // Sanada allaqachon belgilangan boshqa yozuvlar (joriy o'zgartirilayotganlardan tashqari)
                $existingTotal = 0;
                $excludeList = $info['exclude'] ?? [];
                $rowsOnDate = ExamSchedule::where(function ($q) use ($date) {
                    $q->where(function ($q2) use ($date) {
                        $q2->whereDate('oski_date', $date)->where('oski_na', false);
                    })->orWhere(function ($q2) use ($date) {
                        $q2->whereDate('test_date', $date)->where('test_na', false);
                    });
                })->get();

                foreach ($rowsOnDate as $row) {
                    $isExcluded = function (string $ynType) use ($row, $excludeList): bool {
                        foreach ($excludeList as $ex) {
                            if ($ex['group_hemis_id'] === $row->group_hemis_id
                                && (string) $ex['subject_id'] === (string) $row->subject_id
                                && (string) $ex['semester_code'] === (string) $row->semester_code
                                && $ex['yn_type'] === $ynType) {
                                return true;
                            }
                        }
                        return false;
                    };

                    $oskiMatch = $row->oski_date && $row->oski_date->format('Y-m-d') === $date && !$row->oski_na;
                    $testMatch = $row->test_date && $row->test_date->format('Y-m-d') === $date && !$row->test_na;

                    if ($oskiMatch && !$isExcluded('oski')) {
                        $existingTotal += $countOf($row->group_hemis_id);
                    }
                    if ($testMatch && !$isExcluded('test')) {
                        $existingTotal += $countOf($row->group_hemis_id);
                    }
                }

                $combined = $existingTotal + ($info['students'] ?? 0);
                $perDayCapacity = ExamCapacityService::dailyCapacityForDate($date);
                if ($perDayCapacity > 0 && $combined > $perDayCapacity) {
                    $dateFormatted = \Carbon\Carbon::parse($date)->format('d.m.Y');
                    return redirect()->back()->with('error',
                        "Kun ustma-ust tushdi! {$dateFormatted} kuniga jami {$combined} talaba belgilanadi, lekin kunlik sig'im atigi {$perDayCapacity} ta. Boshqa kunni tanlang yoki sozlamalardan sig'imni oshiring.");
                }
            }
        }

        DB::beginTransaction();
        $bookingsToDispatch = [];
        $autoDistributeToDispatch = [];
        try {
            foreach ($validSchedules as $schedule) {
                $oskiNa = !empty($schedule['oski_na']);
                $testNa = !empty($schedule['test_na']);
                $hasResitData = !empty($schedule['oski_resit_date']) || !empty($schedule['test_resit_date'])
                              || !empty($schedule['oski_resit2_date']) || !empty($schedule['test_resit2_date']);
                $hasAnyData = !empty($schedule['oski_date']) || !empty($schedule['test_date'])
                              || $oskiNa || $testNa || $hasResitData;
                $isPerStudent = !empty($schedule['student_hemis_id']);

                if (!$hasAnyData) {
                    // Per-student rowni o'chirsa, faqat shu talabaning yozuvini tozalaymiz
                    $delQuery = ExamSchedule::where('group_hemis_id', $schedule['group_hemis_id'])
                        ->where('subject_id', $schedule['subject_id'])
                        ->where('semester_code', $schedule['semester_code']);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('exam_schedules', 'student_hemis_id')) {
                        if ($isPerStudent) {
                            $delQuery->where('student_hemis_id', $schedule['student_hemis_id']);
                        } else {
                            $delQuery->whereNull('student_hemis_id');
                        }
                    }
                    $delQuery->delete();
                    continue;
                }

                $studentHemisIdForRow = !empty($schedule['student_hemis_id']) ? $schedule['student_hemis_id'] : null;
                $hasStudentCol = \Illuminate\Support\Facades\Schema::hasColumn('exam_schedules', 'student_hemis_id');

                $recordQuery = ExamSchedule::query()
                    ->where('group_hemis_id', $schedule['group_hemis_id'])
                    ->where('subject_id', $schedule['subject_id'])
                    ->where('semester_code', $schedule['semester_code']);
                if ($hasStudentCol) {
                    if ($studentHemisIdForRow) {
                        $recordQuery->where('student_hemis_id', $studentHemisIdForRow);
                    } else {
                        $recordQuery->whereNull('student_hemis_id');
                    }
                }
                $record = $recordQuery->first() ?? new ExamSchedule([
                    'group_hemis_id' => $schedule['group_hemis_id'],
                    'subject_id' => $schedule['subject_id'],
                    'semester_code' => $schedule['semester_code'],
                ]);
                if ($hasStudentCol) {
                    $record->student_hemis_id = $studentHemisIdForRow;
                }

                // Per-student qatorlar uchun asosiy oski_date/test_date saqlanmaydi —
                // faqat resit/resit2 sanalari yoziladi (asosiy guruhga umumiy).
                // 2/3-urinish per-student qatorlarda forma `test_date`/`oski_date` nomi
                // bilan post qiladi (keyin urinishga qarab test_resit_date / test_resit2_date
                // ustuniga yoziladi), shuning uchun ularni ham "resit data" sifatida sanaymiz.
                if ($studentHemisIdForRow) {
                    $rowUrinishCheck = (int) ($schedule['urinish'] ?? 1);
                    $resitOnly = ['oski_resit_date', 'oski_resit2_date', 'test_resit_date', 'test_resit2_date'];
                    $hasAnyResit = false;
                    foreach ($resitOnly as $rf) {
                        if (!empty($schedule[$rf])) { $hasAnyResit = true; break; }
                    }
                    if (!$hasAnyResit && $rowUrinishCheck >= 2) {
                        $hasAnyResit = !empty($schedule['oski_date']) || !empty($schedule['test_date']);
                    }
                    if (!$hasAnyResit && !$record->exists) {
                        // Bo'sh per-student row — yaratmaymiz
                        continue;
                    }
                }

                // Urinish bo'yicha qaysi DB ustuniga yozish kerakligini aniqlaymiz.
                // Form har bir virtual qator uchun oski_date/test_date submit qiladi,
                // lekin urinish=2 bo'lsa oski_resit_date, urinish=3 bo'lsa oski_resit2_date.
                $rowUrinish = (int) ($schedule['urinish'] ?? 1);
                $oskiCol = match ($rowUrinish) { 2 => 'oski_resit_date', 3 => 'oski_resit2_date', default => 'oski_date' };
                $testCol = match ($rowUrinish) { 2 => 'test_resit_date', 3 => 'test_resit2_date', default => 'test_date' };

                // Cheklov 1: Allaqachon saqlangan sanani o'zgartirish mumkin emas
                $newOskiDate = !empty($schedule['oski_date']) ? $schedule['oski_date'] : null;
                $newOskiNa = $oskiNa;
                $newTestDate = !empty($schedule['test_date']) ? $schedule['test_date'] : null;
                $newTestNa = $testNa;

                // Yopilish shakliga qarab keraksiz turdagi sanalarni N/A qilib qo'yish.
                // KTR'da fanga "faqat OSKI" / "faqat Test" / "Yo'q" / "Normativ" / "Sinov" belgilangan
                // bo'lsa, foydalanuvchi qo'lda N/A bosishi shart emas — avto qo'yiladi.
                // Normativ va Sinov fanlar OSKI/Test orqali topshirilmaydi, shuning uchun ikkalasi N/A.
                $cf = $schedule['closing_form'] ?? null;
                if (in_array($cf, ['test', 'none', 'normativ', 'sinov'], true)) {
                    $newOskiDate = null;
                    $newOskiNa = true;
                }
                if (in_array($cf, ['oski', 'none', 'normativ', 'sinov'], true)) {
                    $newTestDate = null;
                    $newTestNa = true;
                }

                if ($record->exists && !$canEditSaved && $rowUrinish === 1) {
                    // Faqat 1-urinish uchun mavjud sanani himoya qilamiz
                    if ($record->oski_date || $record->oski_na) {
                        $newOskiDate = $record->oski_date?->format('Y-m-d');
                        $newOskiNa = (bool) $record->oski_na;
                    }
                    if ($record->test_date || $record->test_na) {
                        $newTestDate = $record->test_date?->format('Y-m-d');
                        $newTestNa = (bool) $record->test_na;
                    }
                }

                $fillData = [
                    'department_hemis_id' => $schedule['department_hemis_id'] ?? '',
                    'specialty_hemis_id' => $schedule['specialty_hemis_id'] ?? '',
                    'curriculum_hemis_id' => $schedule['curriculum_hemis_id'] ?? '',
                    'subject_name' => $schedule['subject_name'] ?? '',
                    'education_year' => $educationYear,
                    'updated_by' => $userId,
                ];
                if ($rowUrinish === 1) {
                    $fillData['oski_date'] = $newOskiDate;
                    $fillData['oski_na'] = $newOskiNa;
                    $fillData['test_date'] = $newTestDate;
                    $fillData['test_na'] = $newTestNa;
                } else {
                    // 2/3-urinish uchun resit ustuniga yozish (na flaglarini bu yerda saqlamaymiz)
                    $fillData[$oskiCol] = $newOskiDate;
                    $fillData[$testCol] = $newTestDate;
                }
                $record->fill($fillData);

                if (!$record->exists) {
                    $record->created_by = $userId;
                }

                $oskiDateChanged = $record->isDirty('oski_date') || $record->isDirty('oski_na');
                $testDateChanged = $record->isDirty('test_date') || $record->isDirty('test_na');

                // 1-urinish (12a) va 2-urinish (12b) qayta topshirish sanalarini ham qabul qilamiz
                $resitFields = ['oski_resit_date', 'oski_resit_time', 'test_resit_date', 'test_resit_time',
                                'oski_resit2_date', 'oski_resit2_time', 'test_resit2_date', 'test_resit2_time'];
                $oskiResitFields = ['oski_resit_date', 'oski_resit_time', 'oski_resit2_date', 'oski_resit2_time'];
                $testResitFields = ['test_resit_date', 'test_resit_time', 'test_resit2_date', 'test_resit2_time'];
                foreach ($resitFields as $rf) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('exam_schedules', $rf) && array_key_exists($rf, $schedule)) {
                        $record->{$rf} = !empty($schedule[$rf]) ? $schedule[$rf] : null;
                    }
                }
                // Yopilish shakliga mos kelmaydigan resit sanalarini tozalash
                if (in_array($cf, ['test', 'none', 'normativ', 'sinov'], true)) {
                    foreach ($oskiResitFields as $rf) {
                        if (\Illuminate\Support\Facades\Schema::hasColumn('exam_schedules', $rf)) {
                            $record->{$rf} = null;
                        }
                    }
                }
                if (in_array($cf, ['oski', 'none', 'normativ', 'sinov'], true)) {
                    foreach ($testResitFields as $rf) {
                        if (\Illuminate\Support\Facades\Schema::hasColumn('exam_schedules', $rf)) {
                            $record->{$rf} = null;
                        }
                    }
                }

                $resitOpened12a = ($record->isDirty('oski_resit_date') && !empty($record->oski_resit_date))
                    || ($record->isDirty('test_resit_date') && !empty($record->test_resit_date));
                $resitOpened12b = ($record->isDirty('oski_resit2_date') && !empty($record->oski_resit2_date))
                    || ($record->isDirty('test_resit2_date') && !empty($record->test_resit2_date));

                $record->save();

                // Sana belgilangan bo'lsa — yn_submission(attempt=2/3) ni avtomatik yaratish
                $this->autoOpenAttemptIfNeeded($record, 2, $resitOpened12a, $userId);
                $this->autoOpenAttemptIfNeeded($record, 3, $resitOpened12b, $userId);

                // Queue Moodle booking when date is set, time already exists, and not N/A.
                // Time is set by saveTestTime(); store() only triggers when both are present.
                if ($oskiDateChanged && $newOskiDate && !$newOskiNa && $record->oski_time) {
                    $bookingsToDispatch[] = [$record->id, 'oski'];
                }
                if ($testDateChanged && $newTestDate && !$newTestNa && $record->test_time) {
                    $bookingsToDispatch[] = [$record->id, 'test'];
                }

                // Date set without an explicit time → auto-distribute via JIT.
                // The job uses work_hours_start from settings, splits the
                // group across slots, and triggers Moodle booking once
                // distribute() persists the earliest slot as test_time.
                if ($oskiDateChanged && $newOskiDate && !$newOskiNa && empty($record->oski_time)) {
                    $autoDistributeToDispatch[] = [$record->id, 'oski'];
                }
                if ($testDateChanged && $newTestDate && !$newTestNa && empty($record->test_time)) {
                    $autoDistributeToDispatch[] = [$record->id, 'test'];
                }
            }
            DB::commit();

            foreach ($bookingsToDispatch as [$id, $yn]) {
                AssignComputersJob::dispatch($id, $yn);
                BookMoodleGroupExam::dispatch($id, $yn);
            }
            foreach ($autoDistributeToDispatch ?? [] as [$id, $yn]) {
                \App\Jobs\AutoDistributeOnDateSetJob::dispatch($id, $yn);
                BookMoodleGroupExam::dispatch($id, $yn)->delay(now()->addSeconds(15));
            }

            return redirect()->back()->with('success', 'Imtihon sanalari muvaffaqiyatli saqlandi!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
    }

    /**
     * Faqat admin: saqlangan YN sanasini o'chirish (oski yoki test)
     */
    public function clearDate(Request $request)
    {
        $user = auth()->user() ?? auth('teacher')->user();
        // Faol rolni tekshirish (multi-role foydalanuvchilar uchun session active_role ishlatiladi)
        $activeRole = session('active_role', $user?->getRoleNames()->first());
        $isAdmin = $user && in_array($activeRole, ExamDateRoleService::adminRoles(), true);
        $canEdit = $isAdmin || ExamDateRoleService::roleHasAnyAccess($activeRole);
        if (!$canEdit) {
            abort(403, 'Bu amalni bajarish uchun ruxsat yo\'q.');
        }

        $groupHemisId = $request->input('group_hemis_id');
        $subjectId = $request->input('subject_id');
        $semesterCode = $request->input('semester_code');
        $dateType = $request->input('date_type'); // 'oski' yoki 'test'

        if (!$groupHemisId || !$subjectId || !$semesterCode || !in_array($dateType, ['oski', 'test'])) {
            return redirect()->back()->with('error', 'Noto\'g\'ri so\'rov.');
        }

        // Admin bo'lmagan rol — faqat sozlamalarda ruxsat etilgan kurs darajasidagi yozuvni o'chirishi mumkin
        if (!$isAdmin) {
            $curriculumId = Group::where('group_hemis_id', $groupHemisId)->value('curriculum_hemis_id');
            $levelCode = $curriculumId
                ? (string) (Semester::where('curriculum_hemis_id', $curriculumId)->where('code', $semesterCode)->value('level_code') ?? '')
                : '';
            if ($levelCode !== '' && !ExamDateRoleService::canEditLevel($activeRole, $levelCode)) {
                abort(403, 'Sizning rolingizga ushbu kurs darajasi uchun YN sanasini o\'chirishga ruxsat yo\'q.');
            }
        }

        $record = ExamSchedule::where('group_hemis_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->first();

        if (!$record) {
            return redirect()->back()->with('error', 'Yozuv topilmadi.');
        }

        if ($dateType === 'oski') {
            $record->oski_date = null;
            $record->oski_na = false;
        } else {
            $record->test_date = null;
            $record->test_na = false;
        }

        $record->updated_by = auth()->id() ?? auth('teacher')->id();

        // Agar ikkala sana ham bo'sh bo'lsa — yozuvni o'chiramiz
        if (!$record->oski_date && !$record->oski_na && !$record->test_date && !$record->test_na) {
            $record->delete();
        } else {
            $record->save();
        }

        $typeLabel = $dateType === 'oski' ? 'OSKI' : 'Test';
        return redirect()->back()->with('success', "{$typeLabel} sanasi muvaffaqiyatli o'chirildi.");
    }

    /**
     * Bidirectional filter: barcha filtr optionlarini qaytaradi
     */
    public function getFilterOptions(Request $request)
    {
        $educationType = $request->education_type ?: null;
        $departmentId = $request->department_id ?: null;
        $specialtyId = $request->specialty_id ?: null;
        $levelCode = $request->level_code ?: null;
        $semesterCode = $request->semester_code ?: null;
        $currentSemesterOnly = $request->get('current_semester', '1') === '1';

        // Joriy o'quv yili
        $currentEducationYear = Semester::where('current', true)->value('education_year');

        $currentSemSubquery = $currentSemesterOnly
            ? Semester::where('current', true)->select('curriculum_hemis_id')
            : Semester::where('education_year', $currentEducationYear)->select('curriculum_hemis_id');

        // Curriculum query builder: barcha filtrlarni qo'llaydi, $exclude ni tashlab
        $buildQuery = function (?string $exclude = null) use (
            $currentSemSubquery, $currentSemesterOnly, $currentEducationYear,
            $educationType, $departmentId, $specialtyId, $levelCode, $semesterCode
        ) {
            $q = Curriculum::where(function ($sub) use ($currentSemSubquery, $currentSemesterOnly) {
                if ($currentSemesterOnly) {
                    $sub->where('current', true)
                        ->orWhereIn('curricula_hemis_id', $currentSemSubquery);
                } else {
                    $sub->whereIn('curricula_hemis_id', $currentSemSubquery);
                }
            });

            if ($exclude !== 'education_type' && $educationType) {
                $q->where('education_type_code', $educationType);
            }
            if ($exclude !== 'department_id' && $departmentId) {
                $q->where('department_hemis_id', $departmentId);
            }
            if ($exclude !== 'specialty_id' && $specialtyId) {
                $q->where('specialty_hemis_id', $specialtyId);
            }

            $applyLevel = ($exclude !== 'level_code' && $levelCode);
            $applySemester = ($exclude !== 'semester_code' && $semesterCode);

            if ($applyLevel || $applySemester) {
                $q->whereIn('curricula_hemis_id', function ($sub) use ($applyLevel, $applySemester, $levelCode, $semesterCode, $currentSemesterOnly, $currentEducationYear) {
                    $sub->select('curriculum_hemis_id')->from('semesters');
                    if ($currentSemesterOnly) {
                        $sub->where('current', true);
                    } else {
                        $sub->where('education_year', $currentEducationYear);
                    }
                    if ($applyLevel) $sub->where('level_code', $levelCode);
                    if ($applySemester) $sub->where('code', $semesterCode);
                });
            }

            return $q;
        };

        // 1. Ta'lim turlari (education_type filtrini tashlab)
        $educationTypes = $buildQuery('education_type')
            ->select('education_type_code', 'education_type_name')
            ->groupBy('education_type_code', 'education_type_name')
            ->orderBy('education_type_name')
            ->get();

        // 2. Fakultetlar (department filtrini tashlab)
        $deptHemisIds = $buildQuery('department_id')->pluck('department_hemis_id')->unique();
        $departments = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->whereIn('department_hemis_id', $deptHemisIds)
            ->orderBy('name')
            ->get(['department_hemis_id', 'name']);

        // 3. Yo'nalishlar (specialty filtrini tashlab)
        $specHemisIds = $buildQuery('specialty_id')->pluck('specialty_hemis_id')->unique();
        $specialties = Specialty::whereIn('specialty_hemis_id', $specHemisIds)
            ->orderBy('name')
            ->get(['specialty_hemis_id', 'name']);

        // 4. Kurslar (level filtrini tashlab) — barcha viewer rollar uchun
        // hech qanday cheklovsiz to'liq ro'yxat. Sana qo'yish huquqi store() da
        // urinish + kurs darajasi bo'yicha alohida tekshiriladi.
        $levelCurrIds = $buildQuery('level_code')->pluck('curricula_hemis_id');
        $levelQuery = Semester::where($currentSemesterOnly ? 'current' : 'education_year', $currentSemesterOnly ? true : $currentEducationYear)
            ->whereIn('curriculum_hemis_id', $levelCurrIds);
        if ($semesterCode) $levelQuery->where('code', $semesterCode);

        $levels = $levelQuery->select('level_code', 'level_name')
            ->distinct()->orderBy('level_code')->get();

        // 5. Semestrlar (semester filtrini tashlab)
        $semCurrIds = $buildQuery('semester_code')->pluck('curricula_hemis_id');
        $semQuery = Semester::where($currentSemesterOnly ? 'current' : 'education_year', $currentSemesterOnly ? true : $currentEducationYear)
            ->whereIn('curriculum_hemis_id', $semCurrIds);
        if ($levelCode) $semQuery->where('level_code', $levelCode);
        $semesters = $semQuery->select('code', 'name')
            ->distinct()->orderBy('code')->get();

        // 6. Guruhlar (barcha filtrlar)
        $allCurrIds = $buildQuery()->pluck('curricula_hemis_id');
        $groups = Group::where('active', true)
            ->whereIn('curriculum_hemis_id', $allCurrIds)
            ->orderBy('name')
            ->get(['group_hemis_id', 'name']);

        // 7. Fanlar (barcha filtrlar)
        $subjectQuery = CurriculumSubject::whereIn('curricula_hemis_id', $allCurrIds)
            ->where('is_active', true);
        if ($semesterCode) {
            $subjectQuery->where('semester_code', $semesterCode);
        } elseif ($levelCode) {
            $levelSemCodes = Semester::where($currentSemesterOnly ? 'current' : 'education_year', $currentSemesterOnly ? true : $currentEducationYear)
                ->where('level_code', $levelCode)->pluck('code')->unique();
            $subjectQuery->whereIn('semester_code', $levelSemCodes);
        }
        $subjects = $subjectQuery->select('subject_id', 'subject_name')
            ->distinct()->orderBy('subject_name')->get();

        return response()->json(compact(
            'educationTypes', 'departments', 'specialties',
            'levels', 'semesters', 'groups', 'subjects'
        ));
    }

    /**
     * Test markazi: YN oldi qaydnoma Word hujjat yaratish
     * YnSubmission'ga bog'liq emas — to'g'ridan-to'g'ri subject_id orqali ishlaydi
     */
    public function generateYnOldiWord(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.group_hemis_id' => 'required|string',
            'items.*.semester_code' => 'required|string',
            'items.*.subject_id' => 'required|string',
        ]);

        try {

        $items = $request->items;
        $files = [];
        $tempDir = storage_path('app/public/yn_oldi_qaydnoma');

        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Step 1: Collect all data and group by subject_id
        $subjectGroups = [];

        foreach ($items as $itemData) {
            $group = Group::where('group_hemis_id', $itemData['group_hemis_id'])->first();
            if (!$group) continue;

            $semesterCode = $itemData['semester_code'];
            $subjectId = $itemData['subject_id'];

            $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
                ->where('code', $semesterCode)
                ->first();

            $department = Department::where('department_hemis_id', $group->department_hemis_id)
                ->where('structure_type_code', 11)
                ->first();

            $specialty = Specialty::where('specialty_hemis_id', $group->specialty_hemis_id)->first();

            $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->first();

            if (!$subject) continue;

            // Talabalarni olish
            $students = Student::select('id', 'full_name as student_name', 'student_id_number as student_id', 'hemis_id')
                ->where('group_id', $group->group_hemis_id)
                ->groupBy('id')
                ->orderBy('full_name')
                ->get();

            // JN/MT ni jurnal "ixcham" tabi bilan bir xil mantiqda jonli hisoblash
            // (snapshot ishlatilmaydi; retake-priority qoidasi va NB=0 mantiqi qo'llaniladi).
            $liveGrades = app(JnMtCalculator::class)->computeForGroup(
                (string) $group->group_hemis_id,
                (int) $subject->subject_id,
                $semesterCode
            );

            foreach ($students as $student) {
                $student->jn = $liveGrades[$student->hemis_id]['jn'] ?? 0;
                $student->mt = $liveGrades[$student->hemis_id]['mt'] ?? 0;
            }

            // O'qituvchilarni olish
            $studentIds = Student::where('group_id', $group->group_hemis_id)
                ->groupBy('hemis_id')
                ->pluck('hemis_id');

            $maruzaTeacher = DB::table('student_grades as s')
                ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
                ->select(DB::raw('GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS full_names'))
                ->where('s.subject_id', $subject->subject_id)
                ->where('s.training_type_code', 11)
                ->whereIn('s.student_hemis_id', $studentIds)
                ->groupBy('s.employee_id')
                ->first();

            $otherTeachers = DB::table('student_grades as s')
                ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
                ->select(DB::raw('GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS full_names'))
                ->where('s.subject_id', $subject->subject_id)
                ->where('s.training_type_code', '!=', 11)
                ->whereIn('s.student_hemis_id', $studentIds)
                ->groupBy('s.employee_id')
                ->get();

            $otherTeacherNames = [];
            foreach ($otherTeachers as $t) {
                foreach (explode(', ', $t->full_names) as $name) {
                    $name = trim($name);
                    if ($name && !in_array($name, $otherTeacherNames)) {
                        $otherTeacherNames[] = $name;
                    }
                }
            }

            $maruzaTeacherNames = [];
            if ($maruzaTeacher && $maruzaTeacher->full_names) {
                foreach (explode(', ', $maruzaTeacher->full_names) as $name) {
                    $name = trim($name);
                    if ($name && !in_array($name, $maruzaTeacherNames)) {
                        $maruzaTeacherNames[] = $name;
                    }
                }
            }

            $subjectKey = $subject->subject_id;
            if (!isset($subjectGroups[$subjectKey])) {
                $subjectGroups[$subjectKey] = [
                    'subject' => $subject,
                    'semester' => $semester,
                    'department' => $department,
                    'specialty' => $specialty,
                    'groupNames' => [],
                    'allMaruzaTeachers' => [],
                    'allOtherTeachers' => [],
                    'entries' => [],
                ];
            }

            // Guruh nomlarini yig'ish
            if ($group->name && !in_array($group->name, $subjectGroups[$subjectKey]['groupNames'])) {
                $subjectGroups[$subjectKey]['groupNames'][] = $group->name;
            }

            // Ma'ruzachi o'qituvchilarni yig'ish
            foreach ($maruzaTeacherNames as $name) {
                if (!in_array($name, $subjectGroups[$subjectKey]['allMaruzaTeachers'])) {
                    $subjectGroups[$subjectKey]['allMaruzaTeachers'][] = $name;
                }
            }

            // Amaliyot o'qituvchilarni yig'ish
            foreach ($otherTeacherNames as $name) {
                if (!in_array($name, $subjectGroups[$subjectKey]['allOtherTeachers'])) {
                    $subjectGroups[$subjectKey]['allOtherTeachers'][] = $name;
                }
            }

            $subjectGroups[$subjectKey]['entries'][] = [
                'group' => $group,
                'semester' => $semester,
                'department' => $department,
                'students' => $students,
                'subject' => $subject,
            ];
        }

        // Step 2: Har bir fan uchun bitta Word hujjat yaratish
        foreach ($subjectGroups as $subjectKey => $subjectData) {
            $subject = $subjectData['subject'];
            $semester = $subjectData['semester'];
            $department = $subjectData['department'];
            $groupNames = $subjectData['groupNames'];
            $allMaruzaText = implode(', ', $subjectData['allMaruzaTeachers']) ?: '-';
            $allOtherText = implode(', ', $subjectData['allOtherTeachers']) ?: '-';

            // Word hujjat yaratish
            $phpWord = new PhpWord();
            $phpWord->setDefaultFontName('Times New Roman');
            $phpWord->setDefaultFontSize(12);

            $section = $phpWord->addSection([
                'orientation' => 'landscape',
                'marginTop' => 600,
                'marginBottom' => 600,
                'marginLeft' => 800,
                'marginRight' => 600,
            ]);

            $section->addText(
                '12-shakl',
                ['bold' => true, 'size' => 11],
                ['alignment' => Jc::END, 'spaceAfter' => 100]
            );

            $section->addText(
                'YAKUNIY NAZORAT OLDIDAN QAYDNOMA',
                ['bold' => true, 'size' => 14],
                ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
            );

            $infoStyle = ['size' => 11];
            $infoBold = ['bold' => true, 'size' => 11];
            $infoParaStyle = ['spaceAfter' => 40];

            $textRun = $section->addTextRun($infoParaStyle);
            $textRun->addText('Fakultet: ', $infoBold);
            $textRun->addText($department->name ?? '-', $infoStyle);

            $textRun = $section->addTextRun($infoParaStyle);
            $textRun->addText('Kurs: ', $infoBold);
            $textRun->addText($semester->level_name ?? '-', $infoStyle);
            $textRun->addText('     Semestr: ', $infoBold);
            $textRun->addText($semester->name ?? '-', $infoStyle);
            $textRun->addText('     Guruh: ', $infoBold);
            $textRun->addText(implode(', ', $groupNames) ?: '-', $infoStyle);

            $textRun = $section->addTextRun($infoParaStyle);
            $textRun->addText('Fan: ', $infoBold);
            $textRun->addText($subject->subject_name ?? '-', $infoStyle);

            $textRun = $section->addTextRun($infoParaStyle);
            $textRun->addText("Ma'ruzachi: ", $infoBold);
            $textRun->addText($allMaruzaText, $infoStyle);

            $textRun = $section->addTextRun($infoParaStyle);
            $textRun->addText("Amaliyot o'qituvchilari: ", $infoBold);
            $textRun->addText($allOtherText, $infoStyle);

            $textRun = $section->addTextRun(['spaceAfter' => 150]);
            $textRun->addText('Soatlar soni: ', $infoBold);
            $textRun->addText($subject->total_acload ?? '-', $infoStyle);

            $section->addText(
                'Sana: ' . now()->format('d.m.Y'),
                $infoStyle,
                ['alignment' => Jc::END, 'spaceAfter' => 150]
            );

            // Jadval
            $tableStyle = [
                'borderSize' => 6,
                'borderColor' => '000000',
                'cellMargin' => 40,
            ];
            $tableName = 'YnOldiTable_' . $subjectKey;
            $phpWord->addTableStyle($tableName, $tableStyle);
            $table = $section->addTable($tableName);

            $headerFont = ['bold' => true, 'size' => 10];
            $cellFont = ['size' => 10];
            $cellFontRed = ['size' => 10, 'color' => 'FF0000'];
            $headerBg = ['bgColor' => 'D9E2F3', 'valign' => 'center'];
            $groupSeparatorBg = ['bgColor' => 'E2EFDA', 'valign' => 'center', 'gridSpan' => 8];
            $cellCenter = ['alignment' => Jc::CENTER];
            $cellLeft = ['alignment' => Jc::START];
            $cellFontOrange = ['size' => 10, 'color' => 'FF8C00'];

            $headerRow = $table->addRow(400);
            $headerRow->addCell(600, $headerBg)->addText('№', $headerFont, $cellCenter);
            $headerRow->addCell(4000, $headerBg)->addText('Talaba F.I.O', $headerFont, $cellCenter);
            $headerRow->addCell(1800, $headerBg)->addText('Talaba ID', $headerFont, $cellCenter);
            $headerRow->addCell(1000, $headerBg)->addText('JN', $headerFont, $cellCenter);
            $headerRow->addCell(1000, $headerBg)->addText('MT', $headerFont, $cellCenter);
            $headerRow->addCell(1300, $headerBg)->addText('Davomat %', $headerFont, $cellCenter);
            $headerRow->addCell(1300, $headerBg)->addText('Kontrakt', $headerFont, $cellCenter);
            $headerRow->addCell(1800, $headerBg)->addText('YN ga ruxsat', $headerFont, $cellCenter);

            // Talabalar ro'yxati - barcha guruhlar uchun davom etadi
            $rowNum = 1;
            $multipleGroups = count($subjectData['entries']) > 1;

            // Kontrakt to'lov muddatlari — sozlamalardan o'qish
            $defaultCutoffs = json_encode([
                ['deadline' => '2025-10-01', 'percent' => 25],
                ['deadline' => '2026-01-01', 'percent' => 50],
                ['deadline' => '2026-03-01', 'percent' => 75],
                ['deadline' => '2026-05-01', 'percent' => 100],
            ]);
            $cutoffsRaw = json_decode(Setting::get('contract_cutoffs', $defaultCutoffs), true) ?: [];
            $now = time();
            $contractThreshold = 100; // default: barcha muddatlar o'tgan
            foreach ($cutoffsRaw as $cutoff) {
                if ($now <= strtotime($cutoff['deadline'] . ' 23:59:59')) {
                    $contractThreshold = (int) $cutoff['percent'];
                    break;
                }
            }

            foreach ($subjectData['entries'] as $entry) {
                $entryGroup = $entry['group'];
                $entryStudents = $entry['students'];
                $entrySubject = $entry['subject'];

                // Bir nechta guruh bo'lsa, guruh nomi bilan ajratuvchi qator qo'shish
                if ($multipleGroups) {
                    $separatorRow = $table->addRow(350);
                    $separatorRow->addCell(12800, $groupSeparatorBg)->addText(
                        $entryGroup->name,
                        ['bold' => true, 'size' => 10, 'color' => '1F4E20'],
                        $cellCenter
                    );
                }

                $nonAuditoriumCodes = ['17'];
                $entryAuditoriumHours = 0;
                if (is_array($entrySubject->subject_details)) {
                    foreach ($entrySubject->subject_details as $detail) {
                        $trainingCode = (string) (($detail['trainingType'] ?? [])['code'] ?? '');
                        if ($trainingCode !== '' && !in_array($trainingCode, $nonAuditoriumCodes)) {
                            $entryAuditoriumHours += (float) ($detail['academic_load'] ?? 0);
                        }
                    }
                }
                if ($entryAuditoriumHours <= 0) {
                    $entryAuditoriumHours = (float) ($entrySubject->total_acload ?: 1);
                }

                foreach ($entryStudents as $student) {
                    $markingScore = MarkingSystemScore::getByStudentHemisId($student->hemis_id);

                    $qoldirgan = (int) Attendance::where('group_id', $entryGroup->group_hemis_id)
                        ->where('subject_id', $entrySubject->subject_id)
                        ->where('student_hemis_id', $student->hemis_id)
                        ->where('semester_code', $semesterCode)
                        ->whereNotIn('training_type_code', [99, 100, 101, 102])
                        ->sum('absent_off');

                    $qoldiq = round($qoldirgan * 100 / $entryAuditoriumHours, 2);

                    // Kontrakt qarzdorligi tekshiruvi
                    $contract = ContractList::where('student_hemis_id', $student->hemis_id)
                        ->where('year', '2025')
                        ->where('edu_year', 'like', '2025-2026%')
                        ->first();

                    $contractPercent = 100; // default: kontrakt topilmasa ruxsat
                    $contractText = '-';
                    $contractFailed = false;
                    if ($contract && $contract->edu_contract_sum > 0) {
                        $contractPercent = round(($contract->paid_credit_amount / $contract->edu_contract_sum) * 100);
                        $contractText = $contractPercent . '%';
                        if ($contractPercent < $contractThreshold) {
                            $contractFailed = true;
                        }
                    }

                    $holat = 'Ruxsat';
                    $jnFailed = false;
                    $mtFailed = false;
                    $davomatFailed = false;

                    if ($student->jn < $markingScore->effectiveLimit('jn')) {
                        $jnFailed = true;
                        $holat = 'X';
                    }
                    if ($student->mt < $markingScore->effectiveLimit('mt')) {
                        $mtFailed = true;
                        $holat = 'X';
                    }
                    if ($qoldiq >= 25) {
                        $davomatFailed = true;
                        $holat = 'X';
                    }
                    // Kontrakt: "X" emas, "Shartli" holat beradi
                    if ($contractFailed && $holat === 'Ruxsat') {
                        $holat = 'Shartli';
                    }

                    $dataRow = $table->addRow();
                    $dataRow->addCell(600)->addText($rowNum, $cellFont, $cellCenter);
                    $dataRow->addCell(4000)->addText($student->student_name, $cellFont, $cellLeft);
                    $dataRow->addCell(1800)->addText($student->student_id, $cellFont, $cellCenter);

                    $jnCell = $dataRow->addCell(1000);
                    $jnCell->addText($student->jn ?? '0', $jnFailed ? $cellFontRed : $cellFont, $cellCenter);

                    $mtCell = $dataRow->addCell(1000);
                    $mtCell->addText($student->mt ?? '0', $mtFailed ? $cellFontRed : $cellFont, $cellCenter);

                    $davomatCell = $dataRow->addCell(1300);
                    $davomatCell->addText(
                        ($qoldiq != 0 ? $qoldiq . '%' : '0%'),
                        $davomatFailed ? $cellFontRed : $cellFont,
                        $cellCenter
                    );

                    $kontraktCell = $dataRow->addCell(1300);
                    $kontraktCell->addText(
                        $contractText,
                        $contractFailed ? $cellFontOrange : $cellFont,
                        $cellCenter
                    );

                    $holatCell = $dataRow->addCell(1800);
                    if ($holat === 'Shartli') {
                        $holatCell->addText($holat, $cellFontOrange, $cellCenter);
                    } else {
                        $holatCell->addText($holat, $holat === 'X' ? $cellFontRed : $cellFont, $cellCenter);
                    }

                    $rowNum++;
                }
            }

            // Imzolar
            $section->addTextBreak(1);

            $dekan = Teacher::whereHas('deanFaculties', fn($q) => $q->where('dean_faculties.department_hemis_id', $department->department_hemis_id ?? ''))
                ->whereHas('roles', fn($q) => $q->where('name', ProjectRole::DEAN->value))
                ->first();

            $signTable = $section->addTable();
            $signRow = $signTable->addRow();
            $signRow->addCell(6500)->addText("Dekan: ___________________  " . ($dekan->full_name ?? ''), ['size' => 11]);
            $signRow->addCell(6500)->addText("Ma'ruzachi: ___________________  " . ($allMaruzaText), ['size' => 11]);

            // QR code yaratish
            $generatedBy = null;
            if (auth()->guard('teacher')->check()) {
                $generatedBy = auth()->guard('teacher')->user()->full_name ?? auth()->guard('teacher')->user()->name ?? null;
            } elseif (auth()->guard('web')->check()) {
                $generatedBy = auth()->guard('web')->user()->name ?? null;
            }

            $verification = DocumentVerification::createForDocument([
                'document_type' => 'YN oldi qaydnoma',
                'subject_name' => $subject->subject_name,
                'group_names' => implode(', ', $groupNames),
                'semester_name' => $semester->name ?? null,
                'department_name' => $department->name ?? null,
                'generated_by' => $generatedBy,
            ]);

            $verificationUrl = $verification->getVerificationUrl();
            $qrImagePath = null;

            try {
                $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($verificationUrl) . '&size=200x200';
                $client = new \GuzzleHttp\Client();
                $qrTempPath = $tempDir . '/' . time() . '_' . mt_rand(1000, 9999) . '_qr.png';
                $response = $client->request('GET', $qrApiUrl, [
                    'verify' => false,
                    'sink' => $qrTempPath,
                    'timeout' => 10,
                ]);
                if ($response->getStatusCode() == 200 && file_exists($qrTempPath) && filesize($qrTempPath) > 0) {
                    $qrImagePath = $qrTempPath;
                }
            } catch (\Exception $e) {
                // QR code generatsiyasi muvaffaqiyatsiz bo'lsa, davom etamiz
            }

            if ($qrImagePath) {
                $section->addTextBreak(1);
                $section->addImage($qrImagePath, [
                    'width' => 80,
                    'height' => 80,
                    'alignment' => Jc::START,
                ]);
                $section->addText(
                    'Hujjat haqiqiyligini tekshirish uchun QR kodni skanerlang',
                    ['size' => 8, 'italic' => true, 'color' => '666666'],
                    ['spaceAfter' => 0]
                );
            }

            $groupNamesStr = str_replace(['/', '\\', ' '], '_', implode('_', $groupNames));
            $subjectNameStr = str_replace(['/', '\\', ' '], '_', $subject->subject_name);
            $fileName = 'YN_oldi_qaydnoma_' . $groupNamesStr . '_' . $subjectNameStr . '.docx';
            $tempPath = $tempDir . '/' . time() . '_' . mt_rand(1000, 9999) . '_' . $fileName;

            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($tempPath);

            // Hujjatni PDF ga aylantirib doimiy saqlash (tekshirish sahifasi uchun)
            try {
                $pdfStorageDir = storage_path('app/public/documents/verified');
                if (!is_dir($pdfStorageDir)) {
                    mkdir($pdfStorageDir, 0755, true);
                }

                $pdfCommand = sprintf(
                    'soffice --headless --convert-to pdf --outdir %s %s 2>&1',
                    escapeshellarg($pdfStorageDir),
                    escapeshellarg($tempPath)
                );
                exec($pdfCommand, $pdfOutput, $pdfReturnCode);

                $generatedPdfName = pathinfo(basename($tempPath), PATHINFO_FILENAME) . '.pdf';
                $generatedPdfFullPath = $pdfStorageDir . '/' . $generatedPdfName;

                if ($pdfReturnCode === 0 && file_exists($generatedPdfFullPath)) {
                    $permanentPdfName = $verification->token . '.pdf';
                    $permanentPdfPath = $pdfStorageDir . '/' . $permanentPdfName;
                    rename($generatedPdfFullPath, $permanentPdfPath);
                    $verification->update(['document_path' => 'documents/verified/' . $permanentPdfName]);
                }
            } catch (\Throwable $e) {
                // PDF saqlash muvaffaqiyatsiz bo'lsa, davom etamiz
            }

            // QR vaqtinchalik faylni tozalash
            if ($qrImagePath) {
                @unlink($qrImagePath);
            }

            $files[] = [
                'path' => $tempPath,
                'name' => $fileName,
            ];
        }

        if (count($files) === 0) {
            return response()->json(['error' => 'Hech qanday ma\'lumot topilmadi'], 404);
        }

        if (count($files) === 1) {
            return response()->download($files[0]['path'], $files[0]['name'])->deleteFileAfterSend(true);
        }

        $zipPath = $tempDir . '/' . time() . '_yn_oldi_qaydnomalar.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);

        foreach ($files as $file) {
            $zip->addFile($file['path'], $file['name']);
        }
        $zip->close();

        foreach ($files as $file) {
            @unlink($file['path']);
        }

        return response()->download($zipPath, 'YN_oldi_qaydnomalar_' . now()->format('d_m_Y') . '.zip')->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Xatolik: ' . $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ], 500);
        }
    }

    /**
     * Test markazi: berilgan kun uchun maxsus ish vaqti / tushlik / sig'im sozlamalarini olish
     */
    public function getDayOverride(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        $request->validate(['date' => 'required|date_format:Y-m-d']);
        $date = $request->input('date');

        $override = ExamCapacityOverride::where('date', $date)->first();
        $defaults = ExamCapacityService::getSettings();
        $effective = ExamCapacityService::getSettingsForDate($date);

        return response()->json([
            'date' => $date,
            'defaults' => $defaults,
            'effective' => $effective,
            'override' => $override ? [
                'work_hours_start' => $override->work_hours_start ? substr($override->work_hours_start, 0, 5) : null,
                'work_hours_end' => $override->work_hours_end ? substr($override->work_hours_end, 0, 5) : null,
                'lunch_start' => $override->lunch_start ? substr($override->lunch_start, 0, 5) : null,
                'lunch_end' => $override->lunch_end ? substr($override->lunch_end, 0, 5) : null,
                'computer_count' => $override->computer_count,
                'test_duration_minutes' => $override->test_duration_minutes,
                'note' => $override->note,
            ] : null,
            'daily_capacity' => ExamCapacityService::dailyCapacityForDate($date),
        ]);
    }

    /**
     * Test markazi: berilgan kun(lar) uchun maxsus sozlamalarni saqlash (override).
     * Bir nechta kun bir vaqtda saqlanishi mumkin: `dates[]` array yoki `date` skalar.
     */
    public function saveDayOverride(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        if ($this->isTestCenterReadOnly()) {
            return response()->json(['success' => false, 'message' => 'Bu amalga ruxsat yo\'q.'], 403);
        }

        $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'dates' => 'nullable|array',
            'dates.*' => 'date_format:Y-m-d',
            'work_hours_start' => 'nullable|date_format:H:i',
            'work_hours_end' => 'nullable|date_format:H:i',
            'lunch_start' => 'nullable|date_format:H:i',
            'lunch_end' => 'nullable|date_format:H:i',
            'computer_count' => 'nullable|integer|min:1|max:100000',
            'test_duration_minutes' => 'nullable|integer|min:1|max:1440',
            'note' => 'nullable|string|max:255',
            'clear' => 'nullable|boolean',
        ]);

        // Sanalarni yig'ish (dates yoki date)
        $dates = collect($request->input('dates', []))->filter()->values()->all();
        if (empty($dates) && $request->filled('date')) {
            $dates = [$request->input('date')];
        }
        if (empty($dates)) {
            return response()->json(['success' => false, 'message' => 'Kamida bitta sana tanlash kerak.'], 422);
        }

        $clearAll = (bool) $request->input('clear', false);
        $userId = auth()->id() ?? auth('teacher')->id();

        // Mantiqiy validatsiya
        if (!$clearAll) {
            if ($request->filled('work_hours_start') && $request->filled('work_hours_end')) {
                $ws = \Carbon\Carbon::createFromFormat('H:i', $request->input('work_hours_start'));
                $we = \Carbon\Carbon::createFromFormat('H:i', $request->input('work_hours_end'));
                if ($we->lte($ws)) {
                    return response()->json(['success' => false, 'message' => 'Ish vaqti tugashi boshlanishidan keyin bo\'lishi kerak.'], 422);
                }
            }
            if ($request->filled('lunch_start') && $request->filled('lunch_end')) {
                $ls = \Carbon\Carbon::createFromFormat('H:i', $request->input('lunch_start'));
                $le = \Carbon\Carbon::createFromFormat('H:i', $request->input('lunch_end'));
                if ($le->lte($ls)) {
                    return response()->json(['success' => false, 'message' => 'Tushlik tugashi boshlanishidan keyin bo\'lishi kerak.'], 422);
                }
            }
        }

        $hasAny = $request->filled('work_hours_start')
            || $request->filled('work_hours_end')
            || $request->filled('lunch_start')
            || $request->filled('lunch_end')
            || $request->filled('computer_count')
            || $request->filled('test_duration_minutes')
            || $request->filled('note');

        $savedCount = 0;
        $clearedCount = 0;
        $perDay = [];

        foreach ($dates as $date) {
            if ($clearAll || !$hasAny) {
                $deleted = ExamCapacityOverride::where('date', $date)->delete();
                if ($deleted) {
                    $clearedCount++;
                }
            } else {
                $override = ExamCapacityOverride::firstOrNew(['date' => $date]);
                $override->fill([
                    'work_hours_start' => $request->input('work_hours_start') ?: null,
                    'work_hours_end' => $request->input('work_hours_end') ?: null,
                    'lunch_start' => $request->input('lunch_start') ?: null,
                    'lunch_end' => $request->input('lunch_end') ?: null,
                    'computer_count' => $request->filled('computer_count') ? (int) $request->input('computer_count') : null,
                    'test_duration_minutes' => $request->filled('test_duration_minutes') ? (int) $request->input('test_duration_minutes') : null,
                    'note' => $request->input('note'),
                    'updated_by' => $userId,
                ]);
                if (!$override->exists) {
                    $override->created_by = $userId;
                }
                $override->save();
                $savedCount++;
            }
            $perDay[$date] = [
                'effective' => ExamCapacityService::getSettingsForDate($date),
                'daily_capacity' => ExamCapacityService::dailyCapacityForDate($date),
            ];
        }

        $message = $clearAll || !$hasAny
            ? "Tanlangan {$clearedCount} ta kunda maxsus sozlama olib tashlandi."
            : "Tanlangan {$savedCount} ta kun uchun maxsus sozlama saqlandi.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'saved_count' => $savedCount,
            'cleared_count' => $clearedCount,
            'per_day' => $perDay,
        ]);
    }

    /**
     * Bulk: re-run AutoAssignService::distribute() for every exam_schedules
     * row in the requested date range that has a yn date but no yn time
     * yet. Covers both test and oski. Lets the test centre fix records
     * whose AutoDistributeOnDateSetJob never ran (queue outage, dates
     * set before that feature shipped, etc.) without clicking the per-row
     * 🎲 button on every line.
     *
     * Restricted to the test_markazi role only.
     */
    public function autoTimeAll(Request $request, AutoAssignService $service)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }

        $user = auth()->user() ?? auth('teacher')->user();
        $activeRole = session('active_role', $user?->getRoleNames()->first());
        if ($activeRole !== \App\Enums\ProjectRole::TEST_CENTER->value) {
            return back()->with('error', "Avto-vaqt belgilash faqat Test markazi roli uchun ochiq.");
        }

        $today = now()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');
        $dateFrom = $request->input('date_from', $tomorrow);
        $dateTo   = $request->input('date_to', $tomorrow);

        try {
            $from = \Carbon\Carbon::parse($dateFrom)->format('Y-m-d');
            $to   = \Carbon\Carbon::parse($dateTo)->format('Y-m-d');
        } catch (\Throwable $e) {
            return back()->with('error', "Sana noto'g'ri formatda.");
        }
        if ($to < $from) {
            $to = $from;
        }
        // Test markazi roli uchun: vaqtni faqat ertangi va undan keyingi
        // sanalarga belgilash mumkin (o'sha kun va o'tgan kunlar taqiqlanadi).
        if ($from <= $today) {
            $from = $tomorrow;
        }
        if ($to < $from) {
            return back()->with('error', "Test markazi rolida vaqt belgilashni faqat ertangi va undan keyingi sanalar uchun amalga oshirish mumkin.");
        }

        // Each (yn_type, attempt) is a separate column triplet. For
        // attempt=1 we run the full AutoAssignService::distribute()
        // (slot allocation + ComputerAssignments + time); for attempts
        // 2/3 (resit) we only set the time, mirroring saveTestTime() —
        // ComputerAssignment doesn't model attempts so resit students
        // are scheduled by the proctor manually.
        $columnSets = [
            'test' => [
                1 => ['date' => 'test_date',         'time' => 'test_time',         'na' => 'test_na', 'distribute' => true],
                2 => ['date' => 'test_resit_date',   'time' => 'test_resit_time',   'na' => null,      'distribute' => false],
                3 => ['date' => 'test_resit2_date',  'time' => 'test_resit2_time',  'na' => null,      'distribute' => false],
            ],
            'oski' => [
                1 => ['date' => 'oski_date',         'time' => 'oski_time',         'na' => 'oski_na', 'distribute' => true],
                2 => ['date' => 'oski_resit_date',   'time' => 'oski_resit_time',   'na' => null,      'distribute' => false],
                3 => ['date' => 'oski_resit2_date',  'time' => 'oski_resit2_time',  'na' => null,      'distribute' => false],
            ],
        ];

        // Pull every schedule whose ANY (yn_type, attempt) date falls in
        // the range and is missing the matching time.
        $candidates = ExamSchedule::query()
            ->where(function ($q) use ($from, $to, $columnSets) {
                foreach ($columnSets as $cols) {
                    foreach ($cols as $c) {
                        $q->orWhere(function ($qq) use ($from, $to, $c) {
                            $qq->whereBetween($c['date'], [$from, $to])
                                ->whereNull($c['time']);
                            if ($c['na']) {
                                $qq->where(function ($x) use ($c) {
                                    $x->where($c['na'], false)->orWhereNull($c['na']);
                                });
                            }
                        });
                    }
                }
            })
            ->get();

        if ($candidates->isEmpty()) {
            return back()->with('warning', "Tanlangan oraliqda vaqt belgilashga muhtoj yozuv topilmadi.");
        }

        $okCount = 0;
        $failures = [];

        foreach ($candidates as $schedule) {
            foreach ($columnSets as $ynType => $attempts) {
                foreach ($attempts as $attempt => $c) {
                    $dateVal = $schedule->{$c['date']};
                    if (empty($dateVal) || !empty($schedule->{$c['time']})) {
                        continue;
                    }
                    if ($c['na'] && !empty($schedule->{$c['na']})) {
                        continue;
                    }

                    $dateStr = $dateVal instanceof \Carbon\Carbon
                        ? $dateVal->format('Y-m-d')
                        : (string) $dateVal;
                    if ($dateStr < $from || $dateStr > $to) {
                        continue;
                    }

                    try {
                        $capacity = ExamCapacityService::getSettingsForDate($dateStr);
                        $startTime = $capacity['work_hours_start'] ?? '09:00';

                        if ($c['distribute']) {
                            // Full slot distribution for the primary attempt.
                            $result = $service->distribute($schedule, $ynType, $startTime);
                        } else {
                            // Resit: just stamp the time, like saveTestTime.
                            $schedule->{$c['time']} = $startTime;
                            $schedule->save();
                            $result = ['ok' => true];
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('autoTimeAll: failed', [
                            'schedule_id' => $schedule->id,
                            'yn'          => $ynType,
                            'attempt'     => $attempt,
                            'message'     => $e->getMessage(),
                        ]);
                        $result = ['ok' => false, 'reason' => $e->getMessage()];
                    }

                    if (!empty($result['ok'])) {
                        $okCount++;
                    } else {
                        $label = strtoupper($ynType) . ($attempt > 1 ? " {$attempt}-urinish" : '');
                        $failures[] = sprintf(
                            '#%d %s (%s): %s',
                            $schedule->id,
                            $label,
                            $schedule->subject_name ?: $schedule->subject_id,
                            $result['reason'] ?? "noma'lum sabab"
                        );
                    }
                }
            }
        }

        $msg = "Avto-vaqt belgilandi: {$okCount} ta.";
        if (!empty($failures)) {
            $shown = array_slice($failures, 0, 5);
            $more = count($failures) - count($shown);
            $msg .= ' Bajarilmadi: ' . count($failures) . ' ta — ' . implode('; ', $shown);
            if ($more > 0) {
                $msg .= " (yana {$more} ta)";
            }
        }

        return back()->with($okCount > 0 ? 'success' : 'error', $msg);
    }

    /**
     * Bulk: clear test_time on every exam_schedules row in the requested
     * date range and wipe their pending ComputerAssignments so a fresh
     * autoTimeAll() pass can re-distribute slots from scratch.
     *
     * Past dates are skipped — they represent history, not future plans.
     * Restricted to test_markazi only.
     */
    public function clearTimes(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }

        $user = auth()->user() ?? auth('teacher')->user();
        $activeRole = session('active_role', $user?->getRoleNames()->first());
        if ($activeRole !== \App\Enums\ProjectRole::TEST_CENTER->value) {
            return back()->with('error', "Vaqtlarni tozalash faqat Test markazi roli uchun ochiq.");
        }

        $today = now()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');
        $dateFrom = $request->input('date_from', $tomorrow);
        $dateTo   = $request->input('date_to', $tomorrow);

        try {
            $from = \Carbon\Carbon::parse($dateFrom)->format('Y-m-d');
            $to   = \Carbon\Carbon::parse($dateTo)->format('Y-m-d');
        } catch (\Throwable $e) {
            return back()->with('error', "Sana noto'g'ri formatda.");
        }
        if ($to < $from) {
            $to = $from;
        }
        // Test markazi roli uchun: o'sha kun va o'tgan sanalarni o'zgartirib
        // bo'lmaydi — vaqtni faqat ertangi va undan keyingi sanalarga tegish mumkin.
        if ($from <= $today) {
            $from = $tomorrow;
        }
        if ($to < $from) {
            return back()->with('error', "Test markazi rolida vaqtlarni faqat ertangi va undan keyingi sanalar uchun tozalash mumkin.");
        }

        $columnSets = [
            'test' => [
                1 => ['date' => 'test_date',         'time' => 'test_time',         'mode' => 'test_assignment_mode', 'wipe_assignments' => true],
                2 => ['date' => 'test_resit_date',   'time' => 'test_resit_time',   'mode' => null,                   'wipe_assignments' => false],
                3 => ['date' => 'test_resit2_date',  'time' => 'test_resit2_time',  'mode' => null,                   'wipe_assignments' => false],
            ],
            'oski' => [
                1 => ['date' => 'oski_date',         'time' => 'oski_time',         'mode' => 'oski_assignment_mode', 'wipe_assignments' => true],
                2 => ['date' => 'oski_resit_date',   'time' => 'oski_resit_time',   'mode' => null,                   'wipe_assignments' => false],
                3 => ['date' => 'oski_resit2_date',  'time' => 'oski_resit2_time',  'mode' => null,                   'wipe_assignments' => false],
            ],
        ];

        $schedules = ExamSchedule::query()
            ->where(function ($q) use ($from, $to, $columnSets) {
                foreach ($columnSets as $cols) {
                    foreach ($cols as $c) {
                        $q->orWhere(function ($qq) use ($from, $to, $c) {
                            $qq->whereBetween($c['date'], [$from, $to])
                                ->whereNotNull($c['time']);
                        });
                    }
                }
            })
            ->get();

        if ($schedules->isEmpty()) {
            return back()->with('warning', "Tanlangan oraliqda tozalashga arziydigan vaqt topilmadi.");
        }

        $clearedCount = 0;
        $assignmentsDeleted = 0;

        DB::transaction(function () use ($schedules, $from, $to, $columnSets, &$clearedCount, &$assignmentsDeleted) {
            foreach ($schedules as $schedule) {
                $touched = false;

                foreach ($columnSets as $ynType => $attempts) {
                    foreach ($attempts as $c) {
                        $dateVal = $schedule->{$c['date']};
                        if (empty($dateVal) || empty($schedule->{$c['time']})) {
                            continue;
                        }
                        $dateStr = $dateVal instanceof \Carbon\Carbon
                            ? $dateVal->format('Y-m-d')
                            : (string) $dateVal;
                        if ($dateStr < $from || $dateStr > $to) {
                            continue;
                        }

                        if ($c['wipe_assignments']) {
                            // ComputerAssignment.yn_type doesn't track
                            // attempts — only attempt=1 has slot rows.
                            $deleted = \App\Models\ComputerAssignment::where('exam_schedule_id', $schedule->id)
                                ->where('yn_type', $ynType)
                                ->where('status', \App\Models\ComputerAssignment::STATUS_SCHEDULED)
                                ->delete();
                            $assignmentsDeleted += (int) $deleted;
                        }

                        $schedule->{$c['time']} = null;
                        if ($c['mode']) {
                            // *_assignment_mode is NOT NULL with default
                            // 'manual'; reset so distribute() can stamp.
                            $schedule->{$c['mode']} = 'manual';
                        }
                        $touched = true;
                    }
                }

                if ($touched) {
                    $schedule->save();
                    $clearedCount++;
                }
            }
        });

        $msg = "Vaqt tozalandi: {$clearedCount} ta yozuv";
        if ($assignmentsDeleted > 0) {
            $msg .= " (talaba slotlari: {$assignmentsDeleted} ta)";
        }
        $msg .= ". Endi qayta avto-vaqt belgilashingiz mumkin.";

        return back()->with('success', $msg);
    }

    public function saveTestTime(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        if ($this->isTestCenterReadOnly()) {
            return response()->json(['success' => false, 'message' => 'Bu amalga ruxsat yo\'q.'], 403);
        }

        $request->validate([
            'group_hemis_id' => 'required|string',
            'subject_id' => 'required|string',
            'semester_code' => 'required|string',
            'test_time' => 'required|date_format:H:i',
            'yn_type' => 'nullable|string|in:OSKI,Test',
            'attempt' => 'nullable|integer|in:1,2,3',
        ]);

        $examSchedule = ExamSchedule::where('group_hemis_id', $request->group_hemis_id)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->first();

        if (!$examSchedule) {
            return response()->json(['success' => false, 'message' => 'Jadval topilmadi'], 404);
        }

        // YN turi va urinishga qarab tegishli sana/vaqt ustunlarini aniqlash
        $ynType = $request->input('yn_type', 'Test');
        $attempt = (int) $request->input('attempt', 1);
        $columns = [
            'OSKI' => [
                1 => ['date' => 'oski_date',         'time' => 'oski_time'],
                2 => ['date' => 'oski_resit_date',   'time' => 'oski_resit_time'],
                3 => ['date' => 'oski_resit2_date',  'time' => 'oski_resit2_time'],
            ],
            'Test' => [
                1 => ['date' => 'test_date',         'time' => 'test_time'],
                2 => ['date' => 'test_resit_date',   'time' => 'test_resit_time'],
                3 => ['date' => 'test_resit2_date',  'time' => 'test_resit2_time'],
            ],
        ];
        $cols = $columns[$ynType][$attempt] ?? $columns[$ynType][1];
        $timeColumn = $cols['time'];
        $dateColumn = $cols['date'];
        $ynLabel = $ynType === 'OSKI' ? 'OSKI' : 'Test';
        if ($attempt > 1) {
            $ynLabel .= ' (' . $attempt . '-urinish)';
        }
        $relatedDate = $examSchedule->{$dateColumn};

        // Test markazi roli uchun: o'sha kun yoki o'tgan sanaga vaqt qo'yish/o'zgartirish taqiqlanadi.
        if ($tooSoonMsg = $this->testCenterDateTooSoon($relatedDate)) {
            return response()->json(['success' => false, 'message' => $tooSoonMsg], 422);
        }

        $oldTime = $examSchedule->{$timeColumn};
        $timeChanged = $oldTime !== null && $oldTime !== $request->test_time;
        $ynSubmitted = (bool) $request->input('yn_submitted', false);

        // Sig'im va vaqt-ust-ust tushishini tekshirish
        if ($relatedDate) {
            $relatedDateStr = $relatedDate instanceof \Carbon\Carbon
                ? $relatedDate->format('Y-m-d')
                : \Carbon\Carbon::parse($relatedDate)->format('Y-m-d');

            // Kun uchun maxsus sozlamalar (agar belgilangan bo'lsa) yoki default
            $capacity = ExamCapacityService::getSettingsForDate($relatedDateStr);
            $computerCount = (int) $capacity['computer_count'];
            $duration = (int) $capacity['test_duration_minutes'];
            $workStart = $capacity['work_hours_start'];
            $workEnd = $capacity['work_hours_end'];

            $newTime = substr($request->test_time, 0, 5);
            // Ish vaqti oralig'idan tashqarida bo'lmasin
            $slotStart = \Carbon\Carbon::createFromFormat('H:i', $newTime);
            $slotEnd = $slotStart->copy()->addMinutes($duration);
            $wStart = \Carbon\Carbon::createFromFormat('H:i', $workStart);
            $wEnd = \Carbon\Carbon::createFromFormat('H:i', $workEnd);

            if ($slotStart->lt($wStart) || $slotEnd->gt($wEnd)) {
                return response()->json([
                    'success' => false,
                    'message' => "Tanlangan vaqt ish soatlaridan ({$workStart}–{$workEnd}) tashqarida. Test davomiyligi {$duration} daqiqa.",
                ], 422);
            }

            // Tushlik vaqti bilan ustma-ust tushishini tekshirish
            if (ExamCapacityService::overlapsLunch($relatedDateStr, $newTime, $duration, $capacity)) {
                $ls = $capacity['lunch_start'];
                $le = $capacity['lunch_end'];
                return response()->json([
                    'success' => false,
                    'message' => "Tanlangan vaqt tushlik tanaffusi ({$ls}–{$le}) bilan to'qnashadi. Boshqa vaqtni tanlang.",
                ], 422);
            }

            $exclude = [
                'group_hemis_id' => $request->group_hemis_id,
                'subject_id' => $request->subject_id,
                'semester_code' => $request->semester_code,
                'yn_type' => strtolower($ynType),
                'attempt' => $attempt,
            ];

            $concurrent = ExamCapacityService::concurrentStudentsForSlot($relatedDateStr, $newTime, $exclude);
            $thisGroupCount = ExamCapacityService::groupStudentCount($request->group_hemis_id);
            $totalAtSlot = $concurrent + $thisGroupCount;

            if ($totalAtSlot > $computerCount) {
                return response()->json([
                    'success' => false,
                    'message' => "Vaqt ustma-ust tushdi! {$newTime} – {$slotEnd->format('H:i')} oralig'ida jami {$totalAtSlot} talaba band bo'ladi, kompyuter sig'imi esa atigi {$computerCount} ta. Boshqa vaqtni tanlang.",
                    'concurrent_students' => $concurrent,
                    'this_group_students' => $thisGroupCount,
                    'computer_count' => $computerCount,
                ], 422);
            }

            // Shu guruhning shu sanada va shu vaqt oralig'ida darsi bormi tekshirish
            // (force=true bo'lsa o'tkazib yuboriladi — foydalanuvchi modaldan tasdiqlagan)
            if (!$request->boolean('force')) {
                $slotStartStr = $slotStart->format('H:i:s');
                $slotEndStr = $slotEnd->format('H:i:s');
                $conflictingLessons = DB::table('schedules')
                    ->where('group_id', $request->group_hemis_id)
                    ->whereNull('deleted_at')
                    ->whereDate('lesson_date', $relatedDateStr)
                    ->where('lesson_pair_start_time', '<', $slotEndStr)
                    ->where('lesson_pair_end_time', '>', $slotStartStr)
                    ->select('subject_name', 'lesson_pair_name', 'lesson_pair_start_time', 'lesson_pair_end_time', 'training_type_name')
                    ->orderBy('lesson_pair_start_time')
                    ->get();

                if ($conflictingLessons->isNotEmpty()) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'lesson_conflict',
                        'message' => "Tanlangan sana va vaqt oralig'ida shu guruhning darslari mavjud — bu vaqtni belgilab bo'lmaydi.",
                        'date' => \Carbon\Carbon::parse($relatedDateStr)->format('d.m.Y'),
                        'time_range' => $newTime . ' – ' . $slotEnd->format('H:i'),
                        'lessons' => $conflictingLessons->map(fn($l) => [
                            'subject_name'  => $l->subject_name,
                            'pair_name'     => $l->lesson_pair_name,
                            'start'         => substr($l->lesson_pair_start_time, 0, 5),
                            'end'           => substr($l->lesson_pair_end_time, 0, 5),
                            'training_type' => $l->training_type_name,
                        ])->values(),
                    ], 422);
                }
            }
        }

        $examSchedule->update([$timeColumn => $request->test_time]);

        // Both date and time are now set → assign computers + book on Moodle.
        // Hozircha kompyuter va Moodle bron qilish faqat 1-urinish uchun ishlaydi;
        // 2-/3-urinishlar (resit) sanalari Test markazi tomonidan vaqt belgilanadi, biroq
        // bron qilish bosqichi alohida ko'rib chiqiladi.
        $ynKey = $ynType === 'OSKI' ? 'oski' : 'test';
        $naFlag = $ynKey === 'oski' ? $examSchedule->oski_na : $examSchedule->test_na;
        $autoRandom = $request->boolean('auto_random');
        if ($attempt === 1 && $relatedDate && !$naFlag) {
            if ($autoRandom) {
                $auto = app(\App\Services\AutoAssignService::class)
                    ->distribute($examSchedule, $ynKey, $request->test_time);
                if (empty($auto['ok'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Avtomatik taqsimlashda xato: ' . ($auto['reason'] ?? 'noma\'lum'),
                    ], 422);
                }
                BookMoodleGroupExam::dispatch($examSchedule->id, $ynKey);
            } else {
                $modeField = $ynKey . '_assignment_mode';
                $examSchedule->update([$modeField => 'manual']);
                AssignComputersJob::dispatch($examSchedule->id, $ynKey);
                BookMoodleGroupExam::dispatch($examSchedule->id, $ynKey);
            }
        }

        // Shu guruhdagi Telegram tasdiqlangan talabalarga notification yuborish
        $students = Student::where('group_id', $request->group_hemis_id)
            ->whereNotNull('telegram_chat_id')
            ->whereNotNull('telegram_verified_at')
            ->get();

        if ($students->isNotEmpty()) {
            $telegram = app(TelegramService::class);
            $subjectName = $examSchedule->subject_name ?? 'Fan';
            $testDate = $relatedDate ? \Carbon\Carbon::parse($relatedDate)->format('d.m.Y') : '';
            $timeFormatted = $request->test_time;

            // Ogohlantirish faqat YN yuborilmagan holatlarda Telegram xabarga ham qo'shiladi
            $warningText = !$ynSubmitted ? "\n\n⚠️ <i>{$ynLabel} vaqti o'zgarishi mumkin, habardor bo'lib turing!</i>" : '';
            $warningPlain = !$ynSubmitted ? " {$ynLabel} vaqti o'zgarishi mumkin, habardor bo'lib turing!" : '';

            if ($timeChanged) {
                $oldTimeFormatted = $oldTime;
                $message = "📋 <b>{$ynLabel} vaqti o'zgartirildi!</b>\n\n"
                    . "📌 Fan: <b>{$subjectName}</b>\n"
                    . ($testDate ? "📅 Sana: <b>{$testDate}</b>\n" : '')
                    . "⏰ Eski vaqt: <s>{$oldTimeFormatted}</s>\n"
                    . "⏰ Yangi vaqt: <b>{$timeFormatted}</b>"
                    . $warningText;
                $notifTitle = "{$ynLabel} vaqti o'zgartirildi: {$subjectName}";
                $notifMessage = "Fan: {$subjectName}" . ($testDate ? ", Sana: {$testDate}" : '') . ", Eski vaqt: {$oldTimeFormatted}, Yangi vaqt: {$timeFormatted}." . $warningPlain;
            } else {
                $message = "📋 <b>{$ynLabel} vaqti belgilandi!</b>\n\n"
                    . "📌 Fan: <b>{$subjectName}</b>\n"
                    . ($testDate ? "📅 Sana: <b>{$testDate}</b>\n" : '')
                    . "⏰ Vaqt: <b>{$timeFormatted}</b>"
                    . $warningText;
                $notifTitle = "{$ynLabel} vaqti belgilandi: {$subjectName}";
                $notifMessage = "Fan: {$subjectName}" . ($testDate ? ", Sana: {$testDate}" : '') . ", Vaqt: {$timeFormatted}." . $warningPlain;
            }

            $notificationRecords = [];

            foreach ($students as $student) {
                $telegram->sendToUser($student->telegram_chat_id, $message);

                $notificationRecords[] = [
                    'student_id' => $student->id,
                    'type' => 'exam_reminder',
                    'title' => $notifTitle,
                    'message' => $notifMessage,
                    'link' => '/student/exam-schedule',
                    'data' => json_encode(['subject' => $subjectName, 'yn_type' => $ynType, 'test_time' => $timeFormatted, 'test_date' => $testDate, 'time_changed' => $timeChanged]),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($notificationRecords)) {
                StudentNotification::insert($notificationRecords);
            }
        }

        $statusMsg = $timeChanged ? ($ynLabel . ' vaqti o\'zgartirildi') : ($ynLabel . ' vaqti saqlandi');

        return response()->json([
            'success' => true,
            'time_changed' => $timeChanged,
            'message' => $statusMsg . ($students->count() > 0 ? " va {$students->count()} ta talabaga xabar yuborildi" : ''),
        ]);
    }

    /**
     * Test markazi: aniq talabaga aniq kompyuter raqamini biriktirish (admin pin).
     * JIT rejimi (auto_jit) ishlayotgan talabalarga ham qo'llanadi — pinned
     * yozuv JIT processor tomonidan tegilmaydi.
     */
    public function pinComputer(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        if ($this->isTestCenterReadOnly()) {
            return response()->json(['success' => false, 'message' => 'Bu amalga ruxsat yo\'q.'], 403);
        }

        $request->validate([
            'group_hemis_id' => 'required|string',
            'subject_id' => 'required|string',
            'semester_code' => 'required|string',
            'student_hemis_id' => 'required|string',
            'yn_type' => 'required|string|in:OSKI,Test',
            'computer_number' => 'required|integer|min:1',
        ]);

        $schedule = ExamSchedule::where('group_hemis_id', $request->group_hemis_id)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->whereNull('student_hemis_id')
            ->first();
        if (!$schedule) {
            return response()->json(['success' => false, 'message' => 'Jadval topilmadi'], 404);
        }

        $ynType = strtolower($request->yn_type);
        $assignment = \App\Models\ComputerAssignment::query()
            ->where('exam_schedule_id', $schedule->id)
            ->where('student_hemis_id', $request->student_hemis_id)
            ->where('yn_type', $ynType)
            ->whereIn('status', [
                \App\Models\ComputerAssignment::STATUS_SCHEDULED,
                \App\Models\ComputerAssignment::STATUS_IN_PROGRESS,
            ])
            ->orderBy('planned_start')
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Talaba uchun faol biriktiruv topilmadi. Avval guruh uchun vaqt belgilang.',
            ], 404);
        }

        $result = app(\App\Services\AutoAssignService::class)
            ->pinComputer($assignment, (int) $request->computer_number);
        if (empty($result['ok'])) {
            return response()->json(['success' => false, 'message' => $result['reason'] ?? 'Xato'], 422);
        }

        return response()->json([
            'success' => true,
            'computer_number' => (int) $request->computer_number,
            'message' => "Kompyuter #{$request->computer_number} talabaga biriktirildi.",
        ]);
    }

    /**
     * Test markazi: bitta talaba uchun shaxsiy 2-/3-urinish vaqtini saqlash.
     * Per-student exam_schedules yozuvi (student_hemis_id NOT NULL) yaratiladi yoki yangilanadi.
     * Sana — agar shaxsiy belgilanmagan bo'lsa — guruh darajasidagi resit sanasidan ko'chiriladi.
     */
    public function saveStudentTime(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        if ($this->isTestCenterReadOnly()) {
            return response()->json(['success' => false, 'message' => 'Bu amalga ruxsat yo\'q.'], 403);
        }

        $request->validate([
            'group_hemis_id' => 'required|string',
            'subject_id' => 'required|string',
            'semester_code' => 'required|string',
            'student_hemis_id' => 'required|string',
            'yn_type' => 'required|string|in:OSKI,Test',
            'attempt' => 'required|integer|in:2,3',
            'test_time' => 'required|date_format:H:i',
        ]);

        $columns = [
            'OSKI' => [
                2 => ['date' => 'oski_resit_date',  'time' => 'oski_resit_time'],
                3 => ['date' => 'oski_resit2_date', 'time' => 'oski_resit2_time'],
            ],
            'Test' => [
                2 => ['date' => 'test_resit_date',  'time' => 'test_resit_time'],
                3 => ['date' => 'test_resit2_date', 'time' => 'test_resit2_time'],
            ],
        ];
        $cols = $columns[$request->yn_type][(int) $request->attempt];
        $timeColumn = $cols['time'];
        $dateColumn = $cols['date'];

        $groupSchedule = ExamSchedule::where('group_hemis_id', $request->group_hemis_id)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->whereNull('student_hemis_id')
            ->first();

        $perStudent = ExamSchedule::where('group_hemis_id', $request->group_hemis_id)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->where('student_hemis_id', $request->student_hemis_id)
            ->first();

        $resolvedDate = $perStudent?->{$dateColumn} ?? $groupSchedule?->{$dateColumn};
        if (!$resolvedDate) {
            return response()->json([
                'success' => false,
                'message' => 'Bu urinish uchun avval sana belgilanmagan. Avval akademik bo\'lim sanani belgilashi kerak.',
            ], 422);
        }

        // Test markazi roli uchun: o'sha kun yoki o'tgan sanaga vaqt qo'yish/o'zgartirish taqiqlanadi.
        if ($tooSoonMsg = $this->testCenterDateTooSoon($resolvedDate)) {
            return response()->json(['success' => false, 'message' => $tooSoonMsg], 422);
        }

        $payload = [$timeColumn => $request->test_time];
        if (!$perStudent) {
            $payload = array_merge($payload, [
                'group_hemis_id' => $request->group_hemis_id,
                'subject_id' => $request->subject_id,
                'subject_name' => $groupSchedule?->subject_name,
                'semester_code' => $request->semester_code,
                'student_hemis_id' => $request->student_hemis_id,
                'department_hemis_id' => $groupSchedule?->department_hemis_id,
                'specialty_hemis_id' => $groupSchedule?->specialty_hemis_id,
                'curriculum_hemis_id' => $groupSchedule?->curriculum_hemis_id,
                'education_year' => $groupSchedule?->education_year,
                $dateColumn => $resolvedDate,
                'created_by' => auth()->id(),
            ]);
            $perStudent = ExamSchedule::create($payload);
        } else {
            // Sana yo'q bo'lsa, guruh sanasidan ko'chiramiz
            if (empty($perStudent->{$dateColumn}) && $groupSchedule?->{$dateColumn}) {
                $payload[$dateColumn] = $groupSchedule->{$dateColumn};
            }
            $payload['updated_by'] = auth()->id();
            $perStudent->update($payload);
        }

        return response()->json([
            'success' => true,
            'message' => 'Talaba uchun vaqt saqlandi.',
            'time' => $request->test_time,
        ]);
    }

    public function exportTestCenter(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        $result = $this->buildTestCenterData($request);
        $scheduleData = $result['scheduleData'];

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\TestCenterExport($scheduleData),
            'yn_jadvali_' . date('Y-m-d_H-i') . '.xlsx'
        );
    }

    /**
     * YN kunini belgilash sahifasidagi filtrdan chiqqan natijalarni Excel ga eksport qilish.
     * Per-student qatorlar (2/3-urinishda yiqilgan talabalar) ham alohida qatorlar sifatida
     * chiqariladi (show_students=1 bo'lsa).
     */
    public function exportExcel(Request $request)
    {
        $user = auth()->user() ?? auth('teacher')->user();
        $activeRole = session('active_role', $user?->getRoleNames()->first());
        $adminRoles = ExamDateRoleService::adminRoles();
        $isAdmin = $user && in_array($activeRole, $adminRoles, true);
        if (!ExamDateRoleService::canViewPage($activeRole)) {
            abort(403, 'Bu amalni bajarish uchun ruxsat yo\'q.');
        }

        $currentSemesters = Semester::where('current', true)->get();

        $selectedEducationType = $request->get('education_type');
        $selectedDepartment = $request->get('department_id');
        $selectedSpecialty = $request->get('specialty_id');
        $selectedLevelCode = $request->get('level_code');
        $selectedSemester = $request->get('semester_code');
        $selectedGroup = $request->get('group_id');
        $selectedSubject = $request->get('subject_id');
        $selectedStatus = $request->get('status');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $currentSemesterToggle = $request->get('current_semester', '1');
        $oskiDateFrom = $request->get('oski_date_from');
        $oskiDateTo = $request->get('oski_date_to');
        $testDateFrom = $request->get('test_date_from');
        $testDateTo = $request->get('test_date_to');
        $showStudents = $request->get('show_students') === '1';
        $urinishFilter = $request->get('urinish');

        $scheduleData = $this->loadScheduleData(
            $currentSemesters, $selectedDepartment, $selectedSpecialty,
            $selectedSemester, $selectedGroup, $selectedEducationType,
            $selectedLevelCode, $selectedSubject, $selectedStatus,
            $currentSemesterToggle, false, $dateFrom, $dateTo
        );

        if ($oskiDateFrom || $oskiDateTo) {
            $scheduleData = $scheduleData->map(function ($items) use ($oskiDateFrom, $oskiDateTo) {
                return $items->filter(function ($item) use ($oskiDateFrom, $oskiDateTo) {
                    $d = $item['oski_date'];
                    if (!$d) return false;
                    if ($oskiDateFrom && $d < $oskiDateFrom) return false;
                    if ($oskiDateTo && $d > $oskiDateTo) return false;
                    return true;
                });
            })->filter(fn($items) => $items->isNotEmpty());
        }
        if ($testDateFrom || $testDateTo) {
            $scheduleData = $scheduleData->map(function ($items) use ($testDateFrom, $testDateTo) {
                return $items->filter(function ($item) use ($testDateFrom, $testDateTo) {
                    $d = $item['test_date'];
                    if (!$d) return false;
                    if ($testDateFrom && $d < $testDateFrom) return false;
                    if ($testDateTo && $d > $testDateTo) return false;
                    return true;
                });
            })->filter(fn($items) => $items->isNotEmpty());
        }

        if ($showStudents) {
            $scheduleData = $this->attachStudentsToSchedule($scheduleData);
        }
        $scheduleData = $this->expandByUrinish($scheduleData, $urinishFilter);

        $closingFormLabels = [
            'oski' => 'Faqat OSKI',
            'test' => 'Faqat Test',
            'oski_test' => 'OSKI + Test',
            'normativ' => 'Normativ',
            'sinov' => 'Sinov (test)',
            'none' => "Yo'q",
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('YN kunlari');

        $headers = ['#', 'Guruh', "Yo'nalish", 'Fan', 'Kredit', 'Yopilish shakli', 'Urinish', 'Talaba soni', 'OSKI sanasi', 'Test sanasi', 'Talaba FISH', 'Talaba ID', 'Holat', 'Qarzlar soni', 'Izoh (qarz fanlari)'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }
        $sheet->getStyle('A1:O1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2B5EA7']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $rowNum = 2;
        $counter = 0;
        $fmt = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '';

        foreach ($scheduleData as $items) {
            foreach ($items as $item) {
                $counter++;
                $itemUrinish = (int) ($item['urinish'] ?? 1);
                $oski = $item['oski_date_for_urinish'] ?? null;
                $test = $item['test_date_for_urinish'] ?? null;
                $oskiNa = $item['oski_na_for_urinish'] ?? false;
                $testNa = $item['test_na_for_urinish'] ?? false;
                $cf = $item['closing_form'] ?? null;
                $cfLabel = $cf ? ($closingFormLabels[$cf] ?? $cf) : 'Belgilanmagan';

                $sheet->setCellValue([1, $rowNum], $counter);
                $sheet->setCellValue([2, $rowNum], $item['group']->name ?? '');
                $sheet->setCellValue([3, $rowNum], $item['specialty_name'] ?? ($item['group']->specialty_name ?? ''));
                $sheet->setCellValue([4, $rowNum], $item['subject']->subject_name ?? '');
                $sheet->setCellValue([5, $rowNum], $item['subject']->credit ?? '');
                $sheet->setCellValue([6, $rowNum], $cfLabel);
                $sheet->setCellValue([7, $rowNum], $itemUrinish . '-urinish');
                $sheet->setCellValue([8, $rowNum], (int) ($item['student_count'] ?? 0));
                $sheet->setCellValue([9, $rowNum], $oskiNa ? 'N/A' : $fmt($oski));
                $sheet->setCellValue([10, $rowNum], $testNa ? 'N/A' : $fmt($test));
                $sheet->setCellValue([11, $rowNum], '');
                $sheet->setCellValue([12, $rowNum], '');
                $sheet->setCellValue([13, $rowNum], '');
                $sheet->setCellValue([14, $rowNum], '');
                $sheet->setCellValue([15, $rowNum], '');
                $rowNum++;

                // Per-student qatorlar
                if ($showStudents && !empty($item['students']) && is_array($item['students'])) {
                    $studentsForRow = $item['students'];
                    if ($itemUrinish === 2) {
                        $studentsForRow = array_values(array_filter($studentsForRow, fn($s) => !empty($s['failed_attempt1'])));
                    } elseif ($itemUrinish === 3) {
                        $studentsForRow = array_values(array_filter($studentsForRow, fn($s) => !empty($s['failed_attempt2'])));
                    }

                    foreach ($studentsForRow as $stu) {
                        if ($itemUrinish === 1) {
                            $stuOski = $stu['oski_date'] ?? '';
                            $stuTest = $stu['test_date'] ?? '';
                        } elseif ($itemUrinish === 2) {
                            $stuOski = $stu['oski_resit_date'] ?? '';
                            $stuTest = $stu['test_resit_date'] ?? '';
                        } else {
                            $stuOski = $stu['oski_resit2_date'] ?? '';
                            $stuTest = $stu['test_resit2_date'] ?? '';
                        }

                        // Qarz hisobi: o'tgan semestrlardagi qarzlar + joriy semestr BARCHA qarzlar
                        $stuPastDebts = $stu['past_debts'] ?? [];
                        $stuCurrentDebts = $stu['current_semester_debts'] ?? [];
                        $stuDebtCount = count($stuPastDebts) + count($stuCurrentDebts);
                        $stuDebtParts = [];
                        foreach ($stuPastDebts as $d) {
                            $stuDebtParts[] = ($d['subject_name'] ?? '') . ' (' . ($d['semester_name'] ?? '') . ')';
                        }
                        foreach ($stuCurrentDebts as $d) {
                            $stuDebtParts[] = ($d['subject_name'] ?? '') . ' (' . ($d['semester_name'] ?? '') . ') — joriy';
                        }
                        $stuDebtNote = implode('; ', $stuDebtParts);

                        // Holat: pullik / 4+ qarz (kursdan qoldirilgan)
                        $stuIsHeldBack = !empty($stu['is_held_back']) || $stuDebtCount >= 4;
                        $stuIsPullik = !empty($stu['is_pullik']);
                        $statusParts = [];
                        if ($stuIsHeldBack) $statusParts[] = '4 tadan ortiq qarz';
                        if ($stuIsPullik) $statusParts[] = 'Pullik';
                        $statusLabel = implode(' / ', $statusParts);

                        $sheet->setCellValue([1, $rowNum], '');
                        $sheet->setCellValue([2, $rowNum], $item['group']->name ?? '');
                        $sheet->setCellValue([3, $rowNum], $item['specialty_name'] ?? ($item['group']->specialty_name ?? ''));
                        $sheet->setCellValue([4, $rowNum], '↳ ' . ($item['subject']->subject_name ?? ''));
                        $sheet->setCellValue([5, $rowNum], '');
                        $sheet->setCellValue([6, $rowNum], $cfLabel);
                        $sheet->setCellValue([7, $rowNum], $itemUrinish . '-urinish');
                        $sheet->setCellValue([8, $rowNum], '');
                        $sheet->setCellValue([9, $rowNum], $fmt($stuOski));
                        $sheet->setCellValue([10, $rowNum], $fmt($stuTest));
                        $sheet->setCellValue([11, $rowNum], $stu['full_name'] ?? '');
                        $sheet->setCellValueExplicit([12, $rowNum], (string) ($stu['student_id_number'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue([13, $rowNum], $statusLabel);
                        $sheet->setCellValue([14, $rowNum], $stuDebtCount);
                        $sheet->setCellValue([15, $rowNum], $stuDebtNote);

                        // Per-student qatorni vizual ajratish (kulrang fon)
                        $sheet->getStyle("A{$rowNum}:O{$rowNum}")->applyFromArray([
                            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '475569']],
                        ]);
                        $sheet->getStyle("O{$rowNum}")->getAlignment()->setWrapText(true);
                        // Qarz soni > 0 bo'lsa, yacheykani qizg'ish rangda ajratish
                        if ($stuDebtCount > 0) {
                            $sheet->getStyle("N{$rowNum}")->applyFromArray([
                                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
                                'font' => ['bold' => true, 'color' => ['rgb' => '92400E']],
                            ]);
                        }
                        // Holat to'lgan bo'lsa, qizil rangda ajratish
                        if ($statusLabel !== '') {
                            $sheet->getStyle("M{$rowNum}")->applyFromArray([
                                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']],
                                'font' => ['bold' => true, 'color' => ['rgb' => '991B1B']],
                            ]);
                        }
                        $rowNum++;
                    }
                }
            }
        }

        $widths = [5, 18, 30, 35, 8, 18, 12, 11, 14, 14, 28, 14, 22, 11, 60];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = $rowNum - 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:O{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G2:J{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H2:H{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("N2:N{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        $sheet->freezePane('A2');

        $fileName = 'YN_kunlari_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'yn_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function bandlikKursatkichi(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        $totalComputers = (int) ExamCapacityService::getSettings()['computer_count'];
        $today = now()->format('Y-m-d');

        // Bugundan keyingi (bugun kiradi) sanalar — vaqti qo'yilmaganlar ham
        // kiritiladi, toki test markazi xodimi qaysi guruhlarga hali vaqt
        // belgilanmaganini ko'ra olsin. Barcha urinishlar (1, 2, 3) hisobga
        // olinadi.
        $oskiDates = ExamSchedule::whereNotNull('oski_date')
            ->where('oski_na', false)
            ->whereDate('oski_date', '>=', $today)
            ->pluck('oski_date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'));

        $testDates = ExamSchedule::whereNotNull('test_date')
            ->where('test_na', false)
            ->whereDate('test_date', '>=', $today)
            ->pluck('test_date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'));

        $oskiResitDates = ExamSchedule::whereNotNull('oski_resit_date')
            ->whereDate('oski_resit_date', '>=', $today)
            ->pluck('oski_resit_date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'));

        $testResitDates = ExamSchedule::whereNotNull('test_resit_date')
            ->whereDate('test_resit_date', '>=', $today)
            ->pluck('test_resit_date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'));

        $oskiResit2Dates = ExamSchedule::whereNotNull('oski_resit2_date')
            ->whereDate('oski_resit2_date', '>=', $today)
            ->pluck('oski_resit2_date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'));

        $testResit2Dates = ExamSchedule::whereNotNull('test_resit2_date')
            ->whereDate('test_resit2_date', '>=', $today)
            ->pluck('test_resit2_date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'));

        $uniqueDates = $oskiDates
            ->merge($testDates)
            ->merge($oskiResitDates)
            ->merge($testResitDates)
            ->merge($oskiResit2Dates)
            ->merge($testResit2Dates)
            ->unique()->sort()->values();

        if ($uniqueDates->isEmpty()) {
            return view('admin.academic-schedule.bandlik-kursatkichi', [
                'dateCards' => collect(),
                'totalComputers' => $totalComputers,
            ]);
        }

        $minDate = $uniqueDates->first();
        $maxDate = $uniqueDates->last();

        // Sanalar oralig'idagi barcha schedule yozuvlarini olish (vaqti
        // qo'yilmaganlar ham olinadi, toki ular "Vaqti qo'yilmagan" sifatida
        // ko'rinsin). Barcha 3 urinish (1, 2, 3) bo'yicha sanalar tekshiriladi.
        $schedules = ExamSchedule::with(['group'])
            ->where(function ($q) use ($minDate, $maxDate) {
                $q->where(function ($q2) use ($minDate, $maxDate) {
                    $q2->whereNotNull('oski_date')
                       ->where('oski_na', false)
                       ->whereBetween('oski_date', [$minDate, $maxDate]);
                })->orWhere(function ($q2) use ($minDate, $maxDate) {
                    $q2->whereNotNull('test_date')
                       ->where('test_na', false)
                       ->whereBetween('test_date', [$minDate, $maxDate]);
                })->orWhere(function ($q2) use ($minDate, $maxDate) {
                    $q2->whereNotNull('oski_resit_date')
                       ->whereBetween('oski_resit_date', [$minDate, $maxDate]);
                })->orWhere(function ($q2) use ($minDate, $maxDate) {
                    $q2->whereNotNull('test_resit_date')
                       ->whereBetween('test_resit_date', [$minDate, $maxDate]);
                })->orWhere(function ($q2) use ($minDate, $maxDate) {
                    $q2->whereNotNull('oski_resit2_date')
                       ->whereBetween('oski_resit2_date', [$minDate, $maxDate]);
                })->orWhere(function ($q2) use ($minDate, $maxDate) {
                    $q2->whereNotNull('test_resit2_date')
                       ->whereBetween('test_resit2_date', [$minDate, $maxDate]);
                });
            })
            ->get();

        // Har bir sana uchun ma'lumotlarni guruhlash. time = null bo'lsa
        // "Vaqti qo'yilmagan" deb belgilanadi. Har bir urinish (1, 2, 3)
        // alohida slot sifatida hisoblanadi.
        $byDate = [];
        foreach ($schedules as $schedule) {
            // 1-urinish (asosiy)
            $oskiDateStr = $schedule->oski_date?->format('Y-m-d');
            if ($oskiDateStr && !$schedule->oski_na && $oskiDateStr >= $today) {
                $byDate[$oskiDateStr][] = [
                    'group_hemis_id' => $schedule->group_hemis_id,
                    'yn_type' => 'OSKI',
                    'attempt' => 1,
                    'time' => $schedule->oski_time
                        ? \Carbon\Carbon::parse($schedule->oski_time)->format('H:i')
                        : null,
                ];
            }
            $testDateStr = $schedule->test_date?->format('Y-m-d');
            if ($testDateStr && !$schedule->test_na && $testDateStr >= $today) {
                $byDate[$testDateStr][] = [
                    'group_hemis_id' => $schedule->group_hemis_id,
                    'yn_type' => 'Test',
                    'attempt' => 1,
                    'time' => $schedule->test_time
                        ? \Carbon\Carbon::parse($schedule->test_time)->format('H:i')
                        : null,
                ];
            }
            // 2-urinish (qayta topshirish)
            $oskiResitDateStr = $schedule->oski_resit_date?->format('Y-m-d');
            if ($oskiResitDateStr && $oskiResitDateStr >= $today) {
                $byDate[$oskiResitDateStr][] = [
                    'group_hemis_id' => $schedule->group_hemis_id,
                    'yn_type' => 'OSKI',
                    'attempt' => 2,
                    'time' => $schedule->oski_resit_time
                        ? \Carbon\Carbon::parse($schedule->oski_resit_time)->format('H:i')
                        : null,
                ];
            }
            $testResitDateStr = $schedule->test_resit_date?->format('Y-m-d');
            if ($testResitDateStr && $testResitDateStr >= $today) {
                $byDate[$testResitDateStr][] = [
                    'group_hemis_id' => $schedule->group_hemis_id,
                    'yn_type' => 'Test',
                    'attempt' => 2,
                    'time' => $schedule->test_resit_time
                        ? \Carbon\Carbon::parse($schedule->test_resit_time)->format('H:i')
                        : null,
                ];
            }
            // 3-urinish (qayta topshirish 2)
            $oskiResit2DateStr = $schedule->oski_resit2_date?->format('Y-m-d');
            if ($oskiResit2DateStr && $oskiResit2DateStr >= $today) {
                $byDate[$oskiResit2DateStr][] = [
                    'group_hemis_id' => $schedule->group_hemis_id,
                    'yn_type' => 'OSKI',
                    'attempt' => 3,
                    'time' => $schedule->oski_resit2_time
                        ? \Carbon\Carbon::parse($schedule->oski_resit2_time)->format('H:i')
                        : null,
                ];
            }
            $testResit2DateStr = $schedule->test_resit2_date?->format('Y-m-d');
            if ($testResit2DateStr && $testResit2DateStr >= $today) {
                $byDate[$testResit2DateStr][] = [
                    'group_hemis_id' => $schedule->group_hemis_id,
                    'yn_type' => 'Test',
                    'attempt' => 3,
                    'time' => $schedule->test_resit2_time
                        ? \Carbon\Carbon::parse($schedule->test_resit2_time)->format('H:i')
                        : null,
                ];
            }
        }

        // Barcha guruhlar uchun talabalar sonini yig'ish
        $allGroupIds = collect($byDate)->flatten(1)->pluck('group_hemis_id')->unique()->toArray();
        $studentCounts = [];
        if (!empty($allGroupIds)) {
            $studentCounts = \Illuminate\Support\Facades\DB::table('students')
                ->whereIn('group_id', $allGroupIds)
                ->where('student_status_code', 11)
                ->groupBy('group_id')
                ->select('group_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
                ->pluck('cnt', 'group_id')
                ->toArray();
        }

        // Har bir sana uchun karta ma'lumotlari. Vaqti qo'yilmagan yozuvlar
        // bandlik hisobiga kirmaydi, lekin alohida hisoblanadi.
        $dateCards = collect();
        foreach ($uniqueDates as $dateStr) {
            $items = $byDate[$dateStr] ?? [];
            $scheduledItems = array_values(array_filter($items, fn($i) => $i['time'] !== null));
            $pendingItems = array_values(array_filter($items, fn($i) => $i['time'] === null));

            // Bir vaqtda turli yn_type/urinishlar bo'lsa ham, kompyuter zalida
            // ular birgalikda joylashadi — shuning uchun slot vaqt bo'yicha
            // birlashtiriladi.
            $slotKeys = collect($scheduledItems)->map(fn($i) => $i['time'])->unique();
            $totalStudents = 0;
            $maxOccupied = 0;
            $slotsOccupancy = [];
            foreach ($scheduledItems as $item) {
                $slotKey = $item['time'];
                $cnt = (int) ($studentCounts[$item['group_hemis_id']] ?? 0);
                $slotsOccupancy[$slotKey] = ($slotsOccupancy[$slotKey] ?? 0) + $cnt;
                $totalStudents += $cnt;
            }
            foreach ($slotsOccupancy as $occ) {
                if ($occ > $maxOccupied) $maxOccupied = $occ;
            }

            $pendingStudents = 0;
            foreach ($pendingItems as $item) {
                $pendingStudents += (int) ($studentCounts[$item['group_hemis_id']] ?? 0);
            }

            $carbonDate = \Carbon\Carbon::parse($dateStr);
            $dateCards->push([
                'date' => $carbonDate,
                'date_str' => $dateStr,
                'slot_count' => $slotKeys->count(),
                'group_count' => count($scheduledItems),
                'total_students' => $totalStudents,
                'max_occupied' => $maxOccupied,
                'is_today' => $dateStr === $today,
                'has_overflow' => $maxOccupied > $totalComputers,
                'pending_time_count' => count($pendingItems),
                'pending_time_students' => $pendingStudents,
            ]);
        }

        return view('admin.academic-schedule.bandlik-kursatkichi', [
            'dateCards' => $dateCards,
            'totalComputers' => $totalComputers,
        ]);
    }

    public function bandlikKursatkichiShow(Request $request, string $date)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        $totalComputers = (int) ExamCapacityService::getSettings()['computer_count'];

        // Sana validatsiyasi
        try {
            $carbonDate = \Carbon\Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable $e) {
            abort(404);
        }

        // Vaqti qo'yilmaganlar ham olinadi — alohida "Vaqti qo'yilmagan"
        // satrida ko'rsatiladi. Barcha 3 urinish (1, 2, 3) bo'yicha tekshiriladi.
        $schedules = ExamSchedule::with(['group'])
            ->where(function ($q) use ($date) {
                $q->where(function ($q2) use ($date) {
                    $q2->where('oski_na', false)
                       ->whereDate('oski_date', $date);
                })->orWhere(function ($q2) use ($date) {
                    $q2->where('test_na', false)
                       ->whereDate('test_date', $date);
                })->orWhere(function ($q2) use ($date) {
                    $q2->whereDate('oski_resit_date', $date);
                })->orWhere(function ($q2) use ($date) {
                    $q2->whereDate('test_resit_date', $date);
                })->orWhere(function ($q2) use ($date) {
                    $q2->whereDate('oski_resit2_date', $date);
                })->orWhere(function ($q2) use ($date) {
                    $q2->whereDate('test_resit2_date', $date);
                });
            })
            ->get();

        // Vaqt bo'yicha guruhlarni birlashtirish — bir vaqtda turli yn_type/urinish
        // bo'lsa ham, kompyuter zalida ular bir vaqtda bo'lgani uchun bitta slotda
        // hisoblanadi. time = null bo'lsa "Vaqti qo'yilmagan" satriga tushadi.
        $rows = [];
        // Har bir schedule uchun barcha 3 urinishni tekshirish.
        // Format: [ynType, attempt, dateField, timeField, naField (or null)]
        $attemptDefs = [
            ['OSKI', 1, 'oski_date', 'oski_time', 'oski_na'],
            ['Test', 1, 'test_date', 'test_time', 'test_na'],
            ['OSKI', 2, 'oski_resit_date', 'oski_resit_time', null],
            ['Test', 2, 'test_resit_date', 'test_resit_time', null],
            ['OSKI', 3, 'oski_resit2_date', 'oski_resit2_time', null],
            ['Test', 3, 'test_resit2_date', 'test_resit2_time', null],
        ];
        foreach ($schedules as $schedule) {
            foreach ($attemptDefs as [$ynType, $attempt, $dateField, $timeField, $naField]) {
                $dStr = $schedule->{$dateField}?->format('Y-m-d');
                if ($dStr !== $date) continue;
                if ($naField !== null && $schedule->{$naField}) continue;

                $timeRaw = $schedule->{$timeField} ?? null;
                $timeStr = $timeRaw
                    ? \Carbon\Carbon::parse($timeRaw)->format('H:i')
                    : null;
                $key = $timeStr ?? '__no_time__';
                if (!isset($rows[$key])) {
                    $rows[$key] = [
                        'time' => $timeStr,
                        'groups' => [],
                    ];
                }
                $rows[$key]['groups'][] = [
                    'schedule_id' => $schedule->id,
                    'group_hemis_id' => $schedule->group_hemis_id,
                    'subject_id' => $schedule->subject_id ?? '',
                    'group_name' => $schedule->group?->name ?? $schedule->group_hemis_id,
                    'subject_name' => $schedule->subject_name ?? '',
                    'yn_type' => $ynType,
                    'attempt' => $attempt,
                ];
            }
        }

        // Talabalar soni
        $allGroups = collect($rows)->pluck('groups')->flatten(1);
        $allGroupIds = $allGroups->pluck('group_hemis_id')->unique()->toArray();
        $studentCounts = [];
        if (!empty($allGroupIds)) {
            $studentCounts = \Illuminate\Support\Facades\DB::table('students')
                ->whereIn('group_id', $allGroupIds)
                ->where('student_status_code', 11)
                ->groupBy('group_id')
                ->select('group_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
                ->pluck('cnt', 'group_id')
                ->toArray();
        }

        // Quiz topshirganlar: computer_assignments jadvalidan real-time
        // status'lar olinadi. Moodle'dagi `local_hemisexport` plagini har
        // bir quiz attempt boshlangani/tugaganida LMS'ga POST yuboradi
        // (MoodleExamEventController) — bu yerda assignment.status
        // 'in_progress'/'finished'/'abandoned' qiymatiga yangilanadi.
        // hemis_quiz_results jadvali esa bulk sync orqali alohida sinxron
        // qilinadi (kuniga 3 marta) — uni ishlatib bo'lmaydi.
        $scheduleIds = $allGroups->pluck('schedule_id')->filter()->unique()->toArray();
        $finishedMap = [];
        if (!empty($scheduleIds)) {
            try {
                $finishedRows = DB::table('computer_assignments')
                    ->whereIn('exam_schedule_id', $scheduleIds)
                    ->whereIn('status', [
                        \App\Models\ComputerAssignment::STATUS_FINISHED,
                        \App\Models\ComputerAssignment::STATUS_ABANDONED,
                    ])
                    ->groupBy('exam_schedule_id', 'yn_type')
                    ->select('exam_schedule_id', 'yn_type', DB::raw('COUNT(*) as cnt'))
                    ->get();
                foreach ($finishedRows as $fr) {
                    $key = $fr->exam_schedule_id . '|' . strtolower((string) $fr->yn_type);
                    $finishedMap[$key] = (int) $fr->cnt;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('bandlikKursatkichiShow: computer_assignments so\'rovi xatolik berdi: ' . $e->getMessage());
            }
        }

        foreach ($rows as &$row) {
            $occupied = 0;
            $submitted = 0;
            $remaining = 0;
            foreach ($row['groups'] as &$grp) {
                $cnt = (int) ($studentCounts[$grp['group_hemis_id']] ?? 0);
                $grp['student_count'] = $cnt;
                $ynLower = strtolower($grp['yn_type'] ?? '');
                $qKey = ($grp['schedule_id'] ?? '') . '|' . $ynLower;
                $qCnt = (int) ($finishedMap[$qKey] ?? 0);
                if ($qCnt > $cnt) {
                    $qCnt = $cnt;
                }
                $grp['quiz_count'] = $qCnt;
                $grp['remaining'] = max(0, $cnt - $qCnt);
                $occupied += $cnt;
                $submitted += $qCnt;
                $remaining += $grp['remaining'];
            }
            unset($grp);
            $row['occupied'] = $occupied;
            $row['submitted'] = $submitted;
            $row['remaining'] = $remaining;
            // Vaqti qo'yilmagan satr uchun bandlik/sig'im hisoblanmaydi.
            if ($row['time'] === null) {
                $row['free'] = 0;
                $row['overflow'] = 0;
                $row['usage_percent'] = 0;
                $row['no_time'] = true;
            } else {
                $row['free'] = max(0, $totalComputers - $occupied);
                $row['overflow'] = max(0, $occupied - $totalComputers);
                $row['usage_percent'] = $totalComputers > 0 ? round(($occupied / $totalComputers) * 100, 1) : 0;
                $row['no_time'] = false;
            }
        }
        unset($row);

        // Vaqt bo'yicha saralash — vaqti qo'yilmaganlar oxirida.
        $slots = collect($rows)
            ->sortBy(fn($r) => $r['time'] === null ? 'zz' : $r['time'])
            ->values();

        return view('admin.academic-schedule.bandlik-kursatkichi-show', [
            'date' => $carbonDate,
            'slots' => $slots,
            'totalComputers' => $totalComputers,
        ]);
    }

    /**
     * Har bir guruh+fan yozuvini urinishlar (1/2/3) bo'yicha alohida virtual
     * qatorlarga ajratish.
     *  - 1-urinish — har doim ko'rinadi
     *  - 2-urinish — agar mavjud yozuv bo'lsa YOKI talabalar V<60 bo'lib qolgan bo'lsa
     *  - 3-urinish — xuddi shu mantiq, attempt=2 dan o'tmaganlar uchun
     */
    private function expandByUrinish($scheduleData, ?string $urinishFilter)
    {
        if ($scheduleData->isEmpty()) return $scheduleData;

        // Guruhdagi faol talabalar soni — 1-urinish ustuni uchun
        $allGroupHemisIds = $scheduleData->flatMap(fn($items) => $items->pluck('group')->pluck('group_hemis_id'))
            ->unique()->values()->toArray();
        $groupSizes = [];
        if (!empty($allGroupHemisIds)) {
            try {
                $groupSizes = DB::table('students')
                    ->whereIn('group_id', $allGroupHemisIds)
                    ->where('student_status_code', 11)
                    ->groupBy('group_id')
                    ->select('group_id', DB::raw('COUNT(*) as cnt'))
                    ->pluck('cnt', 'group_id')
                    ->toArray();
            } catch (\Throwable $e) {
                \Log::warning('groupSizes lookup failed: ' . $e->getMessage());
            }
        }

        // Talabalar qaysi urinishda V<60 bo'lib qolganligini aniqlash.
        // Strict mantiq: faqat haqiqiy bahosi mavjud bo'lib, lekin <60 bo'lgan yozuvlar.
        // Null/null yozuvlar (NB placeholderlar) inobatga olinmaydi.
        // Guruh bo'yicha alohida filterlanadi (chunki har item alohida guruh).
        $needsByKey = [];
        $attemptExistsByKey = []; // Aniq signal: attempt=N yozuv mavjud (qo'lda 12a/12b ga o'tkazilgan)
        try {
            $hasAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');

            foreach ([2, 3] as $att) {
                $check = $att === 2 ? 1 : 2; // attempt=N kerakmi → attempt=(N-1) dagi V<60
                $rows = DB::table('student_grades as sg')
                    ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                    ->whereNull('sg.deleted_at')
                    ->whereIn('sg.training_type_code', [101, 102])
                    ->where(function ($q) {
                        // Haqiqiy baho mavjud bo'lib, <60 bo'lsa
                        $q->where(function ($qq) {
                            $qq->whereNotNull('sg.retake_grade')->where('sg.retake_grade', '<', 60);
                        })->orWhere(function ($qq) {
                            $qq->whereNull('sg.retake_grade')
                               ->whereNotNull('sg.grade')
                               ->where('sg.grade', '<', 60);
                        });
                    })
                    ->when($hasAttemptCol, function ($q) use ($check) {
                        $q->where(function ($qq) use ($check) {
                            if ($check === 1) {
                                $qq->where('sg.attempt', 1)->orWhereNull('sg.attempt');
                            } else {
                                $qq->where('sg.attempt', $check);
                            }
                        });
                    })
                    ->select('st.group_id', 'sg.subject_id', 'sg.semester_code', DB::raw('COUNT(DISTINCT sg.student_hemis_id) as c'))
                    ->groupBy('st.group_id', 'sg.subject_id', 'sg.semester_code')
                    ->get();
                foreach ($rows as $r) {
                    $needsByKey[$r->group_id . '|' . $r->subject_id . '|' . $r->semester_code . '|' . $att] = (int) $r->c;
                }
            }

            // Qo'shimcha aniq signal: agar talaba allaqachon attempt=N (yoki katta)
            // yozuviga ega bo'lsa (qo'lda 12a/12b ga o'tkazilgan, OSKI/Test bahosi NULL bo'lsa ham),
            // baho<60 so'rovi uni topmaydi. Shuning uchun attempt=N mavjudligini ham
            // alohida saqlab, suppress qilmaslik uchun ishlatamiz.
            if ($hasAttemptCol) {
                foreach ([2, 3] as $att) {
                    $rows = DB::table('student_grades as sg')
                        ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                        ->whereNull('sg.deleted_at')
                        ->whereIn('sg.training_type_code', [101, 102])
                        ->where('sg.attempt', $att)
                        ->select('st.group_id', 'sg.subject_id', 'sg.semester_code', DB::raw('COUNT(DISTINCT sg.student_hemis_id) as c'))
                        ->groupBy('st.group_id', 'sg.subject_id', 'sg.semester_code')
                        ->get();
                    foreach ($rows as $r) {
                        $key = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code . '|' . $att;
                        $needsByKey[$key] = max($needsByKey[$key] ?? 0, (int) $r->c);
                        $attemptExistsByKey[$key] = (int) $r->c;
                    }
                }
            }

            // yn_submissions.attempt — agar talaba 12a/12b shakliga o'tkazilgan bo'lsa
            if (\Illuminate\Support\Facades\Schema::hasColumn('yn_submissions', 'attempt')) {
                foreach ([2, 3] as $att) {
                    $rows = DB::table('yn_submissions as yns')
                        ->where('yns.attempt', $att)
                        ->select('yns.group_hemis_id as group_id', 'yns.subject_id', 'yns.semester_code', DB::raw('1 as c'))
                        ->groupBy('yns.group_hemis_id', 'yns.subject_id', 'yns.semester_code')
                        ->get();
                    foreach ($rows as $r) {
                        $key = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code . '|' . $att;
                        if (!isset($needsByKey[$key])) {
                            $needsByKey[$key] = 1;
                        }
                        $attemptExistsByKey[$key] = $attemptExistsByKey[$key] ?? 1;
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('expandByUrinish needs check failed: ' . $e->getMessage());
        }

        return $scheduleData->map(function ($items) use ($urinishFilter, $needsByKey, $attemptExistsByKey, $groupSizes) {
            $expanded = collect();
            foreach ($items as $item) {
                $groupHid = $item['group']->group_hemis_id ?? '';
                $subjectId = $item['subject']->subject_id ?? '';
                $semCode = $item['subject']->semester_code ?? '';
                $needsKeyBase = $groupHid . '|' . $subjectId . '|' . $semCode;

                $studentsAttachedList = (isset($item['students']) && is_array($item['students'])) ? $item['students'] : null;
                $countFor = function (int $att) use ($studentsAttachedList, $groupSizes, $groupHid, $needsByKey, $needsKeyBase) {
                    if (is_array($studentsAttachedList)) {
                        if ($att === 1) return count($studentsAttachedList);
                        $field = $att === 2 ? 'failed_attempt1' : 'failed_attempt2';
                        return count(array_filter($studentsAttachedList, fn($s) => !empty($s[$field])));
                    }
                    if ($att === 1) {
                        return (int) ($groupSizes[$groupHid] ?? 0);
                    }
                    return (int) ($needsByKey[$needsKeyBase . '|' . $att] ?? 0);
                };

                // 1-urinish — har doim
                $row1 = $item;
                $row1['urinish'] = 1;
                $row1['oski_date_for_urinish'] = $item['oski_date'] ?? null;
                $row1['test_date_for_urinish'] = $item['test_date'] ?? null;
                $row1['oski_na_for_urinish'] = $item['oski_na'] ?? false;
                $row1['test_na_for_urinish'] = $item['test_na'] ?? false;
                $row1['student_count'] = $countFor(1);

                // 2-urinish ko'rinish qoidasi:
                //  - oski_resit_date / test_resit_date saqlangan bo'lsa (admin allaqachon yaratgan)
                //  - YOKI: 1-urinish kerakli sanalari belgilangan va o'tib bo'lgan
                //    (closing_form ga qarab) hamda quyidagi avto-signallardan birortasi rost:
                //      - student_grades.attempt=2 yozuvi mavjud (qo'lda 12a ga o'tkazilgan)
                //      - talabalar yuklangan bo'lsa: birortasida effective failed_attempt1
                //      - eski raw <60 signali (talabalar yuklanmagan holat uchun)
                $has2Data = !empty($item['oski_resit_date']) || !empty($item['test_resit_date']);
                $explicit2 = isset($attemptExistsByKey[$needsKeyBase . '|2']);
                $needs2Raw = isset($needsByKey[$needsKeyBase . '|2']);
                $studentsAttached = isset($item['students']) && is_array($item['students']) && !empty($item['students']);
                $anyStudentNeeds2 = false;
                if ($studentsAttached) {
                    foreach ($item['students'] as $stu) {
                        if (!empty($stu['failed_attempt1'])) {
                            $anyStudentNeeds2 = true;
                            break;
                        }
                    }
                }
                $attempt1Done = $this->isAttemptDatesPassed(
                    $item['closing_form'] ?? null,
                    $item['oski_date'] ?? null,
                    $item['oski_na'] ?? false,
                    $item['test_date'] ?? null,
                    $item['test_na'] ?? false
                );
                $show2 = $has2Data || (
                    ($explicit2 || $anyStudentNeeds2 || ($needs2Raw && !$studentsAttached))
                    && $attempt1Done
                );

                $row2 = null;
                if ($show2) {
                    $row2 = $item;
                    $row2['urinish'] = 2;
                    $row2['oski_date_for_urinish'] = $item['oski_resit_date'] ?? null;
                    $row2['test_date_for_urinish'] = $item['test_resit_date'] ?? null;
                    $row2['oski_na_for_urinish'] = false;
                    $row2['test_na_for_urinish'] = false;
                    $row2['student_count'] = $countFor(2);
                }

                // 3-urinish — xuddi shu mantiq, attempt=2 dan o'tmaganlar uchun
                $has3Data = !empty($item['oski_resit2_date']) || !empty($item['test_resit2_date']);
                $explicit3 = isset($attemptExistsByKey[$needsKeyBase . '|3']);
                $needs3Raw = isset($needsByKey[$needsKeyBase . '|3']);
                $anyStudentNeeds3 = false;
                if ($studentsAttached) {
                    foreach ($item['students'] as $stu) {
                        if (!empty($stu['failed_attempt2'])) {
                            $anyStudentNeeds3 = true;
                            break;
                        }
                    }
                }
                $attempt2Done = $this->isAttemptDatesPassed(
                    $item['closing_form'] ?? null,
                    $item['oski_resit_date'] ?? null,
                    false,
                    $item['test_resit_date'] ?? null,
                    false
                );
                $show3 = $has3Data || (
                    ($explicit3 || $anyStudentNeeds3 || ($needs3Raw && !$studentsAttached))
                    && $attempt2Done
                );

                $row3 = null;
                if ($show3) {
                    $row3 = $item;
                    $row3['urinish'] = 3;
                    $row3['oski_date_for_urinish'] = $item['oski_resit2_date'] ?? null;
                    $row3['test_date_for_urinish'] = $item['test_resit2_date'] ?? null;
                    $row3['oski_na_for_urinish'] = false;
                    $row3['test_na_for_urinish'] = false;
                    $row3['student_count'] = $countFor(3);
                }

                // Filter qo'llash
                $rowsToAdd = [];
                if ($urinishFilter === null || $urinishFilter === '' || $urinishFilter === '1') $rowsToAdd[] = $row1;
                if (($urinishFilter === null || $urinishFilter === '' || $urinishFilter === '2') && $row2) $rowsToAdd[] = $row2;
                if (($urinishFilter === null || $urinishFilter === '' || $urinishFilter === '3') && $row3) $rowsToAdd[] = $row3;

                foreach ($rowsToAdd as $r) {
                    if ($r) $expanded->push($r);
                }
            }
            return $expanded;
        })->filter(fn($items) => $items->isNotEmpty());
    }

    /**
     * closing_form ga qarab urinishning kerakli imtihon sanalari belgilangan
     * va bugundan oldin o'tib bo'lganini tekshiradi.
     * "na" (qatnashmadi) bayrog'i sana o'rnini bosadi.
     */
    private function isAttemptDatesPassed(?string $closingForm, ?string $oskiDate, bool $oskiNa, ?string $testDate, bool $testNa): bool
    {
        $today = \Carbon\Carbon::now()->startOfDay();
        $passed = function (?string $date) use ($today): bool {
            if (!$date) return false;
            try {
                return \Carbon\Carbon::parse($date)->startOfDay()->lt($today);
            } catch (\Throwable $e) {
                return false;
            }
        };

        $oskiOk = $oskiNa || $passed($oskiDate);
        $testOk = $testNa || $passed($testDate);

        switch ($closingForm) {
            case 'oski':
                return $oskiOk;
            case 'test':
                return $testOk;
            case 'oski_test':
                return $oskiOk && $testOk;
            case 'normativ':
            case 'sinov':
            case 'none':
                // Bu shakllarda OSKI/Test imtihon sanasi yo'q — sana to'sig'i qo'llanmaydi.
                return true;
            case '':
            case null:
            default:
                // closing_form sozlanmagan yoki noma'lum — qaysi sana talab qilinishi
                // aniq emas (masalan eski o'quv reja qatori). Kamida bitta OSKI yoki
                // Test sanasi qo'yilib o'tgan bo'lsagina urinish tugagan deb hisoblaymiz —
                // shunda erta (sana belgilanmagan) 2-urinish ro'yxatga chiqmaydi.
                return $oskiOk || $testOk;
        }
    }

    /**
     * Imtihon sanasi belgilangach, mos urinish (12a yoki 12b) uchun
     * yn_submission yozuvini avtomatik yaratish — agar hali bo'lmasa.
     * Bu jurnaldan "12a/12b ga o'tkazish" tugmasini bosish o'rniga
     * sanani bir marta belgilash bilan barchasini boshlaydi.
     */
    private function autoOpenAttemptIfNeeded($examScheduleRecord, int $attempt, bool $shouldOpen, $userId): void
    {
        if (!$shouldOpen) return;
        if (!\Illuminate\Support\Facades\Schema::hasColumn('yn_submissions', 'attempt')) return;

        try {
            // Asosiy YN yuborilgan bo'lishi shart
            $mainSubmission = YnSubmission::where('subject_id', $examScheduleRecord->subject_id)
                ->where('semester_code', $examScheduleRecord->semester_code)
                ->where('group_hemis_id', $examScheduleRecord->group_hemis_id)
                ->where(fn($q) => $q->where('attempt', 1)->orWhereNull('attempt'))
                ->first();
            if (!$mainSubmission) return; // asosiy yo'q — hech narsa qilmaymiz

            // 12b uchun avval 12a yaratilgan bo'lishi kerak
            if ($attempt === 3) {
                $aSubmission = YnSubmission::where('subject_id', $examScheduleRecord->subject_id)
                    ->where('semester_code', $examScheduleRecord->semester_code)
                    ->where('group_hemis_id', $examScheduleRecord->group_hemis_id)
                    ->where('attempt', 2)
                    ->first();
                if (!$aSubmission) return;
            }

            // Bu attempt uchun yaratilgan bo'lsa — qaytadan yaratmaymiz
            $existing = YnSubmission::where('subject_id', $examScheduleRecord->subject_id)
                ->where('semester_code', $examScheduleRecord->semester_code)
                ->where('group_hemis_id', $examScheduleRecord->group_hemis_id)
                ->where('attempt', $attempt)
                ->first();
            if ($existing) return;

            $userGuard = auth()->guard('teacher')->check() ? 'teacher' : 'web';
            $userIdToUse = $userId ?: (auth()->guard('web')->id() ?? auth()->guard('teacher')->id());

            YnSubmission::create([
                'subject_id' => $examScheduleRecord->subject_id,
                'semester_code' => $examScheduleRecord->semester_code,
                'group_hemis_id' => $examScheduleRecord->group_hemis_id,
                'attempt' => $attempt,
                'status' => 'draft',
                'submitted_by' => $userIdToUse,
                'submitted_by_guard' => $userGuard,
                'submitted_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('autoOpenAttemptIfNeeded xatolik (attempt=' . $attempt . '): ' . $e->getMessage());
        }
    }

    /**
     * Test markazi: "Qo'lda" rejim modali uchun ma'lumot — guruh
     * talabalari (familiya bo'yicha tartiblangan), faol kompyuterlar va
     * kunning boshqa schedule'lari uchun band oraliqlar. Frontend bu
     * ma'lumot asosida har talabaning vaqtini o'zgartirganda kompyuter
     * ro'yxatini disabled holatga keltirib turadi.
     */
    public function manualAssignOptions(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        if ($this->isTestCenterReadOnly()) {
            return response()->json(['success' => false, 'message' => 'Bu amalga ruxsat yo\'q.'], 403);
        }

        $request->validate([
            'group_hemis_id' => 'required|string',
            'subject_id'     => 'required|string',
            'semester_code'  => 'required|string',
            'yn_type'        => 'required|string|in:OSKI,Test',
        ]);

        $schedule = ExamSchedule::where('group_hemis_id', $request->group_hemis_id)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->whereNull('student_hemis_id')
            ->first();
        if (!$schedule) {
            return response()->json(['success' => false, 'message' => 'Jadval topilmadi.'], 404);
        }

        $ynKey = $request->yn_type === 'OSKI' ? 'oski' : 'test';
        $dateField = $ynKey . '_date';
        $rawDate = $schedule->{$dateField};
        if (empty($rawDate)) {
            return response()->json([
                'success' => false,
                'message' => 'Avval ' . $request->yn_type . ' sanasini belgilang.',
            ], 422);
        }
        $dateStr = $rawDate instanceof \Carbon\Carbon
            ? $rawDate->format('Y-m-d')
            : \Carbon\Carbon::parse((string) $rawDate)->format('Y-m-d');

        $duration = max(1, (int) config('services.moodle.quiz_duration_minutes', 25));
        $buffer   = max(0, (int) config('services.moodle.computer_buffer_minutes', 5));

        $capacity = ExamCapacityService::getSettingsForDate($dateStr);

        $students = Student::where('group_id', $request->group_hemis_id)
            ->whereNotNull('student_id_number')
            ->orderBy('full_name')
            ->get(['hemis_id', 'student_id_number', 'full_name']);

        $computers = \App\Models\Computer::where('active', true)
            ->orderBy('number')
            ->get(['number', 'ip_address', 'is_reserve_pool', 'label']);

        // Pull all OTHER schedules' assignments for this date so the
        // frontend can mark conflicts per (computer, time-window).
        $busy = \App\Models\ComputerAssignment::query()
            ->whereDate('planned_start', $dateStr)
            ->where(function ($q) use ($schedule, $ynKey) {
                $q->where('exam_schedule_id', '!=', $schedule->id)
                    ->orWhere('yn_type', '!=', $ynKey);
            })
            ->whereIn('status', [
                \App\Models\ComputerAssignment::STATUS_SCHEDULED,
                \App\Models\ComputerAssignment::STATUS_IN_PROGRESS,
            ])
            ->with([
                'examSchedule:id,group_hemis_id,subject_name,subject_id,semester_code',
            ])
            ->orderBy('planned_start')
            ->get(['exam_schedule_id', 'computer_number', 'planned_start', 'planned_end', 'yn_type'])
            ->map(function ($a) {
                return [
                    'computer_number' => (int) $a->computer_number,
                    'planned_start'   => $a->planned_start?->format('H:i'),
                    'planned_end'     => $a->planned_end?->format('H:i'),
                    'subject'         => $a->examSchedule?->subject_name,
                    'yn_type'         => $a->yn_type,
                ];
            })
            ->values();

        // Pre-load existing assignments for THIS schedule so we can
        // pre-fill the modal if admin re-opens it.
        $existing = \App\Models\ComputerAssignment::query()
            ->where('exam_schedule_id', $schedule->id)
            ->where('yn_type', $ynKey)
            ->where('status', \App\Models\ComputerAssignment::STATUS_SCHEDULED)
            ->get(['student_hemis_id', 'computer_number', 'planned_start'])
            ->map(fn($a) => [
                'student_hemis_id' => (string) $a->student_hemis_id,
                'computer_number'  => $a->computer_number ? (int) $a->computer_number : null,
                'time'             => $a->planned_start?->format('H:i'),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'date'    => $dateStr,
            'duration_minutes' => $duration,
            'buffer_minutes'   => $buffer,
            'work_hours_start' => $capacity['work_hours_start'] ?? null,
            'work_hours_end'   => $capacity['work_hours_end'] ?? null,
            'lunch_start'      => $capacity['lunch_start'] ?? null,
            'lunch_end'        => $capacity['lunch_end'] ?? null,
            'students' => $students->map(fn($s) => [
                'hemis_id' => (string) $s->hemis_id,
                'student_id_number' => $s->student_id_number,
                'full_name' => $s->full_name,
            ])->values(),
            'computers' => $computers->map(fn($c) => [
                'number' => (int) $c->number,
                'ip' => $c->ip_address,
                'is_reserve' => (bool) $c->is_reserve_pool,
                'label' => $c->label,
            ])->values(),
            'busy' => $busy,
            'existing' => $existing,
        ]);
    }

    /**
     * Test markazi: "Qo'lda" rejim modalidan kelgan POST — har bir
     * talaba uchun (vaqt, kompyuter) jufti bilan ComputerAssignment
     * rowlari yoziladi. Validatsiya va saqlash logikasi
     * ComputerAssignmentService::manualAssign'da.
     */
    public function manualAssignSave(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        if ($this->isTestCenterReadOnly()) {
            return response()->json(['success' => false, 'message' => 'Bu amalga ruxsat yo\'q.'], 403);
        }

        $request->validate([
            'group_hemis_id' => 'required|string',
            'subject_id'     => 'required|string',
            'semester_code'  => 'required|string',
            'yn_type'        => 'required|string|in:OSKI,Test',
            'assignments'    => 'required|array|min:1',
            'assignments.*.student_hemis_id' => 'required|string',
            'assignments.*.computer_number'  => 'required|integer|min:1',
            'assignments.*.time'             => 'required|date_format:H:i',
        ]);

        $schedule = ExamSchedule::where('group_hemis_id', $request->group_hemis_id)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->whereNull('student_hemis_id')
            ->first();
        if (!$schedule) {
            return response()->json(['success' => false, 'message' => 'Jadval topilmadi.'], 404);
        }

        $ynKey = $request->yn_type === 'OSKI' ? 'oski' : 'test';

        $result = app(\App\Services\ComputerAssignmentService::class)
            ->manualAssign($schedule, $ynKey, $request->input('assignments'));

        if (empty($result['ok'])) {
            return response()->json([
                'success' => false,
                'message' => 'Saqlashda xato.',
                'errors' => $result['errors'] ?? ['Noma\'lum xato'],
            ], 422);
        }

        // Trigger the existing Moodle group-exam booking job, mirroring
        // the auto/JIT paths so quizzes are created with the right open
        // window and per-student access list.
        BookMoodleGroupExam::dispatch($schedule->id, $ynKey);

        return response()->json([
            'success' => true,
            'count'   => $result['count'] ?? 0,
            'earliest_time' => $result['earliest_time'] ?? null,
            'message' => "Qo'lda biriktirma saqlandi: " . ($result['count'] ?? 0) . " ta talaba.",
        ]);
    }
}

