<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     */
    public function testCenterView(Request $request)
    {
        $currentSemesters = Semester::where('current', true)->get();
        $currentEducationYear = $currentSemesters->first()?->education_year;

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

        $scheduleData = collect();
        if ($isSearched) {
            $scheduleData = $this->loadScheduleData(
                $currentSemesters, $selectedDepartment, $selectedSpecialty,
                $selectedSemester, $selectedGroup, $selectedEducationType,
                $selectedLevelCode, $selectedSubject, $selectedStatus,
                $currentSemesterToggle, true, $dateFrom, $dateTo
            );
        }

        $routePrefix = $this->routePrefix();

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
        ));
    }

    /**
     * Umumiy: jadval ma'lumotlarini yuklash (index va test-center uchun)
     */
    private function loadScheduleData(
        $currentSemesters, $selectedDepartment, $selectedSpecialty,
        $selectedSemester, $selectedGroup, $selectedEducationType,
        $selectedLevelCode, $selectedSubject, $selectedStatus,
        $currentSemesterToggle, $includeCarbon = false,
        $dateFrom = null, $dateTo = null
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
        $subjectQuery = CurriculumSubject::whereIn('curricula_hemis_id', $curriculumIds);
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

        // Sana oralig'i filtri (dars tugash sanasi bo'yicha)
        if ($dateFrom || $dateTo) {
            $scheduleData = $scheduleData->filter(function ($item) use ($dateFrom, $dateTo) {
                $lessonEnd = $item['lesson_end_date'];
                if (!$lessonEnd) return false;

                if ($dateFrom && $lessonEnd < $dateFrom) return false;
                if ($dateTo && $lessonEnd > $dateTo) return false;

                return true;
            });
        }

        return $scheduleData->groupBy(fn($item) => $item['group']->group_hemis_id);
    }

    /**
     * Imtihon sanalarini saqlash
     */
    public function store(Request $request)
    {
        $request->validate([
            'schedules' => 'required|array',
            'schedules.*.group_hemis_id' => 'required|string',
            'schedules.*.subject_id' => 'required|string',
            'schedules.*.subject_name' => 'required|string',
            'schedules.*.department_hemis_id' => 'required|string',
            'schedules.*.specialty_hemis_id' => 'required|string',
            'schedules.*.curriculum_hemis_id' => 'required|string',
            'schedules.*.semester_code' => 'required|string',
            'schedules.*.oski_date' => 'nullable|date',
            'schedules.*.test_date' => 'nullable|date',
        ]);

        $currentSemester = Semester::where('current', true)->first();
        $educationYear = $currentSemester?->education_year;
        $userId = auth()->id();

        DB::beginTransaction();
        try {
            foreach ($request->schedules as $schedule) {
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

                ExamSchedule::updateOrCreate(
                    [
                        'group_hemis_id' => $schedule['group_hemis_id'],
                        'subject_id' => $schedule['subject_id'],
                        'semester_code' => $schedule['semester_code'],
                    ],
                    [
                        'department_hemis_id' => $schedule['department_hemis_id'],
                        'specialty_hemis_id' => $schedule['specialty_hemis_id'],
                        'curriculum_hemis_id' => $schedule['curriculum_hemis_id'],
                        'subject_name' => $schedule['subject_name'],
                        'oski_date' => $schedule['oski_date'] ?: null,
                        'oski_na' => $oskiNa,
                        'test_date' => $schedule['test_date'] ?: null,
                        'test_na' => $testNa,
                        'education_year' => $educationYear,
                        'updated_by' => $userId,
                        'created_by' => $userId,
                    ]
                );
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
        $subjectQuery = CurriculumSubject::whereIn('curricula_hemis_id', $allCurrIds);
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
}
