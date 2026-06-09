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
use App\Services\ActivityLogService;
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
        // Admin Settings → "Test markazi huquqlari → Bugungi imtihonni
        // o'zgartirish" toggle lifts this restriction entirely. Past days
        // stay blocked even with the toggle on.
        $dateStr = $relatedDate instanceof \Carbon\Carbon
            ? $relatedDate->format('Y-m-d')
            : \Carbon\Carbon::parse($relatedDate)->format('Y-m-d');
        $today = now()->format('Y-m-d');
        $canEditToday = ExamDateRoleService::testCenterCanEditToday();
        if ($canEditToday && $dateStr === $today) {
            return null;
        }
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
     * Talabalarga imtihon vaqti belgilanganligi haqida Telegram + DB
     * notification yuboradi. saveTestTime, saveStudentTime, store() va
     * autoTimeAll — barchasi shu yagona joydan foydalanadi.
     *
     * @param  \Illuminate\Support\Collection|iterable  $students  Notification
     *         yuboriladigan Student modellari (telegram tasdiqlangan).
     * @return int yuborilgan xabarlar soni
     */
    private function notifyStudentsExamTime(
        $students,
        string $subjectName,
        string $ynType,           // 'OSKI' / 'Test'
        string $ynLabel,          // 'OSKI' / 'Test (2-urinish)' ...
        ?string $testDateFmt,     // 'd.m.Y' yoki null/empty
        string $newTimeHM,        // 'H:i'
        ?string $oldTime = null,  // oldingi vaqt (H:i:s yoki H:i); null = yangi
        bool $ynSubmitted = false
    ): int {
        if (empty($students) || (is_object($students) && method_exists($students, 'isEmpty') && $students->isEmpty())) {
            return 0;
        }

        $telegram = app(TelegramService::class);
        $oldNorm = $oldTime ? substr((string) $oldTime, 0, 5) : null;
        $timeChanged = $oldNorm !== null && $oldNorm !== $newTimeHM;

        $warningText = !$ynSubmitted
            ? "\n\n⚠️ <i>{$ynLabel} vaqti o'zgarishi mumkin, habardor bo'lib turing!</i>"
            : '';
        $warningPlain = !$ynSubmitted
            ? " {$ynLabel} vaqti o'zgarishi mumkin, habardor bo'lib turing!"
            : '';

        if ($timeChanged) {
            $message = "📋 <b>{$ynLabel} vaqti o'zgartirildi!</b>\n\n"
                . "📌 Fan: <b>{$subjectName}</b>\n"
                . ($testDateFmt ? "📅 Sana: <b>{$testDateFmt}</b>\n" : '')
                . "⏰ Eski vaqt: <s>{$oldNorm}</s>\n"
                . "⏰ Yangi vaqt: <b>{$newTimeHM}</b>"
                . $warningText;
            $notifTitle = "{$ynLabel} vaqti o'zgartirildi: {$subjectName}";
            $notifMessage = "Fan: {$subjectName}"
                . ($testDateFmt ? ", Sana: {$testDateFmt}" : '')
                . ", Eski vaqt: {$oldNorm}, Yangi vaqt: {$newTimeHM}." . $warningPlain;
        } else {
            $message = "📋 <b>{$ynLabel} vaqti belgilandi!</b>\n\n"
                . "📌 Fan: <b>{$subjectName}</b>\n"
                . ($testDateFmt ? "📅 Sana: <b>{$testDateFmt}</b>\n" : '')
                . "⏰ Vaqt: <b>{$newTimeHM}</b>"
                . $warningText;
            $notifTitle = "{$ynLabel} vaqti belgilandi: {$subjectName}";
            $notifMessage = "Fan: {$subjectName}"
                . ($testDateFmt ? ", Sana: {$testDateFmt}" : '')
                . ", Vaqt: {$newTimeHM}." . $warningPlain;
        }

        $notificationRecords = [];
        $sentCount = 0;
        foreach ($students as $student) {
            try {
                if (!empty($student->telegram_chat_id)) {
                    $telegram->sendToUser($student->telegram_chat_id, $message);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('notifyStudentsExamTime: telegram failed', [
                    'student_id' => $student->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
            $notificationRecords[] = [
                'student_id' => $student->id,
                'type' => 'exam_reminder',
                'title' => $notifTitle,
                'message' => $notifMessage,
                'link' => '/student/exam-schedule',
                'data' => json_encode([
                    'subject' => $subjectName,
                    'yn_type' => $ynType,
                    'test_time' => $newTimeHM,
                    'test_date' => $testDateFmt,
                    'time_changed' => $timeChanged,
                ]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $sentCount++;
        }
        if (!empty($notificationRecords)) {
            StudentNotification::insert($notificationRecords);
        }
        return $sentCount;
    }

    /**
     * Guruh notification ro'yxati: individual grafikka ega talabalarni
     * (ushbu imtihon uchun shaxsiy date column'i to'lgan per-student
     * yozuvga ega bo'lganlarini) chiqarib tashlaydi.
     */
    private function groupStudentsForExamNotify(
        string $groupHemisId,
        string $subjectId,
        string $semesterCode,
        string $dateColumn,
        int $attempt = 1
    ): \Illuminate\Database\Eloquent\Collection {
        $excluded = ExamSchedule::where('group_hemis_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNotNull('student_hemis_id')
            ->whereNotNull($dateColumn)
            ->pluck('student_hemis_id')
            ->all();

        $query = Student::where('group_id', $groupHemisId)
            ->whereNotNull('telegram_chat_id')
            ->whereNotNull('telegram_verified_at');
        if (!empty($excluded)) {
            $query->whereNotIn('hemis_id', $excluded);
        }
        // 2/3-urinish: faqat yiqilgan (retaker) talabalarga jo'natamiz —
        // 1-urinishni o'tganlarga ortiqcha xabar bormasin.
        if ($attempt >= 2) {
            $retakers = \App\Services\ExamCapacityService::resitEligibleStudentIds(
                $groupHemisId, $subjectId, $semesterCode
            );
            if (empty($retakers)) {
                return new \Illuminate\Database\Eloquent\Collection();
            }
            $query->whereIn('hemis_id', $retakers);
        }
        return $query->get();
    }

    /**
     * Bitta talabaning Telegram-tasdiqlangan yozuvini olib qaytaradi
     * (notification yuborish uchun). Tasdiqlanmagan bo'lsa bo'sh collection.
     */
    private function singleStudentForNotify(string $studentHemisId): \Illuminate\Database\Eloquent\Collection
    {
        return Student::where('hemis_id', $studentHemisId)
            ->whereNotNull('telegram_chat_id')
            ->whereNotNull('telegram_verified_at')
            ->get();
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
            // Test markazi roli bu sahifani (YN kunini belgilash) ko'rmaydi,
            // lekin uning o'rniga YN jadvali (test-center) ga kirishi mumkin.
            // Bookmark yoki eski linkdan kelgan bo'lsa 403 berish o'rniga
            // to'g'ri sahifaga yo'naltiramiz.
            if ($activeRole === \App\Enums\ProjectRole::TEST_CENTER->value) {
                $route = auth('teacher')->check()
                    ? 'teacher.academic-schedule.test-center'
                    : 'admin.academic-schedule.test-center';
                return redirect()->route($route);
            }
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

        $allowPastExamDates = ExamDateRoleService::allowPastExamDates();
        $allowTodayExamDates = ExamDateRoleService::allowTodayExamDates();
        $examDateSubmissionCutoffHour = ExamDateRoleService::examDateSubmissionCutoffHour();
        $allow4PlusDebtorsRetake = ExamDateRoleService::allow4PlusDebtorsRetake();

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
            'allowPastExamDates',
            'allowTodayExamDates',
            'examDateSubmissionCutoffHour',
            'allow4PlusDebtorsRetake',
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

        // Per-student urinish-status: jn/mt/oski/test/davomat asosida.
        // Sahifa fan bo'yicha filtrlangan bo'lishi mumkin, lekin 4+ qarz qoidasi
        // (held_back) talabaning JORIY SEMESTRDAGI BARCHA fanlarini talab qiladi —
        // aks holda ko'rinmayotgan fanlardagi qarzlar sanalmay, talaba xato
        // 2-urinishga o'tib ketadi. Shu sababli joriy semestrning barcha
        // (guruh, fan) triplelarini exam_schedules dan olib, statusni shular
        // bo'yicha hisoblaymiz (display faqat filtrlangan qismni ko'rsatadi).
        $semCodesForDebt = [];
        $groupHidsForDebt = [];
        $scheduleData->each(function ($items) use (&$semCodesForDebt, &$groupHidsForDebt) {
            foreach ($items as $it) {
                $g = $it['group']->group_hemis_id ?? null;
                $sem = $it['subject']->semester_code ?? null;
                if ($g) $groupHidsForDebt[(string) $g] = true;
                if ($sem) $semCodesForDebt[(string) $sem] = true;
            }
        });

        $fullTriples = [];
        $examDateMap = []; // g|s|sem => ['oski'=>?Y-m-d, 'test'=>?Y-m-d]
        if (!empty($groupHidsForDebt) && !empty($semCodesForDebt)) {
            try {
                $esRows = DB::table('exam_schedules')
                    ->whereNull('student_hemis_id')
                    ->whereIn('group_hemis_id', array_keys($groupHidsForDebt))
                    ->whereIn('semester_code', array_keys($semCodesForDebt))
                    ->select('group_hemis_id', 'subject_id', 'semester_code', 'oski_date', 'test_date')
                    ->get();
                foreach ($esRows as $r) {
                    $tk = $r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                    $fullTriples[$tk] = [$r->group_hemis_id, $r->subject_id, $r->semester_code];
                    $examDateMap[$tk] = [
                        'oski' => $r->oski_date ? substr((string) $r->oski_date, 0, 10) : null,
                        'test' => $r->test_date ? substr((string) $r->test_date, 0, 10) : null,
                    ];
                }
            } catch (\Throwable $e) {
                \Log::warning('attachStudentsToSchedule: exam_schedules full load failed: ' . $e->getMessage());
            }
        }
        // Ko'rinayotgan triplelar exam_schedules'da bo'lmasligi mumkin — qo'shamiz.
        $scheduleData->each(function ($items) use (&$fullTriples) {
            foreach ($items as $it) {
                $g = $it['group']->group_hemis_id ?? null;
                $s = $it['subject']->subject_id ?? null;
                $sem = $it['subject']->semester_code ?? null;
                if ($g && $s && $sem) {
                    $tk = $g . '|' . $s . '|' . $sem;
                    if (!isset($fullTriples[$tk])) {
                        $fullTriples[$tk] = [$g, $s, $sem];
                    }
                }
            }
        });

        $studentStatus = $this->computeAttemptStatusesForTriples($fullTriples);

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

        // Joriy semestr qarzlari — sahifa fan-filtridan MUSTAQIL, $fullTriples
        // (joriy semestrning barcha fanlari) bo'yicha. Fan "qarz" sanaladi:
        //  - YN kuni (oski/test sanasi) o'tgan VA talaba yiqilgan (failed1), yoki
        //  - talaba qo'lda 2-urinishga o'tkazilgan (student_grades.attempt=2).
        // YN kuni hali kelmagan fan qarz emas — talaba 1-urinishini hali
        // topshirmagan (3 qarz + joriy 1-urinish fan = 3, bloklanmaydi).
        // currentDebtsByStudent[hemis_id] = [subject_id|semester_code => [...]]
        $currentDebtsByStudent = [];
        // Pullik fanlarni har talaba uchun yig'amiz — badge tooltipida ko'rsatamiz.
        $pullikSubjectsByStudent = [];
        $debtSubjMeta = [];
        if (!empty($fullTriples)) {
            $debtSubjIds = array_values(array_unique(array_map(fn($t) => $t[1], $fullTriples)));
            $debtSemCodes = array_values(array_unique(array_map(fn($t) => $t[2], $fullTriples)));
            try {
                $metaRows = DB::table('curriculum_subjects')
                    ->whereIn('subject_id', $debtSubjIds)
                    ->whereIn('semester_code', $debtSemCodes)
                    ->select('subject_id', 'semester_code', 'subject_name', 'semester_name')
                    ->get();
                foreach ($metaRows as $mr) {
                    $debtSubjMeta[$mr->subject_id . '|' . $mr->semester_code] = [
                        'subject_name' => $mr->subject_name,
                        'semester_name' => $mr->semester_name,
                    ];
                }
            } catch (\Throwable $e) {
                \Log::warning('attachStudentsToSchedule: debt subject meta load failed: ' . $e->getMessage());
            }
        }
        foreach ($fullTriples as $tk => $triple) {
            [$g, $s, $sem] = $triple;
            $dates = $examDateMap[$tk] ?? [];
            $oskiD = $dates['oski'] ?? null;
            $testD = $dates['test'] ?? null;
            $ynDayDone = ($oskiD && $oskiD < $today) || ($testD && $testD < $today);
            $statByStu = $studentStatus[$tk] ?? [];
            foreach ($studentsByGroup->get($g, collect()) as $st) {
                $stat = $statByStu[$st->hemis_id] ?? null;

                // Pullik fanlarni yig'amiz — pullik joriy debt mavjudligidan
                // mustaqil, shuning uchun continue'dan oldin tekshiramiz.
                if (!empty($stat) && !empty($stat['pullik'])) {
                    $pSubjName = $debtSubjMeta[$s . '|' . $sem]['subject_name'] ?? '';
                    if ($pSubjName !== '' && !in_array($pSubjName, $pullikSubjectsByStudent[$st->hemis_id] ?? [], true)) {
                        $pullikSubjectsByStudent[$st->hemis_id][] = $pSubjName;
                    }
                }

                $explicitK = $st->hemis_id . '|' . $s . '|' . $sem;
                $hasA2 = !empty($explicitAttemptByStudent[$explicitK]['attempt2']);
                // Fanni amalda o'tgan talaba — qarz emas (attempt=2 yozuvi
                // bo'lsa ham: u 2-urinishni o'tgan bo'lishi mumkin).
                if (!empty($stat) && !empty($stat['passed'])) continue;
                $failedAndDone = $ynDayDone && !empty($stat) && !empty($stat['failed1']);
                if (!$failedAndDone && !$hasA2) continue;
                $debtKey = $s . '|' . $sem;
                if (!isset($currentDebtsByStudent[$st->hemis_id][$debtKey])) {
                    $meta = $debtSubjMeta[$debtKey] ?? [];
                    $currentDebtsByStudent[$st->hemis_id][$debtKey] = [
                        'subject_id' => $s,
                        'subject_name' => $meta['subject_name'] ?? '',
                        'semester_name' => $meta['semester_name'] ?? '',
                        'semester_code' => (int) $sem,
                    ];
                }
            }
        }

        // YN ga ruxsat — har bir talaba uchun (Ruxsat / Shartli / X). Test markazi
        // view'i va vaqt qo'yish endpointlari shu xaritaga tayanadi. Hisob YN
        // oldi qaydnoma bilan bir xil mantiq orqali (YnAdmissionService).
        // Cache — bitta (group|subj|sem) uchun bir marta chaqirilsin.
        $admissionService = app(\App\Services\YnAdmissionService::class);
        $admissionCache = [];

        // SUBGROUP membership: (a)/(b)/(c) varianti bo'lgan fanlar uchun
        // curriculum_subjects.in_group bo'sh emas. Bunday fanlarda guruh
        // talabalari subgroup'larga bo'lingan — har bir talaba faqat bitta
        // variantni oladi. student_subjects jadvali shu bog'lanishni saqlaydi.
        // Bu yerda barcha kerakli curriculum_subject_hemis_id'lar uchun
        // student_subjects'ni batch yuklab, har variant uchun "ruxsat etilgan"
        // hemis_id'lar to'plamini quramiz. Quyida iterate qilinganda guruhdagi
        // boshqa subgrouplardagi talabalar ro'yxatdan chiqarib tashlanadi.
        $subjectIdsWithVariant = [];
        $scheduleData->each(function ($items) use (&$subjectIdsWithVariant) {
            foreach ($items as $it) {
                $subj = $it['subject'] ?? null;
                if (!$subj) continue;
                $inGroup = trim((string) ($subj->in_group ?? ''));
                if ($inGroup === '') continue;
                $csHid = $subj->curriculum_subject_hemis_id ?? null;
                if ($csHid !== null) $subjectIdsWithVariant[(string) $csHid] = true;
            }
        });
        // subgroupMembers[(string) curriculum_subject_hemis_id] => [hemis_id => true]
        $subgroupMembers = [];
        if (!empty($subjectIdsWithVariant)) {
            try {
                $rows = DB::table('student_subjects')
                    ->whereIn('curriculum_subject_hemis_id', array_keys($subjectIdsWithVariant))
                    ->select('student_hemis_id', 'curriculum_subject_hemis_id')
                    ->get();
                foreach ($rows as $r) {
                    $cs = (string) $r->curriculum_subject_hemis_id;
                    $hid = (string) $r->student_hemis_id;
                    if (!isset($subgroupMembers[$cs])) $subgroupMembers[$cs] = [];
                    $subgroupMembers[$cs][$hid] = true;
                }
            } catch (\Throwable $e) {
                \Log::warning('attachStudentsToSchedule: subgroup load failed: ' . $e->getMessage());
            }
        }

        return $scheduleData->map(function ($items) use ($studentsByGroup, $perStudentMap, $studentStatus, $pastDebtsMap, $explicitAttemptByStudent, $attempt1OskiByKey, $attempt1TestByKey, $today, $currentDebtsByStudent, $pullikSubjectsByStudent, $admissionService, &$admissionCache, $subgroupMembers) {
            return $items->map(function ($item) use ($studentsByGroup, $perStudentMap, $studentStatus, $pastDebtsMap, $explicitAttemptByStudent, $attempt1OskiByKey, $attempt1TestByKey, $today, $currentDebtsByStudent, $pullikSubjectsByStudent, $admissionService, &$admissionCache, $subgroupMembers) {
                $gHid = $item['group']->group_hemis_id;
                $subjectId = $item['subject']->subject_id ?? null;
                $semCode = $item['subject']->semester_code ?? null;
                $statusKey = $gHid . '|' . $subjectId . '|' . $semCode;
                $statusByStudent = $studentStatus[$statusKey] ?? [];
                $studentList = $studentsByGroup->get($gHid, collect());

                // SUBGROUP filter: agar fan (a)/(b)/(c) varianti bo'lsa
                // (in_group bo'sh emas), faqat shu variantga yozilgan
                // talabalarni qoldiramiz. Boshqa subgroup talabalari
                // umuman ro'yxatda chiqmaydi (ularda JN/MT yo'q, "ruxsat yo'q"
                // chiqishi noto'g'ri ko'rinishni keltirib chiqarardi).
                $itemInGroup = trim((string) ($item['subject']->in_group ?? ''));
                $itemCsHid = $item['subject']->curriculum_subject_hemis_id ?? null;
                if ($itemInGroup !== '' && $itemCsHid !== null) {
                    $allowed = $subgroupMembers[(string) $itemCsHid] ?? [];
                    if (!empty($allowed)) {
                        $studentList = $studentList->filter(fn($s) => isset($allowed[(string) $s->hemis_id]))->values();
                    }
                }

                // Admission map (Ruxsat / Shartli / X) — bitta marta hisoblanadi.
                $admissionKey = $gHid . '|' . $subjectId . '|' . $semCode;
                if (!isset($admissionCache[$admissionKey])) {
                    try {
                        $admissionCache[$admissionKey] = ($gHid && $subjectId && $semCode)
                            ? $admissionService->computeForGroup((string) $gHid, (string) $subjectId, (string) $semCode)
                            : [];
                    } catch (\Throwable $e) {
                        \Log::warning('attachStudentsToSchedule: admission compute failed', [
                            'group' => $gHid, 'subject' => $subjectId, 'sem' => $semCode,
                            'error' => $e->getMessage(),
                        ]);
                        $admissionCache[$admissionKey] = [];
                    }
                }
                $admissionByStudent = $admissionCache[$admissionKey];

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
                    // Fanni amalda o'tgan (OSKI/Test urinishlari birlashtirilib
                    // o'tilgan) talaba — 2/3-urinishga tushmaydi.
                    if (!empty($stat['passed'])) {
                        $effectiveFailed1 = false;
                        $effectiveFailed2 = false;
                    }
                    // Joriy semestrdagi BARCHA qarz fanlari (joriy semestrning
                    // hamma fanlari bo'yicha, sahifa fan-filtridan mustaqil).
                    $currentDebts = array_values($currentDebtsByStudent[$stu->hemis_id] ?? []);
                    // O'tgan semestr qarzi bilan AYNAN bir xil (fan + semestr)
                    // bo'lgan joriy qarzni chiqaramiz — bitta qarz ikki marta
                    // sanalmasin. Bir xil fan turli semestrlarda bo'lsa (yillik
                    // fan, masalan Odam anatomiyasi) — har biri ALOHIDA qarz.
                    if (!empty($pastDebts)) {
                        $pastDebtKeys = [];
                        foreach ($pastDebts as $pd) {
                            $pastDebtKeys[($pd['subject_id'] ?? '') . '|' . ($pd['semester_code'] ?? '')] = true;
                        }
                        $currentDebts = array_values(array_filter(
                            $currentDebts,
                            fn ($d) => !isset($pastDebtKeys[($d['subject_id'] ?? '') . '|' . ($d['semester_code'] ?? '')])
                        ));
                    }
                    usort($currentDebts, fn($a, $b) => $a['semester_code'] <=> $b['semester_code']);
                    $admission = $admissionByStudent[$stu->hemis_id] ?? null;
                    $rows[] = [
                        'hemis_id' => $stu->hemis_id,
                        'student_id_number' => $stu->student_id_number ?? null,
                        'full_name' => $stu->full_name,
                        'oski_date' => $perRow?->oski_date?->format('Y-m-d'),
                        'oski_time' => $perRow?->oski_time,
                        'test_date' => $perRow?->test_date?->format('Y-m-d'),
                        'test_time' => $perRow?->test_time,
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
                        'pullik_subjects' => array_values($pullikSubjectsByStudent[$stu->hemis_id] ?? []),
                        'is_held_back' => $stat['held_back'] ?? false,
                        'past_debts' => $pastDebts,
                        'current_semester_debts' => $currentDebts,
                        // YN ga ruxsat (YN oldi qaydnoma bilan bir xil mantiq)
                        'admission_status' => $admission['status'] ?? null,
                        'admission_reasons' => $admission['reasons'] ?? [],
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
    public static function computeStudentPastSemesterDebts(array $studentHemisIds): array
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
        // Talabaning JORIY (tiklangan) o'quv rejasi — qarz faqat shu reja
        // bo'yicha hisoblanadi. Transfer/tiklangan talabada academic_records
        // bir nechta eski curriculumga tegishli bo'lishi mumkin; eski rejalar
        // hisobga olinsa (subject_id/semestr mos kelmay) o'tilgan fanlar xato
        // "qarz" bo'lib chiqadi. Tiklangan talabaning baholari yangi rejaning
        // academic_records iga ko'chiriladi — shuning uchun joriy reja kanonik.
        $studentCurrentCurr = []; // hemis_id => joriy curriculum_id
        foreach ($students as $st) {
            if ($st->curriculum_id) {
                $studentCurrentCurr[$st->hemis_id] = $st->curriculum_id;
            }
        }

        $arExistsLookup = []; // hemis_id|subject_id|semester_id => true
        $studentSemCurr = []; // hemis_id => [semester_id => curriculum_id]
        foreach ($arRecords as $ar) {
            $arExistsLookup[$ar->student_id . '|' . $ar->subject_id . '|' . $ar->semester_id] = true;
            // Har semestrni academic_records dagi eski curriculumga emas,
            // talabaning JORIY rejasiga bog'laymiz.
            $curr = $studentCurrentCurr[$ar->student_id] ?? null;
            if ($curr && !isset($studentSemCurr[$ar->student_id][$ar->semester_id])) {
                $studentSemCurr[$ar->student_id][$ar->semester_id] = $curr;
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
                $subjectsForSem = self::filterSubjectsByGroupSuffixSimple($subjectsForSem, $st->group_name ?? '');

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
                        'subject_id' => $effectiveSubjectId,
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
    public static function filterSubjectsByGroupSuffixSimple($records, string $groupName)
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

        return $this->computeAttemptStatusesForTriples($triples);
    }

    /**
     * Berilgan (group|subj|sem) triplelar ro'yxati uchun urinish statusini
     * hisoblaydi. computeStudentAttemptStatuses scheduleData'dan triple yasab
     * shu metodga uzatadi; qarz sonini sahifa fan-filtridan mustaqil hisoblash
     * uchun (joriy semestrning barcha fanlari bilan) ham chaqiriladi.
     *
     * @param array<string,array{0:mixed,1:mixed,2:mixed}> $triples
     */
    private function computeAttemptStatusesForTriples(array $triples): array
    {
        $result = [];
        $hasAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');
        $minLimit = 60;

        if (empty($triples)) return $result;

        $allGroupHids = array_unique(array_column($triples, 0));
        $allSubjectIds = array_unique(array_column($triples, 1));
        $allSemCodes = array_unique(array_column($triples, 2));

        // Har bir guruh uchun JORIY o'quv yili boshlanish sanasi — o'quv reja
        // bo'yicha ALOHIDA (HEMIS curriculum_weeks dan). Bir o'quv yili 2
        // semestr; chegara — yilning 1-semestri boshlanishi. Tiklangan/transfer
        // talabaning eski o'qishidagi baholarini aniq ajratish uchun. Reja
        // topilmasa — global o'quv yili boshi (zaxira).
        $yearStartFallback = \App\Services\JournalGradeService::currentAcademicYearStart();
        $yearStartByGroup = \App\Services\JournalGradeService::academicYearStartByGroup(
            array_values($allGroupHids)
        );

        // Ko'rinadigan triples (status qaytarish uchun) va kengaytirilgan triples
        // (4+ qarz qoidasi uchun, butun o'quv yili bo'yicha sanash kerak).
        $visibleTriples = $triples;

        // semester_code → education_year xaritasi
        $semYearMap = [];
        $semLevelMap = [];
        try {
            $rows = DB::table('semesters')
                ->whereIn('semester_code', $allSemCodes)
                ->select('semester_code', 'education_year', 'level_code')
                ->get();
            foreach ($rows as $r) {
                $semYearMap[$r->semester_code] = $r->education_year;
                $semLevelMap[$r->semester_code] = (string) ($r->level_code ?? '');
            }
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
                    ->select('semester_code', 'education_year', 'level_code')
                    ->get();
                foreach ($rows as $r) {
                    $semYearMap[$r->semester_code] = $r->education_year;
                    $semLevelMap[$r->semester_code] = (string) ($r->level_code ?? '');
                }
            } catch (\Throwable $e) {}
        }

        // Eslatma: o'quv yiliga to'liq kengaytirish (yashirin semestrlar uchun)
        // og'ir SQL so'rovlariga aylanadi va sahifa 504 berib qoladi. Hozircha
        // ko'rinadigan triples ustida ishlaymiz — agar foydalanuvchi yiliga
        // tegishli har ikki semestrni filtr orqali yuklasa, hisob to'g'ri bo'ladi.

        // Talabalarning hemis_id va group_id xaritasi (faqat faol talabalar)
        $studentRows = DB::table('students')
            ->whereIn('group_id', $allGroupHids)
            ->where('student_status_code', 11)
            ->select('hemis_id', 'group_id', 'curriculum_id')
            ->get();
        $studentGroup = [];
        $studentCurriculum = []; // hemis_id => curricula_hemis_id (talabaning o'quv rejasi)
        foreach ($studentRows as $sr) {
            $studentGroup[$sr->hemis_id] = $sr->group_id;
            $studentCurriculum[$sr->hemis_id] = $sr->curriculum_id;
        }
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

        // Per-student exam_schedules — alohida belgilangan individual 2-urinish
        // sanalari (admin SAIDMURODOVA kabi yolg'iz 2-urinishchiga sana
        // qo'ygan holatlar). Group naMap'da bo'lmasa shu yerdan olamiz.
        $perStudentResitMap = []; // hemis|subj|sem => ['oski_resit_date', 'test_resit_date']
        try {
            $rows = DB::table('exam_schedules')
                ->whereNotNull('student_hemis_id')
                ->whereIn('group_hemis_id', $allGroupHids)
                ->whereIn('subject_id', $allSubjectIds)
                ->whereIn('semester_code', $allSemCodes)
                ->select('student_hemis_id', 'subject_id', 'semester_code',
                    'oski_resit_date', 'test_resit_date')
                ->get();
            foreach ($rows as $r) {
                $k = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                $perStudentResitMap[$k] = [
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

        // 1b) Tirik manbalar — snapshot yo'q yoki 0 bo'lgan talabalar uchun.
        // Tartib: snapshot (1a) eng birinchi va eng kuchli — YN topshirilganida
        // qulflangan qiymat kanonik. Snapshot null bo'lsa, tirik qiymat bilan
        // to'ldiramiz.
        //
        // JN/MT jurnal sahifasidagi formula bilan AYNAN bir xil hisoblanadi
        // (App\Services\JournalGradeService): har bir dars kuni uchun kunlik
        // o'rtacha (baho qo'yilmagan "NB" kun 0 sifatida), maxraj = jadvaldagi
        // dars kunlari soni. Tekis AVG() ishlatilsa ko'p parali kunlar ortiqcha
        // og'irlik olib, NB kunlar e'tibordan chetda qolardi — talaba noto'g'ri
        // "pullik" yoki noto'g'ri "2-urinish" bo'lib qolardi.
        try {
            $jnMtLive = \App\Services\JournalGradeService::computeJnMtBulk(
                array_values($triples),
                $studentGroup
            );
            foreach ($jnMtLive as $tripleKey => $perStudent) {
                $parts = explode('|', $tripleKey);
                if (count($parts) !== 3) continue;
                [$g, $s, $sem] = $parts;
                foreach ($perStudent as $hid => $vals) {
                    $k = $hid . '|' . $s . '|' . $sem;
                    if (!isset($jnMtMap[$k])) $jnMtMap[$k] = ['jn' => null, 'mt' => null];
                    if ($jnMtMap[$k]['jn'] === null && $vals['jn'] !== null) {
                        $jnMtMap[$k]['jn'] = $vals['jn'];
                    }
                    if ($jnMtMap[$k]['mt'] === null && $vals['mt'] !== null) {
                        $jnMtMap[$k]['mt'] = $vals['mt'];
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Live JN/MT (JournalGradeService) failed: ' . $e->getMessage());
        }

        // 2) OSKI / Test attempt=1 baholari (101, 102, va legacy 103 quiz)
        // Legacy code 103 quiz grades: resolve to OSKI(101) or Test(102) via quiz_result
        $examMap = []; // hemis_id|subj|sem|type => eng oxirgi (yangi) baho
        try {
            $rows = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $allStudentHids)
                ->whereIn('subject_id', $allSubjectIds)
                ->whereIn('semester_code', $allSemCodes)
                ->whereIn('training_type_code', [101, 102, 103])
                ->when($hasAttemptCol, fn($q) => $q->where(fn($qq) => $qq->where('attempt', 1)->orWhereNull('attempt')))
                ->select('student_hemis_id', 'subject_id', 'semester_code', 'training_type_code',
                    'grade', 'retake_grade', 'quiz_result_id', 'reason', 'lesson_date', 'id')
                ->orderBy('lesson_date')
                ->orderBy('id')
                ->get();

            $quizIds = $rows->where('training_type_code', 103)->pluck('quiz_result_id')->filter()->unique()->values()->all();
            $quizTypeMap = [];
            if (!empty($quizIds)) {
                $quizTypeMap = DB::table('hemis_quiz_results')->whereIn('id', $quizIds)->pluck('quiz_type', 'id')->toArray();
            }
            $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
            $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];

            foreach ($rows as $r) {
                // O'quv yili ajratish — dars/imtihon sanasi shu semestr (o'quv
                // reja) boshlanishidan oldin bo'lsa: tiklangan talabaning eski
                // o'qishidagi yozuvi, hisobga olinmaydi.
                $rg = $studentGroup[$r->student_hemis_id] ?? null;
                $rStart = ($rg !== null ? ($yearStartByGroup[(string) $rg] ?? null) : null)
                    ?? $yearStartFallback;
                if ($rStart && $r->lesson_date
                    && substr((string) $r->lesson_date, 0, 10) < substr((string) $rStart, 0, 10)) {
                    continue;
                }
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
                // teacher_victim placeholder (eski/bekor yozuv, baho 0 yoki yo'q)
                // hisobga olinmaydi — aks holda haqiqiy baho bilan o'rtachalanib
                // (yoki uning o'rniga) natijani buzadi. Reinstated talabada eski
                // o'qishidan qolgan 0-placeholder yangi haqiqiy baho bilan
                // qo'shilib OSKI/Test ni xato pasaytirardi.
                if ($r->reason === 'teacher_victim' && $r->retake_grade === null
                    && ($r->grade === null || (float) $r->grade == 0.0)) {
                    continue;
                }
                $effective = $r->retake_grade ?? $r->grade;
                if ($effective === null) continue;
                $k = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code . '|' . $typeCode;
                // Bitta imtihon uchun bir nechta yozuv bo'lsa — o'rtacha emas,
                // ENG OXIRGISI (yangi sana/id) olinadi. Qatorlar lesson_date,id
                // bo'yicha tartiblangan, oxirgisi yozib qoldiriladi.
                $examMap[$k] = (float) $effective;
            }
        } catch (\Throwable $e) {}

        // 2b) OSKI / Test attempt=2 baholari (12a) — failed_attempt2 ni aniqlash uchun.
        // $enrolledAttempt2Map: talaba 2-urinishga (12a ga) kirgan, ya'ni
        // student_grades.attempt=2 yozuvi mavjud (baho bo'sh bo'lsa ham — qo'lda
        // o'tkazilgan). Bu failed2 ni "kelmadi=yiqilgan" deb belgilashdan oldin
        // talaba haqiqatdan 2-urinishga ro'yxatda bo'lganini tekshirish uchun.
        $examMap2 = [];
        $examLists2 = [];
        $enrolledAttempt2Map = []; // hemis|subj|sem => true
        // Guruh sathida 2-urinish (attempt=2) imtihon sanasi — exam_schedules da
        // resit sanasi belgilanmagan, lekin baho yozilgan holatlar uchun fallback.
        // group|subj|sem|typeCode (101/102) => max(lesson_date) YYYY-MM-DD
        $groupAttempt2DateMap = [];
        // Talaba bo'yicha 2-urinish lesson_date — yakka talaba 2-urinishga
        // kirgan (boshqa hech kim 2-urinishda emas) holatlar uchun.
        // hemis|subj|sem|typeCode => max(lesson_date)
        $studentAttempt2DateMap = [];
        try {
            if ($hasAttemptCol) {
                $rows = DB::table('student_grades')
                    ->whereNull('deleted_at')
                    ->whereIn('student_hemis_id', $allStudentHids)
                    ->whereIn('subject_id', $allSubjectIds)
                    ->whereIn('semester_code', $allSemCodes)
                    ->whereIn('training_type_code', [101, 102, 103])
                    ->where('attempt', 2)
                    ->select('student_hemis_id', 'subject_id', 'semester_code', 'training_type_code', 'grade', 'retake_grade', 'quiz_result_id', 'lesson_date')
                    ->get();

                $quizIds2 = $rows->where('training_type_code', 103)->pluck('quiz_result_id')->filter()->unique()->values()->all();
                $quizTypeMap2 = [];
                if (!empty($quizIds2)) {
                    $quizTypeMap2 = DB::table('hemis_quiz_results')->whereIn('id', $quizIds2)->pluck('quiz_type', 'id')->toArray();
                }
                $oskiTypes2 = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
                $testTypes2 = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];

                foreach ($rows as $r) {
                    // O'quv yili ajratish — eski o'qishidagi attempt=2 yozuvi
                    // (eski sana) joriy hisobga kirmaydi.
                    $rg = $studentGroup[$r->student_hemis_id] ?? null;
                    $rStart = ($rg !== null ? ($yearStartByGroup[(string) $rg] ?? null) : null)
                        ?? $yearStartFallback;
                    if ($rStart && $r->lesson_date
                        && substr((string) $r->lesson_date, 0, 10) < substr((string) $rStart, 0, 10)) {
                        continue;
                    }
                    $enrolledAttempt2Map[$r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code] = true;
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

                    // Group/Student-level attempt=2 sanasini yig'amiz (101/102 uchun)
                    if (in_array($typeCode, [101, 102], true) && $r->lesson_date) {
                        $dateStr = is_string($r->lesson_date)
                            ? substr($r->lesson_date, 0, 10)
                            : \Carbon\Carbon::parse($r->lesson_date)->format('Y-m-d');

                        $gHid = $studentGroup[$r->student_hemis_id] ?? null;
                        if ($gHid) {
                            $gKey = $gHid . '|' . $r->subject_id . '|' . $r->semester_code . '|' . $typeCode;
                            if (!isset($groupAttempt2DateMap[$gKey]) || $dateStr > $groupAttempt2DateMap[$gKey]) {
                                $groupAttempt2DateMap[$gKey] = $dateStr;
                            }
                        }

                        // Per-student fallback — yakka talaba 2-urinishga kirgan
                        // bo'lsa, uning o'z lesson_date'i orqali sana topiladi.
                        $sKey = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code . '|' . $typeCode;
                        if (!isset($studentAttempt2DateMap[$sKey]) || $dateStr > $studentAttempt2DateMap[$sKey]) {
                            $studentAttempt2DateMap[$sKey] = $dateStr;
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

        // 2c) OSKI / Test attempt=3 baholari (12b) — jurnaldagi 3-urinish ✓
        // badge'ini YN sahifasida ham "o'tgan" deb tanish uchun kerak.
        $examMap3 = [];
        $examLists3 = [];
        try {
            if ($hasAttemptCol) {
                $rows = DB::table('student_grades')
                    ->whereNull('deleted_at')
                    ->whereIn('student_hemis_id', $allStudentHids)
                    ->whereIn('subject_id', $allSubjectIds)
                    ->whereIn('semester_code', $allSemCodes)
                    ->whereIn('training_type_code', [101, 102, 103])
                    ->where('attempt', 3)
                    ->select('student_hemis_id', 'subject_id', 'semester_code', 'training_type_code', 'grade', 'retake_grade', 'quiz_result_id', 'lesson_date')
                    ->get();

                $quizIds3 = $rows->where('training_type_code', 103)->pluck('quiz_result_id')->filter()->unique()->values()->all();
                $quizTypeMap3 = [];
                if (!empty($quizIds3)) {
                    $quizTypeMap3 = DB::table('hemis_quiz_results')->whereIn('id', $quizIds3)->pluck('quiz_type', 'id')->toArray();
                }
                $oskiTypes3 = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
                $testTypes3 = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];

                foreach ($rows as $r) {
                    $rg = $studentGroup[$r->student_hemis_id] ?? null;
                    $rStart = ($rg !== null ? ($yearStartByGroup[(string) $rg] ?? null) : null)
                        ?? $yearStartFallback;
                    if ($rStart && $r->lesson_date
                        && substr((string) $r->lesson_date, 0, 10) < substr((string) $rStart, 0, 10)) {
                        continue;
                    }
                    $typeCode = (int) $r->training_type_code;
                    if ($typeCode === 103) {
                        if (!$r->quiz_result_id) continue;
                        $quizType = $quizTypeMap3[$r->quiz_result_id] ?? null;
                        if (in_array($quizType, $oskiTypes3, true)) {
                            $typeCode = 101;
                        } elseif (in_array($quizType, $testTypes3, true)) {
                            $typeCode = 102;
                        } else {
                            continue;
                        }
                    }

                    $effective = $r->retake_grade ?? $r->grade;
                    if ($effective === null) continue;
                    $k = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code . '|' . $typeCode;
                    $examLists3[$k][] = (float) $effective;
                }
                foreach ($examLists3 as $k => $list) {
                    $examMap3[$k] = count($list) ? array_sum($list) / count($list) : null;
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

        // 4) Auditoriya soatlari — talabaning O'QUV REJASI bo'yicha.
        // Bitta (subject_id, semester_code) ko'p rejada turli aud_hours ga ega
        // bo'lishi mumkin (28→45, 174→90, 31→105 va h.k.). Avval har reja uchun
        // alohida saqlaymiz, talaba lookup'da o'z students.curriculum_id ni
        // ishlatadi. Reja topilmasa fallback uchun "har qanday" map ham bor.
        $audHoursByCurr = []; // curr_hemis_id|subj|sem => hours
        $audHoursAny = []; // subj|sem => hours (eng birinchi, fallback)
        try {
            $subjectRows = DB::table('curriculum_subjects')
                ->whereIn('subject_id', $allSubjectIds)
                ->whereIn('semester_code', $allSemCodes)
                ->select('curricula_hemis_id', 'subject_id', 'semester_code', 'subject_details', 'total_acload')
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
                $audHoursByCurr[$sr->curricula_hemis_id . '|' . $sr->subject_id . '|' . $sr->semester_code] = $aud;
                $anyKey = $sr->subject_id . '|' . $sr->semester_code;
                if (!isset($audHoursAny[$anyKey])) {
                    $audHoursAny[$anyKey] = $aud;
                }
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
                $oski3 = $examMap3[$hid . '|' . $s . '|' . $sem . '|101'] ?? null;
                $test3 = $examMap3[$hid . '|' . $s . '|' . $sem . '|102'] ?? null;

                $absentOff = $davomatMap[$hid . '|' . $s . '|' . $sem] ?? 0;
                // Auditoriya soatini talabaning o'z o'quv rejasidan olamiz —
                // ko'p reja ichida ($subj, $sem) turli soatga ega bo'lishi
                // mumkin. Rejaga aniq mos kelmasa, fan/semestr bo'yicha
                // birinchi mavjudini fallback ishlatamiz.
                $stuCurr = $studentCurriculum[$hid] ?? null;
                $audHours = 0;
                if ($stuCurr !== null) {
                    $audHours = $audHoursByCurr[$stuCurr . '|' . $s . '|' . $sem] ?? 0;
                }
                if ($audHours <= 0) {
                    $audHours = $audHoursAny[$s . '|' . $sem] ?? 0;
                }
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
                // 2-urinish sanasi manbalari (ustuvorlik tartibida):
                //   1) Guruh sathidagi exam_schedules (admin guruhga sana qo'ygan)
                //   2) Talabaning individual exam_schedules yozuvi (admin
                //      yakka talabaga sana qo'ygan — SAIDMURODOVA kabi yolg'iz holat)
                //   3) Guruhdagi bironta talabaning attempt=2 student_grades lesson_date
                //   4) Talabaning o'z attempt=2 student_grades lesson_date
                // Birinchi topilgani ishlatiladi.
                $oskiResitDate = $naMap[$naKey]['oski_resit_date'] ?? null;
                $testResitDate = $naMap[$naKey]['test_resit_date'] ?? null;
                $perStuKey = $hid . '|' . $s . '|' . $sem;
                if ($oskiResitDate === null) {
                    $oskiResitDate = $perStudentResitMap[$perStuKey]['oski_resit_date'] ?? null;
                }
                if ($testResitDate === null) {
                    $testResitDate = $perStudentResitMap[$perStuKey]['test_resit_date'] ?? null;
                }
                if ($oskiResitDate === null) {
                    $oskiResitDate = $groupAttempt2DateMap[$g . '|' . $s . '|' . $sem . '|101'] ?? null;
                }
                if ($testResitDate === null) {
                    $testResitDate = $groupAttempt2DateMap[$g . '|' . $s . '|' . $sem . '|102'] ?? null;
                }
                if ($oskiResitDate === null) {
                    $oskiResitDate = $studentAttempt2DateMap[$hid . '|' . $s . '|' . $sem . '|101'] ?? null;
                }
                if ($testResitDate === null) {
                    $testResitDate = $studentAttempt2DateMap[$hid . '|' . $s . '|' . $sem . '|102'] ?? null;
                }
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

                // failed2 ni faqat 2-urinishga haqiqatdan kirgan talabalar uchun
                // hisoblaymiz. Aks holda: 1-urinishdan o'tib ketgan talaba 12a ga
                // umuman ro'yxatga olinmagan — 2-urinish sanasi o'tgan + bahosi yo'q
                // bo'lsa ham "kelmadi=yiqilgan" deyish noto'g'ri.
                // 2-urinishga kirgan deb hisoblanadi: 1-urinishdan o'tmagan
                // ($failed1) YOKI student_grades.attempt=2 yozuvi mavjud.
                $enrolledAttempt2 = $failed1
                    || !empty($enrolledAttempt2Map[$hid . '|' . $s . '|' . $sem]);
                if ($enrolledAttempt2) {
                    $oski2Num = $oski2 !== null ? (float) $oski2 : null;
                    $test2Num = $test2 !== null ? (float) $test2 : null;
                    $oskiFailed2 = $confirmFailed($oskiRequired, $oski2Num, $oskiResitDate);
                    $testFailed2 = $confirmFailed($testRequired, $test2Num, $testResitDate);
                    $failed2 = $isPullik || $oskiFailed2 || $testFailed2;
                } else {
                    $failed2 = false;
                }

                // Talaba fanni AMALDA o'tgan bo'lsa — qarz EMAS. OSKI bir
                // urinishda, Test boshqa urinishda o'tilgan bo'lishi mumkin —
                // urinishlar BIRLASHTIRILADI (jurnal stage-aniqlashi kabi).
                // Aks holda OSKI ni 1-urinishda, Test ni 2-urinishda o'tgan
                // talaba xato 2/3-urinishga (qarzga) tushib qolardi.
                $oskiBest = max(
                    $oskiNum !== null ? $oskiNum : -1.0,
                    (isset($oski2Num) && $oski2Num !== null) ? $oski2Num : -1.0,
                    $oski3 !== null ? (float) $oski3 : -1.0
                );
                $testBest = max(
                    $testNum !== null ? $testNum : -1.0,
                    (isset($test2Num) && $test2Num !== null) ? $test2Num : -1.0,
                    $test3 !== null ? (float) $test3 : -1.0
                );
                $oskiOk = !$oskiRequired || $oskiBest >= $minLimit;
                $testOk = !$testRequired || $testBest >= $minLimit;
                $fullyPassed = !$isPullik && $oskiOk && $testOk;

                // Jurnaldagi badge bilan sinxron "o'tgan" aniqlash: 1/2/3-urinish
                // stsenariylarini quramiz va ✓ badge bo'ladigan stage'larni
                // YN sahifasida ham "imtihondan o'tgan" deb hisoblaymiz.
                $weights = match (true) {
                    $oskiRequired && $testRequired => ['jn' => 50, 'mt' => 20, 'on' => 0, 'oski' => 15, 'test' => 15],
                    $oskiRequired => ['jn' => 50, 'mt' => 20, 'on' => 0, 'oski' => 30, 'test' => 0],
                    $testRequired => ['jn' => 50, 'mt' => 20, 'on' => 0, 'oski' => 0, 'test' => 30],
                    default => ['jn' => 80, 'mt' => 20, 'on' => 0, 'oski' => 0, 'test' => 0],
                };
                $svc = \App\Services\YnAttemptStatusService::class;
                $jnForStage = (int) ($jn ?? 0);
                $mtForStage = (int) ($mt ?? 0);
                $levelCode = $semLevelMap[$sem] ?? '';
                $mainScenario = $svc::buildScenario(
                    $jnForStage, $mtForStage, null, $oskiNum, $testNum, $davomatPct,
                    $weights['jn'], $weights['mt'], $weights['on'], $weights['oski'], $weights['test'], $levelCode
                );
                $aScenario = ($oski2 !== null || $test2 !== null)
                    ? $svc::buildScenario(
                        $jnForStage, $mtForStage, null,
                        $oski2 !== null ? (float) $oski2 : $oskiNum,
                        $test2 !== null ? (float) $test2 : $testNum,
                        $davomatPct,
                        $weights['jn'], $weights['mt'], $weights['on'], $weights['oski'], $weights['test'], $levelCode
                    )
                    : null;
                $bScenario = ($oski3 !== null || $test3 !== null)
                    ? $svc::buildScenario(
                        $jnForStage, $mtForStage, null,
                        $oski3 !== null ? (float) $oski3 : ($oski2 !== null ? (float) $oski2 : $oskiNum),
                        $test3 !== null ? (float) $test3 : ($test2 !== null ? (float) $test2 : $testNum),
                        $davomatPct,
                        $weights['jn'], $weights['mt'], $weights['on'], $weights['oski'], $weights['test'], $levelCode
                    )
                    : null;
                $stageKey = $svc::determineStage($mainScenario, null, $aScenario, null, $bScenario, null)['stage'];
                $oneUrinishEnded = (!$oskiRequired || ($oskiDate !== null && (string) $oskiDate < $today))
                    && (!$testRequired || ($testDate !== null && (string) $testDate < $today));
                $isDavomatFail = ($mainScenario['v'] ?? null) === -3;
                if (
                    !$oneUrinishEnded
                    && !$isDavomatFail
                    && !in_array($stageKey, [$svc::STAGE_ASOSIY_PASSED, $svc::STAGE_QOSHIMCHA_PASSED], true)
                ) {
                    $stageKey = $svc::STAGE_IN_PROGRESS;
                }
                $oskiResitDone = !$oskiRequired || ($oskiResitDate !== null && (string) $oskiResitDate <= $today);
                $testResitDone = !$testRequired || ($testResitDate !== null && (string) $testResitDate <= $today);
                $twoUrinishEnded = $oskiResitDone && $testResitDone;
                $hasAttempt2Stage = $aScenario !== null;
                if ($hasAttempt2Stage || $twoUrinishEnded) {
                    if ($stageKey === $svc::STAGE_IN_12A) {
                        $stageKey = $svc::STAGE_IN_12B;
                    } elseif ($stageKey === $svc::STAGE_IN_12A_PULLIK) {
                        $stageKey = $svc::STAGE_IN_12B_PULLIK;
                    }
                }
                $badgePassed = in_array($stageKey, [
                    $svc::STAGE_ASOSIY_PASSED,
                    $svc::STAGE_QOSHIMCHA_PASSED,
                    $svc::STAGE_12A_PASSED,
                    $svc::STAGE_12A_QOSHIMCHA_PASSED,
                    $svc::STAGE_12B_PASSED,
                    $svc::STAGE_12B_QOSHIMCHA_PASSED,
                ], true);
                $passedByBadge = $fullyPassed || $badgePassed;
                if ($passedByBadge) {
                    $failed1 = false;
                    $failed2 = false;
                }

                $key = $g . '|' . $s . '|' . $sem;
                if (!isset($result[$key])) $result[$key] = [];
                $result[$key][$hid] = [
                    'failed1' => $failed1,
                    'failed2' => $failed2,
                    'pullik' => $isPullik,
                    'held_back' => false,
                    'passed' => $passedByBadge,
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

        // Topshirgan (quiz) statistikasi sahifa yuklanganda hisoblanmaydi —
        // hemis_quiz_results bo'yicha guruh+fan+shakl bo'yicha agregat
        // serverni og'irlashtiradi. "Yangilash" tugmasi orqali refreshQuizCounts
        // AJAX endpoint chaqirilganda dynamic ravishda yuklanadi.
        $quizCounts = [];

        // 2- va 3-urinishlar uchun haqiqiy qayta topshiruvchi talabalar soni
        // (butun guruh emas). 1-urinish uchun butun guruh hisoblanadi.
        // Scope qilingan: faqat sahifada chiqqan guruh/fan/semestrlar uchun
        // hisoblanadi. Lookup kalitlari aynan shulardan tashkil topgani uchun
        // hech qanday qator tushib qolmaydi - faqat keraksiz so'rovlar
        // (unscoped 8 ta SQL bilan butun student_grades/yn_submissions skani)
        // qilinmaydi. Bu test-center sahifasini bir necha barobar tezlashtiradi.
        // computeAttemptNeedsMap faqat 2-3 urinish (resit) itemlari uchun ishlatiladi.
        // Agar sahifada faqat attempt=1 (asosiy OSKI/Test) qatorlar bo'lsa,
        // bu funksiyani umuman chaqirmaymiz — 8 ta student_grades so'rovi
        // tushib qoladi va sahifa darhol ochiladi. Resit kuni bo'lsa, faqat
        // shu (group, subject, semester) triplelari uchungina hisoblaymiz.
        $attemptNeedsMap = [];
        $resitItems = $transformedData->filter(fn($it) => ((int) ($it['attempt'] ?? 1)) >= 2);
        if ($resitItems->isNotEmpty()) {
            $resitGroupHids = $resitItems->pluck('group')->pluck('group_hemis_id')->unique()->filter()->values()->toArray();
            $resitSubjectIds = $resitItems->pluck('subject')->pluck('subject_id')->unique()->filter()->values()->toArray();
            $resitSemCodes = $resitItems->pluck('subject')->pluck('semester_code')->unique()->filter()->values()->toArray();
            $attemptNeedsMap = $this->computeAttemptNeedsMap($resitGroupHids, $resitSubjectIds, $resitSemCodes)['needs'];
        }

        $transformedData = $transformedData->map(function ($item) use ($studentCounts, $quizCounts, $ynSubmissions, $attemptNeedsMap) {
            $attempt = (int) ($item['attempt'] ?? 1);
            $groupHid = $item['group']->group_hemis_id;
            $subjectId = $item['subject']->subject_id ?? '';
            $semCode = $item['subject']->semester_code ?? '';

            if ($attempt === 1) {
                $item['student_count'] = $studentCounts[$groupHid] ?? 0;
            } elseif (isset($item['students']) && is_array($item['students'])) {
                // attachStudentsToSchedule attempt-bo'yicha aniq belgi qo'yadi
                // (failed_attempt1/2 — didNotAttend + V<60 + explicit attempt yozuvi).
                // filterStudentsForAttempt fallback'i bo'sh ro'yxatda butun guruhni
                // qaytarib yuborishi mumkin — count uchun bayroqni qat'iy
                // tekshiramiz, shunda noaniq holatlar 0 chiqadi.
                $flag = $attempt === 2 ? 'failed_attempt1' : 'failed_attempt2';
                $cnt = 0;
                foreach ($item['students'] as $s) {
                    if (!empty($s[$flag])) {
                        $cnt++;
                    }
                }
                // Agar bayroq topilmasa-yu, lekin needsByKey'da yozuv bo'lsa,
                // shu lookup'ni fallback sifatida ishlatamiz.
                if ($cnt === 0) {
                    $needsKey = $groupHid . '|' . $subjectId . '|' . $semCode . '|' . $attempt;
                    $cnt = (int) ($attemptNeedsMap[$needsKey] ?? 0);
                }
                $item['student_count'] = $cnt;
            } else {
                $needsKey = $groupHid . '|' . $subjectId . '|' . $semCode . '|' . $attempt;
                $item['student_count'] = (int) ($attemptNeedsMap[$needsKey] ?? 0);
            }

            $quizKey = $item['group']->group_hemis_id . '|' . ($item['subject']->subject_id ?? '') . '|' . ($item['yn_type'] ?? '') . '|' . $attempt;
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

        // Kompyuter raqami filtri: 'missing' (qo'yilmagan), 'assigned' (qo'yilgan)
        // yoki bo'sh (barchasi). computer_assignments'da computer_number IS NOT NULL
        // bo'lgan qatorlar soni (exam_schedule_id, yn_type, attempt) bo'yicha
        // student_count bilan solishtiriladi.
        $compFilter = $request->get('comp_filter');
        if (in_array($compFilter, ['missing', 'assigned'], true)) {
            $scheduleIdsForComp = $transformedData
                ->pluck('schedule_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
            $compAssignedMap = []; // "schedule_id|yn|attempt" => assigned_count
            if (!empty($scheduleIdsForComp)) {
                try {
                    $rows = DB::table('computer_assignments')
                        ->whereIn('exam_schedule_id', $scheduleIdsForComp)
                        ->whereNotNull('computer_number')
                        ->groupBy('exam_schedule_id', 'yn_type', 'attempt')
                        ->select('exam_schedule_id', 'yn_type', 'attempt', DB::raw('COUNT(*) as cnt'))
                        ->get();
                    foreach ($rows as $r) {
                        $k = $r->exam_schedule_id . '|' . strtolower((string) $r->yn_type) . '|' . (int) $r->attempt;
                        $compAssignedMap[$k] = (int) $r->cnt;
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('buildTestCenterData: comp_filter hisobi xatolik berdi: ' . $e->getMessage());
                }
            }
            $transformedData = $transformedData->filter(function ($item) use ($compFilter, $compAssignedMap) {
                $sid = $item['schedule_id'] ?? null;
                if (!$sid) {
                    return $compFilter === 'missing';
                }
                $yn = strtolower((string) ($item['yn_type'] ?? ''));
                $att = (int) ($item['attempt'] ?? 1);
                $assigned = (int) ($compAssignedMap[$sid . '|' . $yn . '|' . $att] ?? 0);
                $required = (int) ($item['student_count'] ?? 0);
                if ($compFilter === 'missing') {
                    return $assigned < $required;
                }
                return $required > 0 && $assigned >= $required;
            })->values();
        }

        // Individual (per-student) exam_schedules yozuvlarini qo'shish.
        // YN kunini belgilash tabidagi standart guruh yozuvlari yuqorida tuzildi
        // (student_hemis_id IS NULL). Bu yerda esa Individual imtihon sanasi
        // tabida belgilangan talabaning shaxsiy yozuvlari (student_hemis_id NOT NULL)
        // sana oralig'iga mos kelganlari alohida rowlar sifatida qo'shiladi.
        // semesterCodes loadScheduleData lokal o'zgaruvchisi — bu yerda qayta hisoblaymiz.
        $localSemesterCodes = collect();
        if ($selectedSemester) {
            $localSemesterCodes = collect([$selectedSemester]);
        } elseif (($currentSemesterToggle ?? '1') === '1') {
            $localSemesterCodes = $currentSemesters->pluck('code')->unique();
        }
        $individualItems = $this->buildIndividualScheduleItems(
            $dateFrom, $dateTo, $urinishFilter,
            $selectedGroup, $selectedSubject, $localSemesterCodes
        );
        foreach ($individualItems as $indItem) {
            $transformedData->push($indItem);
        }

        $scheduleData = $transformedData->groupBy(fn($item) => $item['group']->group_hemis_id);

        return [
            'scheduleData' => $scheduleData,
            'currentEducationYear' => $currentEducationYear,
            'urinishFilter' => $urinishFilter,
            'showStudents' => $showStudents,
            'compFilter' => $compFilter,
        ];
    }

    /**
     * Sana oralig'idagi individual imtihon yozuvlarini test-center jadvalidagi
     * itemga aylantirish. Har bir yozuv N ta urinish × YN turi qatori beradi.
     * Natija — `is_individual_student=true` belgisi bilan, ko'rinishda alohida
     * fon va talaba ismi bilan render qilinadi.
     *
     * @return \Illuminate\Support\Collection
     */
    private function buildIndividualScheduleItems(
        $dateFrom, $dateTo, $urinishFilter,
        $selectedGroup, $selectedSubject, $semesterCodes
    ) {
        $items = collect();

        $query = ExamSchedule::whereNotNull('student_hemis_id');
        if ($selectedGroup) {
            $query->where('group_hemis_id', $selectedGroup);
        }
        if ($selectedSubject) {
            $query->where('subject_id', $selectedSubject);
        }
        if ($semesterCodes && $semesterCodes->isNotEmpty()) {
            $query->whereIn('semester_code', $semesterCodes->all());
        }
        $dateCols = ['oski_date', 'test_date', 'oski_resit_date', 'test_resit_date',
                     'oski_resit2_date', 'test_resit2_date'];
        $query->where(function ($outer) use ($dateCols, $dateFrom, $dateTo) {
            foreach ($dateCols as $col) {
                $outer->orWhere(function ($qq) use ($col, $dateFrom, $dateTo) {
                    $qq->whereNotNull($col);
                    if ($dateFrom) $qq->where($col, '>=', $dateFrom);
                    if ($dateTo) $qq->where($col, '<=', $dateTo);
                });
            }
        });
        $individualRows = $query->get();
        if ($individualRows->isEmpty()) {
            return $items;
        }

        // Tegishli talabalarni yuklash
        $hemisIds = $individualRows->pluck('student_hemis_id')->unique()->values()->all();
        $students = Student::whereIn('hemis_id', $hemisIds)->get()->keyBy('hemis_id');

        // Tegishli guruhlarni yuklash
        $groupHids = $individualRows->pluck('group_hemis_id')->unique()->values()->all();
        $groups = Group::whereIn('group_hemis_id', $groupHids)->get()->keyBy('group_hemis_id');

        // Tegishli fanlarni yuklash (CurriculumSubject)
        $subjectIds = $individualRows->pluck('subject_id')->unique()->values()->all();
        $semCodes = $individualRows->pluck('semester_code')->unique()->values()->all();
        $subjects = CurriculumSubject::whereIn('subject_id', $subjectIds)
            ->whereIn('semester_code', $semCodes)
            ->get()
            ->keyBy(fn($s) => $s->subject_id . '|' . $s->semester_code);

        // Semester level/name xaritasi
        $semesterRows = Semester::whereIn('code', $semCodes)->get();
        $semesterByCode = $semesterRows->keyBy('code');

        foreach ($individualRows as $row) {
            $student = $students->get($row->student_hemis_id);
            $group = $groups->get($row->group_hemis_id);
            $subject = $subjects->get($row->subject_id . '|' . $row->semester_code);
            $sem = $semesterByCode->get($row->semester_code);
            if (!$student || !$group || !$subject) {
                continue;
            }

            $attemptDefs = [
                ['yn_type' => 'OSKI', 'attempt' => 1, 'date' => $row->oski_date,        'time' => $row->oski_time],
                ['yn_type' => 'OSKI', 'attempt' => 2, 'date' => $row->oski_resit_date,  'time' => $row->oski_resit_time],
                ['yn_type' => 'OSKI', 'attempt' => 3, 'date' => $row->oski_resit2_date, 'time' => $row->oski_resit2_time],
                ['yn_type' => 'Test', 'attempt' => 1, 'date' => $row->test_date,        'time' => $row->test_time],
                ['yn_type' => 'Test', 'attempt' => 2, 'date' => $row->test_resit_date,  'time' => $row->test_resit_time],
                ['yn_type' => 'Test', 'attempt' => 3, 'date' => $row->test_resit2_date, 'time' => $row->test_resit2_time],
            ];

            foreach ($attemptDefs as $def) {
                if ($urinishFilter !== null && $urinishFilter !== '' && (int) $urinishFilter !== $def['attempt']) {
                    continue;
                }
                $d = $def['date'] ? \Carbon\Carbon::parse($def['date'])->format('Y-m-d') : null;
                $inRange = $d && (!$dateFrom || $d >= $dateFrom) && (!$dateTo || $d <= $dateTo);
                if (!$inRange) {
                    continue;
                }

                $items->push([
                    'is_individual_student' => true,
                    'individual_student'    => $student,
                    'group'                 => $group,
                    'subject'               => $subject,
                    'specialty_name'        => $group->specialty_name ?? '',
                    'subject_code'          => $subject->subject_id ?? '',
                    'level_name'            => $sem?->level_name ?? '',
                    'semester_name'         => $subject->semester_name ?? ($sem?->name ?? ''),
                    'education_form_name'   => '',
                    'schedule_id'           => $row->id,
                    'yn_type'               => $def['yn_type'],
                    'attempt'               => $def['attempt'],
                    'yn_date'               => $d,
                    'yn_date_carbon'        => $def['date'] ? \Carbon\Carbon::parse($def['date']) : null,
                    'yn_na'                 => false,
                    'test_time'             => $def['time'],
                    'moodle_status'         => 'na',
                    'student_count'         => 1,
                    'quiz_count'            => 0,
                    'yn_submitted'          => false,
                    'individual_note'       => $row->individual_note ?? null,
                    'override_warning'      => (bool) ($row->override_warning ?? false),
                ]);
            }
        }

        return $items;
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
     * Return the count of BookMoodleGroupExam jobs still pending in the
     * queue, plus how many failed in the last hour. Polled by the test
     * centre UI to show "Moodle push: X / Y" progress after a bulk recheck
     * dispatch — the user no longer has to manually refresh and wait.
     */
    public function bulkRecheckMoodleStatus(Request $request)
    {
        $pending = DB::table('jobs')
            ->where('payload', 'LIKE', '%BookMoodleGroupExam%')
            ->count();

        $failedRecent = DB::table('failed_jobs')
            ->where('payload', 'LIKE', '%BookMoodleGroupExam%')
            ->where('failed_at', '>=', now()->subHour())
            ->count();

        return response()->json([
            'pending'        => $pending,
            'failed_recent'  => $failedRecent,
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
        // Heavy data is built only after "Qidirish" — without filters the
        // controller used to scan the whole curriculum × group × subject
        // matrix and timed out at the nginx upstream (504).
        $isSearched = $request->has('searched');
        $routePrefix = $this->routePrefix();

        $urinishFilter = $request->get('urinish');
        $showStudents = $request->get('show_students') === '1';
        $compFilter = $request->get('comp_filter');

        $scheduleData = collect();
        $currentEducationYear = Semester::where('current', true)->value('education_year');

        if ($isSearched) {
            try {
                $result = $this->buildTestCenterData($request);
                $scheduleData = $result['scheduleData'];
                $currentEducationYear = $result['currentEducationYear'] ?? $currentEducationYear;
                $urinishFilter = $result['urinishFilter'] ?? $urinishFilter;
                $showStudents = $result['showStudents'] ?? $showStudents;
                $compFilter = $result['compFilter'] ?? $compFilter;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('testCenterView xatolik: ' . $e->getMessage(), [
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $allow4PlusDebtorsRetake = ExamDateRoleService::allow4PlusDebtorsRetake();

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
                'compFilter',
                'isSearched',
                'currentEducationYear',
                'routePrefix',
                'readOnly',
                'isTestCenter',
                'allow4PlusDebtorsRetake',
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

        // Quiz natijalar soni — urinish (shakl) bo'yicha alohida
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
                    ->groupBy('st.group_id', 'hqr.fan_id', 'hqr.quiz_type', 'hqr.shakl')
                    ->select('st.group_id', 'hqr.fan_id', 'hqr.quiz_type', 'hqr.shakl', DB::raw('COUNT(DISTINCT hqr.student_id) as cnt'))
                    ->get();

                foreach ($quizRows as $row) {
                    if (in_array($row->quiz_type, $testTypes)) {
                        $ynType = 'Test';
                    } elseif (in_array($row->quiz_type, $oskiTypes)) {
                        $ynType = 'OSKI';
                    } else {
                        continue;
                    }
                    $shakl = strtolower(trim((string) ($row->shakl ?? '')));
                    $att = match ($shakl) {
                        '2-urinish' => 2,
                        '3-urinish' => 3,
                        default => 1,
                    };
                    $key = $row->group_id . '|' . $row->fan_id . '|' . $ynType . '|' . $att;
                    $quizCounts[$key] = ($quizCounts[$key] ?? 0) + $row->cnt;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('refreshQuizCounts: hemis_quiz_results so\'rovi xatolik berdi: ' . $e->getMessage());
            }
        }

        // 2-/3-urinishlar uchun haqiqiy qayta topshiruvchi talabalar
        // (scope: request kelgan items dagi guruh/fan/semestrlar bilan cheklanadi)
        $semCodes = collect($items)->pluck('semester_code')->unique()->filter()->values()->toArray();
        $attemptNeedsMap = $this->computeAttemptNeedsMap($groupHemisIds, $subjectIds, $semCodes)['needs'];

        $result = [];
        foreach ($items as $item) {
            $attempt = (int) ($item['attempt'] ?? 1);
            $gid = $item['group_id'];
            $sid = $item['subject_id'];
            $yn = $item['yn_type'];
            $key = $gid . '|' . $sid . '|' . $yn . '|' . $attempt;
            $qc = $quizCounts[$key] ?? 0;
            if ($attempt === 1) {
                $sc = $studentCounts[$gid] ?? 0;
            } else {
                // semester_code refreshQuizCounts requestida kelmaydi —
                // shuning uchun needsMap'dan shu group+subject uchun
                // mavjud bo'lgan eng yuqori qiymatni olamiz (bir guruh+fan
                // odatda bitta semestrga tegishli).
                $sc = 0;
                $prefix = $gid . '|' . $sid . '|';
                foreach ($attemptNeedsMap as $k => $v) {
                    if (str_starts_with($k, $prefix) && str_ends_with($k, '|' . $attempt)) {
                        $sc = max($sc, (int) $v);
                    }
                }
            }
            $result[] = [
                'group_id' => $gid,
                'subject_id' => $sid,
                'yn_type' => $yn,
                'attempt' => $attempt,
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
        $currentSemesterCodes = $currentSemesters->pluck('code')->filter()->unique()->values();

        // Semester filter closure
        $semesterFilter = function ($query) use ($currentSemesterOnly, $currentEducationYear, $currentSemesterCodes) {
            if ($currentSemesterOnly) {
                if ($currentEducationYear) {
                    $query->where('education_year', $currentEducationYear);
                }
                if ($currentSemesterCodes->isNotEmpty()) {
                    $query->whereIn('code', $currentSemesterCodes);
                } else {
                    $query->where('current', true);
                }
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

        // HEMIS data drift: bitta yo'nalish nomi (masalan "Xalq tabobati") uchun bir
        // necha specialty_hemis_id mavjud bo'lishi mumkin (49 va 15). Curriculum jadvali
        // bir variantni biladi, groups jadvali boshqasini. Filtrlashda barcha variantlar
        // bo'yicha whereIn ishlatish kerak, aks holda guruhlar yo'qoladi.
        $selectedSpecialtyIds = null;
        if ($selectedSpecialty) {
            $specName = Specialty::where('specialty_hemis_id', $selectedSpecialty)->value('name');
            if ($specName) {
                $idsFromSpecTable = Specialty::where('name', $specName)
                    ->pluck('specialty_hemis_id')->toArray();
                $idsFromGroups = Group::where('specialty_name', $specName)
                    ->distinct()->pluck('specialty_hemis_id')->toArray();
                $idsFromCurricula = Curriculum::whereIn('specialty_hemis_id',
                    array_merge($idsFromSpecTable, $idsFromGroups, [$selectedSpecialty]))
                    ->distinct()->pluck('specialty_hemis_id')->toArray();
                $selectedSpecialtyIds = array_values(array_unique(array_filter(array_merge(
                    [$selectedSpecialty], $idsFromSpecTable, $idsFromGroups, $idsFromCurricula
                ))));
            } else {
                $selectedSpecialtyIds = [$selectedSpecialty];
            }
        }

        // O'quv rejalarini olish
        $curriculumQuery = Curriculum::whereIn('curricula_hemis_id', function ($sub) use ($currentSemesterOnly, $currentEducationYear, $currentSemesterCodes) {
            $sub->select('curriculum_hemis_id')->from('semesters');
            if ($currentSemesterOnly) {
                if ($currentEducationYear) {
                    $sub->where('education_year', $currentEducationYear);
                }
                if ($currentSemesterCodes->isNotEmpty()) {
                    $sub->whereIn('code', $currentSemesterCodes);
                } else {
                    $sub->where('current', true);
                }
            } else {
                $sub->where('education_year', $currentEducationYear);
            }
        });
        if ($selectedDepartment) $curriculumQuery->where('department_hemis_id', $selectedDepartment);
        if ($selectedSpecialtyIds) $curriculumQuery->whereIn('specialty_hemis_id', $selectedSpecialtyIds);
        if ($selectedEducationType) $curriculumQuery->where('education_type_code', $selectedEducationType);
        // Kurs/semestr tanlangan bo'lsa — faqat o'sha level/code joriy bo'lgan curriculum'larni qoldirish.
        // Aks holda d2/20 (hozir 6-kurs) kabi eski rejalar 5-kurs filtridan ham chiqib qolyapti
        // (chunki ularda istalgan boshqa current semester bor).
        if ($selectedLevelCode || $selectedSemester) {
            $curriculumQuery->whereIn('curricula_hemis_id', function ($sub) use ($currentSemesterOnly, $currentEducationYear, $currentSemesterCodes, $selectedLevelCode, $selectedSemester) {
                $sub->select('curriculum_hemis_id')->from('semesters');
                if ($currentSemesterOnly) {
                    if ($currentEducationYear) {
                        $sub->where('education_year', $currentEducationYear);
                    }
                    if ($currentSemesterCodes->isNotEmpty()) {
                        $sub->whereIn('code', $currentSemesterCodes);
                    } else {
                        $sub->where('current', true);
                    }
                } else {
                    $sub->where('education_year', $currentEducationYear);
                }
                if ($selectedLevelCode) $sub->where('level_code', $selectedLevelCode);
                if ($selectedSemester) $sub->where('code', $selectedSemester);
            });
        }
        $curriculumIds = $curriculumQuery->pluck('curricula_hemis_id');

        if ($curriculumIds->isEmpty()) return collect();

        // YN sanasi filtri qo'llanilganda — avval exam_schedules dan kichik
        // to'plamni topib, qolgan barcha so'rovlarni (subjects/groups/schedules
        // jurnalidan dars sanalari) shu to'plamdagi guruh/fan/semestrlar bilan
        // cheklaymiz. Aks holda 500+ guruh × 30+ fan kombinatsiyalari uchun
        // og'ir queries ishlatiladi va schedules jadvali (millionlab qator)
        // bo'yicha lessonDatesRaw GROUP BY 504 beradi.
        $dateRestricted = $filterByYnDate && ($dateFrom || $dateTo);
        $preFilteredKeys = null;          // group_hid|subject_id|semester_code => true
        $preFilteredGroupHids = null;
        $preFilteredSubjectIds = null;
        $preFilteredSemCodes = null;
        if ($dateRestricted) {
            $dateCols = ['oski_date', 'test_date', 'oski_resit_date', 'test_resit_date',
                         'oski_resit2_date', 'test_resit2_date'];
            $preQuery = DB::table('exam_schedules')->whereNull('student_hemis_id');
            if ($selectedDepartment) $preQuery->where('department_hemis_id', $selectedDepartment);
            if ($selectedSpecialtyIds) $preQuery->whereIn('specialty_hemis_id', $selectedSpecialtyIds);
            if ($selectedGroup) $preQuery->where('group_hemis_id', $selectedGroup);
            if ($selectedSubject) $preQuery->where('subject_id', $selectedSubject);
            if ($semesterCodes->isNotEmpty()) $preQuery->whereIn('semester_code', $semesterCodes);
            $preQuery->where(function ($outer) use ($dateCols, $dateFrom, $dateTo) {
                foreach ($dateCols as $col) {
                    $outer->orWhere(function ($qq) use ($col, $dateFrom, $dateTo) {
                        $qq->whereNotNull($col);
                        if ($dateFrom) $qq->where($col, '>=', $dateFrom);
                        if ($dateTo) $qq->where($col, '<=', $dateTo);
                    });
                }
            });
            $preRows = $preQuery->select('group_hemis_id', 'subject_id', 'semester_code')->get();
            if ($preRows->isEmpty()) return collect();
            $preFilteredKeys = [];
            $gids = []; $sids = []; $secs = [];
            foreach ($preRows as $r) {
                $preFilteredKeys[$r->group_hemis_id . '_' . $r->subject_id . '_' . $r->semester_code] = true;
                $gids[$r->group_hemis_id] = true;
                $sids[$r->subject_id] = true;
                $secs[$r->semester_code] = true;
            }
            $preFilteredGroupHids = array_keys($gids);
            $preFilteredSubjectIds = array_keys($sids);
            $preFilteredSemCodes = array_keys($secs);
        }

        // Fanlar
        $subjectQuery = CurriculumSubject::whereIn('curricula_hemis_id', $curriculumIds)
            ->where('is_active', true);
        if ($preFilteredSubjectIds !== null) {
            $subjectQuery->whereIn('subject_id', $preFilteredSubjectIds);
        }
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
        if ($selectedSpecialtyIds) $groupQuery->whereIn('specialty_hemis_id', $selectedSpecialtyIds);
        if ($selectedGroup) $groupQuery->where('group_hemis_id', $selectedGroup);
        if ($preFilteredGroupHids !== null) {
            $groupQuery->whereIn('group_hemis_id', $preFilteredGroupHids);
        }
        $filteredGroups = $groupQuery->orderBy('name')->get();

        if ($filteredGroups->isEmpty()) return collect();

        // semester_code → level_code map (kurs darajasini aniqlash uchun)
        $semesterLevelMap = $semesterFilter(Semester::query())
            ->whereIn('curriculum_hemis_id', $curriculumIds)
            ->select('code', 'level_code', 'curriculum_hemis_id')
            ->get()
            ->mapWithKeys(fn($s) => [$s->curriculum_hemis_id . '_' . $s->code => (string) $s->level_code]);

        // Mavjud jadvallar — faqat guruh sathidagi yozuvlar (student_hemis_id NULL).
        // Per-student (individual grafik) yozuvlari $perStudentMap orqali alohida
        // yuklanadi. Bu yerda ham per-student qatorlar olinsa, keyBy ularni guruh
        // qatori ustiga yozib yuboradi va sahifada noto'g'ri sana ko'rsatadi.
        $scheduleQuery = ExamSchedule::query()->whereNull('student_hemis_id');
        if ($selectedDepartment) $scheduleQuery->where('department_hemis_id', $selectedDepartment);
        if ($selectedSpecialtyIds) $scheduleQuery->whereIn('specialty_hemis_id', $selectedSpecialtyIds);
        if ($selectedGroup) $scheduleQuery->where('group_hemis_id', $selectedGroup);
        if ($semesterCodes->isNotEmpty()) $scheduleQuery->whereIn('semester_code', $semesterCodes);
        // Date filter qo'llanilsa — preFilteredKeys orqali kichik to'plamga
        // cheklaymiz (full to'plam yuqorida pre-query qilingan).
        if ($preFilteredGroupHids !== null) {
            $scheduleQuery->whereIn('group_hemis_id', $preFilteredGroupHids);
        }
        if ($preFilteredSubjectIds !== null) {
            $scheduleQuery->whereIn('subject_id', $preFilteredSubjectIds);
        }
        if ($preFilteredSemCodes !== null) {
            $scheduleQuery->whereIn('semester_code', $preFilteredSemCodes);
        }
        $existingSchedules = $scheduleQuery->get()
            ->keyBy(fn($item) => $item->group_hemis_id . '_' . $item->subject_id . '_' . $item->semester_code);

        // Sana filtri qo'llanilsa — outer loopni faqat tegishli (group, subject,
        // semester) triplelar bilan cheklaymiz.
        $dateFilteredKeys = $preFilteredKeys;

        // Dars jadvalidan boshlanish/tugash sanalarini olish (schedules jadvalidan).
        // Date filter faolligida — faqat tegishli guruh+fan kombinatsiyalarini
        // so'raymiz, aks holda 500+ guruhlik full scan minutes ketadi.
        $lessonDatesQuery = DB::table('schedules')
            ->select('group_id', 'subject_id', 'subject_name', DB::raw('MIN(lesson_date) as lesson_start'), DB::raw('MAX(lesson_date) as lesson_end'))
            ->whereIn('group_id', $filteredGroups->pluck('group_hemis_id'))
            ->whereNull('deleted_at');
        if ($preFilteredSubjectIds !== null) {
            // schedules.subject_id curriculum_subject_hemis_id YOKI HEMIS subject_id bo'lishi
            // mumkin — ikkalasini ham qabul qilamiz.
            $curriculumSubjHids = $subjects->pluck('curriculum_subject_hemis_id')->filter()->all();
            $allowedSubjIds = array_values(array_unique(array_merge(
                $preFilteredSubjectIds,
                array_values($curriculumSubjHids)
            )));
            if (!empty($allowedSubjIds)) {
                $lessonDatesQuery->whereIn('subject_id', $allowedSubjIds);
            }
        }
        $lessonDatesRaw = $lessonDatesQuery
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
                // Sana filtri faolligida — exam_schedules da yo'q triplelarni
                // o'tkazib yuboramiz. Aks holda bo'sh placeholder qator yaratilib
                // pastda PHP filtridan ko'tarilardi (16k+ qator vs ~50 qator).
                if ($dateFilteredKeys !== null && !isset($dateFilteredKeys[$key])) {
                    continue;
                }
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

        // PHP max_input_vars limiti tufayli forma maydonlari kesilgan bo'lishi
        // mumkin - bu holda oxirgi qatorlarning hidden fieldlari (jumladan
        // group_hemis_id, subject_id) tushib qoladi va validSchedules filtri
        // ularni jimgina o'tkazib yuboradi. Buni aniqlash uchun received vs
        // expected ni solishtiramiz.
        $receivedRowCount = count($schedules);
        $expectedRowCount = (int) $request->input('_form_total_rows', $receivedRowCount);
        $maxInputVars = (int) ini_get('max_input_vars');

        // Faqat to'liq ma'lumotga ega elementlarni filtrlash
        $validSchedules = [];
        $incompleteRowCount = 0;
        foreach ($schedules as $key => $schedule) {
            if (!empty($schedule['group_hemis_id']) && !empty($schedule['subject_id']) && !empty($schedule['semester_code'])) {
                $validSchedules[$key] = $schedule;
            } else {
                $incompleteRowCount++;
            }
        }

        if (empty($validSchedules)) {
            return redirect()->back()->with('error', 'Ma\'lumotlar to\'liq emas. Sahifani yangilab, qaytadan urinib ko\'ring.');
        }

        // Kesilganni aniqlash: forma yuborgan row soni kutilgandan kam
        // YOKI ko'p row to'liq emas - max_input_vars limit signalsi.
        $truncationDetected = ($expectedRowCount > 0 && $receivedRowCount < $expectedRowCount)
            || ($incompleteRowCount > 0 && $maxInputVars > 0 && ($receivedRowCount * 4) >= $maxInputVars);
        if ($truncationDetected) {
            $msg = sprintf(
                'Diqqat: PHP max_input_vars (%d) limiti tufayli formdagi ba\'zi qatorlar saqlanmagan bo\'lishi mumkin. '
                . 'Kutilgan: %d ta qator, qabul qilindi: %d ta. Iltimos, kichikroq partiyalarga bo\'lib saqlang yoki '
                . 'serverda php.ini ichida max_input_vars qiymatini oshiring (masalan 10000).',
                $maxInputVars,
                $expectedRowCount ?: $receivedRowCount,
                count($validSchedules)
            );
            session()->flash('warning', $msg);
        }

        // Group/subject metadata'ni serverdan yuklab olamiz - formada bu hidden
        // fieldlar yo'q (max_input_vars'ni tejash uchun). Har row uchun
        // department/specialty/curriculum/subject_name/closing_form to'ldiriladi.
        $uniqueGroupIds = array_unique(array_filter(array_column($validSchedules, 'group_hemis_id')));

        $groupMeta = !empty($uniqueGroupIds)
            ? \App\Models\Group::whereIn('group_hemis_id', $uniqueGroupIds)
                ->get(['group_hemis_id', 'department_hemis_id', 'specialty_hemis_id', 'curriculum_hemis_id'])
                ->keyBy('group_hemis_id')
            : collect();

        // closing_form AYNAN qatorning o'quv rejasidagi (curriculum) fan
        // yozuvidan olinishi shart. Bitta (subject_id, semester_code) juftligi
        // bir nechta o'quv rejada uchraydi (semester_code global emas) va har
        // birida closing_form turlicha bo'lishi mumkin. Curriculumsiz qidiruv
        // boshqa rejaning yozuvini ('oski' kabi) tanlab, test sanasini jimgina
        // null qilib yuboradi - shuning uchun qidiruvni curriculum bilan scope qilamiz.
        $curriculumForRow = function (array $s) use ($groupMeta): ?string {
            if (!empty($s['curriculum_hemis_id'])) {
                return (string) $s['curriculum_hemis_id'];
            }
            $g = $groupMeta->get($s['group_hemis_id'] ?? null);
            return $g && $g->curriculum_hemis_id !== null ? (string) $g->curriculum_hemis_id : null;
        };

        $uniqueSubjectKeys = [];
        foreach ($validSchedules as $s) {
            $cur = $curriculumForRow($s);
            $uniqueSubjectKeys[$cur . '|' . $s['subject_id'] . '|' . $s['semester_code']] = [
                'curricula_hemis_id' => $cur,
                'subject_id' => $s['subject_id'],
                'semester_code' => $s['semester_code'],
            ];
        }

        $subjectMeta = collect();
        if (!empty($uniqueSubjectKeys)) {
            $subjectQuery = \App\Models\CurriculumSubject::query();
            $subjectQuery->where(function ($q) use ($uniqueSubjectKeys) {
                foreach ($uniqueSubjectKeys as $sk) {
                    $q->orWhere(function ($sub) use ($sk) {
                        $sub->where('subject_id', $sk['subject_id'])
                            ->where('semester_code', $sk['semester_code']);
                        if (!empty($sk['curricula_hemis_id'])) {
                            $sub->where('curricula_hemis_id', $sk['curricula_hemis_id']);
                        }
                    });
                }
            });
            $subjectMeta = $subjectQuery->get(['curricula_hemis_id', 'subject_id', 'semester_code', 'subject_name', 'closing_form'])
                ->keyBy(fn($s) => $s->curricula_hemis_id . '|' . $s->subject_id . '|' . $s->semester_code);
        }

        foreach ($validSchedules as &$schedule) {
            $g = $groupMeta->get($schedule['group_hemis_id']);
            if ($g) {
                $schedule['department_hemis_id'] = $schedule['department_hemis_id'] ?? $g->department_hemis_id;
                $schedule['specialty_hemis_id'] = $schedule['specialty_hemis_id'] ?? $g->specialty_hemis_id;
                $schedule['curriculum_hemis_id'] = $schedule['curriculum_hemis_id'] ?? $g->curriculum_hemis_id;
            }
            $cur = $curriculumForRow($schedule);
            $sm = $subjectMeta->get($cur . '|' . $schedule['subject_id'] . '|' . $schedule['semester_code']);
            if ($sm) {
                $schedule['subject_name'] = $schedule['subject_name'] ?? $sm->subject_name;
                $schedule['closing_form'] = $schedule['closing_form'] ?? $sm->closing_form;
            }
        }
        unset($schedule);

        $currentSemester = Semester::where('current', true)->first();
        $educationYear = $currentSemester?->education_year;
        $userId = auth()->id();
        $today = \Carbon\Carbon::today();

        // Mavjud yozuvlarni oldindan yuklash (allaqachon saqlangan sanalarni
        // validatsiyadan o'tkazib yuborish uchun). Per-student yozuvlari guruh
        // yozuvi bilan key-da ustma-ust tushmasligi uchun student_hemis_id ham
        // hisobga olinadi.
        $hasStudentColForVal = \Illuminate\Support\Facades\Schema::hasColumn('exam_schedules', 'student_hemis_id');
        $validationKey = function ($groupId, $subjectId, $semCode, $studentId): string {
            return $groupId . '_' . $subjectId . '_' . $semCode . '_' . ($studentId ?? '');
        };
        $existingForValidation = ExamSchedule::where(function ($q) use ($validSchedules, $hasStudentColForVal) {
            foreach ($validSchedules as $s) {
                $q->orWhere(function ($sub) use ($s, $hasStudentColForVal) {
                    $sub->where('group_hemis_id', $s['group_hemis_id'])
                        ->where('subject_id', $s['subject_id'])
                        ->where('semester_code', $s['semester_code']);
                    if ($hasStudentColForVal) {
                        if (!empty($s['student_hemis_id'])) {
                            $sub->where('student_hemis_id', $s['student_hemis_id']);
                        } else {
                            $sub->whereNull('student_hemis_id');
                        }
                    }
                });
            }
        })->get()->keyBy(fn($r) => $validationKey($r->group_hemis_id, $r->subject_id, $r->semester_code, $r->student_hemis_id ?? null));

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
        $allowPastDates = ExamDateRoleService::allowPastExamDates();
        // Toggle: yoqilsa, non-admin rollari ham bugungi kunni qo'ya oladi
        // (shoshilinch hollar uchun admin tomonidan vaqtincha ochiladi).
        $allowToday = ExamDateRoleService::allowTodayExamDates();
        $minDate = ($isAdmin || $allowToday) ? $today : $today->copy()->addDay();
        // Ertangi kunga sana belgilash uchun bugungi oxirgi soat (default 18:00).
        // Dekanat / Registrator ofisi / O'quv bo'limi rollari uchun: agar hozir
        // ushbu soatdan keyin bo'lsa, ertangi kunga sana qo'yish bloklanadi —
        // Test markaziga vaqtlarni belgilash uchun yetarli vaqt qoldirish maqsadida.
        // "Bugunni qo'yish" toggle yoqilgan bo'lsa cutoff ham yumshatiladi (bugunni
        // qo'ya olgandan keyin ertaga uchun 18:00 cutoff mantiqsiz bo'lib qoladi).
        $submissionCutoffHour = ExamDateRoleService::examDateSubmissionCutoffHour();
        $blockTomorrowNow = !$isAdmin && !$allowToday && now()->hour >= $submissionCutoffHour;
        $tomorrowStr = $today->copy()->addDay()->format('Y-m-d');

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
                $validationKey(
                    $schedule['group_hemis_id'],
                    $schedule['subject_id'],
                    $schedule['semester_code'],
                    $schedule['student_hemis_id'] ?? null
                )
            );

            // Bugungi kunni belgilash huquqi faqat adminda. Dekanat, Registrator
            // ofisi va O'quv bo'limi rollari 1-urinish ham, qayta urinishlar ham
            // bo'lsin — eng kamida ertangi kunga sana qo'ya oladi. Shu tariqa
            // Test markaziga vaqtlarni belgilashga yetarli muddat qoladi.
            $rowUrinishVal = (int) ($schedule['urinish'] ?? 1);
            $rowMinDate = ($isAdmin && $rowUrinishVal >= 2) ? $today : $minDate;

            if (!empty($schedule['oski_date']) && !$allowPastDates) {
                $alreadySaved = $existingRec && $existingRec->oski_date
                    && $existingRec->oski_date->format('Y-m-d') === $schedule['oski_date'];
                if (!$alreadySaved) {
                    $oskiDate = \Carbon\Carbon::parse($schedule['oski_date']);
                    if ($oskiDate->lt($rowMinDate)) {
                        return redirect()->back()->with('error', $isAdmin
                            ? 'OSKI sanasi o\'tgan kunni qo\'yib bo\'lmaydi.'
                            : 'OSKI sanasi kamida ertadan bo\'lishi kerak. Bugun yoki o\'tgan kunni qo\'yib bo\'lmaydi.');
                    }
                    if ($blockTomorrowNow && $oskiDate->format('Y-m-d') === $tomorrowStr) {
                        return redirect()->back()->with('error', sprintf(
                            'OSKI sanasini ertangi kunga (%s) belgilab bo\'lmaydi: hozir soat %02d:00 dan kech. Test markaziga vaqtlarni belgilashga muddat qolishi uchun ertangi kunga sana faqat soat %02d:00 gacha qo\'yiladi. Iltimos, kelgusi kunlardan birini tanlang.',
                            \Carbon\Carbon::parse($tomorrowStr)->format('d.m.Y'),
                            $submissionCutoffHour,
                            $submissionCutoffHour
                        ));
                    }
                }
            }
            if (!empty($schedule['test_date']) && !$allowPastDates) {
                $alreadySaved = $existingRec && $existingRec->test_date
                    && $existingRec->test_date->format('Y-m-d') === $schedule['test_date'];
                if (!$alreadySaved) {
                    $testDate = \Carbon\Carbon::parse($schedule['test_date']);
                    if ($testDate->lt($rowMinDate)) {
                        return redirect()->back()->with('error', $isAdmin
                            ? 'Test sanasi o\'tgan kunni qo\'yib bo\'lmaydi.'
                            : 'Test sanasi kamida ertadan bo\'lishi kerak. Bugun yoki o\'tgan kunni qo\'yib bo\'lmaydi.');
                    }
                    if ($blockTomorrowNow && $testDate->format('Y-m-d') === $tomorrowStr) {
                        return redirect()->back()->with('error', sprintf(
                            'Test sanasini ertangi kunga (%s) belgilab bo\'lmaydi: hozir soat %02d:00 dan kech. Test markaziga vaqtlarni belgilashga muddat qolishi uchun ertangi kunga sana faqat soat %02d:00 gacha qo\'yiladi. Iltimos, kelgusi kunlardan birini tanlang.',
                            \Carbon\Carbon::parse($tomorrowStr)->format('d.m.Y'),
                            $submissionCutoffHour,
                            $submissionCutoffHour
                        ));
                    }
                }
            }
        }

        // Kunlik sig'im validatsiyasi (har bir yangi/o'zgargan sana uchun).
        // Sig'im ham, talaba soni ham bandlik ko'rsatkichi dashboard'i bilan
        // bir xil usulda hisoblanadi, aks holda ikki sahifa bir-biriga zid
        // raqam ko'rsatadi:
        //   - sig'im: reserve pool ayrilgan effective kunlik sig'im;
        //   - talaba soni: tanlov fani (variant a/b/c), per-student grafik va
        //     urinish bo'yicha aniq son — butun guruh emas.
        $defaultDailyCapacity = ExamCapacityService::effectiveDailyCapacityForDate(null);
        if ($defaultDailyCapacity > 0) {
            // $pendingByDate[$date] = ushbu so'rovda shu kunga ko'chirilayotgan talabalar soni
            $pendingByDate = [];
            $subjectCountCache = [];

            $countOfSchedule = function (array $schedule) use (&$subjectCountCache) {
                // Per-student grafik — faqat shu talaba.
                if (!empty($schedule['student_hemis_id'])) {
                    return 1;
                }
                // Tanlov fani (variant a/b/c) bo'lsa student_subjects'dan aniq
                // son; mandatory fan bo'lsa butun guruh.
                $key = $schedule['group_hemis_id'] . '|' . ($schedule['subject_id'] ?? '');
                if (!isset($subjectCountCache[$key])) {
                    $subjectCountCache[$key] = ExamCapacityService::subjectStudentCount(
                        $schedule['group_hemis_id'],
                        $schedule['subject_id'] ?? null
                    );
                }
                return $subjectCountCache[$key];
            };

            foreach ($validSchedules as $schedule) {
                $existingRec = $existingForValidation->get(
                    $validationKey(
                        $schedule['group_hemis_id'],
                        $schedule['subject_id'],
                        $schedule['semester_code'],
                        $schedule['student_hemis_id'] ?? null
                    )
                );

                $oskiNa = !empty($schedule['oski_na']);
                $testNa = !empty($schedule['test_na']);

                // OSKI: faqat yangi yoki o'zgargan sana hisobga olinadi
                if (!empty($schedule['oski_date']) && !$oskiNa) {
                    $oldOski = $existingRec?->oski_date?->format('Y-m-d');
                    $newOski = $schedule['oski_date'];
                    if ($oldOski !== $newOski) {
                        $pendingByDate[$newOski] = ($pendingByDate[$newOski] ?? 0) + $countOfSchedule($schedule);
                    }
                }
                // Test: faqat yangi yoki o'zgargan sana hisobga olinadi
                if (!empty($schedule['test_date']) && !$testNa) {
                    $oldTest = $existingRec?->test_date?->format('Y-m-d');
                    $newTest = $schedule['test_date'];
                    if ($oldTest !== $newTest) {
                        $pendingByDate[$newTest] = ($pendingByDate[$newTest] ?? 0) + $countOfSchedule($schedule);
                    }
                }
            }

            foreach ($pendingByDate as $date => $pendingStudents) {
                // Sanada DB'da allaqachon belgilangan yozuvlar bo'yicha aniq
                // talaba soni (tanlov fani / per-student / urinish bilan).
                // O'zgarayotgan qatorlarning eski sanasi DB'da hali boshqa
                // kunda turgani uchun bu yerda qayta sanalmaydi — pending
                // alohida qo'shiladi.
                $existingTotal = ExamCapacityService::totalStudentsOnDate($date);
                $combined = $existingTotal + $pendingStudents;
                $perDayCapacity = ExamCapacityService::effectiveDailyCapacityForDate($date);
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
        // Vaqt yoki sana o'zgarganda ComputerAssignment'ni qayta hisoblash —
        // TV displey planned_start'ni o'qiydi. Doim GURUH schedule id bo'yicha
        // (per-student emas) — assign() ichidagi override pass shaxsiy
        // vaqtlarni qo'llaydi. "groupId|yn" kalit bilan dedup qilinadi.
        $assignReassign = [];
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
                    // 2/3-urinish virtual qatori (guruh sathidagi) bo'sh kelgan bo'lsa,
                    // hech narsa qilmaymiz. Aks holda guruh yozuvini o'chirish
                    // 1-urinishning saqlangan oski_date/test_date sanalarini ham
                    // yo'q qiladi (1-urinish va 2-urinish bitta exam_schedules
                    // qatorida turli ustunlarda yashaydi). Resit ustunlari
                    // allaqachon bo'sh — tozalashga hojat yo'q.
                    $rowUrinishEmpty = (int) ($schedule['urinish'] ?? 1);
                    if (!$isPerStudent && $rowUrinishEmpty >= 2) {
                        continue;
                    }
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
                    // O'chirishdan oldin auditga oldingi qiymatlarni saqlaymiz —
                    // "vaqt qaerga ketdi" tahqiqotida bu eng muhim ma'lumot.
                    $toDelete = $delQuery->get();
                    foreach ($toDelete as $rowBeingDeleted) {
                        ActivityLogService::log(
                            'delete',
                            'exam_schedule',
                            'YN sanasi o\'chirildi: ' . ($rowBeingDeleted->subject_name ?: ('ID ' . $rowBeingDeleted->id))
                                . ' (guruh ' . $rowBeingDeleted->group_hemis_id . ')',
                            $rowBeingDeleted,
                            $rowBeingDeleted->only([
                                'oski_date', 'oski_time', 'oski_na',
                                'test_date', 'test_time', 'test_na',
                                'oski_resit_date', 'oski_resit_time',
                                'oski_resit2_date', 'oski_resit2_time',
                                'test_resit_date', 'test_resit_time',
                                'test_resit2_date', 'test_resit2_time',
                            ]),
                            null
                        );
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
                    // Per-student qatorda biror sana to'ldirilganmi tekshiramiz.
                    // 1-urinishda forma test_date/oski_date jo'natadi va shu ustunlarga
                    // yoziladi; 2/3-urinishda esa keyin test_resit_date / test_resit2_date
                    // ga map qilinadi — ikkala holatda ham qiymat shu yerda paydo bo'ladi.
                    $anyDateFields = [
                        'oski_date', 'test_date',
                        'oski_resit_date', 'oski_resit2_date',
                        'test_resit_date', 'test_resit2_date',
                    ];
                    $hasAnyDate = false;
                    foreach ($anyDateFields as $df) {
                        if (!empty($schedule[$df])) { $hasAnyDate = true; break; }
                    }
                    if (!$hasAnyDate && !$record->exists) {
                        // Hech qanday sana yo'q va yangi qator — yaratmaymiz
                        continue;
                    }
                }

                // Urinish bo'yicha qaysi DB ustuniga yozish kerakligini aniqlaymiz.
                // Form har bir virtual qator uchun oski_date/test_date submit qiladi,
                // lekin urinish=2 bo'lsa oski_resit_date, urinish=3 bo'lsa oski_resit2_date.
                // Vaqt (oski_time/test_time) ham xuddi shu tarzda map qilinadi —
                // individual grafik (per-student) qatorlarda registrator vaqtni ham
                // sana bilan birga belgilashi mumkin.
                $rowUrinish = (int) ($schedule['urinish'] ?? 1);
                $oskiCol = match ($rowUrinish) { 2 => 'oski_resit_date', 3 => 'oski_resit2_date', default => 'oski_date' };
                $testCol = match ($rowUrinish) { 2 => 'test_resit_date', 3 => 'test_resit2_date', default => 'test_date' };
                $oskiTimeCol = match ($rowUrinish) { 2 => 'oski_resit_time', 3 => 'oski_resit2_time', default => 'oski_time' };
                $testTimeCol = match ($rowUrinish) { 2 => 'test_resit_time', 3 => 'test_resit2_time', default => 'test_time' };

                // Per-student qator: yuborilgan sana+vaqt guruh qatori bilan AYNAN bir xil
                // bo'lsa, alohida yozuv yaratmaymiz (mavjud bo'lsa o'chiramiz). Per-student
                // yozuvi faqat individual jadval — guruh sanasidan FARQLI vaqt yoki sana
                // belgilash uchun mantiqan kerak. Bu form yoki brauzer autofill tufayli
                // per-student inputlarga guruh sanasi noxosdan tushib qolishi natijasida
                // 7-8 ta dublikat yozuv yaratilishining oldini oladi.
                if ($studentHemisIdForRow) {
                    $groupRowForCompare = ExamSchedule::where('group_hemis_id', $schedule['group_hemis_id'])
                        ->where('subject_id', $schedule['subject_id'])
                        ->where('semester_code', $schedule['semester_code'])
                        ->whereNull('student_hemis_id')
                        ->first();
                    if ($groupRowForCompare) {
                        $normTime = function ($t): ?string {
                            if (empty($t)) return null;
                            return substr((string) $t, 0, 5);
                        };
                        $groupOski = $groupRowForCompare->{$oskiCol}?->format('Y-m-d');
                        $groupTest = $groupRowForCompare->{$testCol}?->format('Y-m-d');
                        $groupOskiTime = $normTime($groupRowForCompare->{$oskiTimeCol} ?? null);
                        $groupTestTime = $normTime($groupRowForCompare->{$testTimeCol} ?? null);
                        $stuOski = !empty($schedule['oski_date']) ? $schedule['oski_date'] : null;
                        $stuTest = !empty($schedule['test_date']) ? $schedule['test_date'] : null;
                        $stuOskiTime = $normTime($schedule['oski_time'] ?? null);
                        $stuTestTime = $normTime($schedule['test_time'] ?? null);
                        $matchesGroup = ($stuOski === $groupOski)
                            && ($stuTest === $groupTest)
                            && ($stuOskiTime === $groupOskiTime)
                            && ($stuTestTime === $groupTestTime);
                        if ($matchesGroup) {
                            if ($record->exists) {
                                $oldVals = $record->only([
                                    'oski_date', 'oski_time', 'oski_na',
                                    'test_date', 'test_time', 'test_na',
                                    'oski_resit_date', 'oski_resit_time',
                                    'oski_resit2_date', 'oski_resit2_time',
                                    'test_resit_date', 'test_resit_time',
                                    'test_resit2_date', 'test_resit2_time',
                                ]);
                                ActivityLogService::log(
                                    'delete',
                                    'exam_schedule',
                                    'Per-student YN yozuvi tozalandi (guruh bilan bir xil): '
                                        . ($record->subject_name ?: ('ID ' . $record->id))
                                        . ' (guruh ' . $record->group_hemis_id . ')',
                                    $record,
                                    $oldVals,
                                    null
                                );
                                $record->delete();
                            }
                            continue;
                        }
                    }
                }

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
                // Per-student qatorlarda forma vaqtni ham yuborishi mumkin
                // (registrator individual grafikka sana+vaqt belgilaydi). Vaqt
                // H:i formatda kelishi kutiladi; bo'sh bo'lsa null saqlanadi.
                $normalizeTime = function ($raw): ?string {
                    if (empty($raw)) return null;
                    $raw = trim((string) $raw);
                    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $raw, $m)) return null;
                    $h = (int) $m[1]; $mn = (int) $m[2];
                    if ($h < 0 || $h > 23 || $mn < 0 || $mn > 59) return null;
                    return sprintf('%02d:%02d', $h, $mn);
                };
                if (array_key_exists('oski_time', $schedule)) {
                    $newOskiTime = $normalizeTime($schedule['oski_time']);
                    if ($newOskiDate !== null || $newOskiTime === null) {
                        $fillData[$oskiTimeCol] = $newOskiTime;
                    }
                }
                if (array_key_exists('test_time', $schedule)) {
                    $newTestTime = $normalizeTime($schedule['test_time']);
                    if ($newTestDate !== null || $newTestTime === null) {
                        $fillData[$testTimeCol] = $newTestTime;
                    }
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

                // Audit log: faqat sana/vaqt/NA holatlari o'zgargan bo'lsagina yozamiz —
                // mansab yangilash, Moodle sync timestamp kabi servisning ichki
                // o'zgarishlari log'ni shovqin bilan to'ldirib yubormasligi uchun.
                // getDirty() / getOriginal() ni save() dan AVVAL ushlaymiz, chunki
                // save chaqirilgach syncOriginal tufayli dirty ro'yxati bo'shab qoladi.
                $auditWatched = [
                    'oski_date', 'oski_time', 'oski_na',
                    'test_date', 'test_time', 'test_na',
                    'oski_resit_date', 'oski_resit_time',
                    'oski_resit2_date', 'oski_resit2_time',
                    'test_resit_date', 'test_resit_time',
                    'test_resit2_date', 'test_resit2_time',
                ];
                $auditDirty = array_intersect_key($record->getDirty(), array_flip($auditWatched));
                $auditOriginal = $record->exists
                    ? collect($record->getOriginal())->only(array_keys($auditDirty))->toArray()
                    : null;
                $auditWasNew = !$record->exists;

                $record->save();

                if (!empty($auditDirty) || $auditWasNew) {
                    ActivityLogService::log(
                        $auditWasNew ? 'create' : 'update',
                        'exam_schedule',
                        ($auditWasNew ? 'YN sanasi qo\'shildi' : 'YN sanasi/vaqti yangilandi')
                            . ': ' . ($record->subject_name ?: ('ID ' . $record->id))
                            . ' (guruh ' . $record->group_hemis_id . ')',
                        $record,
                        $auditOriginal,
                        !empty($auditDirty) ? $auditDirty : null
                    );
                }

                // Per-student qatorda vaqt belgilanganda yoki o'zgartirilganda
                // FAQAT shu talabaga Telegram + DB notification yuborish.
                // Test markazi guruh vaqtini belgilashidan farqli o'laroq, individual
                // grafikga ega talaba shu yerdan to'g'ridan-to'g'ri xabar oladi.
                if ($studentHemisIdForRow) {
                    $perStudentTimeFields = [
                        ['yn' => 'OSKI', 'time_col' => $oskiTimeCol, 'date_col' => $oskiCol],
                        ['yn' => 'Test', 'time_col' => $testTimeCol, 'date_col' => $testCol],
                    ];
                    $studentForNotify = null; // lazy load
                    foreach ($perStudentTimeFields as $ftf) {
                        if (!array_key_exists($ftf['time_col'], $auditDirty)) continue;
                        $newT = $auditDirty[$ftf['time_col']];
                        if (empty($newT)) continue; // vaqt o'chirilgan bo'lsa xabar yubormaymiz
                        $oldT = $auditOriginal[$ftf['time_col']] ?? null;
                        $dateVal = $record->{$ftf['date_col']};
                        $lbl = $ftf['yn'];
                        if ($rowUrinish > 1) {
                            $lbl .= ' (' . $rowUrinish . '-urinish)';
                        }
                        if ($studentForNotify === null) {
                            $studentForNotify = $this->singleStudentForNotify((string) $studentHemisIdForRow);
                        }
                        $this->notifyStudentsExamTime(
                            $studentForNotify,
                            $record->subject_name ?: 'Fan',
                            $ftf['yn'],
                            $lbl,
                            $dateVal ? \Carbon\Carbon::parse($dateVal)->format('d.m.Y') : null,
                            substr((string) $newT, 0, 5),
                            $oldT
                        );
                    }
                }

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

                // 1-urinish OSKI/Test vaqti yoki sanasi o'zgargan bo'lsa —
                // ComputerAssignment'ni qayta hisoblaymiz (TV displey va JIT
                // shu planned_start'ni o'qiydi). Sana-only o'zgarishni
                // yuqoridagi $bookingsToDispatch allaqachon qamragan, bu yer
                // esa VAQT-only o'zgarishni ham qo'shadi. Per-student qatorda
                // guruh schedule id topiladi — assign() override pass shaxsiy
                // vaqtni qo'llaydi.
                $oskiTimeOrDate = array_key_exists('oski_time', $auditDirty) || array_key_exists('oski_date', $auditDirty);
                $testTimeOrDate = array_key_exists('test_time', $auditDirty) || array_key_exists('test_date', $auditDirty);
                if (($oskiTimeOrDate && !$newOskiNa && $newOskiDate && $record->oski_time)
                    || ($testTimeOrDate && !$newTestNa && $newTestDate && $record->test_time)) {
                    $reassignGroupId = $studentHemisIdForRow
                        ? ExamSchedule::where('group_hemis_id', $record->group_hemis_id)
                            ->where('subject_id', $record->subject_id)
                            ->where('semester_code', $record->semester_code)
                            ->whereNull('student_hemis_id')
                            ->value('id')
                        : $record->id;
                    if ($reassignGroupId) {
                        if ($oskiTimeOrDate && !$newOskiNa && $newOskiDate && $record->oski_time) {
                            $assignReassign[$reassignGroupId . '|oski'] = [(int) $reassignGroupId, 'oski'];
                        }
                        if ($testTimeOrDate && !$newTestNa && $newTestDate && $record->test_time) {
                            $assignReassign[$reassignGroupId . '|test'] = [(int) $reassignGroupId, 'test'];
                        }
                    }
                }
            }
            DB::commit();

            foreach ($bookingsToDispatch as [$id, $yn]) {
                BookMoodleGroupExam::dispatch($id, $yn);
            }
            // AssignComputersJob — vaqt yoki sana o'zgarganda, doim guruh
            // schedule id bo'yicha (assign() per-student override pass bilan).
            foreach ($assignReassign as [$gid, $yn]) {
                AssignComputersJob::dispatch($gid, $yn);
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

        // Audit uchun eski qiymatlarni saqlab qo'yamiz (sana + vaqt + na).
        $clearedSnapshot = $record->only([
            'oski_date', 'oski_time', 'oski_na',
            'test_date', 'test_time', 'test_na',
        ]);

        if ($dateType === 'oski') {
            $record->oski_date = null;
            $record->oski_na = false;
        } else {
            $record->test_date = null;
            $record->test_na = false;
        }

        $record->updated_by = auth()->id() ?? auth('teacher')->id();

        $typeLabel = $dateType === 'oski' ? 'OSKI' : 'Test';
        $auditDescription = "{$typeLabel} sanasi o'chirildi: " . ($record->subject_name ?: ('ID ' . $record->id))
            . ' (guruh ' . $record->group_hemis_id . ')';

        // Agar ikkala sana ham bo'sh bo'lsa — yozuvni o'chiramiz
        if (!$record->oski_date && !$record->oski_na && !$record->test_date && !$record->test_na) {
            $record->delete();
            ActivityLogService::log('delete', 'exam_schedule', $auditDescription, $record, $clearedSnapshot, null);
        } else {
            $record->save();
            ActivityLogService::log('update', 'exam_schedule', $auditDescription, $record, $clearedSnapshot, $record->only(array_keys($clearedSnapshot)));
        }

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
        $currentSemesters = Semester::where('current', true)->get(['curriculum_hemis_id', 'education_year', 'code']);
        $currentEducationYear = $currentSemesters->first()?->education_year;
        $currentSemesterCodes = $currentSemesters->pluck('code')->filter()->unique()->values();

        $applySemesterWindow = function ($query) use ($currentSemesterOnly, $currentEducationYear, $currentSemesterCodes) {
            if ($currentSemesterOnly) {
                if ($currentEducationYear) {
                    $query->where('education_year', $currentEducationYear);
                }
                if ($currentSemesterCodes->isNotEmpty()) {
                    $query->whereIn('code', $currentSemesterCodes);
                } else {
                    $query->where('current', true);
                }
            } else {
                $query->where('education_year', $currentEducationYear);
            }

            return $query;
        };

        $currentSemSubquery = $applySemesterWindow(Semester::query())->select('curriculum_hemis_id');

        // Curriculum query builder: barcha filtrlarni qo'llaydi, $exclude ni tashlab
        $buildQuery = function (?string $exclude = null) use (
            $currentSemSubquery, $currentSemesterOnly, $applySemesterWindow,
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
                // HEMIS data drift: bir nom uchun bir necha specialty_hemis_id
                // (masalan "Xalq tabobati" = 49 va 15). Shu nom ostidagi barcha
                // ID lar bo'yicha cluster filter.
                $name = Specialty::where('specialty_hemis_id', $specialtyId)->value('name');
                if ($name) {
                    $clusterIds = Specialty::where('name', $name)->pluck('specialty_hemis_id');
                    $q->whereIn('specialty_hemis_id', $clusterIds);
                } else {
                    $q->where('specialty_hemis_id', $specialtyId);
                }
            }

            $applyLevel = ($exclude !== 'level_code' && $levelCode);
            $applySemester = ($exclude !== 'semester_code' && $semesterCode);

            if ($applyLevel || $applySemester) {
                $q->whereIn('curricula_hemis_id', function ($sub) use ($applyLevel, $applySemester, $levelCode, $semesterCode, $applySemesterWindow) {
                    $sub->select('curriculum_hemis_id')->from('semesters');
                    $applySemesterWindow($sub);
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
        // HEMIS data drift'ni hisobga olgan holda: curricula'dagi specialty_hemis_id'lar +
        // shu nom ostidagi BOSHQA specialty_hemis_id'lar (groups'da bo'lishi mumkin).
        // Dropdown'da bir nom uchun bitta variant ko'rsatamiz (eng kichik ID — representative).
        $curSpecIds = $buildQuery('specialty_id')->pluck('specialty_hemis_id')->unique();
        $namesInCurricula = Specialty::whereIn('specialty_hemis_id', $curSpecIds)->pluck('name')->unique();
        $specialties = Specialty::whereIn('name', $namesInCurricula)
            ->orderBy('name')
            ->get(['specialty_hemis_id', 'name'])
            ->groupBy('name')
            ->map(fn($g) => $g->sortBy('specialty_hemis_id')->first())
            ->values();

        // 4. Kurslar (level filtrini tashlab) — barcha viewer rollar uchun
        // hech qanday cheklovsiz to'liq ro'yxat. Sana qo'yish huquqi store() da
        // urinish + kurs darajasi bo'yicha alohida tekshiriladi.
        $levelCurrIds = $buildQuery('level_code')->pluck('curricula_hemis_id');
        $levelQuery = $applySemesterWindow(Semester::query())
            ->whereIn('curriculum_hemis_id', $levelCurrIds);
        if ($semesterCode) $levelQuery->where('code', $semesterCode);

        $levels = $levelQuery->select('level_code', 'level_name')
            ->distinct()->orderBy('level_code')->get();

        // 5. Semestrlar (semester filtrini tashlab)
        $semCurrIds = $buildQuery('semester_code')->pluck('curricula_hemis_id');
        $semQuery = $applySemesterWindow(Semester::query())
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
            $levelSemCodes = $applySemesterWindow(Semester::query())
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
        return $this->processYnOldiWord($request);
    }

    /**
     * generateYnOldiWord ning asosiy qismi — kirish huquqi tekshiruvisiz.
     * HTTP endpoint (generateYnOldiWord) va AssignComputersForRangeJob navbat
     * job'i shu metodni chaqiradi. Job kontekstida kirish huquqi tugma
     * bosilganda (assignComputersForRange) allaqachon tekshirilgan bo'ladi.
     */
    public function processYnOldiWord(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.group_hemis_id' => 'required|string',
            'items.*.semester_code' => 'required|string',
            'items.*.subject_id' => 'required|string',
            // 2-/3-urinish (resit) bo'lsa butun guruh emas, faqat yiqilganlar
            // chiqishi uchun urinish raqami va (per-student qator bo'lsa)
            // bitta talabaning hemis_id'si yuboriladi.
            'items.*.attempt' => 'nullable|integer|min:1|max:3',
            'items.*.student_hemis_id' => 'nullable|string',
            // Komp № DB'ga saqlash uchun yn_type va schedule_id ham kerak —
            // bandlik view'i ularni yuboradi. Test-center bulk export'da
            // ular bo'lmaydi → faqat Word generatsiyasi (DB'ga yozish yo'q).
            'items.*.schedule_id' => 'nullable|integer',
            'items.*.yn_type' => 'nullable|in:oski,test,OSKI,Test',
            // Per-item exam_time bo'lsa "multi-slot" rejim — bir nechta vaqt
            // slotlarini tanlab bitta Word hujjatda vaqt tartibida ketma-ket
            // chiqarish (bandlik ko'rsatkichida checkbox + "Tanlanganlarni Word'ga"
            // tugmasi orqali). Top-level exam_time esa eski yagona slot rejimi.
            'items.*.exam_time' => 'nullable|date_format:H:i',
            // Bandlik ko'rsatkichidan chaqirilganda: imzo bloki va QR'siz "ish ro'yxati"
            // varianti + slotning kirish sana/vaqti yuqorida ko'rsatiladi.
            'compact' => 'nullable|boolean',
            'exam_date' => 'nullable|date_format:Y-m-d',
            'exam_time' => 'nullable|date_format:H:i',
            // Diagnostika rejimi: docx o'rniga har item bo'yicha (guruh topildimi,
            // talabalar soni, qaysi filtrda nechta talaba tushib qoldi va sababi)
            // JSON qaytaradi. DB'ga hech narsa yozmaydi.
            'debug' => 'nullable|boolean',
            // "Kompyuter raqamlarini taqsimlash" tugmasi: docx yaratmasdan
            // komp raqamlarini ComputerAssignment'ga yozib JSON qaytaradi.
            // Word yuklab olish (bu bayroqsiz) endi raqam taqsimlamaydi.
            'assign_computers' => 'nullable|boolean',
        ]);

        try {

        // Bir xil (group, subject, semester, yn_type, attempt, exam_time)
        // kombinatsiyasi bir necha marta kelishi mumkin (slotda guruh-level +
        // individual entry'lar bo'lsa). Har birikma uchun: guruh-level yozuv
        // (student_hemis_id bo'sh) bo'lsa — faqat o'shani olamiz (butun guruh
        // ro'yxati); aks holda barcha per-student yozuvlarni saqlaymiz.
        // DIQQAT: yn_type va attempt kalitga kiritilishi shart — aks holda
        // bitta guruhning OSKI va Test (yoki turli urinish) qatorlari
        // ustma-ust tushib, Word'dan biri tushib qolardi.
        $itemBuckets = [];
        foreach ((array) $request->items as $it) {
            $bk = ($it['group_hemis_id'] ?? '') . '|' . ($it['subject_id'] ?? '') . '|'
                . ($it['semester_code'] ?? '') . '|'
                . strtolower((string) ($it['yn_type'] ?? '')) . '|'
                . (int) ($it['attempt'] ?? 1) . '|'
                . (string) ($it['exam_time'] ?? '');
            $itemBuckets[$bk][] = $it;
        }
        $items = [];
        foreach ($itemBuckets as $bucket) {
            $groupLevel = null;
            foreach ($bucket as $it) {
                if (empty($it['student_hemis_id'])) { $groupLevel = $it; break; }
            }
            if ($groupLevel !== null) {
                $items[] = $groupLevel;
            } else {
                foreach ($bucket as $it) { $items[] = $it; }
            }
        }
        $compact = (bool) $request->boolean('compact');
        $debug = (bool) $request->boolean('debug');
        $assignComputers = (bool) $request->boolean('assign_computers');
        $debugInfo = [];
        $examDate = $request->input('exam_date');
        $examTime = $request->input('exam_time');
        // Multi-slot rejim: hech bo'lmasa bitta item per-item exam_time bilan
        // kelgan bo'lsa, slotlarni vaqt tartibida bitta Word'ga yig'amiz.
        $multiSlotMode = false;
        foreach ($items as $_it) {
            if (!empty($_it['exam_time'])) { $multiSlotMode = true; break; }
        }
        $files = [];
        $tempDir = storage_path('app/public/yn_oldi_qaydnoma');

        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Step 1: Collect all data and group by subject_id
        $subjectGroups = [];
        // Cleanup uchun: items'da kelgan har (schedule_id, yn_type, attempt)
        // bucket'ni qayd qilamiz — eligible students 0 bo'lib `continue` ga
        // tushgan bo'lsa ham, shu bucket'dagi stale qatorlarni keyin
        // tozalashimiz uchun. Bo'lmasa "0 eligible" buckets'lar tozalanmasdan
        // qoladi va TV/proctor sahifasi noto'g'ri ko'rsatadi.
        $itemBucketsSeen = []; // "schedule_id|yn_type|attempt" => true

        // Talabalar ro'yxati va eligibility uchun test-center "talabalarni
        // ko'rsatish" pipeline'i bilan bir xil mantiq: attachStudentsToSchedule
        // joriy yilning BARCHA fanlari bo'yicha 4+ qarz (is_held_back) ni va
        // YnAdmissionService natijasini (Ruxsat/Shartli/X) hisoblaydi.
        // Bir martagina, BARCHA itemlar uchun batch tarzda chaqiramiz —
        // attachStudentsToSchedule ichida og'ir extendScheduleDataWithYearSubjects
        // va computeStudentAttemptStatuses bor, har item uchun alohida chaqirsak
        // ko'p marta bajariladi.
        $preCollected = []; // [groupHid|subjectId|sem => [group, subject, ...]]
        foreach ($items as $itemData) {
            $g = Group::where('group_hemis_id', $itemData['group_hemis_id'])->first();
            if (!$g) continue;
            $subj = CurriculumSubject::where('curricula_hemis_id', $g->curriculum_hemis_id)
                ->where('subject_id', $itemData['subject_id'])
                ->where('semester_code', $itemData['semester_code'])
                ->first();
            if (!$subj) continue;
            $k = $g->group_hemis_id . '|' . $itemData['subject_id'] . '|' . $itemData['semester_code'];
            if (isset($preCollected[$k])) continue;
            // Exam_schedules'dan guruh-level sanalarni olish — attachStudentsToSchedule
            // ichidagi "kelmadi=yiqilgan" mantig'i shu sanalarga tayanadi.
            $existingForEnrich = ExamSchedule::where('group_hemis_id', $g->group_hemis_id)
                ->where('subject_id', $itemData['subject_id'])
                ->where('semester_code', $itemData['semester_code'])
                ->whereNull('student_hemis_id')
                ->first();
            $preCollected[$k] = [
                'group' => $g,
                'subject' => $subj,
                'oski_date' => $existingForEnrich?->oski_date?->format('Y-m-d'),
                'test_date' => $existingForEnrich?->test_date?->format('Y-m-d'),
                'oski_resit_date' => $existingForEnrich?->oski_resit_date?->format('Y-m-d'),
                'test_resit_date' => $existingForEnrich?->test_resit_date?->format('Y-m-d'),
                'oski_resit2_date' => $existingForEnrich?->oski_resit2_date?->format('Y-m-d'),
                'test_resit2_date' => $existingForEnrich?->test_resit2_date?->format('Y-m-d'),
                'lesson_end_date' => null,
            ];
        }
        $miniScheduleData = collect($preCollected)
            ->groupBy(fn($i) => $i['group']->group_hemis_id)
            ->map(fn($vals) => collect(array_values($vals->all())));
        $enrichedScheduleData = $miniScheduleData->isNotEmpty()
            ? $this->attachStudentsToSchedule($miniScheduleData)
            : collect();
        // Lookup: (groupHid|subjectId|sem) → enriched students array.
        $enrichedStudentsByKey = [];
        foreach ($enrichedScheduleData as $groupItems) {
            foreach ($groupItems as $eItem) {
                $k = $eItem['group']->group_hemis_id . '|'
                    . ($eItem['subject']->subject_id ?? '') . '|'
                    . ($eItem['subject']->semester_code ?? '');
                $enrichedStudentsByKey[$k] = $eItem['students'] ?? [];
            }
        }

        foreach ($items as $itemData) {
            $group = Group::where('group_hemis_id', $itemData['group_hemis_id'])->first();
            if (!$group) {
                if ($debug) {
                    $debugInfo[] = [
                        'group_hemis_id' => $itemData['group_hemis_id'] ?? null,
                        'subject_id' => $itemData['subject_id'] ?? null,
                        'semester_code' => $itemData['semester_code'] ?? null,
                        'attempt' => (int) ($itemData['attempt'] ?? 1),
                        'skipped' => 'group_not_found',
                    ];
                }
                continue;
            }

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

            if (!$subject) {
                if ($debug) {
                    $debugInfo[] = [
                        'group' => $group->name,
                        'group_hemis_id' => $group->group_hemis_id,
                        'subject_id' => $subjectId,
                        'semester_code' => $semesterCode,
                        'attempt' => (int) ($itemData['attempt'] ?? 1),
                        'skipped' => 'curriculum_subject_not_found',
                        'curriculum_hemis_id' => $group->curriculum_hemis_id,
                    ];
                }
                continue;
            }

            $itemAttempt = (int) ($itemData['attempt'] ?? 1);
            $itemStudentHemisId = !empty($itemData['student_hemis_id'])
                ? (string) $itemData['student_hemis_id']
                : null;

            // Bu item'ning bucket'ini cleanup ro'yxatiga qo'shamiz — eligible
            // 0 bo'lsa ham keyin tozalanadi.
            $itemScheduleId = !empty($itemData['schedule_id']) ? (int) $itemData['schedule_id'] : null;
            $itemYnType = isset($itemData['yn_type']) ? strtolower((string) $itemData['yn_type']) : null;
            if ($itemScheduleId && $itemYnType) {
                $itemBucketsSeen[$itemScheduleId . '|' . $itemYnType . '|' . $itemAttempt] = true;
            }

            // Talabalar ro'yxati va eligibility — yuqorida batch tarzda
            // hisoblangan enrichedStudentsByKey'dan olinadi (test-center
            // "talabalarni ko'rsatish" bilan bir xil mantiq: 4+ qarz
            // is_held_back va YnAdmissionService bo'yicha Ruxsat/Shartli/X).
            $lookupKey = $group->group_hemis_id . '|' . $subjectId . '|' . $semesterCode;
            $enrichedStudents = $enrichedStudentsByKey[$lookupKey] ?? [];
            $dbgRawCount = count($enrichedStudents);
            $dbgKeyExists = array_key_exists($lookupKey, $enrichedStudentsByKey);

            // Urinishga mos talabalarni saralash — test-center bilan bir xil
            // ($itemStudentHemisId per-student override'i ustuvor).
            if ($itemStudentHemisId !== null) {
                $enrichedStudents = array_values(array_filter(
                    $enrichedStudents,
                    fn($s) => (string) ($s['hemis_id'] ?? '') === $itemStudentHemisId
                ));
            } elseif ($itemAttempt >= 2) {
                // 2/3-urinish uchun faqat haqiqatdan qayta topshirishga
                // muhtoj (failed_attempt[N]) talabalarni qoldiramiz —
                // YN belgilash sahifasidagi sub-row mantig'i bilan bir xil.
                // Per-student override (ekam_schedules.student_hemis_id) li
                // talabalarni guruh-level ro'yxatdan chiqarib tashlamaymiz —
                // foydalanuvchi xohlovi: "kiradiganlar ro'yxati bo'lsin",
                // hatto talabaga individual sana qo'yilgan bo'lsa ham u
                // guruh slotidagi Word'da chiqishi kerak.
                $enrichedStudents = $this->filterStudentsForAttempt($enrichedStudents, $itemAttempt);
            }
            $dbgAfterAttemptCount = count($enrichedStudents);

            // Kursdan qoldirilgan (4+ qarz) yoki YN ga ruxsat yo'q (X)
            // talabalarni ro'yxatdan chiqarib tashlaymiz — Word faqat
            // "kiradiganlar"ni ko'rsatadi (Ruxsat yoki Shartli).
            // Qarz hisobi test-center "talabalarni ko'rsatish" bilan AYNI
            // o'lchov: o'tgan semestrlar academic_records'dan (past_debts),
            // joriy semestr jurnal mantig'idan (current_semester_debts).
            // "X" admission_status orqali aniqlanadi.
            // Sozlama: agar "4+ qarzdorlarga qayta topshirishga ruxsat" toggle
            // yoqilgan bo'lsa, debt count filterini chetlab o'tamiz — bunday
            // talabalar ham Word ro'yxatiga tushadi.
            $allow4Plus = ExamDateRoleService::allow4PlusDebtorsRetake();
            $dbgRemoved = [];
            $enrichedStudents = array_values(array_filter($enrichedStudents, function ($row) use ($debug, &$dbgRemoved, $allow4Plus) {
                $hemisId = (string) ($row['hemis_id'] ?? '');
                $debtCount = count($row['past_debts'] ?? []) + count($row['current_semester_debts'] ?? []);
                if (!$allow4Plus && $debtCount >= 4) {
                    if ($debug) {
                        $dbgRemoved[] = [
                            'name' => $row['full_name'] ?? $hemisId,
                            'hemis_id' => $hemisId,
                            'reason' => 'held_back',
                            'debt_count' => $debtCount,
                        ];
                    }
                    return false;
                }
                // admission_status === 'X' (JN/MT/davomat past) — YN'ga
                // ruxsat yo'q, ro'yxatga kiritmaymiz.
                if (($row['admission_status'] ?? null) === \App\Services\YnAdmissionService::STATUS_X) {
                    if ($debug) {
                        $dbgRemoved[] = [
                            'name' => $row['full_name'] ?? $hemisId,
                            'hemis_id' => $hemisId,
                            'reason' => 'admission_status_X',
                        ];
                    }
                    return false;
                }
                return true;
            }));

            if ($debug) {
                $debugInfo[] = [
                    'group' => $group->name,
                    'group_hemis_id' => $group->group_hemis_id,
                    'subject' => $subject->subject_name ?? null,
                    'subject_id' => $subjectId,
                    'semester_code' => $semesterCode,
                    'attempt' => $itemAttempt,
                    'student_hemis_id_filter' => $itemStudentHemisId,
                    'enriched_key_exists' => $dbgKeyExists,
                    'raw_enriched_count' => $dbgRawCount,
                    'after_attempt_filter_count' => $dbgAfterAttemptCount,
                    'after_heldback_x_filter_count' => count($enrichedStudents),
                    'removed_by_heldback_x' => $dbgRemoved,
                    'will_appear_in_word' => count($enrichedStudents) > 0,
                ];
            }

            if (empty($enrichedStudents)) {
                continue;
            }

            // JN/MT ni jurnal "ixcham" tabi bilan bir xil mantiqda jonli hisoblash
            // (snapshot ishlatilmaydi; retake-priority qoidasi va NB=0 mantiqi qo'llaniladi).
            $liveGrades = app(JnMtCalculator::class)->computeForGroup(
                (string) $group->group_hemis_id,
                (int) $subject->subject_id,
                $semesterCode
            );

            // Enriched arraylarni eski kod kutgan stdClass shaklga o'tkazamiz
            // — Word jadvalini quruvchi kod $student->jn / $student->mt /
            // $student->student_name kabi property'larga tayanadi. "Kirmaydigan"
            // talabalar (held_back/X) yuqorida allaqachon filterlangan,
            // shuning uchun bu yerda admission_status faqat Ruxsat/Shartli.
            // Per-student individual imtihon vaqti uchun maydon — yn_type va
            // attempt bo'yicha tanlanadi. Talabaga guruh vaqtidan farqli vaqt
            // qo'yilgan bo'lsa, Word jadvalida ism yonida ko'rsatiladi.
            $indivTimeField = null;
            if ($itemYnType === 'oski') {
                $indivTimeField = match ($itemAttempt) {
                    2 => 'oski_resit_time', 3 => 'oski_resit2_time', default => 'oski_time',
                };
            } elseif ($itemYnType === 'test') {
                $indivTimeField = match ($itemAttempt) {
                    2 => 'test_resit_time', 3 => 'test_resit2_time', default => 'test_time',
                };
            }
            $students = collect($enrichedStudents)->map(function ($row) use ($liveGrades, $indivTimeField) {
                $hemisId = (string) ($row['hemis_id'] ?? '');
                $obj = new \stdClass();
                $obj->hemis_id = $row['hemis_id'] ?? null;
                $obj->student_name = $row['full_name'] ?? '';
                $obj->student_id = $row['student_id_number'] ?? '';
                $obj->jn = $liveGrades[$hemisId]['jn'] ?? 0;
                $obj->mt = $liveGrades[$hemisId]['mt'] ?? 0;
                $obj->admission_status = $row['admission_status'] ?? null;
                // Individual (per-student exam_schedules) vaqti — HH:MM ko'rinishida.
                $rawIndivTime = $indivTimeField ? ($row[$indivTimeField] ?? null) : null;
                $obj->individual_time = !empty($rawIndivTime) ? substr((string) $rawIndivTime, 0, 5) : null;
                return $obj;
            })->sortBy('student_name')->values();

            if ($students->isEmpty()) {
                continue;
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

            // Multi-slot rejimda kalit time'ni ham o'z ichiga oladi — har
            // (slot vaqti, fan) birikma alohida bo'lim sifatida chiqadi va
            // pastda time tartibida saralanadi.
            $itemExamTime = $multiSlotMode ? (string) ($itemData['exam_time'] ?? '') : '';
            $subjectKey = $multiSlotMode
                ? ($itemExamTime . '|' . $subject->subject_id)
                : (string) $subject->subject_id;
            if (!isset($subjectGroups[$subjectKey])) {
                $subjectGroups[$subjectKey] = [
                    'subject' => $subject,
                    'semester' => $semester,
                    'department' => $department,
                    'specialty' => $specialty,
                    'slot_time' => $itemExamTime,
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
                // DB persistence uchun (bandlik path'idan keladi).
                'yn_type' => isset($itemData['yn_type']) ? strtolower((string) $itemData['yn_type']) : null,
                'attempt' => $itemAttempt,
                'schedule_id' => !empty($itemData['schedule_id']) ? (int) $itemData['schedule_id'] : null,
            ];
        }

        // Diagnostika rejimi: docx yaratmasdan va DB'ga hech narsa yozmasdan
        // har item bo'yicha to'plangan ma'lumotni JSON qaytaramiz.
        if ($debug) {
            return response()->json([
                'multi_slot_mode' => $multiSlotMode,
                'requested_items' => count($items),
                'subject_pages_built' => count($subjectGroups),
                'diagnostics' => $debugInfo,
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        // Kompyuter raqamlari xaritasi: har slot vaqti (sana shu Word'da
        // yagona — bandlikdan kelgani uchun) bo'yicha barcha sahifalardagi
        // talabalar ism bo'yicha saralanib 1, 2, 3, ... tartibida raqam oladi.
        // Buzilganlar (per-day override) chetlab o'tiladi. Reserve pool
        // hisobga olinmaydi — bu faqat ko'rsatma uchun on-the-fly preview,
        // DB'dagi ComputerAssignment.computer_number'larga tegmaydi.
        $brokenSet = [];
        $configuredCompCount = 60;
        if ($examDate) {
            $capSettings = ExamCapacityService::getSettingsForDate($examDate);
            $brokenSet = (array) ($capSettings['broken_computers'] ?? []);
            $configuredCompCount = (int) ($capSettings['computer_count'] ?? 60);
        }
        $brokenLookup = array_flip(array_map('intval', $brokenSet));

        // Boshqa jadvallar band qilgan kompyuter raqamlari — har slot vaqti
        // uchun alohida. Bularni hisobga olmasak, bu taqsimotga kirmaydigan
        // (individual vaqt qo'yilgan talaba yoki ayni paytda imtihon
        // topshirayotgan) talabaning kompyuteri qayta ishlatilib, bitta
        // kompyuter ikki kishiga tushib qolardi. Buzilgan kompyuterlar kabi
        // chetlab o'tiladi. Faqat taqsimlash rejimida (assign_computers) kerak.
        $occupiedBySlot = [];
        if ($assignComputers && $examDate) {
            $batchScheduleIds = [];
            foreach ($subjectGroups as $sgOcc) {
                foreach ($sgOcc['entries'] as $entryOcc) {
                    if (!empty($entryOcc['schedule_id'])) {
                        $batchScheduleIds[(int) $entryOcc['schedule_id']] = true;
                    }
                }
            }
            $batchScheduleIds = array_keys($batchScheduleIds);
            $durationForOcc = (int) (ExamCapacityService::getSettingsForDate($examDate)['test_duration_minutes'] ?? 15);
            $slotTimesForOcc = [];
            foreach ($subjectGroups as $sgOcc) {
                $stOcc = $multiSlotMode ? (string) ($sgOcc['slot_time'] ?? '') : (string) ($examTime ?? '');
                if ($stOcc !== '') {
                    $slotTimesForOcc[$stOcc] = true;
                }
            }
            foreach (array_keys($slotTimesForOcc) as $stOcc) {
                try {
                    $winStart = \Carbon\Carbon::parse($examDate . ' ' . $stOcc);
                } catch (\Throwable $e) {
                    continue;
                }
                $winEnd = $winStart->copy()->addMinutes($durationForOcc);
                $occRows = \App\Models\ComputerAssignment::query()
                    ->whereNotNull('computer_number')
                    ->where('planned_start', '<', $winEnd)
                    ->where('planned_end', '>', $winStart)
                    ->where(function ($q) use ($batchScheduleIds) {
                        // in_progress — har qanday jadvalniki (kompyuter fizik band);
                        // scheduled — faqat bu taqsimotga kirmaydigan jadvallar
                        // (masalan, individual vaqt qo'yilgan talabalar).
                        $q->where('status', \App\Models\ComputerAssignment::STATUS_IN_PROGRESS);
                        if (!empty($batchScheduleIds)) {
                            $q->orWhere(function ($q2) use ($batchScheduleIds) {
                                $q2->where('status', \App\Models\ComputerAssignment::STATUS_SCHEDULED)
                                    ->whereNotIn('exam_schedule_id', $batchScheduleIds);
                            });
                        }
                    })
                    ->pluck('computer_number');
                $set = [];
                foreach ($occRows as $cn) {
                    if ($cn !== null) {
                        $set[(int) $cn] = true;
                    }
                }
                $occupiedBySlot[$stOcc] = $set;
            }
        }

        // Raqamlar Word'dagi qator tartibida beriladi — proktor qog'ozda
        // 1, 2, 3, ... ketma-ket o'qiy oladi. Slot ichida tartib: sahifa
        // tartibi (subjectGroups iteratsiyasi) → entry tartibi → entry ichida
        // talaba (Student::orderBy('full_name')) — har joyda turg'un.
        $computerNumberMap = []; // "slot|hemis_id" => int
        $slotCounters = [];      // slot => keyingi sinab ko'riladigan raqam
        foreach ($subjectGroups as $sg) {
            $slot = $multiSlotMode ? ($sg['slot_time'] ?? '') : ($examTime ?? '');
            if (!isset($slotCounters[$slot])) $slotCounters[$slot] = 1;
            $occSlot = $occupiedBySlot[$slot] ?? [];
            foreach ($sg['entries'] as $entry) {
                foreach ($entry['students'] as $st) {
                    $hemis = (string) $st->hemis_id;
                    $key = $slot . '|' . $hemis;
                    if (isset($computerNumberMap[$key])) continue;
                    while ($slotCounters[$slot] <= $configuredCompCount
                        && (isset($brokenLookup[$slotCounters[$slot]]) || isset($occSlot[$slotCounters[$slot]]))) {
                        $slotCounters[$slot]++;
                    }
                    if ($slotCounters[$slot] > $configuredCompCount) break 2;
                    $computerNumberMap[$key] = $slotCounters[$slot];
                    $slotCounters[$slot]++;
                }
            }
        }

        // DB persistence: faqat "Kompyuter raqamlarini taqsimlash" tugmasi
        // (assign_computers=true) chaqirilganda komp raqamlarini
        // ComputerAssignment'ga is_pinned=true bilan yozamiz. Word yuklab
        // olish endi raqam taqsimlamaydi — bu yozuv o'sha tugmaga ko'chirildi.
        // Shunda student portali, JIT/notification — hammasi shu raqamni
        // ko'radi va JIT tick job qayta belgilashga urinmaydi.
        //  - 1-urinish: mavjud "scheduled" qator UPDATE qilinadi.
        //  - 2-/3-urinish: yangi qator INSERT qilinadi (attempt ustuni mavjud).
        //  - status != scheduled bo'lganlar (in_progress/finished) tegmaydi.
        $persistedCount = 0;
        if ($assignComputers && $examDate && !empty($computerNumberMap)) {
            try {
                $durationMin = (int) (ExamCapacityService::getSettingsForDate($examDate)['test_duration_minutes'] ?? 15);
                // JIT tick job processReveal'i reveal_at <= now bo'lganda
                // Telegram yuboradi. Pinned qator shu loop'ga tushishi uchun
                // reveal_at'ni planned_start dan jit_minutes oldin o'rnatamiz.
                $jitMinutesBefore = max(1, (int) config('services.moodle.reveal_minutes_before', 10));
                // Stale cleanup uchun: har (schedule_id, yn_type, attempt) bo'yicha
                // bu chaqiriqda kim'lar pin qilinayotganini yig'amiz. Word so'ngida
                // shu kombinatsiyadagi boshqa "scheduled" qatorlar (avvalgi noto'g'ri
                // generatsiyadan qolganlar) o'chiriladi.
                $persistedKeys = []; // "schedule_id|yn_type|attempt" => [hemis_id, ...]

                foreach ($subjectGroups as $sg) {
                    $slot = $multiSlotMode ? ($sg['slot_time'] ?? '') : ($examTime ?? '');
                    if (empty($slot)) continue;
                    foreach ($sg['entries'] as $entry) {
                        $scheduleId = $entry['schedule_id'] ?? null;
                        $ynType = $entry['yn_type'] ?? null;
                        $attempt = (int) ($entry['attempt'] ?? 1);
                        if (!$scheduleId || !$ynType) continue;
                        $bucketKey = $scheduleId . '|' . $ynType . '|' . $attempt;
                        if (!isset($persistedKeys[$bucketKey])) $persistedKeys[$bucketKey] = [];

                        $plannedStart = \Carbon\Carbon::parse($examDate . ' ' . $slot);
                        $plannedEnd = $plannedStart->copy()->addMinutes($durationMin);

                        foreach ($entry['students'] as $st) {
                            $compNum = $computerNumberMap[$slot . '|' . $st->hemis_id] ?? null;
                            if ($compNum === null) continue;
                            $studentIdNumber = (string) ($st->student_id ?? '');
                            $hemis = (string) $st->hemis_id;
                            $persistedKeys[$bucketKey][] = $hemis;

                            $existing = \App\Models\ComputerAssignment::where('exam_schedule_id', $scheduleId)
                                ->where('yn_type', $ynType)
                                ->where('attempt', $attempt)
                                ->where('student_hemis_id', $hemis)
                                ->first();

                            // reveal_at: JIT tick processReveal shu vaqtdan
                            // keyin Telegram yuboradi. Allaqachon notify qilingan
                            // bo'lsa qayta yubormaydi (reveal_notified bayrog'i).
                            $revealAt = $plannedStart->copy()->subMinutes($jitMinutesBefore);

                            if ($existing) {
                                if ($existing->status !== \App\Models\ComputerAssignment::STATUS_SCHEDULED) {
                                    continue;
                                }
                                $existing->computer_number = $compNum;
                                $existing->is_pinned = true;
                                if (empty($existing->planned_start)) $existing->planned_start = $plannedStart;
                                if (empty($existing->planned_end))   $existing->planned_end = $plannedEnd;
                                // reveal_at yo'q bo'lsa o'rnatamiz; allaqachon
                                // bo'lsa tegmaymiz — admin/JIT'ga begona aralashmaslik.
                                if (empty($existing->reveal_at)) $existing->reveal_at = $revealAt;
                                $existing->save();
                            } else {
                                \App\Models\ComputerAssignment::create([
                                    'exam_schedule_id' => $scheduleId,
                                    'student_id_number' => $studentIdNumber,
                                    'student_hemis_id' => $hemis,
                                    'yn_type' => $ynType,
                                    'attempt' => $attempt,
                                    'computer_number' => $compNum,
                                    'planned_start' => $plannedStart,
                                    'planned_end' => $plannedEnd,
                                    'reveal_at' => $revealAt,
                                    'is_pinned' => true,
                                    'is_reserve' => false,
                                    'status' => \App\Models\ComputerAssignment::STATUS_SCHEDULED,
                                ]);
                            }
                        }
                    }
                }
                $persistedCount = array_sum(array_map('count', $persistedKeys));

                // Stale qatorlarni tozalash: items'da kelgan har bucket bo'yicha
                // (eligible students 0 bo'lsa ham) shu (schedule_id, yn_type,
                // attempt) bo'yicha scheduled qatorlar — kept'larsiz hammasi —
                // o'chiriladi. in_progress/finished'larga tegmaymiz.
                $allBucketsToClean = array_unique(array_merge(
                    array_keys($persistedKeys),
                    array_keys($itemBucketsSeen)
                ));
                foreach ($allBucketsToClean as $bucketKey) {
                    [$scheduleId, $ynType, $attempt] = explode('|', $bucketKey);
                    $keptHemisIds = $persistedKeys[$bucketKey] ?? [];
                    \App\Models\ComputerAssignment::where('exam_schedule_id', (int) $scheduleId)
                        ->where('yn_type', $ynType)
                        ->where('attempt', (int) $attempt)
                        ->where('status', \App\Models\ComputerAssignment::STATUS_SCHEDULED)
                        ->when(!empty($keptHemisIds), fn($q) => $q->whereNotIn('student_hemis_id', $keptHemisIds))
                        ->delete();
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('generateYnOldiWord: komp raqamini DB ga saqlashda xatolik', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // "Kompyuter raqamlarini taqsimlash" tugmasi: DB ga yozgandan keyin
        // og'ir Word generatsiyasini o'tkazib yuborib, JSON natija qaytaramiz.
        if ($assignComputers) {
            return response()->json(['ok' => true, 'assigned' => $persistedCount]);
        }

        // Word yuklab olish kompyuter raqamlarini TAQSIMLAMAYDI — mavjud
        // ComputerAssignment yozuvlaridan faqat o'qib, hujjatda ko'rsatadi.
        // (Avval yuqorida on-the-fly hisoblangan $computerNumberMap shu yerda
        // DB'dagi haqiqiy qiymatlar bilan almashtiriladi.)
        $computerNumberMap = $this->readPersistedComputerNumbers($subjectGroups, $multiSlotMode, $examTime);

        // Multi-slot rejimda: slot vaqti bo'yicha o'sish tartibida, ichida fan
        // bo'yicha — shunda 9:15 → 9:30 → 9:45 ketma-ketligida sahifalar
        // chiqadi. Yagona-slot/legacy rejimda tartib o'zgarmaydi.
        if ($multiSlotMode) {
            uksort($subjectGroups, function ($ka, $kb) use ($subjectGroups) {
                $ta = (string) ($subjectGroups[$ka]['slot_time'] ?? '');
                $tb = (string) ($subjectGroups[$kb]['slot_time'] ?? '');
                $cmp = strcmp($ta, $tb);
                if ($cmp !== 0) return $cmp;
                return strcmp((string) $ka, (string) $kb);
            });
            // Multi-slot — imzo va QR har sahifaga takror chiqib o'rin egallamasin.
            $compact = true;
        }

        // Multi-slot uchun bitta umumiy PhpWord; har section'i alohida sahifaga
        // page-break bilan chiqadi. Legacy rejimda har fan o'ziniki yaratiladi.
        $combinedPhpWord = null;
        if ($multiSlotMode) {
            $combinedPhpWord = new PhpWord();
            $combinedPhpWord->setDefaultFontName('Times New Roman');
            $combinedPhpWord->setDefaultFontSize(12);
        }

        // Compact rejim (Bandlik ko'rsatkichidan chaqirilgan) uchun yangi
        // soddalashtirilgan layout: SLOT bo'yicha guruhlanadi (bitta slot
        // = bitta sahifa), tepada faqat "Test markaziga kirish: SANA VAQT"
        // header, har fan jadvalining tepasida fan nomi. Talaba ko'p bo'lsa
        // ikki ustunli (2-up) joylashuv ishlatiladi va JN/MT/Davomat/Kontrakt
        // ustunlari olib tashlanadi.
        if ($compact) {
            try {
                $examDateFmt = $examDate
                    ? \Carbon\Carbon::createFromFormat('Y-m-d', $examDate)->format('d.m.Y')
                    : null;
            } catch (\Throwable $e) {
                $examDateFmt = $examDate;
            }

            // subjectGroups'ni slot bo'yicha guruhlash. multi-slot bo'lmagan
            // holatda barchasi bitta slotga (top-level $examTime'ga) tushadi.
            $slotGroups = [];
            foreach ($subjectGroups as $sk => $sd) {
                $st = $multiSlotMode
                    ? ((string) ($sd['slot_time'] ?? ''))
                    : ((string) ($examTime ?? ''));
                $slotGroups[$st][$sk] = $sd;
            }
            ksort($slotGroups);

            $phpWord = new PhpWord();
            $phpWord->setDefaultFontName('Times New Roman');
            $phpWord->setDefaultFontSize(10);
            // Default paragraph style: interval do/posle = 0,
            // mezhdustrochnyy = Tochno 14 pt (spacing=14pt*20twips=280).
            $phpWord->setDefaultParagraphStyle([
                'spaceBefore' => 0,
                'spaceAfter' => 0,
                'spacing' => 280,
                'spacingLineRule' => 'exact',
            ]);

            $tStyle = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 30, 'alignment' => 'center'];
            $phpWord->addTableStyle('BkSlotTable', $tStyle);

            $hF = ['bold' => true, 'size' => 10];
            $cF = ['size' => 10];
            $cFOr = ['size' => 10, 'color' => 'FF8C00'];
            $cFCmp = ['size' => 11, 'bold' => true, 'color' => '1E40AF'];
            $cFGroup = ['bold' => true, 'size' => 10, 'color' => '1F4E20'];
            $hBg = ['bgColor' => 'D9E2F3', 'valign' => 'center'];
            $groupBg = ['bgColor' => 'E2EFDA', 'valign' => 'center'];
            // Ko'rinmas separator katakcha — chap va o'ng yarmini ajratish uchun.
            $invisCell = [
                'borderTopSize' => 0, 'borderBottomSize' => 0,
                'borderLeftSize' => 0, 'borderRightSize' => 0,
                'borderTopColor' => 'FFFFFF', 'borderBottomColor' => 'FFFFFF',
                'borderLeftColor' => 'FFFFFF', 'borderRightColor' => 'FFFFFF',
            ];
            // Jadval ichidagi paragraf — interval 0/0, Tochno 14pt.
            $pPara = ['spaceBefore' => 0, 'spaceAfter' => 0, 'spacing' => 280, 'spacingLineRule' => 'exact'];
            $cCtr = array_merge(['alignment' => Jc::CENTER], $pPara);
            $cLeft = array_merge(['alignment' => Jc::START], $pPara);

            // Sahifa kengligiga moslangan ustun kengliklari.
            // Landscape A4 ≈ 16838 twips. Margin chap+o'ng = 700+500=1200.
            // Foydalanishga 15638 twips qoladi. Marginni qisqartirib kengroq
            // foydalanamiz: chap=400, o'ng=400 → 16038 twips foydalaniladi.
            // 2-up uchun bir tomon: №=420, FIO=5000, Holat=1100, Komp=700,
            // Belgi=500 = 7720. Ko'rinmas separator = 350. Jami: 7720*2+350 =
            // 15790 (16038'dan past — sig'adi).
            $cw2 = [420, 5000, 1100, 700, 500]; // 2-up per side
            $cwSep = 350;

            // Fan variantlarini (a)/(b)/(c) suffiks bo'yicha BIRLASHTIRISH
            // uchun helper. Misol: "Ichki kasalliklar propedevtikasi (a)" va
            // "...(b)" bir fanga (kategoriya nomi) yig'iladi.
            $normalizeSubjectName = function (string $name): string {
                $n = preg_replace('/\s*\([a-zA-Zа-яА-Я]\)\s*$/u', '', (string) $name);
                return trim((string) $n);
            };

            foreach ($slotGroups as $slotTime => $slotSubjectsRaw) {
                // Slot ichida fanlarni normalize qilingan nom bo'yicha
                // birlashtirish (a/b/c variantlari bir fanga jamlanadi,
                // entries to'plami umumlashtiriladi).
                $slotSubjects = [];
                foreach ($slotSubjectsRaw as $sk => $sd) {
                    $rawName = $sd['subject']->subject_name ?? '';
                    $normName = $normalizeSubjectName($rawName) ?: $rawName;
                    if (!isset($slotSubjects[$normName])) {
                        $sd['display_name'] = $normName;
                        $slotSubjects[$normName] = $sd;
                    } else {
                        $slotSubjects[$normName]['entries'] = array_merge(
                            $slotSubjects[$normName]['entries'],
                            $sd['entries']
                        );
                    }
                }
                ksort($slotSubjects);

                $slotTotal = 0;
                foreach ($slotSubjects as $sd) {
                    foreach ($sd['entries'] as $e) {
                        $slotTotal += count($e['students']);
                    }
                }
                $twoUp = $slotTotal > 25;

                $section = $phpWord->addSection([
                    'orientation' => 'landscape',
                    'marginTop' => 400, 'marginBottom' => 400,
                    'marginLeft' => 400, 'marginRight' => 400,
                ]);

                $headLine = 'Test markaziga kirish: ' . ($examDateFmt ?? '') . ($slotTime ? ' ' . $slotTime : '');
                $section->addText(
                    trim($headLine),
                    ['bold' => true, 'size' => 13],
                    ['alignment' => Jc::CENTER, 'spaceBefore' => 0, 'spaceAfter' => 60, 'spacing' => 280, 'spacingLineRule' => 'exact']
                );

                $subjIdx = 0;
                foreach ($slotSubjects as $sk => $subjectData) {
                    $subjIdx++;
                    $subj = $subjectData['subject'];
                    $displayName = $subjectData['display_name'] ?? ($subj->subject_name ?? '');

                    $attemptsInSubject = [];
                    $ynTypesInSubject = [];
                    foreach ($subjectData['entries'] as $_e) {
                        $_a = (int) ($_e['attempt'] ?? 1);
                        if ($_a > 0 && !in_array($_a, $attemptsInSubject, true)) {
                            $attemptsInSubject[] = $_a;
                        }
                        $_yn = strtolower((string) ($_e['yn_type'] ?? ''));
                        if ($_yn === 'oski' || $_yn === 'test') {
                            $_ynLabel = $_yn === 'oski' ? 'OSKI' : 'Test';
                            if (!in_array($_ynLabel, $ynTypesInSubject, true)) {
                                $ynTypesInSubject[] = $_ynLabel;
                            }
                        }
                    }
                    sort($attemptsInSubject);
                    if (!empty($ynTypesInSubject)) {
                        $displayName = implode('/', $ynTypesInSubject) . ' — ' . $displayName;
                    }
                    if (!empty($attemptsInSubject)) {
                        $displayName .= ' (' . implode('/', $attemptsInSubject) . '-urinish)';
                    }

                    $section->addText(
                        $displayName,
                        ['bold' => true, 'size' => 11],
                        ['alignment' => Jc::CENTER, 'spaceBefore' => $subjIdx > 1 ? 120 : 30, 'spaceAfter' => 30, 'spacing' => 280, 'spacingLineRule' => 'exact']
                    );

                    $table = $section->addTable('BkSlotTable');

                    if ($twoUp) {
                        // Header (ID olib tashlandi)
                        $hr = $table->addRow(280);
                        // Chap yarim
                        $hr->addCell($cw2[0], $hBg)->addText('№', $hF, $cCtr);
                        $hr->addCell($cw2[1], $hBg)->addText('Talaba F.I.O', $hF, $cCtr);
                        $hr->addCell($cw2[2], $hBg)->addText('YN ga ruxsat', $hF, $cCtr);
                        $hr->addCell($cw2[3], $hBg)->addText('Komp №', $hF, $cCtr);
                        $hr->addCell($cw2[4], $hBg)->addText('Belgi', $hF, $cCtr);
                        // Ko'rinmas separator
                        $hr->addCell($cwSep, $invisCell)->addText('', $cF, $cCtr);
                        // O'ng yarim
                        $hr->addCell($cw2[0], $hBg)->addText('№', $hF, $cCtr);
                        $hr->addCell($cw2[1], $hBg)->addText('Talaba F.I.O', $hF, $cCtr);
                        $hr->addCell($cw2[2], $hBg)->addText('YN ga ruxsat', $hF, $cCtr);
                        $hr->addCell($cw2[3], $hBg)->addText('Komp №', $hF, $cCtr);
                        $hr->addCell($cw2[4], $hBg)->addText('Belgi', $hF, $cCtr);
                    } else {
                        // 1-up (ID olib tashlandi, FIO kengroq)
                        $hr = $table->addRow(320);
                        $hr->addCell(500, $hBg)->addText('№', $hF, $cCtr);
                        $hr->addCell(7000, $hBg)->addText('Talaba F.I.O', $hF, $cCtr);
                        $hr->addCell(800, $hBg)->addText('JN', $hF, $cCtr);
                        $hr->addCell(800, $hBg)->addText('MT', $hF, $cCtr);
                        $hr->addCell(1200, $hBg)->addText('Davomat %', $hF, $cCtr);
                        $hr->addCell(1300, $hBg)->addText('Kontrakt', $hF, $cCtr);
                        $hr->addCell(1500, $hBg)->addText('YN ga ruxsat', $hF, $cCtr);
                        $hr->addCell(900, $hBg)->addText('Komp №', $hF, $cCtr);
                        $hr->addCell(700, $hBg)->addText('Belgi', $hF, $cCtr);
                    }

                    // Talabalarni guruh bo'yicha guruhlash, har guruh ichida ism tartibida
                    $byGroup = []; // group_name => [['student'=>..., 'entry'=>...], ...]
                    foreach ($subjectData['entries'] as $entry) {
                        $gName = (string) ($entry['group']->name ?? '-');
                        foreach ($entry['students'] as $st) {
                            $byGroup[$gName][] = ['student' => $st, 'entry' => $entry];
                        }
                    }
                    foreach ($byGroup as $gName => &$lst) {
                        usort($lst, fn($a, $b) => strcmp((string) $a['student']->student_name, (string) $b['student']->student_name));
                    }
                    unset($lst);
                    // Guruhlarni tabiiy (natural) tartibda saralash —
                    // d1/22-01a → 01b → 02a → 10a (sof alfabetda 10a 1a'dan
                    // oldin chiqib qolardi).
                    uksort($byGroup, 'strnatcmp');

                    $sectionExamTime = $slotTime ?: $examTime;

                    // Talaba ismiga individual imtihon vaqtini qo'shadi — agar
                    // talabaga guruh slot vaqtidan FARQLI shaxsiy vaqt qo'yilgan
                    // bo'lsa, Word jadvalida "F.I.O (HH:MM)" ko'rinishida chiqadi.
                    $studentNameWithTime = function ($stu) use ($sectionExamTime) {
                        $name = (string) ($stu->student_name ?? '');
                        $t = $stu->individual_time ?? null;
                        if (!empty($t) && $t !== $sectionExamTime) {
                            $name .= ' (' . $t . ')';
                        }
                        return $name;
                    };

                    $defaultCutoffs = json_encode([
                        ['deadline' => '2025-10-01', 'percent' => 25],
                        ['deadline' => '2026-01-01', 'percent' => 50],
                        ['deadline' => '2026-03-01', 'percent' => 75],
                        ['deadline' => '2026-05-01', 'percent' => 100],
                    ]);
                    $cutoffsRaw = json_decode(Setting::get('contract_cutoffs', $defaultCutoffs), true) ?: [];
                    $nowTs = time();
                    $contractThreshold = 100;
                    foreach ($cutoffsRaw as $cu) {
                        if ($nowTs <= strtotime($cu['deadline'] . ' 23:59:59')) {
                            $contractThreshold = (int) $cu['percent']; break;
                        }
                    }

                    $renderStudentInfo = function ($student, $entry) use (
                        $sectionExamTime, $computerNumberMap, $contractThreshold
                    ) {
                        $entryGroup = $entry['group'];
                        $entrySubject = $entry['subject'];
                        $entrySemesterCode = $entry['semester']->code ?? null;

                        $nonAud = ['17']; $aud = 0;
                        if (is_array($entrySubject->subject_details)) {
                            foreach ($entrySubject->subject_details as $d) {
                                $tc = (string) (($d['trainingType'] ?? [])['code'] ?? '');
                                if ($tc !== '' && !in_array($tc, $nonAud)) $aud += (float) ($d['academic_load'] ?? 0);
                            }
                        }
                        if ($aud <= 0) $aud = (float) ($entrySubject->total_acload ?: 1);

                        $qoldirgan = (int) Attendance::where('group_id', $entryGroup->group_hemis_id)
                            ->where('subject_id', $entrySubject->subject_id)
                            ->where('student_hemis_id', $student->hemis_id)
                            ->when($entrySemesterCode, fn($q) => $q->where('semester_code', $entrySemesterCode))
                            ->whereNotIn('training_type_code', [99, 100, 101, 102])
                            ->sum('absent_off');
                        $qoldiq = round($qoldirgan * 100 / $aud, 2);

                        $contract = ContractList::where('student_hemis_id', $student->hemis_id)
                            ->where('year', '2025')->where('edu_year', 'like', '2025-2026%')->first();
                        $contractText = '-'; $contractFailed = false;
                        if ($contract && $contract->edu_contract_sum > 0) {
                            $contractPct = round(($contract->paid_credit_amount / $contract->edu_contract_sum) * 100);
                            $contractText = $contractPct . '%';
                            if ($contractPct < $contractThreshold) $contractFailed = true;
                        }
                        $holat = $student->admission_status ?? 'Ruxsat';
                        if (!in_array($holat, ['Ruxsat', 'Shartli', 'X'])) $holat = 'Ruxsat';

                        return [
                            'jn' => $student->jn ?? 0,
                            'mt' => $student->mt ?? 0,
                            'qoldiq' => $qoldiq,
                            'contractText' => $contractText,
                            'contractFailed' => $contractFailed,
                            'holat' => $holat,
                            'compNum' => $computerNumberMap[$sectionExamTime . '|' . $student->hemis_id] ?? null,
                        ];
                    };

                    if ($twoUp) {
                        // 2-up rejimi — har guruh alohida segmentda: oldida
                        // guruh nomi separator qatori, keyin guruh ichidagi
                        // talabalar 2 ustunga taqsimlanadi. Raqamlar HAR
                        // USTUN ICHIDA KETMA-KET: chap = 1,2,3,4; o'ng =
                        // 5,6,7. Bu o'qish uchun qulayroq (chap ustun to'liq
                        // tugagandan keyin o'ng o'qiladi).
                        $globalBase = 1; // joriy fan/guruhdagi boshlang'ich raqam
                        $totalCells = 11; // 5 + 1 sep + 5
                        foreach ($byGroup as $gName => $list) {
                            // Guruh separator qatori — barcha katakchalarni qamrab oladi
                            $gr = $table->addRow(260);
                            $gr->addCell(
                                $cw2[0] + $cw2[1] + $cw2[2] + $cw2[3] + $cw2[4] + $cwSep + $cw2[0] + $cw2[1] + $cw2[2] + $cw2[3] + $cw2[4],
                                array_merge($groupBg, ['gridSpan' => $totalCells])
                            )->addText('Guruh: ' . $gName, $cFGroup, $cCtr);

                            $cnt = count($list);
                            $half = (int) ceil($cnt / 2);
                            for ($i = 0; $i < $half; $i++) {
                                $dr = $table->addRow();

                                // CHAP yarim — i-chi talaba, raqami $globalBase + $i
                                $leftRow = $list[$i] ?? null;
                                if ($leftRow) {
                                    $stu = $leftRow['student']; $ent = $leftRow['entry'];
                                    $info = $renderStudentInfo($stu, $ent);
                                    $dr->addCell($cw2[0])->addText((string) ($globalBase + $i), $cF, $cCtr);
                                    $dr->addCell($cw2[1])->addText($studentNameWithTime($stu), $cF, $cLeft);
                                    $hCell = $dr->addCell($cw2[2]);
                                    $hCell->addText($info['holat'], $info['holat'] === 'Shartli' ? $cFOr : $cF, $cCtr);
                                    $dr->addCell($cw2[3])->addText(
                                        $info['compNum'] !== null ? (string) $info['compNum'] : '—',
                                        $info['compNum'] !== null ? $cFCmp : $cF, $cCtr
                                    );
                                    $dr->addCell($cw2[4])->addText('☐', ['size' => 14], $cCtr);
                                } else {
                                    $dr->addCell($cw2[0])->addText('', $cF, $cCtr);
                                    $dr->addCell($cw2[1])->addText('', $cF, $cCtr);
                                    $dr->addCell($cw2[2])->addText('', $cF, $cCtr);
                                    $dr->addCell($cw2[3])->addText('', $cF, $cCtr);
                                    $dr->addCell($cw2[4])->addText('', $cF, $cCtr);
                                }

                                // Chap-o'ng o'rtasidagi ko'rinmas separator
                                $dr->addCell($cwSep, $invisCell)->addText('', $cF, $cCtr);

                                // O'NG yarim — ($i + $half)-chi talaba, raqami $globalBase + $half + $i
                                $rightRow = $list[$i + $half] ?? null;
                                if ($rightRow) {
                                    $stu = $rightRow['student']; $ent = $rightRow['entry'];
                                    $info = $renderStudentInfo($stu, $ent);
                                    $dr->addCell($cw2[0])->addText((string) ($globalBase + $half + $i), $cF, $cCtr);
                                    $dr->addCell($cw2[1])->addText($studentNameWithTime($stu), $cF, $cLeft);
                                    $hCell = $dr->addCell($cw2[2]);
                                    $hCell->addText($info['holat'], $info['holat'] === 'Shartli' ? $cFOr : $cF, $cCtr);
                                    $dr->addCell($cw2[3])->addText(
                                        $info['compNum'] !== null ? (string) $info['compNum'] : '—',
                                        $info['compNum'] !== null ? $cFCmp : $cF, $cCtr
                                    );
                                    $dr->addCell($cw2[4])->addText('☐', ['size' => 14], $cCtr);
                                } else {
                                    $dr->addCell($cw2[0])->addText('', $cF, $cCtr);
                                    $dr->addCell($cw2[1])->addText('', $cF, $cCtr);
                                    $dr->addCell($cw2[2])->addText('', $cF, $cCtr);
                                    $dr->addCell($cw2[3])->addText('', $cF, $cCtr);
                                    $dr->addCell($cw2[4])->addText('', $cF, $cCtr);
                                }
                            }
                            $globalBase += $cnt;
                        }
                    } else {
                        // 1-ustun, guruh separator qatorlari bilan
                        $globalIdx = 1;
                        $totalCells = 9; // №, FIO, JN, MT, Dav, Kontr, Holat, Komp, Belgi
                        foreach ($byGroup as $gName => $list) {
                            $gr = $table->addRow(260);
                            $gr->addCell(15000, array_merge($groupBg, ['gridSpan' => $totalCells]))
                                ->addText('Guruh: ' . $gName, $cFGroup, $cCtr);
                            foreach ($list as $row) {
                                $stu = $row['student']; $ent = $row['entry'];
                                $info = $renderStudentInfo($stu, $ent);
                                $dr = $table->addRow();
                                $dr->addCell(500)->addText((string) $globalIdx, $cF, $cCtr);
                                $dr->addCell(7000)->addText($studentNameWithTime($stu), $cF, $cLeft);
                                $dr->addCell(800)->addText((string) ($info['jn'] ?? '0'), $cF, $cCtr);
                                $dr->addCell(800)->addText((string) ($info['mt'] ?? '0'), $cF, $cCtr);
                                $dr->addCell(1200)->addText(($info['qoldiq'] != 0 ? $info['qoldiq'] . '%' : '0%'), $cF, $cCtr);
                                $dr->addCell(1300)->addText($info['contractText'], $info['contractFailed'] ? $cFOr : $cF, $cCtr);
                                $hCell = $dr->addCell(1500);
                                $hCell->addText($info['holat'], $info['holat'] === 'Shartli' ? $cFOr : $cF, $cCtr);
                                $dr->addCell(900)->addText(
                                    $info['compNum'] !== null ? (string) $info['compNum'] : '—',
                                    $info['compNum'] !== null ? $cFCmp : $cF, $cCtr
                                );
                                $dr->addCell(700)->addText('☐', ['size' => 14], $cCtr);
                                $globalIdx++;
                            }
                        }
                    }
                }
            }

            // Saqlash va yuborish
            $timesPart = !empty($slotGroups) ? implode('-', array_map(
                fn($t) => str_replace(':', '', (string) $t),
                array_keys($slotGroups)
            )) : 'slot';
            $combinedName = 'YN_oldi_qaydnoma_' . ($examDate ?: 'slot') . '_' . $timesPart . '.docx';
            $combinedPath = $tempDir . '/' . time() . '_' . mt_rand(1000, 9999) . '_' . $combinedName;
            IOFactory::createWriter($phpWord, 'Word2007')->save($combinedPath);
            return response()->download($combinedPath, $combinedName)->deleteFileAfterSend(true);
        }

        // Step 2: Har bir fan uchun bitta Word hujjat yaratish
        foreach ($subjectGroups as $subjectKey => $subjectData) {
            $subject = $subjectData['subject'];
            $semester = $subjectData['semester'];
            $department = $subjectData['department'];
            $groupNames = $subjectData['groupNames'];
            $allMaruzaText = implode(', ', $subjectData['allMaruzaTeachers']) ?: '-';
            $allOtherText = implode(', ', $subjectData['allOtherTeachers']) ?: '-';
            // Multi-slot rejimda har bo'lim o'z slot vaqtini ko'rsatadi
            // (top-level $examTime o'rniga).
            $sectionExamTime = $multiSlotMode
                ? ($subjectData['slot_time'] ?: $examTime)
                : $examTime;

            // Word hujjat yaratish — multi-slot bo'lsa umumiy hujjatdan section,
            // aks holda har fan uchun yangi PhpWord.
            if ($multiSlotMode) {
                $phpWord = $combinedPhpWord;
            } else {
                $phpWord = new PhpWord();
                $phpWord->setDefaultFontName('Times New Roman');
                $phpWord->setDefaultFontSize(12);
            }

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

            $ynTypesForSubject = [];
            foreach ($subjectData['entries'] as $_e) {
                $_yn = strtolower((string) ($_e['yn_type'] ?? ''));
                if ($_yn === 'oski' || $_yn === 'test') {
                    $_ynLabel = $_yn === 'oski' ? 'OSKI' : 'Test';
                    if (!in_array($_ynLabel, $ynTypesForSubject, true)) {
                        $ynTypesForSubject[] = $_ynLabel;
                    }
                }
            }
            $subjectNameWithYn = $subject->subject_name ?? '-';
            if (!empty($ynTypesForSubject)) {
                $subjectNameWithYn = implode('/', $ynTypesForSubject) . ' — ' . $subjectNameWithYn;
            }

            $textRun = $section->addTextRun($infoParaStyle);
            $textRun->addText('Fan: ', $infoBold);
            $textRun->addText($subjectNameWithYn, $infoStyle);

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

            // Test markazida slotga kirish sanasi va vaqti (faqat bandlikdan
            // chaqirilganda — bandlik view'i exam_date/exam_time yuboradi).
            // Multi-slot rejimda har bo'lim o'z slot vaqtini chiqaradi.
            if ($examDate) {
                try {
                    $examDateFmt = \Carbon\Carbon::createFromFormat('Y-m-d', $examDate)->format('d.m.Y');
                } catch (\Throwable $e) {
                    $examDateFmt = $examDate;
                }
                $examLine = 'Test markaziga kirish: ' . $examDateFmt . ($sectionExamTime ? ' ' . $sectionExamTime : '');
                $section->addText(
                    $examLine,
                    ['bold' => true, 'size' => 12],
                    ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
                );
            }

            // Jadval — sahifa o'rtasida joylashtiriladi
            $tableStyle = [
                'borderSize' => 6,
                'borderColor' => '000000',
                'cellMargin' => 40,
                'alignment' => 'center',
            ];
            $tableName = 'YnOldiTable_' . $subjectKey;
            $phpWord->addTableStyle($tableName, $tableStyle);
            $table = $section->addTable($tableName);

            $headerFont = ['bold' => true, 'size' => 10];
            $cellFont = ['size' => 10];
            $cellFontRed = ['size' => 10, 'color' => 'FF0000'];
            $headerBg = ['bgColor' => 'D9E2F3', 'valign' => 'center'];
            // gridSpan 10 — yangi "Komp №" va "Belgi" ustunlari uchun.
            $groupSeparatorBg = ['bgColor' => 'E2EFDA', 'valign' => 'center', 'gridSpan' => 10];
            $cellCenter = ['alignment' => Jc::CENTER];
            $cellLeft = ['alignment' => Jc::START];
            $cellFontOrange = ['size' => 10, 'color' => 'FF8C00'];
            $cellFontComp = ['size' => 11, 'bold' => true, 'color' => '1E40AF'];

            $headerRow = $table->addRow(400);
            $headerRow->addCell(500, $headerBg)->addText('№', $headerFont, $cellCenter);
            $headerRow->addCell(3500, $headerBg)->addText('Talaba F.I.O', $headerFont, $cellCenter);
            $headerRow->addCell(1500, $headerBg)->addText('Talaba ID', $headerFont, $cellCenter);
            $headerRow->addCell(800, $headerBg)->addText('JN', $headerFont, $cellCenter);
            $headerRow->addCell(800, $headerBg)->addText('MT', $headerFont, $cellCenter);
            $headerRow->addCell(1100, $headerBg)->addText('Davomat %', $headerFont, $cellCenter);
            $headerRow->addCell(1200, $headerBg)->addText('Kontrakt', $headerFont, $cellCenter);
            $headerRow->addCell(1400, $headerBg)->addText('YN ga ruxsat', $headerFont, $cellCenter);
            $headerRow->addCell(900, $headerBg)->addText('Komp №', $headerFont, $cellCenter);
            $headerRow->addCell(700, $headerBg)->addText('Belgi', $headerFont, $cellCenter);

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
                    // Eslatma: 4+ qarzdor va admission_status=X talabalar
                    // ro'yxatga olishdan oldin yuqorida filterlangan, shuning
                    // uchun bu yerda "X" override'i kerak emas — Word faqat
                    // Ruxsat / Shartli statusli talabalarni ko'rsatadi.

                    $dataRow = $table->addRow();
                    $dataRow->addCell(500)->addText($rowNum, $cellFont, $cellCenter);
                    $dataRow->addCell(3500)->addText($student->student_name, $cellFont, $cellLeft);
                    $dataRow->addCell(1500)->addText($student->student_id, $cellFont, $cellCenter);

                    $jnCell = $dataRow->addCell(800);
                    $jnCell->addText($student->jn ?? '0', $jnFailed ? $cellFontRed : $cellFont, $cellCenter);

                    $mtCell = $dataRow->addCell(800);
                    $mtCell->addText($student->mt ?? '0', $mtFailed ? $cellFontRed : $cellFont, $cellCenter);

                    $davomatCell = $dataRow->addCell(1100);
                    $davomatCell->addText(
                        ($qoldiq != 0 ? $qoldiq . '%' : '0%'),
                        $davomatFailed ? $cellFontRed : $cellFont,
                        $cellCenter
                    );

                    $kontraktCell = $dataRow->addCell(1200);
                    $kontraktCell->addText(
                        $contractText,
                        $contractFailed ? $cellFontOrange : $cellFont,
                        $cellCenter
                    );

                    $holatCell = $dataRow->addCell(1400);
                    if ($holat === 'Shartli') {
                        $holatCell->addText($holat, $cellFontOrange, $cellCenter);
                    } else {
                        $holatCell->addText($holat, $holat === 'X' ? $cellFontRed : $cellFont, $cellCenter);
                    }

                    // Komp № — shu slot bo'yicha tartibli raqam (buzilganlar
                    // chetlab o'tilgan). Map yuqorida slot bo'yicha hisoblangan.
                    $compNum = $computerNumberMap[$sectionExamTime . '|' . $student->hemis_id] ?? null;
                    $compCell = $dataRow->addCell(900);
                    $compCell->addText(
                        $compNum !== null ? (string) $compNum : '—',
                        $compNum !== null ? $cellFontComp : $cellFont,
                        $cellCenter
                    );

                    // Belgi — proktor chop etilgan ro'yxatda qo'lda belgilash
                    // uchun bo'sh kvadrat (U+2610 BALLOT BOX).
                    $belgiCell = $dataRow->addCell(700);
                    $belgiCell->addText('☐', ['size' => 16], $cellCenter);

                    $rowNum++;
                }
            }

            // Imzolar va QR — faqat to'liq (rasmiy) versiyada. Bandlik ko'rsatkichidan
            // chaqirilganda (compact=true) operator uchun toza ro'yxat chiqadi: imzo
            // bloki, QR va PDF verifikatsiya — kerak emas.
            $verification = null;
            $qrImagePath = null;
            if (!$compact) {
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
            }

            // Multi-slot rejimda har section umumiy hujjatga yoziladi — fayl
            // saqlash pastda bir martagina amalga oshiriladi. QR/PDF ham
            // shunga mos ravishda tashlab yuboriladi (compact=true majburiy).
            if ($multiSlotMode) {
                continue;
            }

            $groupNamesStr = str_replace(['/', '\\', ' '], '_', implode('_', $groupNames));
            $subjectNameStr = str_replace(['/', '\\', ' '], '_', $subject->subject_name);
            $fileName = 'YN_oldi_qaydnoma_' . $groupNamesStr . '_' . $subjectNameStr . '.docx';
            $tempPath = $tempDir . '/' . time() . '_' . mt_rand(1000, 9999) . '_' . $fileName;

            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($tempPath);

            // Hujjatni PDF ga aylantirib doimiy saqlash (tekshirish sahifasi uchun).
            // Compact rejimda verification yaratilmaydi — PDF ham kerak emas.
            if ($verification) {
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

        // Multi-slot rejimda barcha bo'limlar bitta umumiy hujjatga yig'ilgan —
        // shu yerda bir martagina yoziladi va birdaniga qaytariladi.
        if ($multiSlotMode && $combinedPhpWord !== null) {
            $timeParts = [];
            foreach ($subjectGroups as $sg) {
                $t = $sg['slot_time'] ?? '';
                if ($t !== '') $timeParts[$t] = true;
            }
            $timeParts = array_keys($timeParts);
            sort($timeParts);
            $timesSlug = $timeParts ? implode('-', array_map(fn($t) => str_replace(':', '', $t), $timeParts)) : 'slotlar';
            $combinedName = 'YN_oldi_qaydnoma_' . $timesSlug . '.docx';
            $combinedPath = $tempDir . '/' . time() . '_' . mt_rand(1000, 9999) . '_' . $combinedName;
            IOFactory::createWriter($combinedPhpWord, 'Word2007')->save($combinedPath);
            return response()->download($combinedPath, $combinedName)->deleteFileAfterSend(true);
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
                'broken_computers' => is_array($override->broken_computers) ? $override->broken_computers : [],
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
            // CSV ("5, 12, 38") yoki array — controller ichida normalize qilamiz.
            'broken_computers' => 'nullable',
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

        // Buzilgan komp raqamlarini normalize qilish: CSV/string/array — barchasi
        // saralangan unique tamsayilar massiviga aylantiriladi. Bo'sh stringni
        // "ko'rsatilmagan" (null) deb qabul qilamiz.
        $brokenRaw = $request->input('broken_computers');
        $brokenList = null;
        if ($brokenRaw !== null) {
            $items = is_array($brokenRaw)
                ? $brokenRaw
                : preg_split('/[\s,;]+/', (string) $brokenRaw, -1, PREG_SPLIT_NO_EMPTY);
            $clean = [];
            foreach ((array) $items as $n) {
                $n = (int) trim((string) $n);
                if ($n > 0) $clean[$n] = true;
            }
            if (!empty($clean)) {
                $brokenList = array_keys($clean);
                sort($brokenList);
            } elseif ($brokenRaw !== '' && $brokenRaw !== null && $brokenRaw !== []) {
                // Aniq bo'sh ro'yxat berilgan (foydalanuvchi tozaladi)
                $brokenList = [];
            }
        }

        $hasAny = $request->filled('work_hours_start')
            || $request->filled('work_hours_end')
            || $request->filled('lunch_start')
            || $request->filled('lunch_end')
            || $request->filled('computer_count')
            || $request->filled('test_duration_minutes')
            || $brokenList !== null
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
                    'broken_computers' => $brokenList,
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
    /**
     * Berilgan count uchun BEST-FIT slot — sig'adi va eng KAM bo'sh joy
     * qoldiradigan slot tanlanadi. Bir necha mos slot bo'lsa, eng ertasi.
     * Avval first-fit ishlatardi; best-fit "tightest fit" packing beradi —
     * deyarli to'la slotlarga aniq mos guruhlar tushadi, bo'sh slotlar
     * keyinroq guruhlar uchun qoldiriladi.
     */
    private function findSlotForCount(
        int $count,
        string $dateStr,
        array $capacity,
        array &$slotMap
    ): ?string {
        if ($count <= 0) {
            return $capacity['work_hours_start'] ?? '09:00';
        }
        $computerCount = AutoAssignService::effectiveSlotCapacity($capacity);
        $duration = max(1, (int) $capacity['test_duration_minutes']);

        $parseHm = function (string $hm): int {
            $p = explode(':', $hm);
            return ((int) ($p[0] ?? 0)) * 60 + ((int) ($p[1] ?? 0));
        };

        $workStartMin = $parseHm((string) $capacity['work_hours_start']);
        $workEndMin = $parseHm((string) $capacity['work_hours_end']);
        $lunchStartMin = !empty($capacity['lunch_start']) ? $parseHm((string) $capacity['lunch_start']) : null;
        $lunchEndMin = !empty($capacity['lunch_end']) ? $parseHm((string) $capacity['lunch_end']) : null;

        $existing = $slotMap[$dateStr] ?? [];

        $bestStart = null;
        $bestLeftover = null;

        $candidateMin = $workStartMin;
        $iterations = 0;
        while ($iterations++ < 300 && ($candidateMin + $duration) <= $workEndMin) {
            $candStart = $candidateMin;
            $candEnd = $candidateMin + $duration;

            if ($lunchStartMin !== null && $lunchEndMin !== null
                && $candStart < $lunchEndMin && $candEnd > $lunchStartMin) {
                $candidateMin += $duration;
                continue;
            }

            $concurrent = 0;
            foreach ($existing as $entry) {
                $eStart = $entry['start_min'];
                $eEnd = $eStart + $duration;
                if ($eStart < $candEnd && $eEnd > $candStart) {
                    $concurrent += $entry['count'];
                }
            }

            $leftover = $computerCount - $concurrent - $count;
            if ($leftover >= 0) {
                // Best-fit: eng kam leftover (slot bo'shlig'i)
                if ($bestLeftover === null || $leftover < $bestLeftover) {
                    $bestLeftover = $leftover;
                    $bestStart = $candStart;
                    if ($leftover === 0) {
                        break; // perfect fit — search'ni to'xtatamiz
                    }
                }
            }

            $candidateMin += $duration;
        }

        if ($bestStart === null) {
            return null;
        }

        $slotMap[$dateStr][] = ['start_min' => $bestStart, 'count' => $count];
        $h = intdiv($bestStart, 60);
        $m = $bestStart % 60;
        return sprintf('%02d:%02d', $h, $m);
    }

    /**
     * Resit yozuvi uchun sig'imni hisobga olib eng erta MOS slotni topadi
     * (first-fit). Resit guruhlari odatda kichik (1-2 talaba), shu sabab
     * first-fit ularni 1-urinish to'liq to'ldirmagan slotlarga ketma-ket
     * to'ldirib boradi (masalan, 09:00 da 58/60 bo'lsa, 1 talabali resit
     * shu yerga tushadi va slot 60 ga to'ladi).
     *
     * Talaba soni: 2-/3-urinish uchun butun guruh emas, faqat qayta
     * topshiruvchi talabalar soni hisoblanadi ($attemptNeedsMap orqali).
     * Aks holda 1 talabali resit 15 talabali deb ko'rilib, kichik bo'shliqlar
     * (masalan 16:30 da 1 ta) o'tkazib yuboriladi.
     *
     * $slotMap[date] = [['start_min' => 540, 'count' => 12], ...]
     */
    private function findResitSlot(
        \App\Models\ExamSchedule $schedule,
        string $ynType,
        int $attempt,
        string $dateStr,
        array $capacity,
        array &$slotMap,
        array $groupCountMap,
        array $attemptNeedsMap
    ): ?string {
        $groupCount = $this->resolveAttemptStudentCount(
            $schedule, $attempt, $groupCountMap, $attemptNeedsMap
        );
        return $this->findSlotForCount($groupCount, $dateStr, $capacity, $slotMap);
    }

    /**
     * Schedule + attempt uchun haqiqiy talaba sonini qaytaradi.
     * 1-urinish: butun guruh. 2-/3-urinish: qayta topshiruvchi talabalar
     * soni (needsByKey lookup'idan).
     */
    private function resolveAttemptStudentCount(
        \App\Models\ExamSchedule $schedule,
        int $attempt,
        array $groupCountMap,
        array $attemptNeedsMap
    ): int {
        if ($attempt === 1) {
            return (int) ($groupCountMap[$schedule->group_hemis_id] ?? 0);
        }
        $key = $schedule->group_hemis_id . '|' . ($schedule->subject_id ?? '') . '|' . ($schedule->semester_code ?? '') . '|' . $attempt;
        $needs = (int) ($attemptNeedsMap[$key] ?? 0);
        if ($needs > 0) {
            return $needs;
        }
        // Per-student qator (individual grafik) — student_hemis_id bo'lsa 1 talaba
        if (!empty($schedule->student_hemis_id)) {
            return 1;
        }
        // Fallback: butun guruh (xavfsiz over-estimate)
        return (int) ($groupCountMap[$schedule->group_hemis_id] ?? 0);
    }

    /**
     * autoTimeAll uchun: berilgan sana oralig'idagi schedule'lardan
     * (yn_type × attempt) slot bandligini in-memory map'larga yig'adi.
     *
     * Qaytaradi:
     *   [
     *     'all'   => [date => [['start_min', 'count'], ...]],  // hammasi
     *     'resit' => [date => [['start_min', 'count'], ...]],  // faqat 2-/3-urinish
     *   ]
     *
     * 1-urinish vaqtlari ComputerAssignment jadvalida bo'ladi, shu sabab
     * distribute() ularni o'zi hisoblaydi. Resit vaqtlari esa faqat
     * ExamSchedule da — distribute ularni ko'rmaydi, shuning uchun alohida
     * 'resit' map'i extraOccupancy sifatida uzatiladi.
     */
    private function buildAutoTimeSlotMap(string $from, string $to, array $groupCountMap, array $attemptNeedsMap = []): array
    {
        $specs = [
            ['date' => 'oski_date',         'time' => 'oski_time',         'na' => 'oski_na', 'is_resit' => false, 'attempt' => 1],
            ['date' => 'oski_resit_date',   'time' => 'oski_resit_time',   'na' => null,      'is_resit' => true,  'attempt' => 2],
            ['date' => 'oski_resit2_date',  'time' => 'oski_resit2_time',  'na' => null,      'is_resit' => true,  'attempt' => 3],
            ['date' => 'test_date',         'time' => 'test_time',         'na' => 'test_na', 'is_resit' => false, 'attempt' => 1],
            ['date' => 'test_resit_date',   'time' => 'test_resit_time',   'na' => null,      'is_resit' => true,  'attempt' => 2],
            ['date' => 'test_resit2_date',  'time' => 'test_resit2_time',  'na' => null,      'is_resit' => true,  'attempt' => 3],
        ];

        $rows = \App\Models\ExamSchedule::query()
            ->where(function ($q) use ($from, $to, $specs) {
                foreach ($specs as $s) {
                    $q->orWhere(function ($qq) use ($from, $to, $s) {
                        $qq->whereBetween($s['date'], [$from, $to])
                           ->whereNotNull($s['time']);
                    });
                }
            })
            ->get();

        $all = [];
        $resit = [];
        foreach ($rows as $row) {
            foreach ($specs as $s) {
                $d = $row->{$s['date']};
                $t = $row->{$s['time']};
                if (empty($d) || empty($t)) continue;
                if ($s['na'] && !empty($row->{$s['na']})) continue;

                $dateStr = $d instanceof \Carbon\Carbon ? $d->format('Y-m-d') : (string) $d;
                if ($dateStr < $from || $dateStr > $to) continue;

                $hm = substr((string) $t, 0, 5);
                $parts = explode(':', $hm);
                $startMin = ((int) $parts[0]) * 60 + ((int) ($parts[1] ?? 0));

                if ($s['is_resit']) {
                    $count = $this->resolveAttemptStudentCount($row, $s['attempt'], $groupCountMap, $attemptNeedsMap);
                } else {
                    $count = (int) ($groupCountMap[$row->group_hemis_id] ?? 0);
                }
                if ($count <= 0) continue;

                $entry = ['start_min' => $startMin, 'count' => $count];
                $all[$dateStr][] = $entry;
                if ($s['is_resit']) {
                    $resit[$dateStr][] = $entry;
                }
            }
        }
        return ['all' => $all, 'resit' => $resit];
    }

    public function autoTimeAll(Request $request, AutoAssignService $service)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }

        $user = auth()->user() ?? auth('teacher')->user();
        $activeRole = session('active_role', $user?->getRoleNames()->first());
        $allowedBulkRoles = array_merge(
            ExamDateRoleService::adminRoles(),
            [\App\Enums\ProjectRole::TEST_CENTER->value]
        );
        if (!in_array($activeRole, $allowedBulkRoles, true)) {
            return back()->with('error', "Avto-vaqt belgilash faqat Test markazi va admin rollari uchun ochiq.");
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

        // Sig'im tekshiruvini DB so'rovsiz qilish uchun in-memory slotMap
        // tayyorlash. Sana oralig'idagi BARCHA yozuvlar (asosiy + resit)
        // hisobga olinadi. Avval har candidate sayin DB so'rov bo'lgani uchun
        // /auto-time-all 504 timeout berardi.
        $dateCols = ['oski_date', 'oski_resit_date', 'oski_resit2_date', 'test_date', 'test_resit_date', 'test_resit2_date'];
        $allRangeGroupIds = \App\Models\ExamSchedule::query()
            ->where(function ($q) use ($from, $to, $dateCols) {
                foreach ($dateCols as $col) {
                    $q->orWhereBetween($col, [$from, $to]);
                }
            })
            ->pluck('group_hemis_id');
        $allGroupIds = $candidates->pluck('group_hemis_id')
            ->merge($allRangeGroupIds)
            ->unique()
            ->filter()
            ->values()
            ->all();
        $groupCountMap = [];
        if (!empty($allGroupIds)) {
            $groupCountMap = DB::table('students')
                ->whereIn('group_id', $allGroupIds)
                ->where('student_status_code', 11)
                ->groupBy('group_id')
                ->select('group_id', DB::raw('COUNT(*) as cnt'))
                ->pluck('cnt', 'group_id')
                ->all();
        }
        // 2-/3-urinish qatorlarining ASL talaba soni (butun guruh emas, faqat
        // qayta topshiruvchilar). FFD saralash va sig'im hisobida ishlatiladi.
        // Scope: faqat shu auto-time batch'idagi guruhlar bilan cheklanadi -
        // computeAttemptNeedsMap ichidagi 8 ta unfiltered so'rovni dramatik
        // tezlashtiradi (lookup kalitlari aynan shu guruhlardan kelgani uchun
        // hech qaysi candidate tushib qolmaydi).
        $attemptNeedsMap = $this->computeAttemptNeedsMap($allGroupIds)['needs'];
        $maps = $this->buildAutoTimeSlotMap($from, $to, $groupCountMap, $attemptNeedsMap);
        $slotMap = $maps['all'];             // findResitSlot uchun (hammasi)
        $resitSlotMap = $maps['resit'];      // distribute uchun (faqat resit)

        $okCount = 0;
        $failures = [];

        // Pass tartibi: avval BARCHA 1-urinishlar (distribute, 60 talabalik
        // chunklar), so'ng 2-urinish (best-fit decreasing: katta resit guruhlari
        // qolgan eng kichik mos joyga, shunda kichiklari kichik joylarga
        // sig'adi), so'ng 3-urinish. Aks holda kichik guruhlar bo'sh slotlarni
        // band qiladi va katta resit'larga joy qolmaydi.
        //
        // Candidate tartibi: avval klaster (fakultet → yo'nalish → kurs/semestr
        // → fan) bo'yicha ketma-ket — bir xil fakultet/yo'nalish/kurs/fan'dagi
        // yozuvlar qator slotlarga tushadi, shunda bir kafedraning shu kursga
        // mo'ljallangan barcha imtihonlari yaqin vaqtlarda bo'ladi. Slot tanlash
        // bosqichida esa best-fit ishlatiladi (findSlotForCount / distribute).
        // Klaster ichida talaba soni bo'yicha kamayish tartibi (FFD) saqlanadi:
        // katta guruhlar avval ketsa, kichiklari qolgan kichik bo'shliqlarga
        // toza tushadi.
        $sortedByAttempt = [];
        foreach ([1, 2, 3] as $att) {
            $sortedByAttempt[$att] = $candidates->sort(function ($a, $b) {
                $cmp = strcmp((string) $a->department_hemis_id, (string) $b->department_hemis_id);
                if ($cmp !== 0) return $cmp;
                $cmp = strcmp((string) $a->specialty_hemis_id, (string) $b->specialty_hemis_id);
                if ($cmp !== 0) return $cmp;
                $cmp = strcmp((string) $a->semester_code, (string) $b->semester_code);
                if ($cmp !== 0) return $cmp;
                $cmp = strcmp((string) $a->subject_id, (string) $b->subject_id);
                if ($cmp !== 0) return $cmp;
                // Klaster ichida — guruh nomi bo'yicha (d1/d25-01a, d1/d25-01b,
                // d1/d25-02a, ...). natcmp raqamli qismni to'g'ri tartiblaydi.
                return strnatcmp(
                    (string) (optional($a->group)->name ?? $a->group_hemis_id),
                    (string) (optional($b->group)->name ?? $b->group_hemis_id)
                );
            })->values();
        }

        // DEBUG: candidate tartibini log fayl'ga yozish — vaqtinchalik diagnostika
        $debugLogPath = storage_path('logs/auto-time-all-' . now()->format('Y-m-d_H-i-s') . '.log');
        $debugLog = function (string $msg) use ($debugLogPath) {
            @file_put_contents($debugLogPath, '[' . now()->format('H:i:s.u') . '] ' . $msg . "\n", FILE_APPEND);
        };
        $debugLog("=== autoTimeAll BOSHLANDI ({$from} – {$to}) ===");
        foreach ([1, 2, 3] as $passAttempt) {
            $debugLog("--- Pass {$passAttempt} candidate tartibi ---");
            foreach ($sortedByAttempt[$passAttempt] as $idx => $s) {
                $cnt = $this->resolveAttemptStudentCount($s, $passAttempt, $groupCountMap, $attemptNeedsMap);
                $gname = optional($s->group)->name ?? $s->group_hemis_id;
                $debugLog(sprintf("  #%03d schedule_id=%d group=%s subject=%s count=%d", $idx, $s->id, $gname, $s->subject_id ?? '?', $cnt));
            }
        }

        // === Pass 1 — 1-urinish: per-schedule distribute ===
        // 1-urinishda odatda bir guruh+fan uchun bitta group-level qator
        // bo'ladi. distribute() butun guruhni atomik joylashtiradi.
        $debugLog(">>> Pass 1 ishlashga kirishildi");
        foreach ($sortedByAttempt[1] as $schedule) {
            foreach ($columnSets as $ynType => $attempts) {
                $c = $attempts[1] ?? null;
                if (!$c) continue;
                $dateVal = $schedule->{$c['date']};
                if (empty($dateVal) || !empty($schedule->{$c['time']})) continue;
                if ($c['na'] && !empty($schedule->{$c['na']})) continue;

                $dateStr = $dateVal instanceof \Carbon\Carbon
                    ? $dateVal->format('Y-m-d') : (string) $dateVal;
                if ($dateStr < $from || $dateStr > $to) continue;

                try {
                    $capacity = ExamCapacityService::getSettingsForDate($dateStr);
                    $startTime = $capacity['work_hours_start'] ?? '09:00';

                    $extra = $resitSlotMap[$dateStr] ?? [];
                    $result = $service->distribute($schedule, $ynType, $startTime, $extra);
                    $debugLog(sprintf("  distribute(%s 1, sched_id=%d) -> %s, slots: %s",
                        $ynType, $schedule->id,
                        $result['ok'] ? 'OK' : ('FAIL ' . ($result['reason'] ?? '')),
                        json_encode($result['slots'] ?? [], JSON_UNESCAPED_UNICODE)));
                    if (!empty($result['ok']) && !empty($result['slots'])) {
                        foreach ($result['slots'] as $sl) {
                            if (empty($sl['time'])) continue;
                            $p = explode(':', substr((string) $sl['time'], 0, 5));
                            $sm = ((int) $p[0]) * 60 + ((int) ($p[1] ?? 0));
                            $slotMap[$dateStr][] = ['start_min' => $sm, 'count' => (int) ($sl['students'] ?? 0)];
                        }
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('autoTimeAll: pass1 failed', [
                        'schedule_id' => $schedule->id, 'yn' => $ynType, 'error' => $e->getMessage(),
                    ]);
                    $result = ['ok' => false, 'reason' => $e->getMessage()];
                }

                if (!empty($result['ok'])) {
                    $okCount++;
                } else {
                    $failures[] = sprintf('#%d %s (%s): %s',
                        $schedule->id, strtoupper($ynType),
                        $schedule->subject_name ?: $schedule->subject_id,
                        $result['reason'] ?? "noma'lum sabab");
                }
            }
        }

        // === Pass 2/3 — resit: bucket bo'yicha atomik joylashtirish ===
        // Bir (group, subject, sem, attempt, date) birikma bitta atomik
        // birlik sifatida joylashadi. Per-student qatorlar guruh-level qator
        // bilan bir xil sanada bo'lsa — birga bir slotga tushadi. Boshqa
        // sanali per-student'lar alohida bucket bo'ladi.
        foreach ([2, 3] as $passAttempt) {
            $debugLog(">>> Pass {$passAttempt} (resit) bucket'larga ajratilmoqda");
            foreach ($columnSets as $ynType => $attempts) {
                $c = $attempts[$passAttempt] ?? null;
                if (!$c) continue;

                // Shu (ynType, attempt) bo'yicha barcha vaqt yo'q candidate'larni
                // yig'amiz va (group, subject, sem) + per-student stats hisoblaymiz.
                $candidatesForCol = [];
                foreach ($candidates as $s) {
                    $dateVal = $s->{$c['date']};
                    if (empty($dateVal) || !empty($s->{$c['time']})) continue;
                    if ($c['na'] && !empty($s->{$c['na']})) continue;
                    $dateStr = $dateVal instanceof \Carbon\Carbon
                        ? $dateVal->format('Y-m-d') : (string) $dateVal;
                    if ($dateStr < $from || $dateStr > $to) continue;
                    $candidatesForCol[] = ['schedule' => $s, 'date' => $dateStr];
                }
                if (empty($candidatesForCol)) continue;

                // Har (group, subject, sem) uchun jami per-student qator soni
                // (barcha sanalar bo'yicha) — group-level qator nechta talabani
                // "qoplayotganini" hisoblash uchun kerak.
                $totalPerStudentByCombo = [];
                foreach ($candidatesForCol as $cc) {
                    $s = $cc['schedule'];
                    $comboKey = $s->group_hemis_id . '|' . $s->subject_id . '|' . $s->semester_code;
                    if (!isset($totalPerStudentByCombo[$comboKey])) {
                        $totalPerStudentByCombo[$comboKey] = 0;
                    }
                    if (!empty($s->student_hemis_id)) {
                        $totalPerStudentByCombo[$comboKey]++;
                    }
                }

                // Bucket'larga ajratish: (group, subject, sem, date)
                $buckets = [];
                foreach ($candidatesForCol as $cc) {
                    $s = $cc['schedule'];
                    $key = $s->group_hemis_id . '|' . $s->subject_id . '|' . $s->semester_code . '|' . $cc['date'];
                    if (!isset($buckets[$key])) {
                        $buckets[$key] = [
                            'group_hemis_id' => $s->group_hemis_id,
                            'subject_id' => $s->subject_id,
                            'subject_name' => $s->subject_name,
                            'semester_code' => $s->semester_code,
                            'date' => $cc['date'],
                            'schedules' => [],
                            'per_student_count' => 0,
                            'has_group_level' => false,
                        ];
                    }
                    $buckets[$key]['schedules'][] = $s;
                    if (empty($s->student_hemis_id)) {
                        $buckets[$key]['has_group_level'] = true;
                    } else {
                        $buckets[$key]['per_student_count']++;
                    }
                }

                // Har bucket uchun haqiqiy talaba sonini hisoblash.
                foreach ($buckets as &$b) {
                    $needsKey = $b['group_hemis_id'] . '|' . $b['subject_id'] . '|' . $b['semester_code'] . '|' . $passAttempt;
                    $totalRetakers = (int) ($attemptNeedsMap[$needsKey] ?? 0);
                    $comboKey = $b['group_hemis_id'] . '|' . $b['subject_id'] . '|' . $b['semester_code'];
                    $totalPerStudent = (int) ($totalPerStudentByCombo[$comboKey] ?? 0);

                    if ($b['has_group_level']) {
                        // Group-level qator "qolgan" retakerlarni ifoda etadi
                        // (per-student override'siz qolganlar). Jami per-student
                        // (barcha sanalar) ayriladi.
                        $groupLevelRepresents = max(0, $totalRetakers - $totalPerStudent);
                        $b['count'] = $b['per_student_count'] + $groupLevelRepresents;
                    } else {
                        // Faqat per-student'lar shu bucket'da — har biri 1 talaba.
                        $b['count'] = $b['per_student_count'];
                    }
                }
                unset($b);

                // Avval klaster (fakultet → yo'nalish → kurs/semestr → fan)
                // bo'yicha ketma-ket — bir xil yo'nalish/kursning resit'lari
                // qator vaqtlarda joylashadi. Klaster ichida guruh nomi
                // bo'yicha (d1/d25-01a, d1/d25-01b, d1/d25-02a, ...) — natural
                // sort raqamli qismni to'g'ri tartiblaydi.
                usort($buckets, function ($a, $b) {
                    $sa = $a['schedules'][0] ?? null;
                    $sb = $b['schedules'][0] ?? null;
                    $cmp = strcmp(
                        (string) ($sa->department_hemis_id ?? ''),
                        (string) ($sb->department_hemis_id ?? '')
                    );
                    if ($cmp !== 0) return $cmp;
                    $cmp = strcmp(
                        (string) ($sa->specialty_hemis_id ?? ''),
                        (string) ($sb->specialty_hemis_id ?? '')
                    );
                    if ($cmp !== 0) return $cmp;
                    $cmp = strcmp((string) $a['semester_code'], (string) $b['semester_code']);
                    if ($cmp !== 0) return $cmp;
                    $cmp = strcmp((string) $a['subject_id'], (string) $b['subject_id']);
                    if ($cmp !== 0) return $cmp;
                    return strnatcmp(
                        (string) (optional($sa?->group)->name ?? $a['group_hemis_id']),
                        (string) (optional($sb?->group)->name ?? $b['group_hemis_id'])
                    );
                });

                foreach ($buckets as $b) {
                    try {
                        $capacity = ExamCapacityService::getSettingsForDate($b['date']);
                        $assignedTime = $this->findSlotForCount(
                            $b['count'], $b['date'], $capacity, $slotMap
                        );
                        $debugLog(sprintf("  bucket(%s %d, group=%s, subj=%s, date=%s, count=%d, rows=%d) -> %s",
                            $ynType, $passAttempt,
                            optional($b['schedules'][0]->group)->name ?? $b['group_hemis_id'],
                            $b['subject_id'], $b['date'], $b['count'], count($b['schedules']),
                            $assignedTime ?? 'NULL'));

                        if ($assignedTime === null) {
                            foreach ($b['schedules'] as $s) {
                                $failures[] = sprintf('#%d %s %d-urinish (%s): no slot',
                                    $s->id, strtoupper($ynType), $passAttempt,
                                    $s->subject_name ?: $s->subject_id);
                            }
                            continue;
                        }

                        // Barcha bucket schedules ga bir xil vaqt
                        foreach ($b['schedules'] as $s) {
                            $s->{$c['time']} = $assignedTime;
                            $s->save();
                            $okCount++;
                        }
                        // Mavjud ComputerAssignment qatorlarini ham yangi vaqtga
                        // sinxronlash (TV / proctor displeyi planned_start oynasi
                        // bo'yicha ko'rsatadi). saveTestTime'dagi mantiq bilan
                        // bir xil — faqat scheduled qatorlar.
                        try {
                            $bktDur = (int) (ExamCapacityService::getSettingsForDate($b['date'])['test_duration_minutes'] ?? 15);
                            $bktStart = \Carbon\Carbon::parse($b['date'] . ' ' . $assignedTime);
                            $bktEnd = $bktStart->copy()->addMinutes($bktDur);
                            $bktJit = max(1, (int) config('services.moodle.reveal_minutes_before', 10));
                            $bktReveal = $bktStart->copy()->subMinutes($bktJit);
                            foreach ($b['schedules'] as $s) {
                                \App\Models\ComputerAssignment::where('exam_schedule_id', $s->id)
                                    ->where('yn_type', strtolower($ynType))
                                    ->where('attempt', $passAttempt)
                                    ->where('status', \App\Models\ComputerAssignment::STATUS_SCHEDULED)
                                    ->update([
                                        'planned_start' => $bktStart,
                                        'planned_end' => $bktEnd,
                                        'reveal_at' => $bktReveal,
                                        'reveal_notified' => false,
                                        'approach_notified' => false,
                                        'ready_notified' => false,
                                    ]);
                                // Resit stale cleanup: oldingi noto'g'ri Word'dan
                                // qolgan butun guruh ro'yxati yangi vaqtga ko'chib
                                // qolmasin. Eligibility + override mantiq Word'dagi
                                // bilan bir xil.
                                $bktEligible = $this->resolveResitEligibleHemisIds($s);
                                \App\Models\ComputerAssignment::where('exam_schedule_id', $s->id)
                                    ->where('yn_type', strtolower($ynType))
                                    ->where('attempt', $passAttempt)
                                    ->where('status', \App\Models\ComputerAssignment::STATUS_SCHEDULED)
                                    ->when(!empty($bktEligible), fn($q) => $q->whereNotIn('student_hemis_id', $bktEligible))
                                    ->delete();
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning('autoTimeAll: ComputerAssignment sync xato', [
                                'bucket' => $b['group_hemis_id'] . '|' . $b['subject_id'],
                                'error' => $e->getMessage(),
                            ]);
                        }
                        // resitSlotMap'ga ham qo'shamiz (keyingi distribute uchun,
                        // boshqa sanada bo'lsa ham foyda bersin)
                        if ($b['count'] > 0) {
                            $p = explode(':', $assignedTime);
                            $sm = ((int) $p[0]) * 60 + ((int) ($p[1] ?? 0));
                            $resitSlotMap[$b['date']][] = ['start_min' => $sm, 'count' => $b['count']];
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('autoTimeAll: bucket failed', [
                            'bucket' => $b['group_hemis_id'] . '|' . $b['subject_id'],
                            'error' => $e->getMessage(),
                        ]);
                        foreach ($b['schedules'] as $s) {
                            $failures[] = sprintf('#%d %s %d-urinish: %s',
                                $s->id, strtoupper($ynType), $passAttempt, $e->getMessage());
                        }
                    }
                }
            }
        }

        $debugLog("=== TAMOM. ok={$okCount}, fail=" . count($failures) . " ===");
        $msg = "Avto-vaqt belgilandi: {$okCount} ta. Talabalarga xabar yuborilmadi — vaqtlarni yakuniylashtirgach, alohida bildirgi yuboring. Diagnostika fayli: " . basename($debugLogPath);
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
    /**
     * Bulk: vaqtlar yakunlangach talabalar Telegram + DB notification.
     * autoTimeAll xabar yubormaydi (sekin + spam) — yakuniy tasdiqlangach
     * shu endpoint ataylab chaqiriladi va queued job orqali bajariladi.
     */
    public function notifyAllExamTimes(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        $user = auth()->user() ?? auth('teacher')->user();
        $activeRole = session('active_role', $user?->getRoleNames()->first());
        $allowedBulkRoles = array_merge(
            ExamDateRoleService::adminRoles(),
            [\App\Enums\ProjectRole::TEST_CENTER->value]
        );
        if (!in_array($activeRole, $allowedBulkRoles, true)) {
            return back()->with('error', "Bu amal faqat Test markazi va admin rollari uchun ochiq.");
        }

        $today = now()->format('Y-m-d');
        $dateFrom = $request->input('date_from', $today);
        $dateTo = $request->input('date_to', $today);
        try {
            $from = \Carbon\Carbon::parse($dateFrom)->format('Y-m-d');
            $to = \Carbon\Carbon::parse($dateTo)->format('Y-m-d');
        } catch (\Throwable $e) {
            return back()->with('error', "Sana noto'g'ri formatda.");
        }
        if ($to < $from) {
            $to = $from;
        }

        \App\Jobs\NotifyExamTimesJob::dispatch($from, $to);

        return back()->with('success', "Xabarnomalar yuborish navbatga qo'yildi ({$from} – {$to}). Telegram orqali tarqalishi bir necha daqiqa olishi mumkin.");
    }

    /**
     * Test markazi jadvalida belgilangan (checkbox) guruhlar uchun
     * talabalarga kompyuter raqamlarini taqsimlash.
     *
     * Avval bu ish "YN oldi word" yuklab olishning yon ta'siri sifatida
     * bajarilardi — har yuklab olishda raqamlar qayta hisoblanib, talabaning
     * kompyuteri o'zgarib ketardi. Endi taqsimlash faqat shu tugma orqali
     * bajariladi; Word esa faqat mavjud raqamlarni o'qib ko'rsatadi.
     *
     * Og'ir YN pipeline sekin — sinxron bajarilsa HTTP so'rov 504 timeout
     * beradi. Shu sabab AssignComputersForRangeJob navbat job'ida fonda
     * bajariladi.
     */
    public function assignComputersForRange(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        $user = auth()->user() ?? auth('teacher')->user();
        $activeRole = session('active_role', $user?->getRoleNames()->first());
        $allowedBulkRoles = array_merge(
            ExamDateRoleService::adminRoles(),
            [\App\Enums\ProjectRole::TEST_CENTER->value]
        );
        if (!in_array($activeRole, $allowedBulkRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => "Bu amal faqat Test markazi va admin rollari uchun ochiq.",
            ], 403);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.schedule_id' => 'required|integer',
            'items.*.yn_type' => 'required|string|in:OSKI,Test,oski,test',
            'items.*.attempt' => 'nullable|integer|min:1|max:3',
        ]);

        // Belgilangan qatorlardan takrorlanmas (schedule_id, yn_type, attempt)
        // uchliklarini yig'amiz — job ularni sana bo'yicha guruhlab taqsimlaydi.
        $items = [];
        $seen = [];
        foreach ((array) $request->input('items') as $it) {
            $scheduleId = (int) ($it['schedule_id'] ?? 0);
            $ynType = strtolower((string) ($it['yn_type'] ?? ''));
            $attempt = (int) ($it['attempt'] ?? 1);
            if ($scheduleId <= 0 || !in_array($ynType, ['oski', 'test'], true)) {
                continue;
            }
            $key = $scheduleId . '|' . $ynType . '|' . $attempt;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = [
                'schedule_id' => $scheduleId,
                'yn_type' => $ynType,
                'attempt' => $attempt,
            ];
        }

        if (empty($items)) {
            return response()->json([
                'success' => false,
                'message' => 'Yaroqli guruh topilmadi.',
            ], 422);
        }

        // Holatni cache'da kuzatamiz — frontend token bilan polling qiladi
        // va job tugaganda "tayyor" xabarini ko'rsatadi.
        $token = (string) \Illuminate\Support\Str::uuid();
        cache()->put('assign_computers:' . $token, [
            'status' => 'queued',
            'requested' => count($items),
        ], 1800);

        \App\Jobs\AssignComputersForRangeJob::dispatch($items, $token);

        return response()->json([
            'success' => true,
            'token' => $token,
            'message' => count($items) . ' ta guruh navbatga qo\'yildi.',
        ]);
    }

    /**
     * AssignComputersForRangeJob holatini qaytaradi — frontend polling uchun.
     * Holat cache'da 'assign_computers:{token}' kalitida saqlanadi.
     */
    public function assignComputersStatus(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        $token = trim((string) $request->query('token', ''));
        $state = $token !== '' ? cache()->get('assign_computers:' . $token) : null;
        if (!is_array($state)) {
            return response()->json(['status' => 'unknown']);
        }
        return response()->json($state);
    }

    /**
     * Bandlik ko'rsatkichi sahifasidagi "Yetishmaganlarni biriktirish"
     * tugmasi. pendingDetails dagi (schedule_id, yn_type, attempt) qatorlari
     * uchun komp raqamlarini biriktiradi — ham guruh-level, ham per-student
     * schedule_id'larni to'g'ridan-to'g'ri ComputerAssignmentService orqali
     * ishlaydi (processYnOldiWord pipeline'idan tashqari).
     */
    public function assignMissingComputers(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        $user = auth()->user() ?? auth('teacher')->user();
        $activeRole = session('active_role', $user?->getRoleNames()->first());
        $allowedBulkRoles = array_merge(
            ExamDateRoleService::adminRoles(),
            [\App\Enums\ProjectRole::TEST_CENTER->value]
        );
        if (!in_array($activeRole, $allowedBulkRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => "Bu amal faqat Test markazi va admin rollari uchun ochiq.",
            ], 403);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.schedule_id' => 'required|integer',
            'items.*.yn_type' => 'required|string|in:OSKI,Test,oski,test',
            'items.*.attempt' => 'nullable|integer|min:1|max:3',
        ]);

        $items = [];
        $seen = [];
        foreach ((array) $request->input('items') as $it) {
            $sid = (int) ($it['schedule_id'] ?? 0);
            $yn = strtolower((string) ($it['yn_type'] ?? ''));
            $att = (int) ($it['attempt'] ?? 1);
            if ($sid <= 0 || !in_array($yn, ['oski', 'test'], true)) {
                continue;
            }
            $key = $sid . '|' . $yn . '|' . $att;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $items[] = ['schedule_id' => $sid, 'yn_type' => $yn, 'attempt' => $att];
        }

        if (empty($items)) {
            return response()->json([
                'success' => false,
                'message' => 'Yaroqli qator topilmadi.',
            ], 422);
        }

        $token = (string) \Illuminate\Support\Str::uuid();
        cache()->put('assign_missing_computers:' . $token, [
            'status' => 'queued',
            'requested' => count($items),
        ], 1800);

        \App\Jobs\AssignMissingComputersJob::dispatch($items, $token);

        return response()->json([
            'success' => true,
            'token' => $token,
            'message' => count($items) . ' ta qator navbatga qo\'yildi.',
        ]);
    }

    /**
     * AssignMissingComputersJob holatini qaytaradi — polling uchun.
     */
    public function assignMissingComputersStatus(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }
        $token = trim((string) $request->query('token', ''));
        $state = $token !== '' ? cache()->get('assign_missing_computers:' . $token) : null;
        if (!is_array($state)) {
            return response()->json(['status' => 'unknown']);
        }
        return response()->json($state);
    }

    /**
     * Word yuklab olish uchun: $subjectGroups ichidagi har talabaning
     * oldindan taqsimlangan kompyuter raqamini ComputerAssignment jadvalidan
     * o'qiydi. Word endi raqam taqsimlamaydi — bu faqat ko'rsatish uchun.
     *
     * @return array<string,int> "slot|hemis_id" => computer_number
     */
    private function readPersistedComputerNumbers(array $subjectGroups, bool $multiSlotMode, ?string $examTime): array
    {
        $map = [];
        $scheduleIdsCache = [];
        foreach ($subjectGroups as $sg) {
            $slot = $multiSlotMode ? (string) ($sg['slot_time'] ?? '') : (string) ($examTime ?? '');
            foreach ($sg['entries'] as $entry) {
                $group = $entry['group'] ?? null;
                $subject = $entry['subject'] ?? null;
                if (!$group || !$subject) {
                    continue;
                }
                $ynType = !empty($entry['yn_type']) ? strtolower((string) $entry['yn_type']) : null;
                $attempt = (int) ($entry['attempt'] ?? 1);

                $hemisIds = [];
                foreach ($entry['students'] as $st) {
                    if (!empty($st->hemis_id)) {
                        $hemisIds[] = (string) $st->hemis_id;
                    }
                }
                if (empty($hemisIds)) {
                    continue;
                }

                // Bu (guruh, fan, semestr) ga tegishli BARCHA exam_schedules
                // id'lari — guruh-level qator va individual (per-student)
                // qatorlar. Talabaning komp raqami qaysi jadval ostida
                // saqlangan bo'lsa ham topiladi: bulk tugma guruh jadvali
                // ostiga, individual vaqt esa per-student jadvali ostiga
                // yozadi — faqat guruh id bo'yicha qidirsak individuallar
                // tushib qolardi.
                $cacheKey = $group->group_hemis_id . '|' . $subject->subject_id . '|' . $subject->semester_code;
                if (!array_key_exists($cacheKey, $scheduleIdsCache)) {
                    $scheduleIdsCache[$cacheKey] = ExamSchedule::where('group_hemis_id', $group->group_hemis_id)
                        ->where('subject_id', $subject->subject_id)
                        ->where('semester_code', $subject->semester_code)
                        ->pluck('id')
                        ->all();
                }
                $scheduleIds = $scheduleIdsCache[$cacheKey];
                if (empty($scheduleIds)) {
                    continue;
                }

                $query = \App\Models\ComputerAssignment::query()
                    ->whereIn('exam_schedule_id', $scheduleIds)
                    ->where('attempt', $attempt)
                    ->whereIn('student_hemis_id', $hemisIds)
                    ->whereNotNull('computer_number')
                    ->orderBy('updated_at');
                if ($ynType !== null) {
                    $query->where('yn_type', $ynType);
                }
                foreach ($query->get(['student_hemis_id', 'computer_number']) as $row) {
                    // orderBy updated_at — talabada bir nechta qator bo'lsa
                    // eng so'nggi yangilangani qoladi.
                    $map[$slot . '|' . (string) $row->student_hemis_id] = (int) $row->computer_number;
                }
            }
        }
        return $map;
    }

    public function clearTimes(Request $request)
    {
        if ($deny = $this->ensureTestCenterAccess()) {
            return $deny;
        }

        $user = auth()->user() ?? auth('teacher')->user();
        $activeRole = session('active_role', $user?->getRoleNames()->first());
        $allowedBulkRoles = array_merge(
            ExamDateRoleService::adminRoles(),
            [\App\Enums\ProjectRole::TEST_CENTER->value]
        );
        if (!in_array($activeRole, $allowedBulkRoles, true)) {
            return back()->with('error', "Vaqtlarni tozalash faqat Test markazi va admin rollari uchun ochiq.");
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
            ->whereNull('student_hemis_id')
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

        // Test markazi roli uchun: o'sha kun yoki o'tgan sanaga vaqt qo'yish/o'zgartirish taqiqlanadi
        // (Settings tomonidagi "Test markazi huquqlari → Bugungi imtihonni o'zgartirish" toggle'i yoqilsa cheklov yo'qoladi).
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
            // Joriy yozuv uchun real talabalar soni — 2/3-urinish bo'lsa faqat
            // yiqilganlar, per-student override bo'lsa 1, va individual vaqt
            // qo'yilgan talabalar guruh hisobidan ayriladi.
            $thisGroupCount = ExamCapacityService::effectiveStudentCountForSchedule($examSchedule, $attempt, $ynType);
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

            // Shu guruhning shu sanada YN vaqti atrofida (±1 soat buffer) darsi
            // bormi tekshirish. Talaba dars va imtihon o'rtasida kamida 1 soat
            // oraliqqa ega bo'lishi kerak — bypass yo'q.
            $lessonBufferMinutes = 60;
            $bufferStart = $slotStart->copy()->subMinutes($lessonBufferMinutes);
            $bufferEnd = $slotEnd->copy()->addMinutes($lessonBufferMinutes);
            $bufferStartStr = $bufferStart->format('H:i:s');
            $bufferEndStr = $bufferEnd->format('H:i:s');
            $conflictingLessons = DB::table('schedules')
                ->where('group_id', $request->group_hemis_id)
                ->whereNull('deleted_at')
                ->whereDate('lesson_date', $relatedDateStr)
                ->where('lesson_pair_start_time', '<', $bufferEndStr)
                ->where('lesson_pair_end_time', '>', $bufferStartStr)
                ->select('subject_name', 'lesson_pair_name', 'lesson_pair_start_time', 'lesson_pair_end_time', 'training_type_name')
                ->orderBy('lesson_pair_start_time')
                ->get();

            if ($conflictingLessons->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'lesson_conflict',
                    'message' => "Tanlangan sana va vaqt atrofida (±1 soat) shu guruhning darslari mavjud — bu vaqtni belgilab bo'lmaydi.",
                    'date' => \Carbon\Carbon::parse($relatedDateStr)->format('d.m.Y'),
                    'time_range' => $newTime . ' – ' . $slotEnd->format('H:i'),
                    'buffer_range' => $bufferStart->format('H:i') . ' – ' . $bufferEnd->format('H:i'),
                    'buffer_minutes' => $lessonBufferMinutes,
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

        $examSchedule->update([$timeColumn => $request->test_time]);

        // Mavjud ComputerAssignment qatorlarining planned_start/end/reveal_at'ini
        // yangi vaqtga sinxronlash. Bo'lmasa TV displey (planned_start oynasi
        // bo'yicha) va proctor sahifasi eski vaqtdagi qatorlarni topa olmaydi.
        // Faqat status=scheduled qatorlarga tegamiz (in_progress/finished tarix
        // sifatida saqlanadi). reveal_notified=false — yangi vaqt uchun JIT
        // tick processReveal qaytadan Telegram yuboradi.
        if ($relatedDate && $request->test_time) {
            $relatedDateStr2 = $relatedDate instanceof \Carbon\Carbon
                ? $relatedDate->format('Y-m-d')
                : \Carbon\Carbon::parse($relatedDate)->format('Y-m-d');
            $duration2 = (int) (ExamCapacityService::getSettingsForDate($relatedDateStr2)['test_duration_minutes'] ?? 15);
            $newPlannedStart = \Carbon\Carbon::parse($relatedDateStr2 . ' ' . substr($request->test_time, 0, 5));
            $newPlannedEnd = $newPlannedStart->copy()->addMinutes($duration2);
            $jitMin = max(1, (int) config('services.moodle.reveal_minutes_before', 10));
            $newRevealAt = $newPlannedStart->copy()->subMinutes($jitMin);

            \App\Models\ComputerAssignment::where('exam_schedule_id', $examSchedule->id)
                ->where('yn_type', strtolower($ynType))
                ->where('attempt', $attempt)
                ->where('status', \App\Models\ComputerAssignment::STATUS_SCHEDULED)
                ->update([
                    'planned_start' => $newPlannedStart,
                    'planned_end' => $newPlannedEnd,
                    'reveal_at' => $newRevealAt,
                    'reveal_notified' => false,
                    'approach_notified' => false,
                    'ready_notified' => false,
                ]);

            // Resit (2-/3-urinish) uchun stale qatorlarni tozalash: oldingi
            // noto'g'ri Word'dan qolgan butun guruh ro'yxati yangi vaqtga
            // ko'chib qolmasin. generateYnOldiWord ichidagi eligibility +
            // override mantiq'i bilan bir xil. status != scheduled tegmaydi.
            if ($attempt >= 2) {
                $eligibleHemisIds = $this->resolveResitEligibleHemisIds($examSchedule);
                \App\Models\ComputerAssignment::where('exam_schedule_id', $examSchedule->id)
                    ->where('yn_type', strtolower($ynType))
                    ->where('attempt', $attempt)
                    ->where('status', \App\Models\ComputerAssignment::STATUS_SCHEDULED)
                    ->when(!empty($eligibleHemisIds), fn($q) => $q->whereNotIn('student_hemis_id', $eligibleHemisIds))
                    ->delete();
            }
        }

        // Audit: vaqt o'zgartirishlarini alohida log qilamiz — "vaqt qaerga ketdi"
        // tahqiqotida bu eng tez topiladigan ma'lumot.
        ActivityLogService::log(
            'update',
            'exam_schedule_time',
            $ynLabel . ' vaqti ' . ($timeChanged ? 'o\'zgartirildi' : 'belgilandi')
                . ': ' . ($examSchedule->subject_name ?: ('ID ' . $examSchedule->id))
                . ' (guruh ' . $examSchedule->group_hemis_id . ')',
            $examSchedule,
            [$timeColumn => $oldTime],
            [$timeColumn => $request->test_time]
        );

        // Sana va vaqt belgilangach → kompyuter biriktirish + Moodle bron qilish.
        // Endi har bir urinish (1, 2, 3) uchun alohida ishlatiladi — computer_assignments
        // jadvalida attempt ustuni bor, va BookMoodleGroupExam ham attempt'ni qabul qiladi.
        $ynKey = $ynType === 'OSKI' ? 'oski' : 'test';
        // N/A bayrog'i faqat 1-urinish ustunida bor — resit'larda yo'q.
        $naFlag = $attempt === 1 && ($ynKey === 'oski' ? $examSchedule->oski_na : $examSchedule->test_na);
        $autoRandom = $request->boolean('auto_random');
        if ($relatedDate && !$naFlag) {
            if ($autoRandom) {
                $auto = app(\App\Services\AutoAssignService::class)
                    ->distribute($examSchedule, $ynKey, $request->test_time, [], $attempt);
                if (empty($auto['ok'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Avtomatik taqsimlashda xato: ' . ($auto['reason'] ?? 'noma\'lum'),
                    ], 422);
                }
                BookMoodleGroupExam::dispatch($examSchedule->id, $ynKey, false, $attempt);
            } else {
                // *_assignment_mode atributi faqat 1-urinish uchun mavjud.
                if ($attempt === 1) {
                    $modeField = $ynKey . '_assignment_mode';
                    $examSchedule->update([$modeField => 'manual']);
                }
                AssignComputersJob::dispatch($examSchedule->id, $ynKey, $attempt);
                BookMoodleGroupExam::dispatch($examSchedule->id, $ynKey, false, $attempt);
            }
        }

        // Guruh saqlashida talabalarga avtomatik Telegram/LMS xabarnoma yuborilmaydi.
        // Xabar faqat "Xabarnoma yuborish" tugmasi orqali yuboriladi
        // (test-center.notify-all route — notifyAllExamTimes()).
        // Individual grafikga ega talabalar — saveStudentTime() va store() audit
        // orqali saqlanganda o'zlari avtomatik xabar oladi.

        $statusMsg = $timeChanged ? ($ynLabel . ' vaqti o\'zgartirildi') : ($ynLabel . ' vaqti saqlandi');

        return response()->json([
            'success' => true,
            'time_changed' => $timeChanged,
            'message' => $statusMsg,
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

        // X (YN ga ruxsat yo'q) talabaga kompyuter biriktirib bo'lmaydi.
        $admission = app(\App\Services\YnAdmissionService::class)
            ->statusForStudent(
                (string) $request->student_hemis_id,
                (string) $request->group_hemis_id,
                (string) $request->subject_id,
                (string) $request->semester_code
            );
        if ($admission === \App\Services\YnAdmissionService::STATUS_X) {
            return response()->json([
                'success' => false,
                'message' => 'Bu talabaga YN ga ruxsat yo\'q (X holati) — kompyuter biriktirib bo\'lmaydi.',
                'admission_status' => $admission,
            ], 422);
        }

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

        // YN ga ruxsat tekshiruvi — YN oldi qaydnomadagi qaror bilan bir xil.
        // X (ruxsat yo'q) bo'lgan talabaga vaqt qo'yib bo'lmaydi: JN/MT < limit
        // yoki davomat ≥ 25% bo'lsa, talaba YN/test ga umuman kirita olmaydi.
        $admission = app(\App\Services\YnAdmissionService::class)
            ->statusForStudent(
                (string) $request->student_hemis_id,
                (string) $request->group_hemis_id,
                (string) $request->subject_id,
                (string) $request->semester_code
            );
        if ($admission === \App\Services\YnAdmissionService::STATUS_X) {
            return response()->json([
                'success' => false,
                'message' => 'Bu talabaga YN ga ruxsat yo\'q (X holati). YN oldi qaydnoma asosida JN/MT yoki davomat shartlari bajarilmagan — vaqt qo\'yib bo\'lmaydi.',
                'admission_status' => $admission,
            ], 422);
        }

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

        $oldStudentTime = $perStudent?->{$timeColumn};

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

        // ComputerAssignment.planned_start sinxronlash — per-student qator
        // o'z schedule_id'siga ega, shu sabab faqat shu talaba qatorlariga
        // tegamiz. saveTestTime'dagi mantiq bilan bir xil.
        $resolvedDateStr = $resolvedDate instanceof \Carbon\Carbon
            ? $resolvedDate->format('Y-m-d')
            : \Carbon\Carbon::parse($resolvedDate)->format('Y-m-d');
        $durSt = (int) (ExamCapacityService::getSettingsForDate($resolvedDateStr)['test_duration_minutes'] ?? 15);
        $stPlannedStart = \Carbon\Carbon::parse($resolvedDateStr . ' ' . substr($request->test_time, 0, 5));
        $stPlannedEnd = $stPlannedStart->copy()->addMinutes($durSt);
        $jitMinSt = max(1, (int) config('services.moodle.reveal_minutes_before', 10));

        \App\Models\ComputerAssignment::where('exam_schedule_id', $perStudent->id)
            ->where('yn_type', strtolower($request->yn_type))
            ->where('attempt', (int) $request->attempt)
            ->where('status', \App\Models\ComputerAssignment::STATUS_SCHEDULED)
            ->update([
                'planned_start' => $stPlannedStart,
                'planned_end' => $stPlannedEnd,
                'reveal_at' => $stPlannedStart->copy()->subMinutes($jitMinSt),
                'reveal_notified' => false,
                'approach_notified' => false,
                'ready_notified' => false,
            ]);

        // Resit stale cleanup (per-student qator uchun — bu schedule_id'da
        // faqat shu talabaning komp qatori bo'lishi kerak).
        $eligibleStHemisIds = $this->resolveResitEligibleHemisIds($perStudent);
        \App\Models\ComputerAssignment::where('exam_schedule_id', $perStudent->id)
            ->where('yn_type', strtolower($request->yn_type))
            ->where('attempt', (int) $request->attempt)
            ->where('status', \App\Models\ComputerAssignment::STATUS_SCHEDULED)
            ->when(!empty($eligibleStHemisIds), fn($q) => $q->whereNotIn('student_hemis_id', $eligibleStHemisIds))
            ->delete();

        // Individual vaqt qo'yilgan talabaga bo'sh kompyuter raqamini avtomatik
        // biriktiramiz — guruh va boshqa individual talabalar band qilgan
        // raqamlar chetlab o'tiladi (bitta kompyuter ikki kishiga tushmasin).
        // Komp biriktirishdagi xato vaqt saqlashni bekor qilmaydi.
        $compResult = ['ok' => false];
        try {
            $compResult = app(\App\Services\ComputerAssignmentService::class)->assignSingleStudent(
                $perStudent,
                (string) $request->yn_type,
                (int) $request->attempt,
                (string) $request->student_hemis_id,
                $stPlannedStart
            );
            // assignSingleStudent ok=false qaytarsa (masalan, "no free
            // computer at this slot") — exception otmaydi, lekin yozuv
            // yaratilmaydi. Bandlik ko'rsatkichi keyinchalik bu talabani
            // "komp raqamisiz" deb ko'rsatadi va sabab noma'lum qoladi.
            // Endi sabab log'da qoladi va bandlik sahifasidagi
            // "Yetishmaganlarni biriktirish" tugmasi qayta urinishi mumkin.
            if (empty($compResult['ok'])) {
                \Log::warning('saveStudentTime: komp avtomatik biriktirilmadi', [
                    'schedule_id' => $perStudent->id,
                    'student_hemis_id' => $request->student_hemis_id,
                    'yn_type' => $request->yn_type,
                    'attempt' => (int) $request->attempt,
                    'reason' => $compResult['reason'] ?? 'unknown',
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('saveStudentTime: komp avtomatik biriktirish xatosi: ' . $e->getMessage());
        }

        // Faqat shu talabaga notification (individual grafik — guruh xabari emas).
        $ynLabel = $request->yn_type === 'OSKI' ? 'OSKI' : 'Test';
        $attemptInt = (int) $request->attempt;
        if ($attemptInt > 1) {
            $ynLabel .= ' (' . $attemptInt . '-urinish)';
        }
        $sentCount = $this->notifyStudentsExamTime(
            $this->singleStudentForNotify((string) $request->student_hemis_id),
            $perStudent->subject_name ?: ($groupSchedule?->subject_name ?? 'Fan'),
            $request->yn_type,
            $ynLabel,
            $resolvedDate ? \Carbon\Carbon::parse($resolvedDate)->format('d.m.Y') : null,
            $request->test_time,
            $oldStudentTime
        );

        $compMsg = '';
        if (!empty($compResult['ok']) && !empty($compResult['computer_number'])) {
            $compMsg = ' Kompyuter №' . $compResult['computer_number'] . ' biriktirildi.';
        } elseif (empty($compResult['ok'])) {
            $compMsg = ' Diqqat: bu vaqt uchun bo\'sh kompyuter topilmadi — raqamni qo\'lda biriktiring.';
        }

        return response()->json([
            'success' => true,
            'message' => 'Talaba uchun vaqt saqlandi.'
                . ($sentCount > 0 ? ' Telegramda xabar yuborildi.' : '')
                . $compMsg,
            'time' => $request->test_time,
            'notified' => $sentCount,
            'computer_number' => $compResult['computer_number'] ?? null,
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
        // Effective slot capacity — reserve pool ayrilgan, config bilan cheklangan.
        $totalComputers = AutoAssignService::effectiveSlotCapacity(ExamCapacityService::getSettings());
        if ($totalComputers < 1) {
            $totalComputers = (int) ExamCapacityService::getSettings()['computer_count'];
        }
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
        $itemBase = function ($schedule, string $ynType, int $attempt, ?string $timeRaw): array {
            return [
                'group_hemis_id' => $schedule->group_hemis_id,
                'subject_id' => $schedule->subject_id ?? '',
                'semester_code' => $schedule->semester_code ?? '',
                'student_hemis_id' => $schedule->student_hemis_id ?? null,
                'yn_type' => $ynType,
                'attempt' => $attempt,
                'time' => $timeRaw ? \Carbon\Carbon::parse($timeRaw)->format('H:i') : null,
            ];
        };
        foreach ($schedules as $schedule) {
            // 1-urinish (asosiy)
            $oskiDateStr = $schedule->oski_date?->format('Y-m-d');
            if ($oskiDateStr && !$schedule->oski_na && $oskiDateStr >= $today) {
                $byDate[$oskiDateStr][] = $itemBase($schedule, 'OSKI', 1, $schedule->oski_time);
            }
            $testDateStr = $schedule->test_date?->format('Y-m-d');
            if ($testDateStr && !$schedule->test_na && $testDateStr >= $today) {
                $byDate[$testDateStr][] = $itemBase($schedule, 'Test', 1, $schedule->test_time);
            }
            // 2-urinish (qayta topshirish)
            $oskiResitDateStr = $schedule->oski_resit_date?->format('Y-m-d');
            if ($oskiResitDateStr && $oskiResitDateStr >= $today) {
                $byDate[$oskiResitDateStr][] = $itemBase($schedule, 'OSKI', 2, $schedule->oski_resit_time);
            }
            $testResitDateStr = $schedule->test_resit_date?->format('Y-m-d');
            if ($testResitDateStr && $testResitDateStr >= $today) {
                $byDate[$testResitDateStr][] = $itemBase($schedule, 'Test', 2, $schedule->test_resit_time);
            }
            // 3-urinish (qayta topshirish 2)
            $oskiResit2DateStr = $schedule->oski_resit2_date?->format('Y-m-d');
            if ($oskiResit2DateStr && $oskiResit2DateStr >= $today) {
                $byDate[$oskiResit2DateStr][] = $itemBase($schedule, 'OSKI', 3, $schedule->oski_resit2_time);
            }
            $testResit2DateStr = $schedule->test_resit2_date?->format('Y-m-d');
            if ($testResit2DateStr && $testResit2DateStr >= $today) {
                $byDate[$testResit2DateStr][] = $itemBase($schedule, 'Test', 3, $schedule->test_resit2_time);
            }
        }

        // Barcha guruhlar uchun talabalar sonini yig'ish
        $allItems = collect($byDate)->flatten(1);
        $allGroupIds = $allItems->pluck('group_hemis_id')->unique()->toArray();
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

        // Tanlov fanlari uchun aniq son — student_subjects'dan
        // (har talaba bitta variant tanlaydi; butun guruh sonini
        // ishlatish (a)+(b)+(c) variantlar bandlik sig'imini
        // soxtalashtirardi).
        $subjectCounts = []; // [group_hemis_id|subject_id => cnt]
        $subjectIdsForCount = $allItems->pluck('subject_id')->filter()->unique()->toArray();
        if (!empty($allGroupIds) && !empty($subjectIdsForCount)) {
            try {
                $rowsCnt = \Illuminate\Support\Facades\DB::table('student_subjects as ss')
                    ->join('students as st', 'st.hemis_id', '=', 'ss.student_hemis_id')
                    ->whereIn('st.group_id', $allGroupIds)
                    ->whereIn('ss.subject_id', $subjectIdsForCount)
                    ->where('st.student_status_code', 11)
                    ->select('st.group_id', 'ss.subject_id',
                        \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT ss.student_hemis_id) as cnt'))
                    ->groupBy('st.group_id', 'ss.subject_id')
                    ->get();
                foreach ($rowsCnt as $r) {
                    $subjectCounts[$r->group_id . '|' . $r->subject_id] = (int) $r->cnt;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('bandlikKursatkichi: student_subjects so\'rovi xatolik: ' . $e->getMessage());
            }
        }

        // 2/3-urinish (resit) uchun haqiqiy talabalar sonini hisoblash —
        // butun guruh emas, faqat yiqilganlar. computeStudentAttemptStatuses
        // qoidalari bilan to'liq mos kelmasligi mumkin (V<60 ning aniq
        // formulasi murakkab), lekin amaliy yetarli darajada — bir BATCH
        // DB so'rovi orqali. 504 timeout xavfini oldini olish uchun har
        // (group, subject, semester) uchun computeStudentAttemptStatuses
        // chaqirilmaydi.
        $resitEligibleMap = []; // [group|subject|semester|attempt] => count of distinct hemis_ids
        if ($allItems->isNotEmpty()) {
            $resitTriples = $allItems->filter(fn($it) => (int) ($it['attempt'] ?? 1) >= 2 && empty($it['student_hemis_id']))
                ->unique(fn($it) => $it['group_hemis_id'] . '|' . $it['subject_id'] . '|' . $it['semester_code'])
                ->values();
            if ($resitTriples->isNotEmpty()) {
                $hasAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');
                $subjectIds = $resitTriples->pluck('subject_id')->unique()->all();
                $semCodes = $resitTriples->pluck('semester_code')->unique()->all();
                $groupIdsForResit = $resitTriples->pluck('group_hemis_id')->unique()->all();
                try {
                    $resitQuery = \Illuminate\Support\Facades\DB::table('student_grades as sg')
                        ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                        ->whereIn('st.group_id', $groupIdsForResit)
                        ->where('st.student_status_code', 11)
                        ->whereIn('sg.subject_id', $subjectIds)
                        ->whereIn('sg.semester_code', $semCodes)
                        ->whereIn('sg.training_type_code', [101, 102])
                        ->whereNull('sg.deleted_at');
                    if ($hasAttemptCol) {
                        // failed1 (attempt 1 da yiqilgan) yoki explicit attempt>=2 record bo'lsa eligible.
                        $resitQuery->where(function ($w) {
                            $w->where('sg.attempt', '>=', 2)
                              ->orWhere(function ($x) {
                                  $x->where(function ($y) { $y->where('sg.attempt', 1)->orWhereNull('sg.attempt'); })
                                    ->whereRaw('COALESCE(sg.retake_grade, sg.grade) < 60');
                              });
                        });
                    } else {
                        // attempt ustuni yo'q bo'lsa, oddiy "grade<60" tekshiruv.
                        $resitQuery->whereRaw('COALESCE(sg.retake_grade, sg.grade) < 60');
                    }
                    $resitRows = $resitQuery
                        ->select('st.group_id', 'sg.subject_id', 'sg.semester_code',
                            \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT sg.student_hemis_id) as cnt'))
                        ->groupBy('st.group_id', 'sg.subject_id', 'sg.semester_code')
                        ->get();
                    foreach ($resitRows as $r) {
                        $k2 = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code . '|2';
                        $k3 = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code . '|3';
                        $resitEligibleMap[$k2] = (int) $r->cnt;
                        $resitEligibleMap[$k3] = (int) $r->cnt;
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('bandlikKursatkichi: resit count failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $eligibleCountForItem = function (array $item) use ($studentCounts, $resitEligibleMap, $subjectCounts): int {
            $attemptN = (int) ($item['attempt'] ?? 1);
            if (!empty($item['student_hemis_id'])) {
                return 1; // individual grafik — faqat shu talaba
            }
            $totalGroup = (int) ($studentCounts[$item['group_hemis_id']] ?? 0);
            if ($attemptN <= 1) {
                // Tanlov fanlari uchun student_subjects'dan aniq sonni olamiz;
                // mandatory fanlar uchun butun guruh.
                $subjKey = ($item['group_hemis_id'] ?? '') . '|' . ($item['subject_id'] ?? '');
                return isset($subjectCounts[$subjKey])
                    ? $subjectCounts[$subjKey]
                    : $totalGroup;
            }
            $key = $item['group_hemis_id'] . '|' . ($item['subject_id'] ?? '') . '|' . ($item['semester_code'] ?? '') . '|' . $attemptN;
            if (array_key_exists($key, $resitEligibleMap)) {
                return $resitEligibleMap[$key];
            }
            // Resit eligibility ma'lumotini topa olmaganda 0 ga moyil bo'lamiz —
            // hech kim yiqilmagan bo'lsa, 2-urinishga hech kim kelmaydi.
            return 0;
        };

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
                $cnt = $eligibleCountForItem($item);
                $slotsOccupancy[$slotKey] = ($slotsOccupancy[$slotKey] ?? 0) + $cnt;
                $totalStudents += $cnt;
            }
            foreach ($slotsOccupancy as $occ) {
                if ($occ > $maxOccupied) $maxOccupied = $occ;
            }

            $pendingStudents = 0;
            foreach ($pendingItems as $item) {
                $pendingStudents += $eligibleCountForItem($item);
            }

            $carbonDate = \Carbon\Carbon::parse($dateStr);
            // O'sha kunga moslangan kunlik sig'im — effective (reserve ayrilgan).
            $daySettings = ExamCapacityService::getSettingsForDate($dateStr);
            $daySettings['computer_count'] = $totalComputers;
            $dayCapacity = ExamCapacityService::dailyCapacity($daySettings);
            $dateCards->push([
                'date' => $carbonDate,
                'date_str' => $dateStr,
                'slot_count' => $slotKeys->count(),
                'group_count' => count($scheduledItems),
                'total_students' => $totalStudents,
                'max_occupied' => $maxOccupied,
                'daily_capacity' => $dayCapacity,
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
        $settings = ExamCapacityService::getSettingsForDate($date);
        // Effective slot capacity = primary (reserve ayrilgan) ∩ config.
        // Avval $totalComputers config'dan to'g'ridan-to'g'ri olinardi va
        // bandlik 60 ni ko'rsatardi, distribute esa 55 ga (60-5 reserve)
        // chegaralanardi → slotlar har doim 5 spotga kam to'lardi va UI
        // 45/60 ko'rinardi. Endi UI ham 45/55 ko'rsatadi.
        $totalComputers = AutoAssignService::effectiveSlotCapacity($settings);
        if ($totalComputers < 1) {
            $totalComputers = (int) $settings['computer_count'];
        }
        // dailyCapacity ham effective bo'yicha qayta hisoblaymiz —
        // kunlik jami sig'im real (reserve ayrilgan) bo'lsin.
        $settingsForCapacity = $settings;
        $settingsForCapacity['computer_count'] = $totalComputers;
        $dailyCapacity = ExamCapacityService::dailyCapacity($settingsForCapacity);

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

        // Guruh sathidagi (group-level) qatorlarning shu sanadagi vaqti —
        // per-student qator vaqti guruh vaqti bilan teng bo'lsa, alohida
        // "individual" sifatida ko'rsatilmay guruh ichida birlashtiriladi.
        $groupTimeByCombo = [];
        foreach ($schedules as $schedule) {
            if (!empty($schedule->student_hemis_id)) continue;
            foreach ($attemptDefs as [$ynType, $attempt, $dateField, $timeField, $naField]) {
                $rawDate = $schedule->{$dateField} ?? null;
                $rawTime = $schedule->{$timeField} ?? null;
                if (!$rawDate || !$rawTime) continue;
                if ($rawDate->format('Y-m-d') !== $date) continue;
                if ($naField !== null && $schedule->{$naField}) continue;
                $k = $schedule->group_hemis_id . '|' . ($schedule->subject_id ?? '') . '|' . ($schedule->semester_code ?? '')
                    . '|' . strtolower($ynType) . '|' . $attempt;
                $groupTimeByCombo[$k] = \Carbon\Carbon::parse($rawTime)->format('H:i');
            }
        }

        // Guruh bilan birlashtirilgan (merged) per-student qatorlarni hisobga olamiz:
        //   - alohida ko'rsatilmaydi
        //   - guruh hisobidan ayrilmaydi (perStudentTimeMap'dan chiqarib tashlanadi)
        //   - lekin ularning quiz_count'i guruh quiz_count'iga qo'shiladi
        $mergedByCombo = [];        // combo key -> int (count)
        $mergedQuizByCombo = [];    // combo key -> [['schedule_id'=>int,'yn'=>str,'attempt'=>int], ...]

        foreach ($schedules as $schedule) {
            foreach ($attemptDefs as [$ynType, $attempt, $dateField, $timeField, $naField]) {
                $dStr = $schedule->{$dateField}?->format('Y-m-d');
                if ($dStr !== $date) continue;
                if ($naField !== null && $schedule->{$naField}) continue;

                $timeRaw = $schedule->{$timeField} ?? null;
                $timeStr = $timeRaw
                    ? \Carbon\Carbon::parse($timeRaw)->format('H:i')
                    : null;

                // Per-student qatorda vaqt yo'q bo'lsa — talaba guruh vaqtiga
                // bo'ysunadi va alohida "Vaqti qo'yilmagan" qatori sifatida
                // ko'rsatilmaydi. U guruh hisobiga avtomatik kiradi.
                $isPerStudent = !empty($schedule->student_hemis_id);
                if ($isPerStudent && $timeStr === null) {
                    continue;
                }

                // Per-student vaqti guruh vaqti bilan teng → guruh ichida
                // birlashtiriladi, alohida ko'rsatilmaydi.
                $comboKey = $schedule->group_hemis_id . '|' . ($schedule->subject_id ?? '') . '|' . ($schedule->semester_code ?? '')
                    . '|' . strtolower($ynType) . '|' . $attempt;
                if ($isPerStudent && $timeStr !== null
                    && isset($groupTimeByCombo[$comboKey])
                    && $groupTimeByCombo[$comboKey] === $timeStr) {
                    $mergedByCombo[$comboKey] = ($mergedByCombo[$comboKey] ?? 0) + 1;
                    $mergedQuizByCombo[$comboKey][] = [
                        'schedule_id' => $schedule->id,
                        'yn' => strtolower($ynType),
                        'attempt' => $attempt,
                    ];
                    continue;
                }

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
                    'semester_code' => $schedule->semester_code ?? '',
                    'student_hemis_id' => $schedule->student_hemis_id ?? null,
                    'group_name' => $schedule->group?->name ?? $schedule->group_hemis_id,
                    'subject_name' => $schedule->subject_name ?? '',
                    'yn_type' => $ynType,
                    'attempt' => $attempt,
                    'is_individual' => $isPerStudent,
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

        // Tanlov fanlari (subject_type_code=12) — bir guruhda variant (a), (b),
        // (c) bo'lishi mumkin va har talaba faqat BITTASIni tanlaydi. Butun
        // guruh sonini olib ishlatish bandlik sig'imini noto'g'ri ko'rsatadi
        // (15 talaba bo'lgan guruh variant (a)+(c) bo'lsa, 15+15=30 deb
        // sanaladi). student_subjects jadvalida har talabaning aniq tanlagan
        // variantining subject_id bor — shuni hisoblaymiz. Mandatory fanlar
        // (subject_type != 12) uchun student_subjects yozuvi bo'lmasligi
        // mumkin, ular uchun butun guruh soni fallback bo'ladi.
        $subjectCounts = []; // [group_hemis_id|subject_id => cnt]
        $subjectIdsForCount = $allGroups->pluck('subject_id')->filter()->unique()->toArray();
        if (!empty($allGroupIds) && !empty($subjectIdsForCount)) {
            try {
                $rowsCnt = \Illuminate\Support\Facades\DB::table('student_subjects as ss')
                    ->join('students as st', 'st.hemis_id', '=', 'ss.student_hemis_id')
                    ->whereIn('st.group_id', $allGroupIds)
                    ->whereIn('ss.subject_id', $subjectIdsForCount)
                    ->where('st.student_status_code', 11)
                    ->select('st.group_id', 'ss.subject_id',
                        \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT ss.student_hemis_id) as cnt'))
                    ->groupBy('st.group_id', 'ss.subject_id')
                    ->get();
                foreach ($rowsCnt as $r) {
                    $subjectCounts[$r->group_id . '|' . $r->subject_id] = (int) $r->cnt;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('bandlikKursatkichiShow: student_subjects so\'rovi xatolik: ' . $e->getMessage());
            }
        }

        // 2/3-urinish (resit) uchun mos talabalar sonini hisoblash —
        // butun guruh emas, faqat yiqilganlar. Bitta batch DB so'rovi.
        $resitEligibleMap = [];
        if ($allGroups->isNotEmpty()) {
            $resitTriples = $allGroups->filter(fn($g) => (int) ($g['attempt'] ?? 1) >= 2 && empty($g['student_hemis_id']))
                ->unique(fn($g) => $g['group_hemis_id'] . '|' . $g['subject_id'] . '|' . $g['semester_code'])
                ->values();
            if ($resitTriples->isNotEmpty()) {
                $hasAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');
                $subjectIds = $resitTriples->pluck('subject_id')->unique()->all();
                $semCodes = $resitTriples->pluck('semester_code')->unique()->all();
                $groupIdsForResit = $resitTriples->pluck('group_hemis_id')->unique()->all();
                try {
                    $resitQuery = \Illuminate\Support\Facades\DB::table('student_grades as sg')
                        ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                        ->whereIn('st.group_id', $groupIdsForResit)
                        ->where('st.student_status_code', 11)
                        ->whereIn('sg.subject_id', $subjectIds)
                        ->whereIn('sg.semester_code', $semCodes)
                        ->whereIn('sg.training_type_code', [101, 102])
                        ->whereNull('sg.deleted_at');
                    if ($hasAttemptCol) {
                        $resitQuery->where(function ($w) {
                            $w->where('sg.attempt', '>=', 2)
                              ->orWhere(function ($x) {
                                  $x->where(function ($y) { $y->where('sg.attempt', 1)->orWhereNull('sg.attempt'); })
                                    ->whereRaw('COALESCE(sg.retake_grade, sg.grade) < 60');
                              });
                        });
                    } else {
                        $resitQuery->whereRaw('COALESCE(sg.retake_grade, sg.grade) < 60');
                    }
                    $resitRows = $resitQuery
                        ->select('st.group_id', 'sg.subject_id', 'sg.semester_code',
                            \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT sg.student_hemis_id) as cnt'))
                        ->groupBy('st.group_id', 'sg.subject_id', 'sg.semester_code')
                        ->get();
                    foreach ($resitRows as $r) {
                        $k2 = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code . '|2';
                        $k3 = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code . '|3';
                        $resitEligibleMap[$k2] = (int) $r->cnt;
                        $resitEligibleMap[$k3] = (int) $r->cnt;
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('bandlikKursatkichiShow: resit count failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Per-(group, subject, sem, yn, attempt) bo'yicha INDIVIDUAL vaqti
        // belgilangan per-student qatorlar soni — sanadan qat'iy nazar.
        // Vaqtsiz per-student qatorlar guruh vaqtiga bo'ysunadi va ayrilmaydi.
        // Misol: guruhda 6 retaker, 1 talaba individual 11:00 ga qo'yilgan
        // (boshqa kun bo'lsa ham) → guruh slot'i 5 ta talaba ko'rsatadi.
        $perStudentTimeMap = [];
        if ($allGroups->isNotEmpty()) {
            $triples = $allGroups
                ->filter(fn($g) => !empty($g['subject_id']) && !empty($g['semester_code']))
                ->unique(fn($g) => $g['group_hemis_id'] . '|' . $g['subject_id'] . '|' . $g['semester_code'])
                ->map(fn($g) => [
                    'group_hemis_id' => $g['group_hemis_id'],
                    'subject_id' => $g['subject_id'],
                    'semester_code' => $g['semester_code'],
                ])
                ->values()
                ->all();
            $perStudentTimeMap = ExamCapacityService::perStudentWithTimeCounts($triples);
        }

        // Per-group helper: berilgan urinish uchun haqiqiy talabalar soni.
        // 1-urinish → butun guruh, 2/3 → retakerlar; ikkalasidan ham
        // individual vaqti qo'yilgan talabalar ayriladi (vaqtsiz per-student
        // qatorlar guruh ichida qoladi). Per-student vaqti guruh vaqti bilan
        // teng bo'lsa ($mergedByCombo'da hisoblangan), guruhdan ayrilmaydi —
        // ular guruh slotida birlashtirib ko'rsatiladi.
        $eligibleCount = function (array $grp, int $totalGroupCount) use ($resitEligibleMap, $perStudentTimeMap, $mergedByCombo, $subjectCounts): int {
            $attemptN = (int) ($grp['attempt'] ?? 1);
            if (!empty($grp['student_hemis_id'])) {
                return 1;
            }
            if ($attemptN <= 1) {
                // Tanlov fanlari uchun student_subjects'dan aniq sonni olamiz
                // (variant (a)/(b)/(c) lar uchun har talaba faqat birini
                // tanlaydi, butun guruh soni emas). Yo'q bo'lsa — majburiy
                // fan deb hisoblab butun guruh sonini ishlatamiz.
                $subjKey = ($grp['group_hemis_id'] ?? '') . '|' . ($grp['subject_id'] ?? '');
                $base = isset($subjectCounts[$subjKey])
                    ? $subjectCounts[$subjKey]
                    : $totalGroupCount;
            } else {
                $resitKey = $grp['group_hemis_id'] . '|' . $grp['subject_id'] . '|' . $grp['semester_code'] . '|' . $attemptN;
                $base = (int) ($resitEligibleMap[$resitKey] ?? 0);
            }
            $ynLower = strtolower((string) ($grp['yn_type'] ?? 'test'));
            $offsetKey = $grp['group_hemis_id'] . '|' . $grp['subject_id'] . '|' . $grp['semester_code']
                . '|' . $ynLower . '|' . $attemptN;
            $perStudent = (int) ($perStudentTimeMap[$offsetKey] ?? 0);
            $merged = (int) ($mergedByCombo[$offsetKey] ?? 0);
            $individual = max(0, $perStudent - $merged);
            return max(0, $base - $individual);
        };

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
                    ->groupBy('exam_schedule_id', 'yn_type', 'attempt')
                    ->select('exam_schedule_id', 'yn_type', 'attempt', DB::raw('COUNT(*) as cnt'))
                    ->get();
                foreach ($finishedRows as $fr) {
                    $key = $fr->exam_schedule_id . '|' . strtolower((string) $fr->yn_type) . '|' . (int) $fr->attempt;
                    $finishedMap[$key] = (int) $fr->cnt;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('bandlikKursatkichiShow: computer_assignments so\'rovi xatolik berdi: ' . $e->getMessage());
            }
        }

        foreach ($rows as &$row) {
            // Bitta slot ichidagi guruhlar guruh nomi bo'yicha o'sish tartibida
            // (d1/d25-01a → d1/d25-01b → d1/d25-02a, ...). natcmp raqamli qismni
            // to'g'ri tartiblaydi; teng nomlar uchun fan va urinish — barqaror
            // ikkilamchi mezon.
            usort($row['groups'], function ($a, $b) {
                $cmp = strnatcmp((string) ($a['group_name'] ?? ''), (string) ($b['group_name'] ?? ''));
                if ($cmp !== 0) return $cmp;
                $cmp = strnatcmp((string) ($a['subject_name'] ?? ''), (string) ($b['subject_name'] ?? ''));
                if ($cmp !== 0) return $cmp;
                return ((int) ($a['attempt'] ?? 1)) <=> ((int) ($b['attempt'] ?? 1));
            });

            $occupied = 0;
            $submitted = 0;
            $remaining = 0;
            foreach ($row['groups'] as &$grp) {
                $totalGroup = (int) ($studentCounts[$grp['group_hemis_id']] ?? 0);
                // 2/3-urinish uchun butun guruh emas, faqat haqiqatda imtihon
                // topshiradiganlar (yiqilganlar, pullik/held_back emas).
                $cnt = $eligibleCount($grp, $totalGroup);
                $grp['student_count'] = $cnt;
                $ynLower = strtolower($grp['yn_type'] ?? '');
                $attemptN = (int) ($grp['attempt'] ?? 1);
                $qKey = ($grp['schedule_id'] ?? '') . '|' . $ynLower . '|' . $attemptN;
                $qCnt = (int) ($finishedMap[$qKey] ?? 0);
                // Guruh ichiga birlashtirilgan per-student qatorlarning
                // (vaqti guruh vaqti bilan teng) topshirish soni guruh hisobiga
                // qo'shiladi — alohida ko'rinmaydi-yu, jami slot statistikasiga
                // hissasi bo'lishi kerak.
                if (empty($grp['student_hemis_id'])) {
                    $comboKey = $grp['group_hemis_id'] . '|' . $grp['subject_id'] . '|' . $grp['semester_code']
                        . '|' . $ynLower . '|' . $attemptN;
                    foreach ($mergedQuizByCombo[$comboKey] ?? [] as $mergedRef) {
                        $qCnt += (int) ($finishedMap[$mergedRef['schedule_id'] . '|' . $mergedRef['yn'] . '|' . $mergedRef['attempt']] ?? 0);
                    }
                }
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

        // Kompyuter raqami qo'yilmagan talabalar soni: per-slot, per-(yn_type,
        // attempt) hisoblash — guruh-level qator uchun merged per-student
        // schedule_id'lar ham hisobga olinadi (chunki ularning komp raqami o'z
        // per-student exam_schedule_id ostida saqlanadi, guruh schedule_id
        // ostida emas). Avvalgi flat hisoblash (faqat exam_schedule_id IN ...)
        // (yn_type, attempt) filtri bo'lmagani uchun boshqa kun/urinish CA
        // qatorlarini ham qo'shib hisoblardi va son haqiqiy holatdan kam
        // ko'rinardi. Endi hisob diagnostika jadvali bilan bir xil.
        $pendingDetails = [];
        try {
            foreach ($slots as $row) {
                if (!empty($row['no_time'])) continue;
                foreach ($row['groups'] as $grp) {
                    $sid = (int) ($grp['schedule_id'] ?? 0);
                    if ($sid <= 0) continue;
                    $ynLower = strtolower((string) ($grp['yn_type'] ?? ''));
                    $attempt = (int) ($grp['attempt'] ?? 1);
                    $expected = (int) ($grp['student_count'] ?? 0);
                    if ($expected <= 0) continue;

                    // Bu (schedule, yn_type, attempt) bilan birga sanaladigan
                    // schedule_id'lar to'plami — guruh qatori bo'lsa merged
                    // per-student qatorlarining schedule_id'lari ham qo'shiladi.
                    $sidSet = [$sid];
                    if (empty($grp['is_individual'])) {
                        $comboKey = ($grp['group_hemis_id'] ?? '') . '|' . ($grp['subject_id'] ?? '')
                            . '|' . ($grp['semester_code'] ?? '') . '|' . $ynLower . '|' . $attempt;
                        foreach ($mergedQuizByCombo[$comboKey] ?? [] as $m) {
                            if (!empty($m['schedule_id'])) {
                                $sidSet[] = (int) $m['schedule_id'];
                            }
                        }
                    }
                    $sidSet = array_values(array_unique($sidSet));

                    $assigned = (int) DB::table('computer_assignments')
                        ->whereIn('exam_schedule_id', $sidSet)
                        ->where('yn_type', $ynLower)
                        ->where('attempt', $attempt)
                        ->whereNotNull('computer_number')
                        ->count();
                    $missing = max(0, $expected - $assigned);
                    if ($missing <= 0) continue;

                    $pendingDetails[] = [
                        'time' => $row['time'],
                        'group_name' => $grp['group_name'] ?? '',
                        'subject_name' => $grp['subject_name'] ?? '',
                        'yn_type' => $grp['yn_type'] ?? '',
                        'attempt' => $attempt,
                        'is_individual' => !empty($grp['is_individual']),
                        // "Yetishmaganlarni biriktirish" tugmasi shu schedule_id'larni
                        // server tomonga to'g'ridan-to'g'ri yuboradi.
                        'schedule_id' => $sid,
                        'schedule_ids' => $sidSet,
                        'expected' => $expected,
                        'assigned' => $assigned,
                        'missing' => $missing,
                    ];
                }
            }
            // Eng ko'p qolgan qatorlar tepada chiqsin.
            usort($pendingDetails, fn($a, $b) => $b['missing'] <=> $a['missing']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('bandlikKursatkichiShow: pendingDetails xatolik: ' . $e->getMessage());
        }
        $pendingComputerStudents = array_sum(array_column($pendingDetails, 'missing'));

        return view('admin.academic-schedule.bandlik-kursatkichi-show', [
            'date' => $carbonDate,
            'slots' => $slots,
            'totalComputers' => $totalComputers,
            'settings' => $settings,
            'dailyCapacity' => $dailyCapacity,
            'pendingComputerStudents' => $pendingComputerStudents,
            'pendingDetails' => $pendingDetails,
        ]);
    }

    /**
     * TV displeyi: test markazi tashqarisida o'rnatilgan ekran uchun joriy
     * kunning guruh kirish jadvali. Aeroportdagi uchish tabosiga o'xshash
     * minimal, katta shriftli, autentifikatsiyasiz sahifa.
     *
     * Holat (kutilmoqda/tayyorlaning/hozir/tugadi) frontendda joriy vaqt
     * asosida har soniyada qayta hisoblanadi — server faqat slot vaqti va
     * davomiyligini beradi.
     */
    public function tvJadval(Request $request)
    {
        $now = now();
        $dateParam = $request->query('date');
        try {
            $carbonDate = $dateParam
                ? \Carbon\Carbon::createFromFormat('Y-m-d', $dateParam)
                : $now->copy()->startOfDay();
        } catch (\Throwable $e) {
            $carbonDate = $now->copy()->startOfDay();
        }
        $date = $carbonDate->format('Y-m-d');
        // Ekranda kim kirishi ko'rinishi uchun keng oyna (60 daq). Komp №
        // esa faqat reveal vaqti yetganda (reveal_minutes_before, default 10)
        // ko'rsatiladi — talaba erta bilib qo'yib boshqa kompga o'tirmasin.
        $upcomingWindowMin = 60;
        $revealWindowMin = max(1, (int) config('services.moodle.reveal_minutes_before', 10));
        // Kechikkan SCHEDULED qatorlar uchun "grace" oyna: planned_start o'tib
        // ketgan bo'lsa ham shuncha daqiqagacha ko'rsatib turamiz (talaba
        // kechikib kelishi ham mumkin). Bundan oshsa — ekranga tushmaydi.
        $staleGraceMin = 15;
        $windowEnd = $now->copy()->addMinutes($upcomingWindowMin);
        $windowStart = $now->copy()->subMinutes($staleGraceMin);

        // Shu kunda planned_start'i bo'lgan barcha aktiv qatorlar (in_progress
        // yoki kelgusi 60 daqiqada boshlanadigan). computer_number NULL bo'lsa
        // ham olamiz — view'da "tez orada" deb chiqaramiz.
        // SCHEDULED qatorlar uchun ham yuqori, ham pastki chegara qo'yiladi —
        // aks holda allaqachon o'tib ketgan, lekin status'i yangilanmagan
        // qatorlar ekranda muzlab qoladi.
        $rows = DB::table('computer_assignments as ca')
            ->leftJoin('students as st', 'st.hemis_id', '=', 'ca.student_hemis_id')
            ->leftJoin('exam_schedules as es', 'es.id', '=', 'ca.exam_schedule_id')
            ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'es.group_hemis_id')
            ->whereDate('ca.planned_start', $date)
            ->where(function ($q) use ($windowEnd, $windowStart) {
                $q->where('ca.status', \App\Models\ComputerAssignment::STATUS_IN_PROGRESS)
                    ->orWhere(function ($q2) use ($windowEnd, $windowStart) {
                        $q2->where('ca.status', \App\Models\ComputerAssignment::STATUS_SCHEDULED)
                            ->where('ca.planned_start', '<=', $windowEnd)
                            ->where('ca.planned_start', '>=', $windowStart);
                    });
            })
            ->orderBy('ca.planned_start')
            ->orderBy('ca.computer_number')
            ->select(
                'ca.id', 'ca.computer_number', 'ca.status', 'ca.planned_start',
                'ca.actual_start', 'ca.yn_type', 'ca.attempt',
                'st.full_name', 'st.student_id_number',
                'es.subject_name', 'es.group_hemis_id',
                'g.name as group_name'
            )
            ->get();

        $items = [];
        foreach ($rows as $r) {
            $plannedStart = $r->planned_start ? \Carbon\Carbon::parse($r->planned_start) : null;
            $isInProgress = $r->status === \App\Models\ComputerAssignment::STATUS_IN_PROGRESS;
            $minutesUntil = !$isInProgress && $plannedStart
                ? max(0, (int) ceil($now->diffInSeconds($plannedStart, false) / 60))
                : 0;
            // Komp № "ochilgan" deb hisoblanadi: imtihon boshlangan bo'lsa
            // YOKI reveal vaqti (planned_start − reveal_window) yetib kelgan
            // bo'lsa. Aks holda raqamni yashirib turamiz — talaba uzoq oldin
            // ko'rib qolmasin.
            $compNum = $r->computer_number !== null ? (int) $r->computer_number : null;
            $isRevealed = $isInProgress || ($plannedStart && $now->gte($plannedStart->copy()->subMinutes($revealWindowMin)));
            $isImminent = !$isInProgress && $plannedStart && $plannedStart->lte($now);

            if ($isInProgress) {
                $status = 'in_progress';
            } elseif ($isImminent) {
                $status = 'imminent';
            } elseif ($isRevealed) {
                $status = 'near';
            } else {
                $status = 'waiting';
            }

            $items[] = [
                'computer_number' => $compNum,
                'show_computer' => $isRevealed && $compNum !== null,
                'short_name' => self::formatTvShortName((string) ($r->full_name ?? '')),
                'group_name' => (string) ($r->group_name ?? $r->group_hemis_id ?? ''),
                'subject_name' => (string) ($r->subject_name ?? ''),
                'yn_type' => strtoupper((string) ($r->yn_type ?? '')),
                'attempt' => (int) ($r->attempt ?? 1),
                'planned_time' => $plannedStart ? $plannedStart->format('H:i') : '',
                'status' => $status,
                'minutes_until' => $minutesUntil,
            ];
        }

        // Tartib bandlik ko'rsatkichi bilan bir xil:
        //   1. planned_time (vaqt) — bandlik slotlar bilan bir xil
        //   2. group_name natural (d1/22-01a → 01b → 02a, ...)
        //   3. subject_name (bir guruh bir necha fanli bo'lsa)
        //   4. attempt (1-, 2-, 3-urinish tartibida)
        //   5. talaba ismi (har guruh ichida alphabetical)
        // Status (in_progress/imminent/near/waiting) endi tartibga emas, faqat
        // ko'rsatuvga ta'sir qiladi (kartochka pulse + badge).
        usort($items, function ($a, $b) {
            if ($a['planned_time'] !== $b['planned_time']) {
                return strcmp($a['planned_time'], $b['planned_time']);
            }
            $cmp = strnatcmp((string) ($a['group_name'] ?? ''), (string) ($b['group_name'] ?? ''));
            if ($cmp !== 0) return $cmp;
            $cmp = strnatcmp((string) ($a['subject_name'] ?? ''), (string) ($b['subject_name'] ?? ''));
            if ($cmp !== 0) return $cmp;
            $cmp = ((int) ($a['attempt'] ?? 1)) <=> ((int) ($b['attempt'] ?? 1));
            if ($cmp !== 0) return $cmp;
            return strcmp((string) ($a['short_name'] ?? ''), (string) ($b['short_name'] ?? ''));
        });

        return response()
            ->view('admin.academic-schedule.tv-jadval', [
                'date' => $carbonDate,
                'items' => $items,
                'now' => $now,
                'windowMin' => $upcomingWindowMin,
                'revealMin' => $revealWindowMin,
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * Resit (2-/3-urinish) bo'yicha ExamSchedule qatori uchun haqiqatdan
     * imtihon topshirishga kerak bo'lgan talabalarning hemis_id ro'yxati.
     * generateYnOldiWord ichidagi mantiq bilan bir xil:
     *  - Per-student qator (student_hemis_id bor) → faqat shu talaba
     *    (eligibility check yo'q — admin ataylab belgilagan)
     *  - Guruh-level qator → student_grades'da yiqilganlar (yoki attempt >= 2)
     *    MINUS per-student override qatorlari bor talabalar
     */
    private function resolveResitEligibleHemisIds(ExamSchedule $schedule): array
    {
        if (!empty($schedule->student_hemis_id)) {
            return [(string) $schedule->student_hemis_id];
        }

        $hasAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');
        $eligible = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->where('st.group_id', $schedule->group_hemis_id)
            ->where('st.student_status_code', 11)
            ->where('sg.subject_id', $schedule->subject_id)
            ->where('sg.semester_code', $schedule->semester_code)
            ->whereIn('sg.training_type_code', [101, 102])
            ->whereNull('sg.deleted_at')
            ->when($hasAttemptCol, function ($q) {
                $q->where(function ($w) {
                    $w->where('sg.attempt', '>=', 2)
                        ->orWhere(function ($x) {
                            $x->where(function ($y) {
                                $y->where('sg.attempt', 1)->orWhereNull('sg.attempt');
                            })->whereRaw('COALESCE(sg.retake_grade, sg.grade) < 60');
                        });
                });
            }, function ($q) {
                $q->whereRaw('COALESCE(sg.retake_grade, sg.grade) < 60');
            })
            ->distinct()
            ->pluck('sg.student_hemis_id')
            ->map(fn($v) => (string) $v)
            ->all();

        $overridden = DB::table('exam_schedules')
            ->where('group_hemis_id', $schedule->group_hemis_id)
            ->where('subject_id', $schedule->subject_id)
            ->where('semester_code', $schedule->semester_code)
            ->whereNotNull('student_hemis_id')
            ->pluck('student_hemis_id')
            ->map(fn($v) => (string) $v)
            ->all();

        return array_values(array_diff($eligible, $overridden));
    }

    /**
     * "Tursunov Sardor Akmal o'g'li" → "Tursunov S.A.". Familiya to'liq,
     * ism va ota ismi bosh harf bilan. Suffixlar (o'g'li/qizi) chiqarib
     * tashlanadi. UTF-8 xavfsiz.
     */
    private static function formatTvShortName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName)) ?: [];
        $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
        if (empty($parts)) return '—';
        $surname = $parts[0];
        $initials = '';
        $taken = 0;
        for ($i = 1; $i < count($parts) && $taken < 2; $i++) {
            $p = $parts[$i];
            // Suffixlarni tashlab ketamiz (turli yozilishlari bilan).
            if (preg_match('/^(o\'g\'li|o\x{2018}g\x{2018}li|o\x{2019}g\x{2019}li|ugli|o‘g‘li|o’g’li|qizi|qizi\.|qizi,|qizining)$/iu', $p)) {
                continue;
            }
            $first = mb_substr($p, 0, 1, 'UTF-8');
            if ($first === '') continue;
            $initials .= mb_strtoupper($first, 'UTF-8') . '.';
            $taken++;
        }
        return $initials !== '' ? ($surname . ' ' . $initials) : $surname;
    }

    /**
     * Har bir guruh+fan+semestr uchun N-urinishda imtihon topshirishi kerak
     * bo'lgan talabalar sonini hisoblaydi (2- va 3-urinishlar uchun).
     *
     * Manbalar:
     *  - student_grades: oldingi urinishda V<60 olganlar
     *  - student_grades.attempt=N: aniq qo'lda N-urinishga o'tkazilganlar
     *  - yn_submissions.attempt=N: qog'oz ariza (12a/12b)
     *
     * Kalit format: "group_hemis_id|subject_id|semester_code|attempt"
     *
     * @return array{needs: array<string,int>, exists: array<string,int>}
     */
    private function computeAttemptNeedsMap(array $groupHemisIds = [], array $subjectIds = [], array $semesterCodes = []): array
    {
        $needsByKey = [];
        $attemptExistsByKey = [];
        try {
            $hasAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');

            // 1-so'rov: student_grades dan failed (V<60) talabalarni attempt
            // bo'yicha bir martada hisoblaymiz (4 ta alohida so'rov o'rniga 1 ta
            // CASE WHEN ichida). 8 ta query — bu metodning asosiy 504 sababi edi.
            $sgQuery = DB::table('student_grades as sg')
                ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->when(!empty($groupHemisIds), fn($q) => $q->whereIn('st.group_id', $groupHemisIds))
                ->when(!empty($subjectIds), fn($q) => $q->whereIn('sg.subject_id', $subjectIds))
                ->when(!empty($semesterCodes), fn($q) => $q->whereIn('sg.semester_code', $semesterCodes))
                ->whereNull('sg.deleted_at')
                ->whereIn('sg.training_type_code', [101, 102]);
            $failedExpr = 'CASE WHEN COALESCE(sg.retake_grade, sg.grade) < 60 THEN 1 ELSE 0 END';
            $att1Cond = $hasAttemptCol ? '(sg.attempt = 1 OR sg.attempt IS NULL)' : '1';
            $att2Cond = $hasAttemptCol ? 'sg.attempt = 2' : '0';
            $att3Cond = $hasAttemptCol ? 'sg.attempt = 3' : '0';
            $sgRows = $sgQuery
                ->select('st.group_id', 'sg.subject_id', 'sg.semester_code',
                    DB::raw("COUNT(DISTINCT CASE WHEN {$att1Cond} AND {$failedExpr} = 1 THEN sg.student_hemis_id END) as failed1"),
                    DB::raw("COUNT(DISTINCT CASE WHEN {$att2Cond} AND {$failedExpr} = 1 THEN sg.student_hemis_id END) as failed2"),
                    DB::raw("COUNT(DISTINCT CASE WHEN {$att2Cond} THEN sg.student_hemis_id END) as att2_explicit"),
                    DB::raw("COUNT(DISTINCT CASE WHEN {$att3Cond} THEN sg.student_hemis_id END) as att3_explicit")
                )
                ->groupBy('st.group_id', 'sg.subject_id', 'sg.semester_code')
                ->get();
            foreach ($sgRows as $r) {
                $kBase = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code;
                $failed1 = (int) $r->failed1;
                $failed2 = (int) $r->failed2;
                $att2 = (int) $r->att2_explicit;
                $att3 = (int) $r->att3_explicit;
                if ($failed1 > 0 || $att2 > 0) {
                    $needsByKey[$kBase . '|2'] = max($needsByKey[$kBase . '|2'] ?? 0, max($failed1, $att2));
                }
                if ($failed2 > 0 || $att3 > 0) {
                    $needsByKey[$kBase . '|3'] = max($needsByKey[$kBase . '|3'] ?? 0, max($failed2, $att3));
                }
                if ($att2 > 0) $attemptExistsByKey[$kBase . '|2'] = $att2;
                if ($att3 > 0) $attemptExistsByKey[$kBase . '|3'] = $att3;
            }

            // 2-so'rov: yn_submissions (qog'oz ariza) — attempt 2,3 ni bir
            // martada olamiz va PHP'da ajratamiz.
            if (\Illuminate\Support\Facades\Schema::hasColumn('yn_submissions', 'attempt')) {
                $ynRows = DB::table('yn_submissions as yns')
                    ->when(!empty($groupHemisIds), fn($q) => $q->whereIn('yns.group_hemis_id', $groupHemisIds))
                    ->when(!empty($subjectIds), fn($q) => $q->whereIn('yns.subject_id', $subjectIds))
                    ->when(!empty($semesterCodes), fn($q) => $q->whereIn('yns.semester_code', $semesterCodes))
                    ->whereIn('yns.attempt', [2, 3])
                    ->select('yns.group_hemis_id as group_id', 'yns.subject_id', 'yns.semester_code', 'yns.attempt')
                    ->distinct()
                    ->get();
                foreach ($ynRows as $r) {
                    $key = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code . '|' . ((int) $r->attempt);
                    if (!isset($needsByKey[$key])) $needsByKey[$key] = 1;
                    $attemptExistsByKey[$key] = $attemptExistsByKey[$key] ?? 1;
                }
            }

            // 3-so'rov: per-student exam_schedules (shaxsiy resit sanalari) —
            // attempt 2 va 3 ni bir martada hisoblaymiz.
            if (\Illuminate\Support\Facades\Schema::hasColumn('exam_schedules', 'student_hemis_id')) {
                $esRows = DB::table('exam_schedules as es')
                    ->join('students as st', 'st.hemis_id', '=', 'es.student_hemis_id')
                    ->when(!empty($groupHemisIds), fn($q) => $q->whereIn('st.group_id', $groupHemisIds))
                    ->when(!empty($subjectIds), fn($q) => $q->whereIn('es.subject_id', $subjectIds))
                    ->when(!empty($semesterCodes), fn($q) => $q->whereIn('es.semester_code', $semesterCodes))
                    ->whereNotNull('es.student_hemis_id')
                    ->where(function ($q) {
                        $q->whereNotNull('es.oski_resit_date')
                          ->orWhereNotNull('es.test_resit_date')
                          ->orWhereNotNull('es.oski_resit2_date')
                          ->orWhereNotNull('es.test_resit2_date');
                    })
                    ->select('st.group_id', 'es.subject_id', 'es.semester_code',
                        DB::raw("COUNT(DISTINCT CASE WHEN es.oski_resit_date IS NOT NULL OR es.test_resit_date IS NOT NULL THEN es.student_hemis_id END) as a2"),
                        DB::raw("COUNT(DISTINCT CASE WHEN es.oski_resit2_date IS NOT NULL OR es.test_resit2_date IS NOT NULL THEN es.student_hemis_id END) as a3")
                    )
                    ->groupBy('st.group_id', 'es.subject_id', 'es.semester_code')
                    ->get();
                foreach ($esRows as $r) {
                    $kBase = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code;
                    if ((int) $r->a2 > 0) $needsByKey[$kBase . '|2'] = max($needsByKey[$kBase . '|2'] ?? 0, (int) $r->a2);
                    if ((int) $r->a3 > 0) $needsByKey[$kBase . '|3'] = max($needsByKey[$kBase . '|3'] ?? 0, (int) $r->a3);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('computeAttemptNeedsMap failed: ' . $e->getMessage());
        }

        return ['needs' => $needsByKey, 'exists' => $attemptExistsByKey];
    }

    /**
     * Tooltip uchun yiqilgan talabalar hemis_id ro'yxati.
     * Qaytadi: 'group|subject|sem|attempt' => [hemis_id, ...]
     * attempt=2 — 1-urinishdan yiqilganlar (V<60 yoki student_grades.attempt=2 yozuvi bor)
     * attempt=3 — 12a dan yiqilganlar (attempt=2 da V<60 yoki attempt=3 yozuvi bor)
     *
     * Yiqilgan deb hisoblanadi:
     *   - student_grades da grade<60 (yoki retake_grade<60), YOKI
     *   - student_grades.attempt=2/3 yozuvi mavjud (qo'lda 12a/12b ga o'tkazilgan), YOKI
     *   - per-student exam_schedules da resit sanasi belgilangan, YOKI
     *   - imtihon sanasi o'tgan, lekin talaba qatnashmagan (OSKI/Test bahosi yo'q).
     *     closing_form ga qarab tegishli imtihon turi (OSKI yoki Test) uchun
     *     baho yo'qligi tekshiriladi.
     */
    private function computeFailedHemisIdsByKey(array $groupHemisIds, $scheduleData = null, array $subjectIds = [], array $semesterCodes = []): array
    {
        $result = [];
        if (empty($groupHemisIds)) return $result;
        try {
            $hasAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');

            // V<60 asosida yiqilganlar (attempt=1 / null -> 2-urinish, attempt=2 -> 3-urinish)
            foreach ([2, 3] as $att) {
                $checkAttempt = $att === 2 ? 1 : 2;
                $rows = DB::table('student_grades as sg')
                    ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                    ->whereIn('st.group_id', $groupHemisIds)
                    ->when(!empty($subjectIds), fn($q) => $q->whereIn('sg.subject_id', $subjectIds))
                    ->when(!empty($semesterCodes), fn($q) => $q->whereIn('sg.semester_code', $semesterCodes))
                    ->whereNull('sg.deleted_at')
                    ->whereIn('sg.training_type_code', [101, 102])
                    ->where(function ($q) {
                        $q->where(function ($qq) {
                            $qq->whereNotNull('sg.retake_grade')->where('sg.retake_grade', '<', 60);
                        })->orWhere(function ($qq) {
                            $qq->whereNull('sg.retake_grade')
                               ->whereNotNull('sg.grade')
                               ->where('sg.grade', '<', 60);
                        });
                    })
                    ->when($hasAttemptCol, function ($q) use ($checkAttempt) {
                        $q->where(function ($qq) use ($checkAttempt) {
                            if ($checkAttempt === 1) {
                                $qq->where('sg.attempt', 1)->orWhereNull('sg.attempt');
                            } else {
                                $qq->where('sg.attempt', $checkAttempt);
                            }
                        });
                    })
                    ->select('st.group_id', 'sg.subject_id', 'sg.semester_code', 'sg.student_hemis_id')
                    ->distinct()
                    ->get();
                foreach ($rows as $r) {
                    $key = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code . '|' . $att;
                    $result[$key][] = (string) $r->student_hemis_id;
                }
            }

            // Aniq attempt=2/3 yozuvi bor talabalar (qo'lda 12a/12b ga o'tkazilgan)
            if ($hasAttemptCol) {
                foreach ([2, 3] as $att) {
                    $rows = DB::table('student_grades as sg')
                        ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                        ->whereIn('st.group_id', $groupHemisIds)
                        ->when(!empty($subjectIds), fn($q) => $q->whereIn('sg.subject_id', $subjectIds))
                        ->when(!empty($semesterCodes), fn($q) => $q->whereIn('sg.semester_code', $semesterCodes))
                        ->whereNull('sg.deleted_at')
                        ->whereIn('sg.training_type_code', [101, 102])
                        ->where('sg.attempt', $att)
                        ->select('st.group_id', 'sg.subject_id', 'sg.semester_code', 'sg.student_hemis_id')
                        ->distinct()
                        ->get();
                    foreach ($rows as $r) {
                        $key = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code . '|' . $att;
                        $result[$key][] = (string) $r->student_hemis_id;
                    }
                }
            }

            // Per-student resit sanasi belgilangan talabalar
            if (\Illuminate\Support\Facades\Schema::hasColumn('exam_schedules', 'student_hemis_id')) {
                $rows2 = DB::table('exam_schedules as es')
                    ->join('students as st', 'st.hemis_id', '=', 'es.student_hemis_id')
                    ->whereIn('st.group_id', $groupHemisIds)
                    ->when(!empty($subjectIds), fn($q) => $q->whereIn('es.subject_id', $subjectIds))
                    ->when(!empty($semesterCodes), fn($q) => $q->whereIn('es.semester_code', $semesterCodes))
                    ->whereNotNull('es.student_hemis_id')
                    ->where(function ($q) {
                        $q->whereNotNull('es.oski_resit_date')->orWhereNotNull('es.test_resit_date');
                    })
                    ->select('st.group_id', 'es.subject_id', 'es.semester_code', 'es.student_hemis_id')
                    ->distinct()
                    ->get();
                foreach ($rows2 as $r) {
                    $key = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code . '|2';
                    $result[$key][] = (string) $r->student_hemis_id;
                }
                $rows3 = DB::table('exam_schedules as es')
                    ->join('students as st', 'st.hemis_id', '=', 'es.student_hemis_id')
                    ->whereIn('st.group_id', $groupHemisIds)
                    ->when(!empty($subjectIds), fn($q) => $q->whereIn('es.subject_id', $subjectIds))
                    ->when(!empty($semesterCodes), fn($q) => $q->whereIn('es.semester_code', $semesterCodes))
                    ->whereNotNull('es.student_hemis_id')
                    ->where(function ($q) {
                        $q->whereNotNull('es.oski_resit2_date')->orWhereNotNull('es.test_resit2_date');
                    })
                    ->select('st.group_id', 'es.subject_id', 'es.semester_code', 'es.student_hemis_id')
                    ->distinct()
                    ->get();
                foreach ($rows3 as $r) {
                    $key = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code . '|3';
                    $result[$key][] = (string) $r->student_hemis_id;
                }
            }

            // Unique
            foreach ($result as $k => $ids) {
                $result[$k] = array_values(array_unique($ids));
            }

            // Imtihon sanasi o'tgan lekin qatnashmaganlar (NB) ham yiqilgan deb hisoblanadi.
            // Bu jurnal sahifasidagi V<60 (V=-1 "kelmadi") mantig'iga mos keladi.
            if ($scheduleData !== null) {
                $this->addMissedExamFailures($result, $groupHemisIds, $scheduleData, $subjectIds, $semesterCodes);
            }
        } catch (\Throwable $e) {
            \Log::warning('computeFailedHemisIdsByKey failed: ' . $e->getMessage());
        }
        return $result;
    }

    /**
     * Imtihon sanasi o'tgan, lekin talaba qatnashmagan (yoki bahosi <60 dan boshqa
     * sababga ko'ra yo'q) holatlarini topib, $result xaritasiga 2-urinish kerakli
     * sifatida qo'shadi. closing_form ga qarab OSKI yoki Test bahosi talab
     * qilinishi tekshiriladi.
     */
    private function addMissedExamFailures(array &$result, array $groupHemisIds, $scheduleData, array $subjectIds = [], array $semesterCodes = []): void
    {
        try {
            $today = now()->format('Y-m-d');

            // Faqat passing (>=60) OSKI/Test baholarini olamiz - shu xaritada
            // bo'lmagan talaba "qatnashmadi yoki yiqildi" deb hisoblanadi.
            // attempt=1 yoki null - asosiy urinish.
            $hasAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');
            $passedOski = []; // 'group|subject|sem' => [hemis_id => true]
            $passedTest = [];

            $passQuery = function (int $trainingTypeCode) use ($groupHemisIds, $subjectIds, $semesterCodes, $hasAttemptCol) {
                return DB::table('student_grades as sg')
                    ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                    ->whereIn('st.group_id', $groupHemisIds)
                    ->when(!empty($subjectIds), fn($q) => $q->whereIn('sg.subject_id', $subjectIds))
                    ->when(!empty($semesterCodes), fn($q) => $q->whereIn('sg.semester_code', $semesterCodes))
                    ->whereNull('sg.deleted_at')
                    ->where('sg.training_type_code', $trainingTypeCode)
                    ->whereRaw('COALESCE(sg.retake_grade, sg.grade) >= 60')
                    ->when($hasAttemptCol, function ($q) {
                        $q->where(function ($qq) {
                            $qq->where('sg.attempt', 1)->orWhereNull('sg.attempt');
                        });
                    })
                    ->select('st.group_id', 'sg.subject_id', 'sg.semester_code', 'sg.student_hemis_id')
                    ->distinct()
                    ->get();
            };

            foreach ($passQuery(101) as $r) {
                $passedOski[$r->group_id . '|' . $r->subject_id . '|' . $r->semester_code][(string) $r->student_hemis_id] = true;
            }
            foreach ($passQuery(102) as $r) {
                $passedTest[$r->group_id . '|' . $r->subject_id . '|' . $r->semester_code][(string) $r->student_hemis_id] = true;
            }

            // Guruh bo'yicha barcha faol talabalar
            $studentsByGroup = [];
            $rows = DB::table('students')
                ->whereIn('group_id', $groupHemisIds)
                ->where('student_status_code', 11)
                ->select('hemis_id', 'group_id')
                ->get();
            foreach ($rows as $r) {
                $studentsByGroup[$r->group_id][] = (string) $r->hemis_id;
            }

            // Har bir schedule item uchun closing_form va imtihon sanalariga qarab
            // qatnashmaganlarni topish.
            foreach ($scheduleData as $items) {
                foreach ($items as $item) {
                    $g = $item['group']->group_hemis_id ?? null;
                    $s = $item['subject']->subject_id ?? null;
                    $sem = $item['subject']->semester_code ?? null;
                    if (!$g || !$s || !$sem) continue;

                    $cf = $item['closing_form'] ?? null;
                    // Normativ va sinov shakllari OSKI/Test orqali topshirilmaydi
                    if (in_array($cf, ['normativ', 'sinov', 'none'], true)) continue;

                    $needsOski = $cf === null || in_array($cf, ['oski', 'oski_test'], true);
                    $needsTest = $cf === null || in_array($cf, ['test', 'oski_test'], true);

                    $oskiDate = $item['oski_date'] ?? null;
                    $testDate = $item['test_date'] ?? null;
                    $oskiNa = !empty($item['oski_na']);
                    $testNa = !empty($item['test_na']);

                    // Imtihon o'tgan deb hisoblaymiz: NA yoki sana o'tmishda
                    $oskiPassed = !$needsOski || $oskiNa || ($oskiDate && $oskiDate < $today);
                    $testPassed = !$needsTest || $testNa || ($testDate && $testDate < $today);
                    // Kamida bittasi o'tgan bo'lishi kerak — aks holda hali imtihon
                    // umuman boshlanmagan, qatnashmadi deb belgilash erta
                    if (!$oskiPassed && !$testPassed) continue;

                    $key = $g . '|' . $s . '|' . $sem;
                    foreach ($studentsByGroup[$g] ?? [] as $hid) {
                        // Talaba o'tgan deb hisoblanadi: kerakli imtihon turlarining
                        // har birida bahosi >=60. Aks holda yiqilgan.
                        $oskiOk = !$needsOski || $oskiNa || !empty($passedOski[$key][$hid]);
                        $testOk = !$needsTest || $testNa || !empty($passedTest[$key][$hid]);
                        if (!$oskiOk || !$testOk) {
                            $result[$key . '|2'][] = $hid;
                        }
                    }
                }
            }

            // Dublikatlarni qaytadan tozalash (eski yiqilganlarga qo'shilgan bo'lishi mumkin)
            foreach ($result as $k => $ids) {
                $result[$k] = array_values(array_unique($ids));
            }
        } catch (\Throwable $e) {
            \Log::warning('addMissedExamFailures failed: ' . $e->getMessage());
        }
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
        // Tooltip uchun: guruh bo'yicha talabalar ismlari ro'yxati (badge'ga hover).
        $studentNamesByGroup = [];
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

            // Talabalar ismlari ro'yxati (badge tooltip uchun)
            try {
                $rows = DB::table('students')
                    ->whereIn('group_id', $allGroupHemisIds)
                    ->where('student_status_code', 11)
                    ->orderBy('full_name')
                    ->select('hemis_id', 'full_name', 'group_id')
                    ->get();
                foreach ($rows as $r) {
                    $studentNamesByGroup[$r->group_id][(string) $r->hemis_id] = $r->full_name;
                }
            } catch (\Throwable $e) {
                \Log::warning('studentNamesByGroup lookup failed: ' . $e->getMessage());
            }
        }

        // Sahifadagi guruh/fan/semestrlarni yig'amiz - so'rovlarni shularga
        // scope qilamiz (bog'lanishsiz to'liq jadval scan'idan qochish uchun).
        $allSubjectIds = [];
        $allSemCodes = [];
        foreach ($scheduleData as $items) {
            foreach ($items as $item) {
                $sid = $item['subject']->subject_id ?? null;
                $sem = $item['subject']->semester_code ?? null;
                if ($sid) $allSubjectIds[$sid] = true;
                if ($sem) $allSemCodes[$sem] = true;
            }
        }
        $allSubjectIds = array_keys($allSubjectIds);
        $allSemCodes = array_keys($allSemCodes);

        // computeAttemptNeedsMap() ni guruh/fan/semestr bo'yicha scope qilamiz
        // (sahifa bo'yicha cheklanmagan so'rovlar 502 timeout berardi). Bu funksiya
        // failedHemisIdsByKey qamrab olmaydigan signallarni ham beradi:
        //  - yn_submissions(attempt=2/3): admin qo'lda 12a/12b ochgan
        //  - student_grades.attempt=N: aniq qo'lda urinishga o'tkazilgan
        // Shu sababli ushbu chaqiruv 2-urinish qatori ko'rinishi uchun zarur.
        $maps = $this->computeAttemptNeedsMap($allGroupHemisIds, $allSubjectIds, $allSemCodes);
        $needsByKey = $maps['needs'];
        $attemptExistsByKey = $maps['exists'];

        // Tooltip uchun yiqilgan talabalar hemis_id ro'yxati (group|subject|sem|attempt).
        // attachStudentsToSchedule yuklanmagan bo'lsa (showStudents=false), shu xarita
        // 2/3-urinish badge hover'da kim yiqilganini ko'rsatish uchun ishlatiladi.
        // scheduleData/subject/sem uzatamiz - so'rovlar scope qilingan bo'ladi va
        // qatnashmagan talabalar ham hisobga olinadi.
        $failedHemisIdsByKey = $this->computeFailedHemisIdsByKey($allGroupHemisIds, $scheduleData, $allSubjectIds, $allSemCodes);

        return $scheduleData->map(function ($items) use ($urinishFilter, $needsByKey, $attemptExistsByKey, $groupSizes, $studentNamesByGroup, $failedHemisIdsByKey) {
            $expanded = collect();
            foreach ($items as $item) {
                $groupHid = $item['group']->group_hemis_id ?? '';
                $subjectId = $item['subject']->subject_id ?? '';
                $semCode = $item['subject']->semester_code ?? '';
                $needsKeyBase = $groupHid . '|' . $subjectId . '|' . $semCode;

                $studentsAttachedList = (isset($item['students']) && is_array($item['students'])) ? $item['students'] : null;
                $countFor = function (int $att) use ($studentsAttachedList, $groupSizes, $groupHid, $needsByKey, $needsKeyBase, $failedHemisIdsByKey) {
                    // Asosiy hisob: studentsAttachedList bo'lsa undan, aks holda groupSizes / needsByKey dan
                    if (is_array($studentsAttachedList)) {
                        if ($att === 1) {
                            $base = count($studentsAttachedList);
                        } else {
                            $field = $att === 2 ? 'failed_attempt1' : 'failed_attempt2';
                            $base = count(array_filter($studentsAttachedList, fn($s) => !empty($s[$field])));
                        }
                    } elseif ($att === 1) {
                        $base = (int) ($groupSizes[$groupHid] ?? 0);
                    } else {
                        $base = (int) ($needsByKey[$needsKeyBase . '|' . $att] ?? 0);
                    }
                    if ($att === 1) return $base;
                    // 2/3-urinish uchun failedHemisIdsByKey to'liqroq:
                    // qatnashmaganlarni (NB) ham qamrab oladi - eski mantiq tashlab ketgan
                    // talabalar shu yerda ushlanadi.
                    $failedCount = isset($failedHemisIdsByKey[$needsKeyBase . '|' . $att])
                        ? count($failedHemisIdsByKey[$needsKeyBase . '|' . $att])
                        : 0;
                    return max($base, $failedCount);
                };

                // Tooltip uchun talabalar ismlari ro'yxatini tayyorlaymiz.
                // 1-urinish: guruhdagi barcha faol talabalar.
                // 2/3-urinish: failedHemisIdsByKey (eng to'liq) + studentsAttachedList
                //   union — har ikkalasidan kelgan ismlar birlashtiriladi. failedHemisIdsByKey
                //   qatnashmaganlarni ham qamrab oladi (eski mantiq tashlab ketgan).
                $tooltipFor = function (int $att) use ($studentsAttachedList, $studentNamesByGroup, $failedHemisIdsByKey, $groupHid, $needsKeyBase) {
                    if ($att === 1) {
                        $names = array_values($studentNamesByGroup[$groupHid] ?? []);
                        sort($names);
                        return $names;
                    }
                    $namesMap = $studentNamesByGroup[$groupHid] ?? [];
                    $unique = []; // hid => name
                    // 1) failedHemisIdsByKey dan
                    $ids = $failedHemisIdsByKey[$needsKeyBase . '|' . $att] ?? [];
                    foreach ($ids as $hid) {
                        if (isset($namesMap[(string) $hid])) {
                            $unique[(string) $hid] = $namesMap[(string) $hid];
                        }
                    }
                    // 2) studentsAttachedList dan (mavjud bo'lsa)
                    if (is_array($studentsAttachedList)) {
                        $field = $att === 2 ? 'failed_attempt1' : 'failed_attempt2';
                        foreach ($studentsAttachedList as $s) {
                            if (!empty($s[$field])) {
                                $hid = (string) ($s['hemis_id'] ?? '');
                                if ($hid !== '') {
                                    $unique[$hid] = $s['full_name'] ?? '';
                                }
                            }
                        }
                    }
                    $names = array_values($unique);
                    sort($names);
                    return $names;
                };

                // Yiqilgan talabalar borligini avval aniqlaymiz - row2/row3
                // ko'rinishi va row1 tagidagi badge mantiqi shularga tayanadi.
                $hasFailed1 = false;
                $hasFailed2 = false;
                if (is_array($studentsAttachedList)) {
                    foreach ($studentsAttachedList as $stu) {
                        if (!empty($stu['failed_attempt1'])) $hasFailed1 = true;
                        if (!empty($stu['failed_attempt2'])) $hasFailed2 = true;
                        if ($hasFailed1 && $hasFailed2) break;
                    }
                } else {
                    // Talabalar yuklanmagan (showStudents=false) — eski signallarga tayanamiz
                    // failedHemisIdsByKey qatnashmaganlarni ham qamrab oladi
                    $hasFailed1 = isset($needsByKey[$needsKeyBase . '|2'])
                        || isset($attemptExistsByKey[$needsKeyBase . '|2'])
                        || !empty($failedHemisIdsByKey[$needsKeyBase . '|2']);
                    $hasFailed2 = isset($needsByKey[$needsKeyBase . '|3'])
                        || isset($attemptExistsByKey[$needsKeyBase . '|3'])
                        || !empty($failedHemisIdsByKey[$needsKeyBase . '|3']);
                }

                // 1-urinish — har doim
                $row1 = $item;
                $row1['urinish'] = 1;
                $row1['oski_date_for_urinish'] = $item['oski_date'] ?? null;
                $row1['test_date_for_urinish'] = $item['test_date'] ?? null;
                $row1['oski_na_for_urinish'] = $item['oski_na'] ?? false;
                $row1['test_na_for_urinish'] = $item['test_na'] ?? false;
                $row1['student_count'] = $countFor(1);
                $row1['tooltip_students'] = $tooltipFor(1);
                // 1-urinish uchun "passed/total" ko'rsatish — yiqilganlar bo'lsa
                // badge "7/10" formatda chiqadi. Yiqilganlar = $countFor(2).
                $row1FailedCount = $countFor(2);
                $row1['passed_count'] = max(0, $row1['student_count'] - $row1FailedCount);
                $row1['failed_count'] = $row1FailedCount;

                // 2-urinish ko'rinish qoidasi:
                //  - guruh sathida resit sanasi saqlangan, YOKI
                //  - guruhda 1-urinishdan yiqilgan (failed_attempt1) talaba bor.
                //  Shu qatorga guruh sathida 2-urinish sana/vaqti qo'yiladi va
                //  per-student qator ostida har talabaga alohida sana qo'yish ham
                //  mumkin. Row1 ostida yiqilganlar dublikat bo'lmasligi uchun
                //  view ushbu qator chiqsa, ularni row1 dan chiqarib tashlaydi.
                $has2Data = !empty($item['oski_resit_date']) || !empty($item['test_resit_date']);
                $show2 = $has2Data || $hasFailed1;

                $row2 = null;
                if ($show2) {
                    $row2 = $item;
                    $row2['urinish'] = 2;
                    $row2['oski_date_for_urinish'] = $item['oski_resit_date'] ?? null;
                    $row2['test_date_for_urinish'] = $item['test_resit_date'] ?? null;
                    $row2['oski_na_for_urinish'] = false;
                    $row2['test_na_for_urinish'] = false;
                    $row2['student_count'] = $countFor(2);
                    $row2['tooltip_students'] = $tooltipFor(2);
                }

                // 3-urinish — guruh sathida resit2 sanasi saqlangan YOKI
                // 12a dan yiqilgan (failed_attempt2) talaba bor bo'lsa chiqadi.
                $has3Data = !empty($item['oski_resit2_date']) || !empty($item['test_resit2_date']);
                $show3 = $has3Data || $hasFailed2;

                $row3 = null;
                if ($show3) {
                    $row3 = $item;
                    $row3['urinish'] = 3;
                    $row3['oski_date_for_urinish'] = $item['oski_resit2_date'] ?? null;
                    $row3['test_date_for_urinish'] = $item['test_resit2_date'] ?? null;
                    $row3['oski_na_for_urinish'] = false;
                    $row3['test_na_for_urinish'] = false;
                    $row3['student_count'] = $countFor(3);
                    $row3['tooltip_students'] = $tooltipFor(3);
                }

                // View row1 ostida yiqilgan talabalarni dublikat qilib
                // ko'rsatmasligi uchun row2/row3 ko'rinishini bildiramiz.
                $row1['has_group_resit'] = $show2;
                $row1['has_group_resit2'] = $show3;

                // Filter qo'llash
                //  - 1-urinish qator HAR DOIM ko'rsatiladi (urinish=2/3 filterda ham),
                //    chunki yiqilganlar bo'lmaganda row2/row3 chiqmaydi va talabalar
                //    boshqacha joyda ko'rinmasligi mumkin. urinish=2/3 filterida esa
                //    guruh+fan qatorlari faqat tegishli yiqilganlar bo'lsa qoldiriladi
                //    (jadval keraksiz to'lib ketmasligi uchun).
                $rowsToAdd = [];
                // Filter aniq urinish raqamiga sozlangan bo'lsa, faqat shu
                // urinish qatori ko'rinadi. Filtersiz (null/'') esa hamma
                // urinishlar ketma-ket ko'rsatiladi.
                $includeRow1 = ($urinishFilter === null || $urinishFilter === '' || $urinishFilter === '1');
                $includeRow2 = ($urinishFilter === null || $urinishFilter === '' || $urinishFilter === '2');
                $includeRow3 = ($urinishFilter === null || $urinishFilter === '' || $urinishFilter === '3');
                if ($includeRow1) $rowsToAdd[] = $row1;
                if ($includeRow2 && $row2) $rowsToAdd[] = $row2;
                if ($includeRow3 && $row3) $rowsToAdd[] = $row3;

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

        // YN ga ruxsati yo'q (X) talabalarni qo'lda biriktirishga kiritmaslik —
        // ro'yxatda bo'lsa, butun so'rovni rad etib qaysi talabalar bloklanganini
        // aniq xabar qilamiz (yaxshi UX).
        $admissionMap = app(\App\Services\YnAdmissionService::class)
            ->computeForGroup(
                (string) $request->group_hemis_id,
                (string) $request->subject_id,
                (string) $request->semester_code
            );
        $deniedIds = [];
        foreach ($request->input('assignments') as $a) {
            $hid = (string) ($a['student_hemis_id'] ?? '');
            if ($hid === '') continue;
            $st = $admissionMap[$hid]['status'] ?? null;
            if ($st === \App\Services\YnAdmissionService::STATUS_X) {
                $deniedIds[] = $hid;
            }
        }
        if (!empty($deniedIds)) {
            $deniedNames = Student::whereIn('hemis_id', $deniedIds)
                ->pluck('full_name', 'hemis_id')
                ->toArray();
            $labels = array_map(fn($hid) => $deniedNames[$hid] ?? $hid, $deniedIds);
            return response()->json([
                'success' => false,
                'message' => 'Quyidagi talabalarga YN ga ruxsat yo\'q (X holati) — biriktirib bo\'lmaydi: ' . implode(', ', $labels),
                'denied_hemis_ids' => $deniedIds,
            ], 422);
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

    /**
     * Debug yordamchi: Yo'nalish filtrli qidiruv nima uchun bo'sh natija berayotganini
     * bosqichma-bosqich tushuntiradi. URL paramlari index() bilan bir xil.
     * Foydalanish: /admin/academic-schedule/debug-specialty?department_id=54&specialty_id=...
     */
    public function debugSpecialty(Request $request)
    {
        if (!auth()->user()?->hasAnyRole(['admin', 'superadmin'])) {
            abort(403);
        }

        $departmentId = $request->get('department_id');
        $specialtyId = $request->get('specialty_id');
        $levelCode = $request->get('level_code');
        $semesterCode = $request->get('semester_code');
        $currentSemesterToggle = $request->get('current_semester', '1');
        $currentSemesterOnly = $currentSemesterToggle === '1';

        $currentEducationYear = Semester::where('current', true)->value('education_year');

        $out = [
            'inputs' => compact('departmentId', 'specialtyId', 'levelCode', 'semesterCode', 'currentSemesterOnly', 'currentEducationYear'),
        ];

        // 1. Tanlangan specialty_hemis_id ga mos Specialty yozuvlari
        if ($specialtyId) {
            $selectedSpec = Specialty::where('specialty_hemis_id', $specialtyId)->get(['specialty_hemis_id', 'name']);
            $out['selected_specialty_record'] = $selectedSpec->toArray();

            // Shu nom ostida boshqa specialty_hemis_id variantlari
            $names = $selectedSpec->pluck('name')->unique()->toArray();
            if (!empty($names)) {
                $sameNameSpecs = Specialty::whereIn('name', $names)->get(['specialty_hemis_id', 'name']);
                $out['same_name_specialty_variants'] = $sameNameSpecs->toArray();
            }
        }

        // 2. Curriculum bosqichi
        $curriculumBase = Curriculum::query();
        if ($currentSemesterOnly) {
            $curriculumBase->where(function($q) {
                $q->where('current', true)
                  ->orWhereIn('curricula_hemis_id', Semester::where('current', true)->select('curriculum_hemis_id'));
            });
        } else {
            $curriculumBase->whereIn('curricula_hemis_id', Semester::where('education_year', $currentEducationYear)->select('curriculum_hemis_id'));
        }

        $curQ1 = (clone $curriculumBase);
        if ($departmentId) $curQ1->where('department_hemis_id', $departmentId);
        $out['curricula_dept_only'] = [
            'count' => $curQ1->count(),
            'specialty_hemis_id_variants' => $curQ1->select('specialty_hemis_id')->distinct()->pluck('specialty_hemis_id')->toArray(),
            'sample' => $curQ1->limit(20)->get(['curricula_hemis_id', 'name', 'department_hemis_id', 'specialty_hemis_id'])->toArray(),
        ];

        if ($specialtyId) {
            $curQ2 = (clone $curriculumBase);
            if ($departmentId) $curQ2->where('department_hemis_id', $departmentId);
            $curQ2->where('specialty_hemis_id', $specialtyId);
            $out['curricula_dept_plus_specialty'] = [
                'count' => $curQ2->count(),
                'sample' => $curQ2->limit(20)->get(['curricula_hemis_id', 'name', 'department_hemis_id', 'specialty_hemis_id'])->toArray(),
            ];
        }

        // 3. Group bosqichi (curriculum filtrisiz va bilan)
        $groupQ1 = Group::where('active', true);
        if ($departmentId) $groupQ1->where('department_hemis_id', $departmentId);
        $out['groups_dept_only'] = [
            'count' => $groupQ1->count(),
            'specialty_hemis_id_variants' => $groupQ1->select('specialty_hemis_id', 'specialty_name')
                ->distinct()->get()->toArray(),
        ];

        if ($specialtyId) {
            $groupQ2 = Group::where('active', true);
            if ($departmentId) $groupQ2->where('department_hemis_id', $departmentId);
            $groupQ2->where('specialty_hemis_id', $specialtyId);
            $out['groups_dept_plus_specialty'] = [
                'count' => $groupQ2->count(),
                'sample' => $groupQ2->limit(20)->get(['group_hemis_id', 'name', 'department_hemis_id', 'specialty_hemis_id', 'specialty_name', 'curriculum_hemis_id'])->toArray(),
            ];

            // Groups in dept that have specialty_name = selected name but different ID
            $names = Specialty::where('specialty_hemis_id', $specialtyId)->pluck('name')->toArray();
            if (!empty($names)) {
                $groupQ3 = Group::where('active', true);
                if ($departmentId) $groupQ3->where('department_hemis_id', $departmentId);
                $groupQ3->whereIn('specialty_name', $names);
                $out['groups_with_same_specialty_name'] = [
                    'count' => $groupQ3->count(),
                    'distinct_specialty_hemis_id_in_groups' => $groupQ3->distinct()->pluck('specialty_hemis_id')->toArray(),
                    'sample' => $groupQ3->limit(20)->get(['group_hemis_id', 'name', 'specialty_hemis_id', 'specialty_name', 'curriculum_hemis_id'])->toArray(),
                ];
            }
        }

        // 4. Curriculum+Group bog'liqligi: yo'nalish bo'yicha guruh va uning curriculumi
        if ($specialtyId) {
            $groupsBySpecName = $out['groups_with_same_specialty_name']['sample'] ?? [];
            $debugLinks = [];
            foreach ($groupsBySpecName as $g) {
                $curr = Curriculum::where('curricula_hemis_id', $g['curriculum_hemis_id'] ?? null)
                    ->first(['curricula_hemis_id', 'department_hemis_id', 'specialty_hemis_id', 'name']);
                $debugLinks[] = [
                    'group' => $g,
                    'curriculum' => $curr ? $curr->toArray() : null,
                    'mismatch_specialty' => $curr ? ($curr->specialty_hemis_id != ($g['specialty_hemis_id'] ?? null)) : null,
                    'mismatch_department' => $curr && $departmentId ? ($curr->department_hemis_id != $departmentId) : null,
                ];
            }
            $out['group_curriculum_links'] = $debugLinks;
        }

        return response()->json($out, 200, ['Content-Type' => 'application/json; charset=UTF-8'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
