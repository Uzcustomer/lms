<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\ExamSchedule;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\MarkingSystemScore;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Specialty;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\DocumentVerification;
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
     * O'quv bo'limi uchun: YN kunini belgilash sahifasi
     */
    public function index(Request $request)
    {
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
        }

        $routePrefix = $this->routePrefix();

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
            'currentSemesterToggle',
            'isSearched',
            'currentEducationYear',
            'routePrefix',
        ));
    }

    /**
     * Test markazi uchun: Yakuniy nazoratlar jadvali (faqat ko'rish)
     * Filtrlari by default bugungi sanaga o'rnatiladi va avtomatik qidiriladi
     * OSKI/Test alohida ustun emas — YN turi va YN sanasi sifatida ko'rsatiladi
     */
    public function testCenterView(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('testCenterView: metod boshlandi', [
            'url' => $request->fullUrl(),
            'guard' => auth()->getDefaultDriver(),
            'user_id' => auth()->id(),
            'user_class' => auth()->user() ? get_class(auth()->user()) : 'null',
        ]);

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

        $currentSemesters = Semester::where('current', true)->get();
        $currentEducationYear = $currentSemesters->first()?->education_year;

        // Ma'lumotlarni yuklash (YN sanasi bo'yicha filtr)
        $scheduleData = $this->loadScheduleData(
            $currentSemesters, $selectedDepartment, $selectedSpecialty,
            $selectedSemester, $selectedGroup, $selectedEducationType,
            $selectedLevelCode, $selectedSubject, $selectedStatus,
            $currentSemesterToggle, true, $dateFrom, $dateTo, true
        );

        // OSKI/Test ma'lumotlarini alohida qatorlarga ajratish (YN turi + 1-urinish sanasi)
        // Qo'shimcha ustunlar uchun: semester -> kurs, curriculum -> shakl
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

                // Qo'shimcha ma'lumotlar
                $sem = $semesterMap->get($item['subject']->semester_code);
                $extraFields = [
                    'subject_code' => $item['subject']->subject_id ?? '',
                    'level_name' => $sem?->level_name ?? '',
                    'semester_name' => $item['subject']->semester_name ?? ($sem?->name ?? ''),
                    'education_form_name' => $curriculumFormMap->get($item['group']->curriculum_hemis_id) ?? '',
                ];

                // OSKI qatori: sana oraliqqa to'g'ri kelsa yoki N/A bo'lsa
                $oskiInRange = $oskiDate && (!$dateFrom || $oskiDate >= $dateFrom) && (!$dateTo || $oskiDate <= $dateTo);
                if ($oskiInRange || ($oskiNa && !$dateFrom && !$dateTo)) {
                    $ynItem = array_merge($item, $extraFields);
                    $ynItem['yn_type'] = 'OSKI';
                    $ynItem['yn_date'] = $oskiDate;
                    $ynItem['yn_date_carbon'] = $item['oski_date_carbon'] ?? null;
                    $ynItem['yn_na'] = $oskiNa;
                    $transformedData->push($ynItem);
                }

                // Test qatori: sana oraliqqa to'g'ri kelsa yoki N/A bo'lsa
                $testInRange = $testDate && (!$dateFrom || $testDate >= $dateFrom) && (!$dateTo || $testDate <= $dateTo);
                if ($testInRange || ($testNa && !$dateFrom && !$dateTo)) {
                    $ynItem = array_merge($item, $extraFields);
                    $ynItem['yn_type'] = 'Test';
                    $ynItem['yn_date'] = $testDate;
                    $ynItem['yn_date_carbon'] = $item['test_date_carbon'] ?? null;
                    $ynItem['yn_na'] = $testNa;
                    $transformedData->push($ynItem);
                }
            }
        }

        // Guruh talabalar sonini hisoblash
        $groupHemisIds = $transformedData->pluck('group')->pluck('group_hemis_id')->unique()->toArray();
        $studentCounts = DB::table('students')
            ->whereIn('group_id', $groupHemisIds)
            ->where('student_status_code', 11)
            ->groupBy('group_id')
            ->select('group_id', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'group_id');

        // YN yuborilgan holati (o'qituvchi YN ga yuborish tugmasini bosganmi)
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

        // Quiz natijalarini hisoblash (guruh + fan + yn_turi bo'yicha nechta talaba topshirgan)
        $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
        $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];

        $subjectIds = $transformedData->pluck('subject')->pluck('subject_id')->unique()->toArray();

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
                \Illuminate\Support\Facades\Log::warning('testCenterView: hemis_quiz_results so\'rovi xatolik berdi: ' . $e->getMessage());
            }
        }

        // Ma'lumotlarga qo'shish
        $transformedData = $transformedData->map(function ($item) use ($studentCounts, $quizCounts, $ynSubmissions) {
            $item['student_count'] = $studentCounts[$item['group']->group_hemis_id] ?? 0;
            $quizKey = $item['group']->group_hemis_id . '|' . ($item['subject']->subject_id ?? '') . '|' . ($item['yn_type'] ?? '');
            $item['quiz_count'] = $quizCounts[$quizKey] ?? 0;
            $ynKey = $item['group']->group_hemis_id . '|' . ($item['subject']->subject_id ?? '') . '|' . ($item['subject']->semester_code ?? '');
            $item['yn_submitted'] = isset($ynSubmissions[$ynKey]);
            return $item;
        });

        $scheduleData = $transformedData->groupBy(fn($item) => $item['group']->group_hemis_id);

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
        // Semestr kodlarini aniqlash
        $semesterCodes = collect();
        if ($selectedSemester) {
            $semesterCodes = collect([$selectedSemester]);
        } elseif ($currentSemesterToggle === '1') {
            $semesterCodes = $currentSemesters->pluck('code')->unique();
        }

        if ($selectedLevelCode) {
            $levelSemCodes = Semester::where('current', true)
                ->where('level_code', $selectedLevelCode)
                ->pluck('code')->unique();
            $semesterCodes = $semesterCodes->isEmpty()
                ? $levelSemCodes
                : $semesterCodes->intersect($levelSemCodes);
        }

        // O'quv rejalarini olish (current=1 yoki joriy semestri bor)
        $curriculumQuery = Curriculum::where(function($q) {
            $q->where('current', true)
              ->orWhereIn('curricula_hemis_id', Semester::where('current', true)->select('curriculum_hemis_id'));
        });
        if ($selectedDepartment) $curriculumQuery->where('department_hemis_id', $selectedDepartment);
        if ($selectedSpecialty) $curriculumQuery->where('specialty_hemis_id', $selectedSpecialty);
        if ($selectedEducationType) $curriculumQuery->where('education_type_code', $selectedEducationType);
        $curriculumIds = $curriculumQuery->pluck('curricula_hemis_id');

        if ($curriculumIds->isEmpty()) return collect();

        // Fanlar
        $subjectQuery = CurriculumSubject::whereIn('curricula_hemis_id', $curriculumIds)
            ->where('is_active', true);
        if ($semesterCodes->isNotEmpty()) {
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
                    'specialty_name' => $group->specialty_name,
                    'lesson_start_date' => $lessonInfo?->lesson_start ? substr($lessonInfo->lesson_start, 0, 10) : null,
                    'lesson_end_date' => $lessonInfo?->lesson_end ? substr($lessonInfo->lesson_end, 0, 10) : null,
                    'oski_date' => $existing?->oski_date?->format('Y-m-d'),
                    'oski_na' => (bool) $existing?->oski_na,
                    'test_date' => $existing?->test_date?->format('Y-m-d'),
                    'test_na' => (bool) $existing?->test_na,
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

        DB::beginTransaction();
        try {
            foreach ($validSchedules as $schedule) {
                $oskiNa = !empty($schedule['oski_na']);
                $testNa = !empty($schedule['test_na']);
                $hasAnyData = !empty($schedule['oski_date']) || !empty($schedule['test_date']) || $oskiNa || $testNa;

                if (!$hasAnyData) {
                    ExamSchedule::where('group_hemis_id', $schedule['group_hemis_id'])
                        ->where('subject_id', $schedule['subject_id'])
                        ->where('semester_code', $schedule['semester_code'])
                        ->delete();
                    continue;
                }

                $record = ExamSchedule::firstOrNew([
                    'group_hemis_id' => $schedule['group_hemis_id'],
                    'subject_id' => $schedule['subject_id'],
                    'semester_code' => $schedule['semester_code'],
                ]);

                $record->fill([
                    'department_hemis_id' => $schedule['department_hemis_id'] ?? '',
                    'specialty_hemis_id' => $schedule['specialty_hemis_id'] ?? '',
                    'curriculum_hemis_id' => $schedule['curriculum_hemis_id'] ?? '',
                    'subject_name' => $schedule['subject_name'] ?? '',
                    'oski_date' => !empty($schedule['oski_date']) ? $schedule['oski_date'] : null,
                    'oski_na' => $oskiNa,
                    'test_date' => !empty($schedule['test_date']) ? $schedule['test_date'] : null,
                    'test_na' => $testNa,
                    'education_year' => $educationYear,
                    'updated_by' => $userId,
                ]);

                if (!$record->exists) {
                    $record->created_by = $userId;
                }

                $record->save();
            }
            DB::commit();

            return redirect()->back()->with('success', 'Imtihon sanalari muvaffaqiyatli saqlandi!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
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

        $currentSemSubquery = Semester::where('current', true)->select('curriculum_hemis_id');

        // Curriculum query builder: barcha filtrlarni qo'llaydi, $exclude ni tashlab
        $buildQuery = function (?string $exclude = null) use (
            $currentSemSubquery, $educationType, $departmentId, $specialtyId, $levelCode, $semesterCode
        ) {
            $q = Curriculum::where(function ($sub) use ($currentSemSubquery) {
                $sub->where('current', true)
                    ->orWhereIn('curricula_hemis_id', $currentSemSubquery);
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
                $q->whereIn('curricula_hemis_id', function ($sub) use ($applyLevel, $applySemester, $levelCode, $semesterCode) {
                    $sub->select('curriculum_hemis_id')->from('semesters')->where('current', true);
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
        $levelQuery = Semester::where('current', true)
            ->whereIn('curriculum_hemis_id', $levelCurrIds);
        if ($semesterCode) $levelQuery->where('code', $semesterCode);
        $levels = $levelQuery->select('level_code', 'level_name')
            ->distinct()->orderBy('level_code')->get();

        // 5. Semestrlar (semester filtrini tashlab)
        $semCurrIds = $buildQuery('semester_code')->pluck('curricula_hemis_id');
        $semQuery = Semester::where('current', true)
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
            $levelSemCodes = Semester::where('current', true)
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

            $currentDate = now();
            $lessonCount = Schedule::where('subject_id', $subject->subject_id)
                ->where('group_id', $group->group_hemis_id)
                ->whereNotIn('training_type_code', config('app.training_type_code'))
                ->where('lesson_date', '<=', $currentDate)
                ->distinct('lesson_date')
                ->count();

            if ($lessonCount == 0) $lessonCount = 1;

            $students = Student::selectRaw('
                students.full_name as student_name,
                students.student_id_number as student_id,
                students.hemis_id as hemis_id,
                ROUND(
                    (SELECT sum(inner_table.average_grade) / ' . $lessonCount . '
                    FROM (
                        SELECT lesson_date, AVG(COALESCE(
                            CASE
                                WHEN status = "retake" AND (reason = "absent" OR reason = "teacher_victim")
                                THEN retake_grade
                                WHEN status = "retake" AND reason = "low_grade"
                                THEN GREATEST(grade, retake_grade)
                                WHEN status = "pending" AND reason = "absent"
                                THEN grade
                                ELSE grade
                            END, 0)) AS average_grade
                        FROM student_grades
                        WHERE student_grades.student_hemis_id = students.hemis_id
                        AND student_grades.subject_id = ' . $subject->subject_id . '
                        AND student_grades.training_type_code NOT IN (' . implode(',', config('app.training_type_code')) . ')
                        GROUP BY student_grades.lesson_date
                    ) AS inner_table)
                ) as jn,
                ROUND(
                    (SELECT avg(student_grades.grade) as average_grade
                    FROM student_grades
                    WHERE student_grades.student_hemis_id = students.hemis_id
                    AND student_grades.subject_id = ' . $subject->subject_id . '
                    AND student_grades.training_type_code = 99
                    GROUP BY student_grades.student_hemis_id)
                ) as mt
            ')
                ->where('students.group_id', $group->group_hemis_id)
                ->groupBy('students.id')
                ->orderBy('students.full_name')
                ->get();

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
            $groupSeparatorBg = ['bgColor' => 'E2EFDA', 'valign' => 'center', 'gridSpan' => 7];
            $cellCenter = ['alignment' => Jc::CENTER];
            $cellLeft = ['alignment' => Jc::START];

            $headerRow = $table->addRow(400);
            $headerRow->addCell(600, $headerBg)->addText('№', $headerFont, $cellCenter);
            $headerRow->addCell(4500, $headerBg)->addText('Talaba F.I.O', $headerFont, $cellCenter);
            $headerRow->addCell(1800, $headerBg)->addText('Talaba ID', $headerFont, $cellCenter);
            $headerRow->addCell(1200, $headerBg)->addText('JN', $headerFont, $cellCenter);
            $headerRow->addCell(1200, $headerBg)->addText("O'N", $headerFont, $cellCenter);
            $headerRow->addCell(1500, $headerBg)->addText('Davomat %', $headerFont, $cellCenter);
            $headerRow->addCell(2000, $headerBg)->addText('YN ga ruxsat', $headerFont, $cellCenter);

            // Talabalar ro'yxati - barcha guruhlar uchun davom etadi
            $rowNum = 1;
            $multipleGroups = count($subjectData['entries']) > 1;

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

                foreach ($entryStudents as $student) {
                    $markingScore = MarkingSystemScore::getByStudentHemisId($student->hemis_id);

                    $qoldirgan = (int) Attendance::where('group_id', $entryGroup->group_hemis_id)
                        ->where('subject_id', $entrySubject->subject_id)
                        ->where('student_hemis_id', $student->hemis_id)
                        ->sum('absent_off');

                    $totalAcload = $entrySubject->total_acload ?: 1;
                    $qoldiq = round($qoldirgan * 100 / $totalAcload, 2);

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
                    if ($qoldiq > 25) {
                        $davomatFailed = true;
                        $holat = 'X';
                    }

                    $dataRow = $table->addRow();
                    $dataRow->addCell(600)->addText($rowNum, $cellFont, $cellCenter);
                    $dataRow->addCell(4500)->addText($student->student_name, $cellFont, $cellLeft);
                    $dataRow->addCell(1800)->addText($student->student_id, $cellFont, $cellCenter);

                    $jnCell = $dataRow->addCell(1200);
                    $jnCell->addText($student->jn ?? '0', $jnFailed ? $cellFontRed : $cellFont, $cellCenter);

                    $mtCell = $dataRow->addCell(1200);
                    $mtCell->addText($student->mt ?? '0', $mtFailed ? $cellFontRed : $cellFont, $cellCenter);

                    $davomatCell = $dataRow->addCell(1500);
                    $davomatCell->addText(
                        ($qoldiq != 0 ? $qoldiq . '%' : '0%'),
                        $davomatFailed ? $cellFontRed : $cellFont,
                        $cellCenter
                    );

                    $holatCell = $dataRow->addCell(2000);
                    $holatCell->addText($holat, $holat === 'X' ? $cellFontRed : $cellFont, $cellCenter);

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
}
