<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\ExamCapacityOverride;
use App\Models\ExamSchedule;
use App\Models\YnSubmission;
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
     * O'quv bo'limi uchun: YN kunini belgilash sahifasi
     */
    public function index(Request $request)
    {
        // Sahifaga kirish huquqini tekshirish (sozlamalardan kelib chiqib)
        $user = auth()->user() ?? auth('teacher')->user();
        $activeRole = session('active_role', $user?->getRoleNames()->first());
        $adminRoles = ExamDateRoleService::adminRoles();
        $isAdmin = $user && in_array($activeRole, $adminRoles, true);
        if (!$isAdmin && !ExamDateRoleService::roleHasAnyAccess($activeRole)) {
            abort(403, 'Bu sahifaga kirish uchun ruxsat yo\'q.');
        }

        // Ushbu rol uchun ruxsat etilgan kurs darajalari
        $allowedLevelCodes = $isAdmin
            ? array_keys(ExamDateRoleService::getMapping())
            : ExamDateRoleService::levelsForRole($activeRole);

        $currentSemesters = Semester::where('current', true)->get();
        $currentEducationYear = $currentSemesters->first()?->education_year;

        // Filtrlar
        $selectedEducationType = $request->get('education_type');
        $selectedDepartment = $request->get('department_id');
        $selectedSpecialty = $request->get('specialty_id');
        $selectedLevelCode = $request->get('level_code');

        // Agar foydalanuvchi admin bo'lmasa va tanlagan kursi ruxsat etilgan
        // ro'yxatda bo'lmasa — filtrni avtomatik tozalash
        if (!$isAdmin && $selectedLevelCode && !in_array((string) $selectedLevelCode, $allowedLevelCodes, true)) {
            $selectedLevelCode = null;
        }
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

            // Admin bo'lmagan rol — faqat unga ruxsat etilgan kurs darajalari
            if (!$isAdmin && !empty($allowedLevelCodes)) {
                $scheduleData = $scheduleData->map(function ($items) use ($allowedLevelCodes) {
                    return $items->filter(function ($item) use ($allowedLevelCodes) {
                        $lvl = (string) ($item['group']->level_code ?? $item['level_code'] ?? '');
                        return $lvl !== '' && in_array($lvl, $allowedLevelCodes, true);
                    });
                })->filter(fn($items) => $items->isNotEmpty());
            }

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
        }

        $routePrefix = $this->routePrefix();
        $canDelete = $isAdmin;
        // Edit huquqi: admin yoki sozlamalarda biror kurs uchun ruxsatga ega rol
        $canEdit = $canDelete || !empty($allowedLevelCodes);

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
            'currentSemesterToggle',
            'showStudents',
            'urinishFilter',
            'isSearched',
            'currentEducationYear',
            'routePrefix',
            'canDelete',
            'canEdit',
            'allowedLevelCodes',
        ));
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

        $studentsByGroup = DB::table('students')
            ->whereIn('group_id', $allGroupHemisIds)
            ->where('student_status_code', 11)
            ->select('hemis_id', 'full_name', 'group_id')
            ->orderBy('full_name')
            ->get()
            ->groupBy('group_id');

        // Per-student exam_schedules yozuvlarini olish (student_hemis_id NOT NULL)
        $perStudentMap = [];
        $perStudentRows = ExamSchedule::whereNotNull('student_hemis_id')
            ->whereIn('group_hemis_id', $allGroupHemisIds)
            ->get();
        foreach ($perStudentRows as $row) {
            $key = $row->group_hemis_id . '|' . $row->subject_id . '|' . $row->semester_code . '|' . $row->student_hemis_id;
            $perStudentMap[$key] = $row;
        }

        // Per-student urinish-status: jn/mt/oski/test/davomat asosida — 2/3-urinish ro'yxatda
        // kim "yiqilgan" va kim "pullik" ekanligini bilish uchun.
        $studentStatus = $this->computeStudentAttemptStatuses($scheduleData);

        return $scheduleData->map(function ($items) use ($studentsByGroup, $perStudentMap, $studentStatus) {
            return $items->map(function ($item) use ($studentsByGroup, $perStudentMap, $studentStatus) {
                $gHid = $item['group']->group_hemis_id;
                $subjectId = $item['subject']->subject_id ?? null;
                $semCode = $item['subject']->semester_code ?? null;
                $statusKey = $gHid . '|' . $subjectId . '|' . $semCode;
                $statusByStudent = $studentStatus[$statusKey] ?? [];
                $studentList = $studentsByGroup->get($gHid, collect());

                $rows = [];
                foreach ($studentList as $stu) {
                    $key = $gHid . '|' . $subjectId . '|' . $semCode . '|' . $stu->hemis_id;
                    $perRow = $perStudentMap[$key] ?? null;
                    $stat = $statusByStudent[$stu->hemis_id] ?? ['failed1' => false, 'failed2' => false, 'pullik' => false, 'held_back' => false];
                    $rows[] = [
                        'hemis_id' => $stu->hemis_id,
                        'full_name' => $stu->full_name,
                        'oski_resit_date' => $perRow?->oski_resit_date?->format('Y-m-d'),
                        'oski_resit2_date' => $perRow?->oski_resit2_date?->format('Y-m-d'),
                        'test_resit_date' => $perRow?->test_resit_date?->format('Y-m-d'),
                        'test_resit2_date' => $perRow?->test_resit2_date?->format('Y-m-d'),
                        'failed_attempt1' => $stat['failed1'],
                        'failed_attempt2' => $stat['failed2'],
                        'is_pullik' => $stat['pullik'],
                        'is_held_back' => $stat['held_back'] ?? false,
                    ];
                }
                $item['students'] = $rows;
                return $item;
            });
        });
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

        // Talabalarning hemis_id va group_id xaritasi (faqat faol talabalar)
        $studentGroup = DB::table('students')
            ->whereIn('group_id', $allGroupHids)
            ->where('student_status_code', 11)
            ->pluck('group_id', 'hemis_id')
            ->toArray();
        $allStudentHids = array_keys($studentGroup);
        if (empty($allStudentHids)) return $result;

        // exam_schedules dan oski_na / test_na flaglarini olish (asosiy schedule, student_hemis_id NULL)
        $naMap = []; // group|subj|sem => ['oski_na'=>bool, 'test_na'=>bool]
        try {
            $rows = DB::table('exam_schedules')
                ->whereNull('student_hemis_id')
                ->whereIn('group_hemis_id', $allGroupHids)
                ->whereIn('subject_id', $allSubjectIds)
                ->whereIn('semester_code', $allSemCodes)
                ->select('group_hemis_id', 'subject_id', 'semester_code', 'oski_na', 'test_na')
                ->get();
            foreach ($rows as $r) {
                $k = $r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                $naMap[$k] = [
                    'oski_na' => (bool) $r->oski_na,
                    'test_na' => (bool) $r->test_na,
                ];
            }
        } catch (\Throwable $e) {}

        // 1) JN/MT olish — snapshot va tirik AVG ni birlashtirib ishlatamiz.
        $jnMtMap = []; // hemis_id|subj|sem => [jn, mt]

        // 1a) Snapshot (yn_student_grades) — defolt
        try {
            $hasYnSubAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('yn_submissions', 'attempt');
            $ynQuery = DB::table('yn_student_grades as ysg')
                ->join('yn_submissions as yns', 'yns.id', '=', 'ysg.yn_submission_id')
                ->whereIn('yns.subject_id', $allSubjectIds)
                ->whereIn('yns.semester_code', $allSemCodes)
                ->whereIn('yns.group_hemis_id', $allGroupHids);
            if ($hasYnSubAttemptCol) {
                $ynQuery->where(function ($q) {
                    $q->where('yns.attempt', 1)->orWhereNull('yns.attempt');
                });
            }
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

        // 1b) Tirik AVG — DB darajasida aggregatsiya (memory uchun yengil).
        // JN: training_type_code NOT IN [99,100,101,102,103] uchun AVG
        // MT: training_type_code=99 uchun AVG yoki manual MT
        try {
            $jnAvg = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $allStudentHids)
                ->whereIn('subject_id', $allSubjectIds)
                ->whereIn('semester_code', $allSemCodes)
                ->whereNotIn('training_type_code', [99, 100, 101, 102, 103])
                ->when($hasAttemptCol, fn($q) => $q->where(fn($qq) => $qq->where('attempt', 1)->orWhereNull('attempt')))
                ->whereRaw('COALESCE(retake_grade, grade) IS NOT NULL')
                ->selectRaw('student_hemis_id, subject_id, semester_code,
                    AVG(COALESCE(retake_grade, grade)) as avg_grade')
                ->groupBy('student_hemis_id', 'subject_id', 'semester_code')
                ->get();
            foreach ($jnAvg as $r) {
                $k = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                if (!isset($jnMtMap[$k])) $jnMtMap[$k] = ['jn' => null, 'mt' => null];
                $jnMtMap[$k]['jn'] = (int) round((float) $r->avg_grade, 0, PHP_ROUND_HALF_UP);
            }

            $mtAvg = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $allStudentHids)
                ->whereIn('subject_id', $allSubjectIds)
                ->whereIn('semester_code', $allSemCodes)
                ->where('training_type_code', 99)
                ->when($hasAttemptCol, fn($q) => $q->where(fn($qq) => $qq->where('attempt', 1)->orWhereNull('attempt')))
                ->whereRaw('COALESCE(retake_grade, grade) IS NOT NULL')
                ->selectRaw('student_hemis_id, subject_id, semester_code,
                    AVG(COALESCE(retake_grade, grade)) as avg_grade')
                ->groupBy('student_hemis_id', 'subject_id', 'semester_code')
                ->get();
            foreach ($mtAvg as $r) {
                $k = $r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                if (!isset($jnMtMap[$k])) $jnMtMap[$k] = ['jn' => null, 'mt' => null];
                $jnMtMap[$k]['mt'] = (int) round((float) $r->avg_grade, 0, PHP_ROUND_HALF_UP);
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

        // Endi har talaba uchun statusni hisoblaymiz
        foreach ($studentGroup as $hid => $gHid) {
            foreach ($triples as $triple) {
                [$g, $s, $sem] = $triple;
                if ($g !== $gHid) continue;

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

                // Pullik faqat haqiqatda past bo'lsa: null/yo'q ma'lumotni "past" deb sanamaymiz
                $jnLow = ($jn !== null) && ($jn < $minLimit);
                $mtLow = ($mt !== null) && ($mt < $minLimit);
                $isPullik = $jnLow || $mtLow || ($davomatPct >= 25);

                // Yiqilgan attempt=1: pullik yoki kerakli OSKI/Test < 60 yoki kelmagan
                $oskiNum = $oski !== null ? (float) $oski : null;
                $testNum = $test !== null ? (float) $test : null;
                $oskiFailed1 = $oskiRequired && (($oskiNum === null) || ($oskiNum < $minLimit));
                $testFailed1 = $testRequired && (($testNum === null) || ($testNum < $minLimit));
                $failed1 = $isPullik || $oskiFailed1 || $testFailed1;

                $oski2Num = $oski2 !== null ? (float) $oski2 : null;
                $test2Num = $test2 !== null ? (float) $test2 : null;
                $oskiFailed2 = $oskiRequired && (($oski2Num === null) || ($oski2Num < $minLimit));
                $testFailed2 = $testRequired && (($test2Num === null) || ($test2Num < $minLimit));
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
        // Talaba bo'yicha guruh + semestr bo'yicha failed1 fanlar sonini sanab,
        // chegaradan oshganlarni held_back deb belgilaymiz.
        $debtThreshold = 4;
        $debtCount = []; // hemis_id|gHid|sem => count
        foreach ($result as $key => $studs) {
            [$g, $s, $sem] = explode('|', $key);
            foreach ($studs as $hid => $stat) {
                if (!empty($stat['failed1'])) {
                    $bucket = $hid . '|' . $g . '|' . $sem;
                    $debtCount[$bucket] = ($debtCount[$bucket] ?? 0) + 1;
                }
            }
        }
        foreach ($result as $key => $studs) {
            [$g, $s, $sem] = explode('|', $key);
            foreach ($studs as $hid => $stat) {
                $bucket = $hid . '|' . $g . '|' . $sem;
                if (($debtCount[$bucket] ?? 0) >= $debtThreshold) {
                    $result[$key][$hid]['held_back'] = true;
                }
            }
        }

        return $result;
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

        $currentSemesters = Semester::where('current', true)->get();
        $currentEducationYear = $currentSemesters->first()?->education_year;

        $scheduleData = $this->loadScheduleData(
            $currentSemesters, $selectedDepartment, $selectedSpecialty,
            $selectedSemester, $selectedGroup, $selectedEducationType,
            $selectedLevelCode, $selectedSubject, $selectedStatus,
            $currentSemesterToggle, true, $dateFrom, $dateTo, true
        );

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
                $oskiDate = $item['oski_date'] ?? null;
                $testDate = $item['test_date'] ?? null;
                $oskiNa = $item['oski_na'] ?? false;
                $testNa = $item['test_na'] ?? false;

                $sem = $semesterMap->get($item['subject']->semester_code);
                $extraFields = [
                    'subject_code' => $item['subject']->subject_id ?? '',
                    'level_name' => $sem?->level_name ?? '',
                    'semester_name' => $item['subject']->semester_name ?? ($sem?->name ?? ''),
                    'education_form_name' => $curriculumFormMap->get($item['group']->curriculum_hemis_id) ?? '',
                ];

                $oskiInRange = $oskiDate && (!$dateFrom || $oskiDate >= $dateFrom) && (!$dateTo || $oskiDate <= $dateTo);
                if ($oskiInRange || ($oskiNa && !$dateFrom && !$dateTo)) {
                    $ynItem = array_merge($item, $extraFields);
                    $ynItem['yn_type'] = 'OSKI';
                    $ynItem['yn_date'] = $oskiDate;
                    $ynItem['yn_date_carbon'] = $item['oski_date_carbon'] ?? null;
                    $ynItem['yn_na'] = $oskiNa;
                    $ynItem['test_time'] = $item['oski_time'] ?? null;
                    $transformedData->push($ynItem);
                }

                $testInRange = $testDate && (!$dateFrom || $testDate >= $dateFrom) && (!$dateTo || $testDate <= $dateTo);
                if ($testInRange || ($testNa && !$dateFrom && !$dateTo)) {
                    $ynItem = array_merge($item, $extraFields);
                    $ynItem['yn_type'] = 'Test';
                    $ynItem['yn_date'] = $testDate;
                    $ynItem['yn_date_carbon'] = $item['test_date_carbon'] ?? null;
                    $ynItem['yn_na'] = $testNa;
                    $ynItem['test_time'] = $item['test_time'] ?? null;
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
        ];
    }

    public function testCenterView(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('testCenterView: metod boshlandi', [
            'url' => $request->fullUrl(),
            'guard' => auth()->getDefaultDriver(),
            'user_id' => auth()->id(),
            'user_class' => auth()->user() ? get_class(auth()->user()) : 'null',
        ]);

        $readOnly = $this->isTestCenterReadOnly();
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

        try {
            $result = $this->buildTestCenterData($request);
            $scheduleData = $result['scheduleData'];
            $currentEducationYear = $result['currentEducationYear'];
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
                'isSearched',
                'currentEducationYear',
                'routePrefix',
                'readOnly',
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
                    'level_code' => $semesterLevelMap[$group->curriculum_hemis_id . '_' . $subject->semester_code] ?? null,
                    'specialty_name' => $group->specialty_name,
                    'lesson_start_date' => $lessonInfo?->lesson_start ? substr($lessonInfo->lesson_start, 0, 10) : null,
                    'lesson_end_date' => $lessonInfo?->lesson_end ? substr($lessonInfo->lesson_end, 0, 10) : null,
                    'oski_date' => $existing?->oski_date?->format('Y-m-d'),
                    'oski_na' => (bool) $existing?->oski_na,
                    'oski_time' => $existing?->oski_time,
                    'oski_resit_date' => $existing?->oski_resit_date?->format('Y-m-d'),
                    'oski_resit2_date' => $existing?->oski_resit2_date?->format('Y-m-d'),
                    'test_date' => $existing?->test_date?->format('Y-m-d'),
                    'test_na' => (bool) $existing?->test_na,
                    'test_time' => $existing?->test_time,
                    'test_resit_date' => $existing?->test_resit_date?->format('Y-m-d'),
                    'test_resit2_date' => $existing?->test_resit2_date?->format('Y-m-d'),
                    'schedule_id' => $existing?->id,
                ];

                if ($includeCarbon) {
                    $item['lesson_start_date_carbon'] = $lessonInfo?->lesson_start ? \Carbon\Carbon::parse($lessonInfo->lesson_start) : null;
                    $item['lesson_end_date_carbon'] = $lessonInfo?->lesson_end ? \Carbon\Carbon::parse($lessonInfo->lesson_end) : null;
                    $item['oski_date_carbon'] = $existing?->oski_date;
                    $item['test_date_carbon'] = $existing?->test_date;
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
                // YN sanasi bo'yicha filtr (OSKI yoki Test sanasi)
                $scheduleData = $scheduleData->filter(function ($item) use ($dateFrom, $dateTo) {
                    $oskiDate = $item['oski_date'];
                    $testDate = $item['test_date'];

                    // OSKI yoki Test sanalaridan kamida biri oraliqqa to'g'ri kelsa ko'rsatiladi
                    $oskiMatch = $oskiDate && (!$dateFrom || $oskiDate >= $dateFrom) && (!$dateTo || $oskiDate <= $dateTo);
                    $testMatch = $testDate && (!$dateFrom || $testDate >= $dateFrom) && (!$dateTo || $testDate <= $dateTo);

                    return $oskiMatch || $testMatch;
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
        if (!$isAdmin && !ExamDateRoleService::roleHasAnyAccess($activeRole)) {
            abort(403, 'Bu amalni bajarish uchun ruxsat yo\'q.');
        }
        // Saqlangan sanani o'zgartirish — admin yoki sozlamalarda ruxsat etilgan rol
        $canEditSaved = $isAdmin || ExamDateRoleService::roleHasAnyAccess($activeRole);
        $minDate = $isAdmin ? $today : $today->copy()->addDay();

        // Foydalanuvchi rolga ruxsat etilgan kurs darajalari
        $allowedLevelCodes = $isAdmin
            ? null // admin uchun cheklov yo'q
            : ExamDateRoleService::levelsForRole($activeRole);

        // (curriculum_hemis_id . '_' . semester_code) → level_code map (validatsiya uchun)
        $semesterLevelMap = collect();
        if (is_array($allowedLevelCodes)) {
            $groupIds = collect($validSchedules)->pluck('group_hemis_id')->filter()->unique()->values();
            $groupCurriculumMap = Group::whereIn('group_hemis_id', $groupIds)
                ->pluck('curriculum_hemis_id', 'group_hemis_id');
            $curriculumIds = $groupCurriculumMap->unique()->values();
            $semesterCodes = collect($validSchedules)->pluck('semester_code')->filter()->unique()->values();

            if ($curriculumIds->isNotEmpty() && $semesterCodes->isNotEmpty()) {
                $semesterLevelMap = Semester::whereIn('curriculum_hemis_id', $curriculumIds)
                    ->whereIn('code', $semesterCodes)
                    ->get(['curriculum_hemis_id', 'code', 'level_code'])
                    ->mapWithKeys(fn($s) => [$s->curriculum_hemis_id . '_' . $s->code => (string) $s->level_code]);
            }

            // Foydalanuvchi rolga ruxsat etilmagan kurslarga tegishli yozuvlarni rad etish
            foreach ($validSchedules as $schedule) {
                $curriculumId = $groupCurriculumMap[$schedule['group_hemis_id']] ?? null;
                if (!$curriculumId) {
                    continue;
                }
                $lvl = (string) ($semesterLevelMap[$curriculumId . '_' . $schedule['semester_code']] ?? '');
                if ($lvl !== '' && !in_array($lvl, $allowedLevelCodes, true)) {
                    return redirect()->back()->with('error', 'Sizning rolingizga ushbu kurs darajasi uchun YN sanasini belgilashga ruxsat yo\'q. Sozlamalardan tekshiring.');
                }
            }
        }

        foreach ($validSchedules as $schedule) {
            $existingRec = $existingForValidation->get(
                $schedule['group_hemis_id'] . '_' . $schedule['subject_id'] . '_' . $schedule['semester_code']
            );

            if (!empty($schedule['oski_date'])) {
                $alreadySaved = $existingRec && $existingRec->oski_date
                    && $existingRec->oski_date->format('Y-m-d') === $schedule['oski_date'];
                if (!$alreadySaved) {
                    $oskiDate = \Carbon\Carbon::parse($schedule['oski_date']);
                    if ($oskiDate->lt($minDate)) {
                        return redirect()->back()->with('error', $isAdmin
                            ? 'OSKI sanasi o\'tgan kunni qo\'yib bo\'lmaydi.'
                            : 'OSKI sanasi kamida ertadan bo\'lishi kerak. Bugun yoki o\'tgan kunni qo\'yib bo\'lmaydi.');
                    }
                }
            }
            if (!empty($schedule['test_date'])) {
                $alreadySaved = $existingRec && $existingRec->test_date
                    && $existingRec->test_date->format('Y-m-d') === $schedule['test_date'];
                if (!$alreadySaved) {
                    $testDate = \Carbon\Carbon::parse($schedule['test_date']);
                    if ($testDate->lt($minDate)) {
                        return redirect()->back()->with('error', $isAdmin
                            ? 'Test sanasi o\'tgan kunni qo\'yib bo\'lmaydi.'
                            : 'Test sanasi kamida ertadan bo\'lishi kerak. Bugun yoki o\'tgan kunni qo\'yib bo\'lmaydi.');
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
                // faqat resit/resit2 sanalari yoziladi (asosiy guruhga umumiy)
                if ($studentHemisIdForRow) {
                    $resitOnly = ['oski_resit_date', 'oski_resit2_date', 'test_resit_date', 'test_resit2_date'];
                    $hasAnyResit = false;
                    foreach ($resitOnly as $rf) {
                        if (!empty($schedule[$rf])) { $hasAnyResit = true; break; }
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
                foreach ($resitFields as $rf) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('exam_schedules', $rf) && array_key_exists($rf, $schedule)) {
                        $record->{$rf} = !empty($schedule[$rf]) ? $schedule[$rf] : null;
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
            }
            DB::commit();

            foreach ($bookingsToDispatch as [$id, $yn]) {
                AssignComputersJob::dispatch($id, $yn);
                BookMoodleGroupExam::dispatch($id, $yn);
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

        // 4. Kurslar (level filtrini tashlab)
        $levelCurrIds = $buildQuery('level_code')->pluck('curricula_hemis_id');
        $levelQuery = Semester::where($currentSemesterOnly ? 'current' : 'education_year', $currentSemesterOnly ? true : $currentEducationYear)
            ->whereIn('curriculum_hemis_id', $levelCurrIds);
        if ($semesterCode) $levelQuery->where('code', $semesterCode);

        // Foydalanuvchi rolga ruxsat etilgan kurs darajalari (admin emas bo'lsa)
        $user = auth()->user() ?? auth('teacher')->user();
        $activeRole = session('active_role', $user?->getRoleNames()->first());
        $isAdmin = $user && in_array($activeRole, ExamDateRoleService::adminRoles(), true);
        if (!$isAdmin) {
            $allowedLevelCodes = ExamDateRoleService::levelsForRole($activeRole);
            if (!empty($allowedLevelCodes)) {
                $levelQuery->whereIn('level_code', $allowedLevelCodes);
            }
        }

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

            // --- JN/MT hisoblash (jurnal logikasi bilan bir xil) ---
            $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

            // JB jadval sanalarini olish
            $jbScheduleRows = DB::table('schedules')
                ->where('group_id', $group->group_hemis_id)
                ->where('subject_id', $subject->subject_id)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->whereNotIn('training_type_code', $excludedTrainingCodes)
                ->whereNotNull('lesson_date')
                ->select('lesson_date', 'lesson_pair_code')
                ->orderBy('lesson_date')
                ->orderBy('lesson_pair_code')
                ->get();

            $jbColumns = $jbScheduleRows->map(fn($s) => [
                    'date' => \Carbon\Carbon::parse($s->lesson_date)->format('Y-m-d'),
                    'pair' => $s->lesson_pair_code,
                ])->unique(fn($item) => $item['date'] . '_' . $item['pair'])->values();

            $jbLessonDates = $jbColumns->pluck('date')->unique()->sort()->values()->toArray();

            $jbPairsPerDay = [];
            foreach ($jbColumns as $col) {
                $jbPairsPerDay[$col['date']] = ($jbPairsPerDay[$col['date']] ?? 0) + 1;
            }

            $jbDatePairSet = [];
            foreach ($jbColumns as $col) {
                $jbDatePairSet[$col['date'] . '_' . $col['pair']] = true;
            }

            $gradingCutoffDate = \Carbon\Carbon::now('Asia/Tashkent')->endOfDay();
            $jbLessonDatesForAverage = array_values(array_filter($jbLessonDates, function ($date) use ($gradingCutoffDate) {
                return \Carbon\Carbon::parse($date, 'Asia/Tashkent')->startOfDay()->lte($gradingCutoffDate);
            }));
            $jbLessonDatesForAverageLookup = array_flip($jbLessonDatesForAverage);
            $totalJbDaysForAverage = count($jbLessonDatesForAverage);

            // MT jadval sanalarini olish
            $mtScheduleRows = DB::table('schedules')
                ->where('group_id', $group->group_hemis_id)
                ->where('subject_id', $subject->subject_id)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->where('training_type_code', 99)
                ->whereNotNull('lesson_date')
                ->select('lesson_date', 'lesson_pair_code')
                ->orderBy('lesson_date')
                ->orderBy('lesson_pair_code')
                ->get();

            $mtColumns = $mtScheduleRows->map(fn($s) => [
                    'date' => \Carbon\Carbon::parse($s->lesson_date)->format('Y-m-d'),
                    'pair' => $s->lesson_pair_code,
                ])->unique(fn($item) => $item['date'] . '_' . $item['pair'])->values();

            $mtLessonDates = $mtColumns->pluck('date')->unique()->sort()->values()->toArray();

            $mtPairsPerDay = [];
            foreach ($mtColumns as $col) {
                $mtPairsPerDay[$col['date']] = ($mtPairsPerDay[$col['date']] ?? 0) + 1;
            }

            $mtDatePairSet = [];
            foreach ($mtColumns as $col) {
                $mtDatePairSet[$col['date'] . '_' . $col['pair']] = true;
            }

            $totalMtDays = count($mtLessonDates);

            // Talabalarni olish
            $students = Student::select('id', 'full_name as student_name', 'student_id_number as student_id', 'hemis_id')
                ->where('group_id', $group->group_hemis_id)
                ->groupBy('id')
                ->orderBy('full_name')
                ->get();

            $studentHemisIds = $students->pluck('hemis_id')->toArray();

            // Baholarni olish
            $allGradesRaw = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subject->subject_id)
                ->where('semester_code', $semesterCode)
                ->whereNotIn('training_type_code', [100, 101, 102, 103])
                ->whereNotNull('lesson_date')
                ->select('student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                ->orderBy('lesson_date')
                ->orderBy('lesson_pair_code')
                ->get();

            // Baho filtrlash (jurnal logikasi bilan bir xil)
            $getEffectiveGrade = function ($row) {
                if ($row->status === 'pending' && $row->reason === 'low_grade' && $row->grade !== null) {
                    return $row->grade;
                }
                if ($row->status === 'pending') return null;
                if ($row->reason === 'absent' && $row->grade === null) {
                    return $row->retake_grade !== null ? $row->retake_grade : null;
                }
                if ($row->status === 'closed' && $row->reason === 'teacher_victim' && $row->grade == 0 && $row->retake_grade === null) {
                    return null;
                }
                if ($row->status === 'recorded') return $row->grade;
                if ($row->status === 'closed') return $row->grade;
                if ($row->retake_grade !== null) return $row->retake_grade;
                return null;
            };

            // JB va MT baholarni tuzish
            $jbGrades = [];
            $mtGradesArr = [];
            foreach ($allGradesRaw as $g) {
                $effectiveGrade = $getEffectiveGrade($g);
                if ($effectiveGrade === null) continue;
                $normalizedDate = \Carbon\Carbon::parse($g->lesson_date)->format('Y-m-d');
                $key = $normalizedDate . '_' . $g->lesson_pair_code;
                if (isset($jbDatePairSet[$key])) {
                    $jbGrades[$g->student_hemis_id][$normalizedDate][$g->lesson_pair_code] = $effectiveGrade;
                }
                if (isset($mtDatePairSet[$key])) {
                    $mtGradesArr[$g->student_hemis_id][$normalizedDate][$g->lesson_pair_code] = $effectiveGrade;
                }
            }

            // Manual MT baholarini olish
            $manualMtGrades = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subject->subject_id)
                ->where('semester_code', $semesterCode)
                ->where('training_type_code', 99)
                ->whereNull('lesson_date')
                ->whereNotNull('grade')
                ->select('student_hemis_id', 'grade')
                ->get()
                ->keyBy('student_hemis_id');

            // Har bir talaba uchun JN va MT hisoblash
            foreach ($students as $student) {
                $hemisId = $student->hemis_id;

                // JN hisoblash
                $dailySum = 0;
                $studentDayGrades = $jbGrades[$hemisId] ?? [];
                foreach ($jbLessonDates as $date) {
                    $dayGrades = $studentDayGrades[$date] ?? [];
                    $pairsInDay = $jbPairsPerDay[$date] ?? 1;
                    $gradeSum = array_sum($dayGrades);
                    $dayAverage = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                    if (isset($jbLessonDatesForAverageLookup[$date])) {
                        $dailySum += $dayAverage;
                    }
                }
                $student->jn = $totalJbDaysForAverage > 0
                    ? round($dailySum / $totalJbDaysForAverage, 0, PHP_ROUND_HALF_UP)
                    : 0;

                // MT hisoblash
                $mtDailySum = 0;
                $studentMtGrades = $mtGradesArr[$hemisId] ?? [];
                foreach ($mtLessonDates as $date) {
                    $dayGrades = $studentMtGrades[$date] ?? [];
                    $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                    $gradeSum = array_sum($dayGrades);
                    $mtDailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                }
                $mt = $totalMtDays > 0
                    ? round($mtDailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                    : 0;

                // Manual MT override
                if (isset($manualMtGrades[$hemisId])) {
                    $mt = round((float) $manualMtGrades[$hemisId]->grade, 0, PHP_ROUND_HALF_UP);
                }

                $student->mt = $mt;
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

    public function saveTestTime(Request $request)
    {
        if ($this->isTestCenterReadOnly()) {
            return response()->json(['success' => false, 'message' => 'Bu amalga ruxsat yo\'q.'], 403);
        }

        $request->validate([
            'group_hemis_id' => 'required|string',
            'subject_id' => 'required|string',
            'semester_code' => 'required|string',
            'test_time' => 'required|date_format:H:i',
            'yn_type' => 'nullable|string|in:OSKI,Test',
        ]);

        $examSchedule = ExamSchedule::where('group_hemis_id', $request->group_hemis_id)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->first();

        if (!$examSchedule) {
            return response()->json(['success' => false, 'message' => 'Jadval topilmadi'], 404);
        }

        // YN turiga qarab tegishli vaqt ustunini yangilash
        $ynType = $request->input('yn_type', 'Test');
        $timeColumn = $ynType === 'OSKI' ? 'oski_time' : 'test_time';
        $ynLabel = $ynType === 'OSKI' ? 'OSKI' : 'Test';
        $relatedDate = $ynType === 'OSKI' ? $examSchedule->oski_date : $examSchedule->test_date;

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
        $ynKey = $ynType === 'OSKI' ? 'oski' : 'test';
        $naFlag = $ynKey === 'oski' ? $examSchedule->oski_na : $examSchedule->test_na;
        if ($relatedDate && !$naFlag) {
            AssignComputersJob::dispatch($examSchedule->id, $ynKey);
            BookMoodleGroupExam::dispatch($examSchedule->id, $ynKey);
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

    public function exportTestCenter(Request $request)
    {
        $result = $this->buildTestCenterData($request);
        $scheduleData = $result['scheduleData'];

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\TestCenterExport($scheduleData),
            'yn_jadvali_' . date('Y-m-d_H-i') . '.xlsx'
        );
    }

    public function bandlikKursatkichi(Request $request)
    {
        $totalComputers = (int) ExamCapacityService::getSettings()['computer_count'];
        $today = now()->format('Y-m-d');

        // Faqat bugundan keyingi (bugun kiradi) test vaqtlari belgilangan sanalar
        $oskiDates = ExamSchedule::whereNotNull('oski_date')
            ->whereNotNull('oski_time')
            ->where('oski_na', false)
            ->whereDate('oski_date', '>=', $today)
            ->pluck('oski_date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'));

        $testDates = ExamSchedule::whereNotNull('test_date')
            ->whereNotNull('test_time')
            ->where('test_na', false)
            ->whereDate('test_date', '>=', $today)
            ->pluck('test_date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'));

        $uniqueDates = $oskiDates->merge($testDates)->unique()->sort()->values();

        if ($uniqueDates->isEmpty()) {
            return view('admin.academic-schedule.bandlik-kursatkichi', [
                'dateCards' => collect(),
                'totalComputers' => $totalComputers,
            ]);
        }

        $minDate = $uniqueDates->first();
        $maxDate = $uniqueDates->last();

        // Sanalar oralig'idagi barcha schedule yozuvlarini olish
        $schedules = ExamSchedule::with(['group'])
            ->where(function ($q) use ($minDate, $maxDate) {
                $q->where(function ($q2) use ($minDate, $maxDate) {
                    $q2->whereNotNull('oski_date')
                       ->whereNotNull('oski_time')
                       ->where('oski_na', false)
                       ->whereBetween('oski_date', [$minDate, $maxDate]);
                })->orWhere(function ($q2) use ($minDate, $maxDate) {
                    $q2->whereNotNull('test_date')
                       ->whereNotNull('test_time')
                       ->where('test_na', false)
                       ->whereBetween('test_date', [$minDate, $maxDate]);
                });
            })
            ->get();

        // Har bir sana uchun ma'lumotlarni guruhlash
        $byDate = [];
        foreach ($schedules as $schedule) {
            $oskiDateStr = $schedule->oski_date?->format('Y-m-d');
            if ($oskiDateStr && $schedule->oski_time && !$schedule->oski_na && $oskiDateStr >= $today) {
                $byDate[$oskiDateStr][] = [
                    'group_hemis_id' => $schedule->group_hemis_id,
                    'yn_type' => 'OSKI',
                    'time' => \Carbon\Carbon::parse($schedule->oski_time)->format('H:i'),
                ];
            }
            $testDateStr = $schedule->test_date?->format('Y-m-d');
            if ($testDateStr && $schedule->test_time && !$schedule->test_na && $testDateStr >= $today) {
                $byDate[$testDateStr][] = [
                    'group_hemis_id' => $schedule->group_hemis_id,
                    'yn_type' => 'Test',
                    'time' => \Carbon\Carbon::parse($schedule->test_time)->format('H:i'),
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

        // Har bir sana uchun karta ma'lumotlari
        $dateCards = collect();
        foreach ($uniqueDates as $dateStr) {
            $items = $byDate[$dateStr] ?? [];
            $slotKeys = collect($items)->map(fn($i) => $i['time'] . '|' . $i['yn_type'])->unique();
            $totalStudents = 0;
            $maxOccupied = 0;
            $slotsOccupancy = [];
            foreach ($items as $item) {
                $slotKey = $item['time'] . '|' . $item['yn_type'];
                $cnt = (int) ($studentCounts[$item['group_hemis_id']] ?? 0);
                $slotsOccupancy[$slotKey] = ($slotsOccupancy[$slotKey] ?? 0) + $cnt;
                $totalStudents += $cnt;
            }
            foreach ($slotsOccupancy as $occ) {
                if ($occ > $maxOccupied) $maxOccupied = $occ;
            }

            $carbonDate = \Carbon\Carbon::parse($dateStr);
            $dateCards->push([
                'date' => $carbonDate,
                'date_str' => $dateStr,
                'slot_count' => $slotKeys->count(),
                'group_count' => count($items),
                'total_students' => $totalStudents,
                'max_occupied' => $maxOccupied,
                'is_today' => $dateStr === $today,
                'has_overflow' => $maxOccupied > $totalComputers,
            ]);
        }

        return view('admin.academic-schedule.bandlik-kursatkichi', [
            'dateCards' => $dateCards,
            'totalComputers' => $totalComputers,
        ]);
    }

    public function bandlikKursatkichiShow(Request $request, string $date)
    {
        $totalComputers = (int) ExamCapacityService::getSettings()['computer_count'];

        // Sana validatsiyasi
        try {
            $carbonDate = \Carbon\Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable $e) {
            abort(404);
        }

        $schedules = ExamSchedule::with(['group'])
            ->where(function ($q) use ($date) {
                $q->where(function ($q2) use ($date) {
                    $q2->whereNotNull('oski_time')
                       ->where('oski_na', false)
                       ->whereDate('oski_date', $date);
                })->orWhere(function ($q2) use ($date) {
                    $q2->whereNotNull('test_time')
                       ->where('test_na', false)
                       ->whereDate('test_date', $date);
                });
            })
            ->get();

        // (time, yn_type) bo'yicha guruhlarni birlashtirish
        $rows = [];
        foreach ($schedules as $schedule) {
            $oskiDateStr = $schedule->oski_date?->format('Y-m-d');
            if ($oskiDateStr === $date && $schedule->oski_time && !$schedule->oski_na) {
                $timeStr = \Carbon\Carbon::parse($schedule->oski_time)->format('H:i');
                $key = $timeStr . '|OSKI';
                if (!isset($rows[$key])) {
                    $rows[$key] = [
                        'time' => $timeStr,
                        'yn_type' => 'OSKI',
                        'groups' => [],
                    ];
                }
                $rows[$key]['groups'][] = [
                    'group_hemis_id' => $schedule->group_hemis_id,
                    'group_name' => $schedule->group?->name ?? $schedule->group_hemis_id,
                    'subject_name' => $schedule->subject_name ?? '',
                ];
            }
            $testDateStr = $schedule->test_date?->format('Y-m-d');
            if ($testDateStr === $date && $schedule->test_time && !$schedule->test_na) {
                $timeStr = \Carbon\Carbon::parse($schedule->test_time)->format('H:i');
                $key = $timeStr . '|Test';
                if (!isset($rows[$key])) {
                    $rows[$key] = [
                        'time' => $timeStr,
                        'yn_type' => 'Test',
                        'groups' => [],
                    ];
                }
                $rows[$key]['groups'][] = [
                    'group_hemis_id' => $schedule->group_hemis_id,
                    'group_name' => $schedule->group?->name ?? $schedule->group_hemis_id,
                    'subject_name' => $schedule->subject_name ?? '',
                ];
            }
        }

        // Talabalar soni
        $allGroupIds = collect($rows)->pluck('groups')->flatten(1)->pluck('group_hemis_id')->unique()->toArray();
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

        foreach ($rows as &$row) {
            $occupied = 0;
            foreach ($row['groups'] as &$grp) {
                $cnt = (int) ($studentCounts[$grp['group_hemis_id']] ?? 0);
                $grp['student_count'] = $cnt;
                $occupied += $cnt;
            }
            unset($grp);
            $row['occupied'] = $occupied;
            $row['free'] = max(0, $totalComputers - $occupied);
            $row['overflow'] = max(0, $occupied - $totalComputers);
            $row['usage_percent'] = $totalComputers > 0 ? round(($occupied / $totalComputers) * 100, 1) : 0;
        }
        unset($row);

        // Vaqt bo'yicha saralash
        $slots = collect($rows)->sortBy('time')->values();

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

        // Talabalar qaysi urinishda V<60 bo'lib qolganligini aniqlash.
        // Strict mantiq: faqat haqiqiy bahosi mavjud bo'lib, lekin <60 bo'lgan yozuvlar.
        // Null/null yozuvlar (NB placeholderlar) inobatga olinmaydi.
        // Guruh bo'yicha alohida filterlanadi (chunki har item alohida guruh).
        $needsByKey = [];
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
        } catch (\Throwable $e) {
            \Log::warning('expandByUrinish needs check failed: ' . $e->getMessage());
        }

        return $scheduleData->map(function ($items) use ($urinishFilter, $needsByKey) {
            $expanded = collect();
            foreach ($items as $item) {
                $groupHid = $item['group']->group_hemis_id ?? '';
                $subjectId = $item['subject']->subject_id ?? '';
                $semCode = $item['subject']->semester_code ?? '';
                $needsKeyBase = $groupHid . '|' . $subjectId . '|' . $semCode;

                // 1-urinish — har doim
                $row1 = $item;
                $row1['urinish'] = 1;
                $row1['oski_date_for_urinish'] = $item['oski_date'] ?? null;
                $row1['test_date_for_urinish'] = $item['test_date'] ?? null;
                $row1['oski_na_for_urinish'] = $item['oski_na'] ?? false;
                $row1['test_na_for_urinish'] = $item['test_na'] ?? false;

                // 2-urinish — FAQAT mavjud yoki haqiqatan kerakli (yiqilgan talabalar bor)
                $has2Data = !empty($item['oski_resit_date']) || !empty($item['test_resit_date']);
                $needs2 = isset($needsByKey[$needsKeyBase . '|2']);
                $show2 = $has2Data || $needs2;

                $row2 = null;
                if ($show2) {
                    $row2 = $item;
                    $row2['urinish'] = 2;
                    $row2['oski_date_for_urinish'] = $item['oski_resit_date'] ?? null;
                    $row2['test_date_for_urinish'] = $item['test_resit_date'] ?? null;
                    $row2['oski_na_for_urinish'] = false;
                    $row2['test_na_for_urinish'] = false;
                }

                // 3-urinish — FAQAT mavjud yoki haqiqatan kerakli (12a yiqilganlar bor)
                $has3Data = !empty($item['oski_resit2_date']) || !empty($item['test_resit2_date']);
                $needs3 = isset($needsByKey[$needsKeyBase . '|3']);
                $show3 = $has3Data || $needs3;

                $row3 = null;
                if ($show3) {
                    $row3 = $item;
                    $row3['urinish'] = 3;
                    $row3['oski_date_for_urinish'] = $item['oski_resit2_date'] ?? null;
                    $row3['test_date_for_urinish'] = $item['test_resit2_date'] ?? null;
                    $row3['oski_na_for_urinish'] = false;
                    $row3['test_na_for_urinish'] = false;
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
}
