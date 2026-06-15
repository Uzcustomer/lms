<?php

namespace App\Services;

use App\Models\CurriculumSubject;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\Teacher;
use App\Models\VedomostSubmission;
use App\Support\WorkdayCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VedomostSubmissionService
{
    /** Yopilish shakllari — vedomost topshirilishi kerak bo'lganlar. */
    public const CLOSING_FORMS_WITH_VEDOMOST = ['oski', 'test', 'oski_test', 'sinov', 'normativ'];

    /** 12a/12b (qayta topshirish) ochiladigan yopilish shakllari — OSKI/Test imtihonli fanlar. */
    public const RESIT_CLOSING_FORMS = ['oski', 'test', 'oski_test'];

    /** Yiqilgan deb hisoblanadigan chegara (V < 60). */
    public const PASS_GRADE = 60;

    /** Deadline: base sanadan necha ish kuni keyin. */
    public const DEADLINE_WORKDAYS = 3;

    /** Sync davomida takroriy so'rovlarni kamaytirish uchun kesh. */
    private array $teacherCache = [];
    private array $mudiriCache = [];
    private ?\Illuminate\Support\Collection $fanMasuliMap = null;
    private ?bool $studentGradeAttemptCol = null;

    public function __construct(private VedomostMergeService $merge)
    {
    }

    /**
     * Joriy o'quv yili kodi (HEMIS "current" bayrog'idan).
     */
    public function currentEducationYear(): ?string
    {
        return DB::table('semesters')->where('current', true)->max('education_year');
    }

    /**
     * Har bir GURUH uchun joriy semestrni qaytaradi.
     *
     * Joriy semestr TALABALAR jadvalidan aniqlanadi: faol (status 11) talaba
     * HEMIS'da doim o'z joriy semestrida turadi. curriculum_weeks sanalariga
     * tayanilmaydi — xalqaro ta'lim fakulteti kabi bahorda o'qish boshlagan
     * kohortalar (toq semestr bahorda) ham to'g'ri chiqishi uchun.
     *
     * Reja bo'yicha emas, guruh bo'yicha: bitta o'quv rejada ikki kohorta
     * (masalan 48 talaba 7-semestrda, 24 talaba 8-semestrda) bo'lishi mumkin —
     * har bir guruh o'z talabalari turgan semestrni oladi. Guruh ichida
     * bir nechta semestr uchrasa (akademik qarzdor va h.k.), eng ko'p
     * talabalisi olinadi.
     *
     * @return Collection<int, object{code:string, student_count:int}> group_hemis_id => semestr
     */
    public function currentSemestersByGroup(): Collection
    {
        return DB::table('students')
            ->where('student_status_code', 11)
            ->whereNotNull('group_id')
            ->whereNotNull('semester_code')
            ->selectRaw('group_id, semester_code as code, count(*) as student_count')
            ->groupBy('group_id', 'semester_code')
            ->get()
            ->groupBy('group_id')
            ->map(fn($g) => $g->sortByDesc('student_count')->first());
    }

    /**
     * Joriy semestr bo'yicha guruh×fan vedomost yozuvlarini yaratadi/yangilaydi.
     * Mavjud yozuvlarning status/fayl/tekshiruv ma'lumotlari saqlanadi.
     *
     * @return int yaratilgan/yangilangan yozuvlar soni
     */
    public function sync(): int
    {
        $currentYear = $this->currentEducationYear();
        if (!$currentYear) {
            return 0;
        }

        $semByGroup = $this->currentSemestersByGroup();
        if ($semByGroup->isEmpty()) {
            return 0;
        }

        $groups = Group::where('active', true)
            ->whereIn('group_hemis_id', $semByGroup->keys()->all())
            ->whereNotNull('curriculum_hemis_id')
            ->get();

        $curriculumIds = $groups->pluck('curriculum_hemis_id')->unique()->all();

        // Har reja+semestr uchun o'quv yili — semesters jadvalidan (global
        // max emas: xalqaro fakultet kohortalarining yili farq qilishi mumkin)
        $semesterYears = DB::table('semesters')
            ->whereIn('curriculum_hemis_id', $curriculumIds)
            ->get(['curriculum_hemis_id', 'code', 'education_year'])
            ->keyBy(fn($s) => $s->curriculum_hemis_id . '|' . $s->code)
            ->map(fn($s) => $s->education_year);

        // Fan mas'ullarini oldindan yuklab olamiz (har qator uchun alohida so'rov bermaslik uchun)
        $this->fanMasuliMap = DB::table('teacher_responsible_subjects as trs')
            ->join('teachers as t', 't.id', '=', 'trs.teacher_id')
            ->select('trs.curriculum_subject_id', 't.hemis_id', 't.full_name', 't.phone')
            ->get()
            ->keyBy('curriculum_subject_id');

        $count = 0;
        $units = []; // 12a/12b uchun yo'nalish × fan × semestr birliklari
        foreach ($groups as $group) {
            $sem = $semByGroup->get($group->group_hemis_id);
            if (!$sem) {
                continue;
            }
            $semCode = (string) $sem->code;

            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semCode)
                ->where('is_active', true)
                ->whereIn('closing_form', self::CLOSING_FORMS_WITH_VEDOMOST)
                ->get();

            foreach ($subjects as $subject) {
                $base = $this->computeBaseDate($group, $subject, $semCode);
                $deadline = $base['date']
                    ? WorkdayCalculator::addWorkdays(Carbon::parse($base['date']), self::DEADLINE_WORKDAYS)->toDateString()
                    : null;
                $teacher = $this->primaryTeacher((int) $group->group_hemis_id, $subject);
                $deptHemisId = $subject->department_id ?: $group->department_hemis_id;

                $teacherInfo = $teacher ? $this->teacherInfo($teacher->employee_id) : null;
                $fanMasuli = $this->fanMasuli($subject->id);
                $kafedraMudiri = $this->kafedraMudiri($deptHemisId);
                $educationYear = $semesterYears["{$group->curriculum_hemis_id}|{$semCode}"] ?? $currentYear;

                VedomostSubmission::updateOrCreate(
                    [
                        'group_hemis_id' => $group->group_hemis_id,
                        'subject_id' => $subject->subject_id,
                        'semester_code' => $semCode,
                        'form_type' => VedomostSubmission::FORM_12,
                    ],
                    [
                        'education_year' => $educationYear,
                        'group_name' => $group->name,
                        'curriculum_hemis_id' => $group->curriculum_hemis_id,
                        'curriculum_subject_id' => $subject->id,
                        'subject_name' => $subject->subject_name,
                        'department_hemis_id' => $deptHemisId,
                        'department_name' => $subject->department_name ?: $group->department_name,
                        'specialty_name' => $group->specialty_name,
                        'closing_form' => $subject->closing_form,
                        'teacher_hemis_id' => $teacher?->employee_id,
                        'teacher_name' => $teacher?->employee_name,
                        'teacher_phone' => $teacherInfo?->phone,
                        'fan_masuli_hemis_id' => $fanMasuli?->hemis_id,
                        'fan_masuli_name' => $fanMasuli?->full_name,
                        'fan_masuli_phone' => $fanMasuli?->phone,
                        'kafedra_mudiri_hemis_id' => $kafedraMudiri?->hemis_id,
                        'kafedra_mudiri_name' => $kafedraMudiri?->full_name,
                        'kafedra_mudiri_phone' => $kafedraMudiri?->phone,
                        'base_type' => $base['type'],
                        'base_date' => $base['date'],
                        'deadline' => $deadline,
                        // status / fayllar / tekshiruv — atayin yangilanmaydi (oqim saqlanadi)
                    ]
                );
                $count++;

                // 12a/12b — faqat OSKI/Test imtihonli fanlar uchun birlik to'playmiz.
                if (in_array($subject->closing_form, self::RESIT_CLOSING_FORMS, true)) {
                    $rootSubject = $this->merge->rootSubjectName($subject->subject_name);
                    $unitKey = implode('|', [
                        $educationYear, $semCode, (string) $group->specialty_name,
                        $subject->closing_form, $rootSubject,
                    ]);
                    if (!isset($units[$unitKey])) {
                        $units[$unitKey] = [
                            'education_year' => $educationYear,
                            'semester_code' => $semCode,
                            'specialty_name' => $group->specialty_name,
                            'closing_form' => $subject->closing_form,
                            'subject_id' => $subject->subject_id,
                            'subject_name' => $rootSubject,
                            'curriculum_hemis_id' => $group->curriculum_hemis_id,
                            'department_hemis_id' => $deptHemisId,
                            'department_name' => $subject->department_name ?: $group->department_name,
                            'fan_masuli_hemis_id' => $fanMasuli?->hemis_id,
                            'fan_masuli_name' => $fanMasuli?->full_name,
                            'fan_masuli_phone' => $fanMasuli?->phone,
                            'kafedra_mudiri_hemis_id' => $kafedraMudiri?->hemis_id,
                            'kafedra_mudiri_name' => $kafedraMudiri?->full_name,
                            'kafedra_mudiri_phone' => $kafedraMudiri?->phone,
                            'group_ids' => [],
                            'subject_keys' => [],
                        ];
                    }
                    $units[$unitKey]['group_ids'][] = (int) $group->group_hemis_id;
                    foreach ([$subject->subject_id, $subject->curriculum_subject_hemis_id] as $sk) {
                        if ($sk && !in_array($sk, $units[$unitKey]['subject_keys'], true)) {
                            $units[$unitKey]['subject_keys'][] = $sk;
                        }
                    }
                }
            }
        }

        $this->syncResitForms($units);

        return $count;
    }

    /**
     * 12a/12b (qayta topshirish) umumiy varaqlarini natijaga qarab ochadi/yopadi.
     *
     * Har bir yo'nalish × fan × semestr birligi uchun (BARCHA guruhlar bitta varaq):
     *  - 12a : 1-urinish (oski/test) sanasi o'tgan VA 1-urinishda yiqilganlar bo'lsa.
     *  - 12b : 2-urinish (resit) sanasi o'tgan VA 2-urinishda yiqilganlar bo'lsa.
     * Yiqilgan/o'tgan aniqlash individual-exam-schedule logikasiga mos
     * (student_grades: training_type 101/102, attempt, retake_grade ?? grade < 60).
     */
    private function syncResitForms(array $units): void
    {
        $today = Carbon::today()->toDateString();

        foreach ($units as $unit) {
            $groupIds = array_values(array_unique($unit['group_ids']));
            $subjectKeys = $unit['subject_keys'] ?: [$unit['subject_id']];

            $failures = $this->detectFailures($groupIds, $subjectKeys, $unit['semester_code']);
            $dates = $this->examDates($groupIds, $subjectKeys, $unit['semester_code'], $unit['closing_form']);

            // 12a — 1-urinish tugagan (sana o'tgan) va 1-urinishda yiqilganlar bor.
            $open12a = $failures['failed1'] > 0
                && $dates['attempt1'] !== null
                && $dates['attempt1'] < $today;
            $this->upsertOrCleanResitRow($unit, $groupIds, VedomostSubmission::FORM_12A, $open12a, $dates['resit']);

            // 12b — 2-urinish (resit) tugagan va 2-urinishda yiqilganlar bor.
            $open12b = $failures['failed2'] > 0
                && $dates['resit'] !== null
                && $dates['resit'] < $today;
            $this->upsertOrCleanResitRow($unit, $groupIds, VedomostSubmission::FORM_12B, $open12b, $dates['resit2']);
        }
    }

    /**
     * Shart bajarilsa 12a/12b umumiy qatorini yaratadi/yangilaydi; aks holda
     * (hali yuklanmagan, status pending) bo'lsa olib tashlaydi.
     */
    private function upsertOrCleanResitRow(array $unit, array $groupIds, string $formType, bool $shouldOpen, ?string $baseDate): void
    {
        $repGroupId = !empty($groupIds) ? min($groupIds) : null;
        if (!$repGroupId) {
            return;
        }

        $keys = [
            'group_hemis_id' => $repGroupId,
            'subject_id' => $unit['subject_id'],
            'semester_code' => $unit['semester_code'],
            'form_type' => $formType,
        ];

        if (!$shouldOpen) {
            // Faqat hali ishlatilmagan (pending, fayl yo'q) qatorni tozalaymiz —
            // yuklangan/tasdiqlangan vedomostga tegmaymiz.
            VedomostSubmission::where($keys)
                ->where('status', VedomostSubmission::STATUS_PENDING)
                ->whereNull('pdf_path')
                ->delete();
            return;
        }

        $deadline = $baseDate
            ? WorkdayCalculator::addWorkdays(Carbon::parse($baseDate), self::DEADLINE_WORKDAYS)->toDateString()
            : null;

        VedomostSubmission::updateOrCreate($keys, [
            'education_year' => $unit['education_year'],
            'group_name' => 'Barcha guruhlar (' . count($groupIds) . ' ta)',
            'curriculum_hemis_id' => $unit['curriculum_hemis_id'],
            'curriculum_subject_id' => null,
            'subject_name' => $unit['subject_name'],
            'department_hemis_id' => $unit['department_hemis_id'],
            'department_name' => $unit['department_name'],
            'specialty_name' => $unit['specialty_name'],
            'closing_form' => $unit['closing_form'],
            // 12a/12b — umumiy varaq, alohida o'qituvchi yo'q (fan mas'uli javobgar).
            'teacher_hemis_id' => null,
            'teacher_name' => null,
            'teacher_phone' => null,
            'fan_masuli_hemis_id' => $unit['fan_masuli_hemis_id'],
            'fan_masuli_name' => $unit['fan_masuli_name'],
            'fan_masuli_phone' => $unit['fan_masuli_phone'],
            'kafedra_mudiri_hemis_id' => $unit['kafedra_mudiri_hemis_id'],
            'kafedra_mudiri_name' => $unit['kafedra_mudiri_name'],
            'kafedra_mudiri_phone' => $unit['kafedra_mudiri_phone'],
            'base_type' => 'exam',
            'base_date' => $baseDate,
            'deadline' => $deadline,
            // status / fayllar / tekshiruv — atayin yangilanmaydi (oqim saqlanadi)
        ]);
    }

    /**
     * Birlikdagi (guruhlar) talabalardan 1- va 2-urinishda yiqilganlar sonini
     * aniqlaydi. individual-exam-schedule eligibility logikasi bilan bir xil.
     *
     * @return array{failed1:int, failed2:int}
     */
    private function detectFailures(array $groupIds, array $subjectKeys, string $semCode): array
    {
        if (empty($groupIds) || empty($subjectKeys)) {
            return ['failed1' => 0, 'failed2' => 0];
        }

        $hasAttempt = $this->studentGradeAttemptColumn();

        $rows = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereIn('st.group_id', $groupIds)
            ->whereIn('sg.subject_id', $subjectKeys)
            ->where('sg.semester_code', $semCode)
            ->whereIn('sg.training_type_code', [101, 102])
            ->whereNull('sg.deleted_at')
            ->select(
                'sg.student_hemis_id',
                'sg.grade',
                'sg.retake_grade',
                $hasAttempt ? 'sg.attempt' : DB::raw('1 as attempt')
            )
            ->get();

        $failed1 = 0;
        $failed2 = 0;
        foreach ($rows->groupBy('student_hemis_id') as $studentRows) {
            $f1 = false;
            $f2 = false;
            foreach ($studentRows as $r) {
                $att = (int) ($r->attempt ?? 1);
                $val = $r->retake_grade ?? $r->grade;
                if ($val === null) {
                    continue;
                }
                if ($att <= 1 && (float) $val < self::PASS_GRADE) {
                    $f1 = true;
                } elseif ($att === 2 && (float) $val < self::PASS_GRADE) {
                    $f2 = true;
                }
            }
            if ($f1) {
                $failed1++;
            }
            if ($f2) {
                $failed2++;
            }
        }

        return ['failed1' => $failed1, 'failed2' => $failed2];
    }

    /**
     * Birlik uchun urinish sanalari (eng kech sana) — yopilish shakliga qarab
     * OSKI va/yoki Test ustunlaridan olinadi.
     *
     * @return array{attempt1:?string, resit:?string, resit2:?string}
     */
    private function examDates(array $groupIds, array $subjectKeys, string $semCode, string $closingForm): array
    {
        if (empty($groupIds) || empty($subjectKeys)) {
            return ['attempt1' => null, 'resit' => null, 'resit2' => null];
        }

        $rows = ExamSchedule::whereNull('student_hemis_id')
            ->whereIn('group_hemis_id', $groupIds)
            ->whereIn('subject_id', $subjectKeys)
            ->where('semester_code', $semCode)
            ->get();

        $wantOski = in_array($closingForm, ['oski', 'oski_test'], true);
        $wantTest = in_array($closingForm, ['test', 'oski_test'], true);

        $attempt1 = [];
        $resit = [];
        $resit2 = [];
        foreach ($rows as $r) {
            if ($wantOski) {
                if (!$r->oski_na && $r->oski_date) {
                    $attempt1[] = $r->oski_date->toDateString();
                }
                if ($r->oski_resit_date) {
                    $resit[] = $r->oski_resit_date->toDateString();
                }
                if ($r->oski_resit2_date) {
                    $resit2[] = $r->oski_resit2_date->toDateString();
                }
            }
            if ($wantTest) {
                if (!$r->test_na && $r->test_date) {
                    $attempt1[] = $r->test_date->toDateString();
                }
                if ($r->test_resit_date) {
                    $resit[] = $r->test_resit_date->toDateString();
                }
                if ($r->test_resit2_date) {
                    $resit2[] = $r->test_resit2_date->toDateString();
                }
            }
        }

        return [
            'attempt1' => !empty($attempt1) ? max($attempt1) : null,
            'resit' => !empty($resit) ? max($resit) : null,
            'resit2' => !empty($resit2) ? max($resit2) : null,
        ];
    }

    /**
     * student_grades.attempt ustuni mavjudmi (bir marta tekshirilib keshlanadi).
     */
    private function studentGradeAttemptColumn(): bool
    {
        if ($this->studentGradeAttemptCol === null) {
            try {
                $this->studentGradeAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');
            } catch (\Throwable $e) {
                $this->studentGradeAttemptCol = false;
            }
        }

        return $this->studentGradeAttemptCol;
    }

    /**
     * Deadline uchun asos sana:
     *  - sinov / normativ → oxirgi dars sanasi (schedules.MAX(lesson_date))
     *  - test / oski / oski_test → oxirgi YN sanasi (exam_schedules)
     *
     * @return array{type:?string, date:?string}
     */
    public function computeBaseDate(Group $group, CurriculumSubject $subject, string $semCode): array
    {
        $cf = $subject->closing_form;
        // schedules/exam_schedules da subject_id HEMIS subject_id YOKI
        // curriculum_subject_hemis_id bo'lishi mumkin — ikkalasini ham qabul qilamiz.
        $subjectKeys = array_values(array_filter([
            $subject->subject_id,
            $subject->curriculum_subject_hemis_id,
        ]));

        if (in_array($cf, ['sinov', 'normativ'], true)) {
            $end = DB::table('schedules')
                ->whereNull('deleted_at')
                ->where('group_id', $group->group_hemis_id)
                ->whereIn('subject_id', $subjectKeys)
                ->max('lesson_date');

            return ['type' => 'lesson', 'date' => $end ? substr($end, 0, 10) : null];
        }

        if (in_array($cf, ['test', 'oski', 'oski_test'], true)) {
            $exam = ExamSchedule::whereNull('student_hemis_id')
                ->where('group_hemis_id', $group->group_hemis_id)
                ->whereIn('subject_id', $subjectKeys)
                ->where('semester_code', $semCode)
                ->first();

            $dates = [];
            if ($exam) {
                if (in_array($cf, ['oski', 'oski_test'], true) && !$exam->oski_na && $exam->oski_date) {
                    $dates[] = $exam->oski_date->toDateString();
                }
                if (in_array($cf, ['test', 'oski_test'], true) && !$exam->test_na && $exam->test_date) {
                    $dates[] = $exam->test_date->toDateString();
                }
            }

            return ['type' => 'exam', 'date' => !empty($dates) ? max($dates) : null];
        }

        return ['type' => null, 'date' => null];
    }

    /**
     * O'qituvchi ma'lumoti (telefon) — hemis_id bo'yicha, keshlanadi.
     */
    private function teacherInfo($hemisId): ?object
    {
        if (!$hemisId) {
            return null;
        }
        if (!array_key_exists($hemisId, $this->teacherCache)) {
            $this->teacherCache[$hemisId] = DB::table('teachers')
                ->where('hemis_id', $hemisId)
                ->select('hemis_id', 'full_name', 'phone')
                ->first();
        }

        return $this->teacherCache[$hemisId];
    }

    /**
     * Fan mas'uli — oldindan yuklangan map'dan (teacher_responsible_subjects).
     */
    private function fanMasuli($curriculumSubjectId): ?object
    {
        if (!$curriculumSubjectId || !$this->fanMasuliMap) {
            return null;
        }

        return $this->fanMasuliMap->get($curriculumSubjectId);
    }

    /**
     * Kafedra mudiri — kafedra (department_hemis_id) bo'yicha, keshlanadi.
     * JournalController bilan bir xil mantiq (rol → role ustuni → staff_position).
     */
    private function kafedraMudiri($departmentHemisId): ?object
    {
        if (!$departmentHemisId) {
            return null;
        }
        if (array_key_exists($departmentHemisId, $this->mudiriCache)) {
            return $this->mudiriCache[$departmentHemisId];
        }

        $mudiri = Teacher::whereHas('roles', fn($q) => $q->where('name', 'kafedra_mudiri'))
            ->where('department_hemis_id', $departmentHemisId)
            ->where('is_active', true)
            ->first();
        if (!$mudiri) {
            $mudiri = Teacher::where('role', 'kafedra_mudiri')
                ->where('department_hemis_id', $departmentHemisId)
                ->where('is_active', true)
                ->first();
        }
        if (!$mudiri) {
            $mudiri = Teacher::where('staff_position', 'LIKE', '%mudiri%')
                ->where('department_hemis_id', $departmentHemisId)
                ->where('is_active', true)
                ->first();
        }

        return $this->mudiriCache[$departmentHemisId] = $mudiri;
    }

    /**
     * Dars jadvalidan guruh+fan uchun asosiy o'qituvchi (eng ko'p dars o'tgan).
     */
    private function primaryTeacher(int $groupHemisId, CurriculumSubject $subject): ?object
    {
        $subjectKeys = array_values(array_filter([
            $subject->subject_id,
            $subject->curriculum_subject_hemis_id,
        ]));
        if (empty($subjectKeys)) {
            return null;
        }

        return DB::table('schedules')
            ->whereNull('deleted_at')
            ->where('group_id', $groupHemisId)
            ->whereIn('subject_id', $subjectKeys)
            ->whereNotNull('employee_id')
            ->select('employee_id', 'employee_name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('employee_id', 'employee_name')
            ->orderByDesc('cnt')
            ->first();
    }
}
