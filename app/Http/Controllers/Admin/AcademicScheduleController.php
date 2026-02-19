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
     * Fakultet, yo'nalish, kurs, fanlar, guruhlar ro'yxati
     */
    public function index(Request $request)
    {
        // Joriy yildagi joriy semestrlar
        $currentSemesters = Semester::where('current', true)->get();
        $currentEducationYear = $currentSemesters->first()?->education_year;

        // Fakultetlar (structure_type_code = 11 — fakultetlar)
        $departments = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // Filtrlar
        $selectedDepartment = $request->get('department_id');
        $selectedSpecialty = $request->get('specialty_id');
        $selectedSemester = $request->get('semester_code');
        $selectedGroup = $request->get('group_id');

        // Yo'nalishlar
        $specialties = collect();
        if ($selectedDepartment) {
            $specialties = Specialty::where('department_hemis_id', $selectedDepartment)
                ->orderBy('name')
                ->get();
        }

        // Semestrlar (joriy o'quv rejadagi)
        $semesters = collect();
        if ($selectedDepartment) {
            $semesters = Semester::where('current', true)
                ->whereHas('curriculum', function ($q) use ($selectedDepartment, $selectedSpecialty) {
                    $q->where('department_hemis_id', $selectedDepartment);
                    if ($selectedSpecialty) {
                        $q->where('specialty_hemis_id', $selectedSpecialty);
                    }
                })
                ->select('code', 'name')
                ->distinct()
                ->orderBy('code')
                ->get();
        }

        // Guruhlar
        $groups = collect();
        if ($selectedDepartment) {
            $groupQuery = Group::where('department_hemis_id', $selectedDepartment)
                ->where('active', true);
            if ($selectedSpecialty) {
                $groupQuery->where('specialty_hemis_id', $selectedSpecialty);
            }
            $groups = $groupQuery->orderBy('name')->get();
        }

        // Fanlar ro'yxati va jadval ma'lumotlari
        $scheduleData = collect();
        if ($selectedDepartment && $selectedSemester) {
            $subjectQuery = CurriculumSubject::where('semester_code', $selectedSemester)
                ->whereHas('curriculum', function ($q) use ($selectedDepartment, $selectedSpecialty, $currentEducationYear) {
                    $q->where('department_hemis_id', $selectedDepartment)
                      ->where('current', true);
                    if ($selectedSpecialty) {
                        $q->where('specialty_hemis_id', $selectedSpecialty);
                    }
                });

            $subjects = $subjectQuery->get();

            // Guruhlarni olish
            $filteredGroups = Group::where('department_hemis_id', $selectedDepartment)
                ->where('active', true);
            if ($selectedSpecialty) {
                $filteredGroups->where('specialty_hemis_id', $selectedSpecialty);
            }
            if ($selectedGroup) {
                $filteredGroups->where('group_hemis_id', $selectedGroup);
            }
            $filteredGroups = $filteredGroups->get();

            // Mavjud jadvallarni olish
            $existingSchedules = ExamSchedule::where('department_hemis_id', $selectedDepartment)
                ->where('semester_code', $selectedSemester)
                ->when($selectedSpecialty, fn($q) => $q->where('specialty_hemis_id', $selectedSpecialty))
                ->when($selectedGroup, fn($q) => $q->where('group_hemis_id', $selectedGroup))
                ->get()
                ->keyBy(fn($item) => $item->group_hemis_id . '_' . $item->subject_id);

            // Har bir guruh uchun fanlarni chiqarish
            foreach ($filteredGroups as $group) {
                $groupSubjects = $subjects->filter(function ($subject) use ($group) {
                    return $subject->curricula_hemis_id === $group->curriculum_hemis_id;
                });

                foreach ($groupSubjects as $subject) {
                    $key = $group->group_hemis_id . '_' . $subject->subject_id;
                    $existing = $existingSchedules->get($key);

                    $scheduleData->push([
                        'group' => $group,
                        'subject' => $subject,
                        'specialty_name' => $group->specialty_name,
                        'oski_date' => $existing?->oski_date?->format('Y-m-d'),
                        'test_date' => $existing?->test_date?->format('Y-m-d'),
                        'schedule_id' => $existing?->id,
                    ]);
                }
            }

            // Guruh bo'yicha guruhlash
            $scheduleData = $scheduleData->groupBy(fn($item) => $item['group']->group_hemis_id);
        }

        $routePrefix = $this->routePrefix();

        return view('admin.academic-schedule.index', compact(
            'departments',
            'specialties',
            'semesters',
            'groups',
            'scheduleData',
            'selectedDepartment',
            'selectedSpecialty',
            'selectedSemester',
            'selectedGroup',
            'currentEducationYear',
            'routePrefix',
        ));
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
                // Faqat sana belgilangan qatorlarni saqlash
                if (empty($schedule['oski_date']) && empty($schedule['test_date'])) {
                    // Agar oldin bor bo'lsa o'chirish
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
        $query = Semester::where('current', true)
            ->whereHas('curriculum', function ($q) use ($request) {
                $q->where('department_hemis_id', $request->department_id);
                if ($request->specialty_id) {
                    $q->where('specialty_hemis_id', $request->specialty_id);
                }
            });

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
        $query = Group::where('department_hemis_id', $request->department_id)
            ->where('active', true);

        if ($request->specialty_id) {
            $query->where('specialty_hemis_id', $request->specialty_id);
        }

        $groups = $query->orderBy('name')->get(['group_hemis_id', 'name']);

        return response()->json($groups);
    }

    /**
     * Test markazi uchun: Yakuniy nazoratlar jadvali (faqat ko'rish)
     */
    public function testCenterView(Request $request)
    {
        $departments = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $selectedDepartment = $request->get('department_id');
        $selectedSpecialty = $request->get('specialty_id');
        $selectedSemester = $request->get('semester_code');
        $selectedGroup = $request->get('group_id');

        $specialties = collect();
        if ($selectedDepartment) {
            $specialties = Specialty::where('department_hemis_id', $selectedDepartment)
                ->orderBy('name')
                ->get();
        }

        $semesters = collect();
        if ($selectedDepartment) {
            $semesters = Semester::where('current', true)
                ->whereHas('curriculum', function ($q) use ($selectedDepartment, $selectedSpecialty) {
                    $q->where('department_hemis_id', $selectedDepartment);
                    if ($selectedSpecialty) {
                        $q->where('specialty_hemis_id', $selectedSpecialty);
                    }
                })
                ->select('code', 'name')
                ->distinct()
                ->orderBy('code')
                ->get();
        }

        $groups = collect();
        if ($selectedDepartment) {
            $groupQuery = Group::where('department_hemis_id', $selectedDepartment)
                ->where('active', true);
            if ($selectedSpecialty) {
                $groupQuery->where('specialty_hemis_id', $selectedSpecialty);
            }
            $groups = $groupQuery->orderBy('name')->get();
        }

        // Faqat belgilangan sanalar bor yozuvlarni ko'rsatish
        $schedules = collect();
        if ($selectedDepartment) {
            $query = ExamSchedule::where('department_hemis_id', $selectedDepartment)
                ->where(function ($q) {
                    $q->whereNotNull('oski_date')->orWhereNotNull('test_date');
                });

            if ($selectedSpecialty) {
                $query->where('specialty_hemis_id', $selectedSpecialty);
            }
            if ($selectedSemester) {
                $query->where('semester_code', $selectedSemester);
            }
            if ($selectedGroup) {
                $query->where('group_hemis_id', $selectedGroup);
            }

            $schedules = $query->orderBy('test_date')->orderBy('oski_date')->get();

            // Guruh bo'yicha guruhlash
            $schedules = $schedules->groupBy('group_hemis_id');
        }

        $routePrefix = $this->routePrefix();

        return view('admin.academic-schedule.test-center', compact(
            'departments',
            'specialties',
            'semesters',
            'groups',
            'schedules',
            'selectedDepartment',
            'selectedSpecialty',
            'selectedSemester',
            'selectedGroup',
            'routePrefix',
        ));
    }
}
