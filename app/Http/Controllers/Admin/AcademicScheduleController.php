<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\Schedule;
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

        // Ta'lim turlari
        $educationTypes = Curriculum::where('current', true)
            ->select('education_type_code', 'education_type_name')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        // Fakultetlar
        $departments = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // Filtrlar
        $selectedEducationType = $request->get('education_type');
        $selectedDepartment = $request->get('department_id');
        $selectedSpecialty = $request->get('specialty_id');
        $selectedLevelCode = $request->get('level_code');
        $selectedSemester = $request->get('semester_code');
        $selectedGroup = $request->get('group_id');
        $selectedSubject = $request->get('subject_id');
        $selectedStatus = $request->get('status');
        $currentSemesterToggle = $request->get('current_semester', '1');
        $isSearched = $request->has('searched');

        // Dropdown data (sahifaga qayta yuklanganda tanlanganlarni ko'rsatish uchun)
        $specialties = collect();
        if ($selectedDepartment) {
            $specialties = Specialty::where('department_hemis_id', $selectedDepartment)
                ->orderBy('name')
                ->get();
        }

        $semesters = collect();
        if ($selectedDepartment) {
            $query = Semester::where('current', true)
                ->whereHas('curriculum', function ($q) use ($selectedDepartment, $selectedSpecialty, $selectedEducationType) {
                    $q->where('department_hemis_id', $selectedDepartment);
                    if ($selectedSpecialty) $q->where('specialty_hemis_id', $selectedSpecialty);
                    if ($selectedEducationType) $q->where('education_type_code', $selectedEducationType);
                });
            if ($selectedLevelCode) $query->where('level_code', $selectedLevelCode);
            $semesters = $query->select('code', 'name')->distinct()->orderBy('code')->get();
        }

        $groups = collect();
        if ($selectedDepartment) {
            $groupQuery = Group::where('department_hemis_id', $selectedDepartment)->where('active', true);
            if ($selectedSpecialty) $groupQuery->where('specialty_hemis_id', $selectedSpecialty);
            $groups = $groupQuery->orderBy('name')->get();
        }

        // Fanlar ro'yxati va jadval ma'lumotlari
        $scheduleData = collect();
        if ($isSearched) {
            $scheduleData = $this->loadScheduleData(
                $currentSemesters, $selectedDepartment, $selectedSpecialty,
                $selectedSemester, $selectedGroup, $selectedEducationType,
                $selectedLevelCode, $selectedSubject, $selectedStatus,
                $currentSemesterToggle
            );
        }

        $routePrefix = $this->routePrefix();

        return view('admin.academic-schedule.index', compact(
            'departments',
            'educationTypes',
            'specialties',
            'semesters',
            'groups',
            'scheduleData',
            'selectedEducationType',
            'selectedDepartment',
            'selectedSpecialty',
            'selectedLevelCode',
            'selectedSemester',
            'selectedGroup',
            'selectedSubject',
            'selectedStatus',
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

        $educationTypes = Curriculum::where('current', true)
            ->select('education_type_code', 'education_type_name')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        $departments = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $selectedEducationType = $request->get('education_type');
        $selectedDepartment = $request->get('department_id');
        $selectedSpecialty = $request->get('specialty_id');
        $selectedLevelCode = $request->get('level_code');
        $selectedSemester = $request->get('semester_code');
        $selectedGroup = $request->get('group_id');
        $selectedSubject = $request->get('subject_id');
        $selectedStatus = $request->get('status');
        $currentSemesterToggle = $request->get('current_semester', '1');
        $isSearched = $request->has('searched');

        $specialties = collect();
        if ($selectedDepartment) {
            $specialties = Specialty::where('department_hemis_id', $selectedDepartment)
                ->orderBy('name')
                ->get();
        }

        $semesters = collect();
        if ($selectedDepartment) {
            $query = Semester::where('current', true)
                ->whereHas('curriculum', function ($q) use ($selectedDepartment, $selectedSpecialty, $selectedEducationType) {
                    $q->where('department_hemis_id', $selectedDepartment);
                    if ($selectedSpecialty) $q->where('specialty_hemis_id', $selectedSpecialty);
                    if ($selectedEducationType) $q->where('education_type_code', $selectedEducationType);
                });
            if ($selectedLevelCode) $query->where('level_code', $selectedLevelCode);
            $semesters = $query->select('code', 'name')->distinct()->orderBy('code')->get();
        }

        $groups = collect();
        if ($selectedDepartment) {
            $groupQuery = Group::where('department_hemis_id', $selectedDepartment)->where('active', true);
            if ($selectedSpecialty) $groupQuery->where('specialty_hemis_id', $selectedSpecialty);
            $groups = $groupQuery->orderBy('name')->get();
        }

        $scheduleData = collect();
        if ($isSearched) {
            $scheduleData = $this->loadScheduleData(
                $currentSemesters, $selectedDepartment, $selectedSpecialty,
                $selectedSemester, $selectedGroup, $selectedEducationType,
                $selectedLevelCode, $selectedSubject, $selectedStatus,
                $currentSemesterToggle, true
            );
        }

        $routePrefix = $this->routePrefix();

        return view('admin.academic-schedule.test-center', compact(
            'departments',
            'educationTypes',
            'specialties',
            'semesters',
            'groups',
            'scheduleData',
            'selectedEducationType',
            'selectedDepartment',
            'selectedSpecialty',
            'selectedLevelCode',
            'selectedSemester',
            'selectedGroup',
            'selectedSubject',
            'selectedStatus',
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
        $currentSemesterToggle, $includeCarbon = false
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

        // O'quv rejalarini olish
        $curriculumQuery = Curriculum::where('current', true);
        if ($selectedDepartment) $curriculumQuery->where('department_hemis_id', $selectedDepartment);
        if ($selectedSpecialty) $curriculumQuery->where('specialty_hemis_id', $selectedSpecialty);
        if ($selectedEducationType) $curriculumQuery->where('education_type_code', $selectedEducationType);
        $curriculumIds = $curriculumQuery->pluck('curricula_hemis_id');

        if ($curriculumIds->isEmpty()) return collect();

        // Fanlar (curriculum_subjects dan)
        $subjectQuery = CurriculumSubject::whereIn('curricula_hemis_id', $curriculumIds);
        if ($semesterCodes->isNotEmpty()) {
            $subjectQuery->whereIn('semester_code', $semesterCodes);
        }
        if ($selectedSubject) {
            $subjectQuery->where('subject_id', $selectedSubject);
        }
        $subjects = $subjectQuery->get();

        // Guruhlar
        $groupQuery = Group::where('active', true)
            ->whereIn('curriculum_hemis_id', $curriculumIds);
        if ($selectedDepartment) $groupQuery->where('department_hemis_id', $selectedDepartment);
        if ($selectedSpecialty) $groupQuery->where('specialty_hemis_id', $selectedSpecialty);
        if ($selectedGroup) $groupQuery->where('group_hemis_id', $selectedGroup);
        $filteredGroups = $groupQuery->orderBy('name')->get();

        if ($filteredGroups->isEmpty()) return collect();

        // curriculum_subjects da yo'q semestrlar uchun schedules jadvalidan to'ldirish
        if ($semesterCodes->isNotEmpty()) {
            $coveredSemesters = $subjects->pluck('semester_code')->unique();
            $missingSemesters = $semesterCodes->diff($coveredSemesters);

            if ($missingSemesters->isNotEmpty()) {
                $groupHemisIds = $filteredGroups->pluck('group_hemis_id');
                $groupCurriculumMap = $filteredGroups->pluck('curriculum_hemis_id', 'group_hemis_id');

                $scheduleSubjectQuery = Schedule::whereIn('group_id', $groupHemisIds)
                    ->whereIn('semester_code', $missingSemesters)
                    ->where('education_year_current', true);

                if ($selectedSubject) {
                    $scheduleSubjectQuery->where('subject_id', $selectedSubject);
                }

                $scheduleSubjects = $scheduleSubjectQuery
                    ->select('group_id', 'subject_id', 'subject_name', 'semester_code')
                    ->distinct()
                    ->get();

                foreach ($scheduleSubjects as $ss) {
                    $virtual = new \stdClass();
                    $virtual->subject_id = (string) $ss->subject_id;
                    $virtual->subject_name = $ss->subject_name;
                    $virtual->semester_code = $ss->semester_code;
                    $virtual->credit = null;
                    $virtual->curricula_hemis_id = $groupCurriculumMap->get((string) $ss->group_id);
                    $virtual->_group_hemis_id = (string) $ss->group_id;
                    $subjects->push($virtual);
                }
            }
        }

        if ($subjects->isEmpty()) return collect();

        // Mavjud jadvallar
        $scheduleQuery = ExamSchedule::query();
        if ($selectedDepartment) $scheduleQuery->where('department_hemis_id', $selectedDepartment);
        if ($selectedSpecialty) $scheduleQuery->where('specialty_hemis_id', $selectedSpecialty);
        if ($selectedGroup) $scheduleQuery->where('group_hemis_id', $selectedGroup);
        if ($semesterCodes->isNotEmpty()) $scheduleQuery->whereIn('semester_code', $semesterCodes);
        $existingSchedules = $scheduleQuery->get()
            ->keyBy(fn($item) => $item->group_hemis_id . '_' . $item->subject_id . '_' . $item->semester_code);

        // Ma'lumotlarni yig'ish
        $scheduleData = collect();
        foreach ($filteredGroups as $group) {
            $groupSubjects = $subjects->filter(function($s) use ($group) {
                if (isset($s->_group_hemis_id)) {
                    return (string) $s->_group_hemis_id === (string) $group->group_hemis_id;
                }
                return $s->curricula_hemis_id === $group->curriculum_hemis_id;
            });

            foreach ($groupSubjects as $subject) {
                $key = $group->group_hemis_id . '_' . $subject->subject_id . '_' . $subject->semester_code;
                $existing = $existingSchedules->get($key);

                $item = [
                    'group' => $group,
                    'subject' => $subject,
                    'specialty_name' => $group->specialty_name,
                    'oski_date' => $existing?->oski_date?->format('Y-m-d'),
                    'test_date' => $existing?->test_date?->format('Y-m-d'),
                    'schedule_id' => $existing?->id,
                ];

                if ($includeCarbon) {
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
                if (empty($schedule['oski_date']) && empty($schedule['test_date'])) {
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
                        'test_date' => $schedule['test_date'] ?: null,
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
     * Filter: Yo'nalishlar
     */
    public function getSpecialties(Request $request)
    {
        $specialties = Specialty::where('department_hemis_id', $request->department_id)
            ->orderBy('name')
            ->get(['specialty_hemis_id', 'name']);

        return response()->json($specialties);
    }

    /**
     * Filter: Semestrlar
     */
    public function getSemesters(Request $request)
    {
        $query = Semester::where('current', true);

        if ($request->department_id) {
            $query->whereHas('curriculum', function ($q) use ($request) {
                $q->where('department_hemis_id', $request->department_id);
                if ($request->specialty_id) {
                    $q->where('specialty_hemis_id', $request->specialty_id);
                }
                if ($request->education_type) {
                    $q->where('education_type_code', $request->education_type);
                }
            });
        }

        if ($request->level_code) {
            $query->where('level_code', $request->level_code);
        }

        $semesters = $query->select('code', 'name')
            ->distinct()
            ->orderBy('code')
            ->get();

        return response()->json($semesters);
    }

    /**
     * Filter: Guruhlar
     */
    public function getGroups(Request $request)
    {
        $query = Group::where('active', true);

        if ($request->department_id) {
            $query->where('department_hemis_id', $request->department_id);
        }

        if ($request->specialty_id) {
            $query->where('specialty_hemis_id', $request->specialty_id);
        }

        $groups = $query->orderBy('name')->get(['group_hemis_id', 'name']);

        return response()->json($groups);
    }

    /**
     * Filter: Kurs (level_code)
     */
    public function getLevelCodes(Request $request)
    {
        $query = Semester::where('current', true);

        if ($request->education_type) {
            $query->whereHas('curriculum', function ($q) use ($request) {
                $q->where('education_type_code', $request->education_type);
            });
        }

        $levels = $query->select('level_code', 'level_name')
            ->distinct()
            ->orderBy('level_code')
            ->get()
            ->pluck('level_name', 'level_code');

        return response()->json($levels);
    }

    /**
     * Filter: Fanlar
     */
    public function getSubjects(Request $request)
    {
        $query = CurriculumSubject::whereHas('curriculum', function ($q) use ($request) {
            $q->where('current', true);
            if ($request->department_id) $q->where('department_hemis_id', $request->department_id);
            if ($request->specialty_id) $q->where('specialty_hemis_id', $request->specialty_id);
            if ($request->education_type) $q->where('education_type_code', $request->education_type);
        });

        if ($request->semester_code) {
            $query->where('semester_code', $request->semester_code);
        } elseif ($request->current_semester === '1') {
            $currentCodes = Semester::where('current', true)->pluck('code')->unique();
            $query->whereIn('semester_code', $currentCodes);
        }

        if ($request->level_code) {
            $levelSemCodes = Semester::where('current', true)
                ->where('level_code', $request->level_code)
                ->pluck('code')->unique();
            $query->whereIn('semester_code', $levelSemCodes);
        }

        $subjects = $query->select('subject_id', 'subject_name')
            ->distinct()
            ->orderBy('subject_name')
            ->get()
            ->pluck('subject_name', 'subject_id');

        // curriculum_subjects da topilmasa, schedules jadvalidan to'ldirish
        if ($subjects->isEmpty()) {
            $semesterCodes = collect();
            if ($request->semester_code) {
                $semesterCodes = collect([$request->semester_code]);
            } elseif ($request->current_semester === '1') {
                $semesterCodes = Semester::where('current', true)->pluck('code')->unique();
            }

            if ($request->level_code) {
                $levelSemCodes = Semester::where('current', true)
                    ->where('level_code', $request->level_code)
                    ->pluck('code')->unique();
                $semesterCodes = $semesterCodes->isEmpty() ? $levelSemCodes : $semesterCodes->intersect($levelSemCodes);
            }

            if ($semesterCodes->isNotEmpty()) {
                $scheduleQuery = Schedule::whereIn('semester_code', $semesterCodes)
                    ->where('education_year_current', true);

                if ($request->department_id) {
                    $groupIds = Group::where('department_hemis_id', $request->department_id)
                        ->where('active', true)
                        ->when($request->specialty_id, fn($q) => $q->where('specialty_hemis_id', $request->specialty_id))
                        ->pluck('group_hemis_id');
                    $scheduleQuery->whereIn('group_id', $groupIds);
                }

                $subjects = $scheduleQuery
                    ->select('subject_id', 'subject_name')
                    ->distinct()
                    ->orderBy('subject_name')
                    ->get()
                    ->pluck('subject_name', 'subject_id');
            }
        }

        return response()->json($subjects);
    }
}
