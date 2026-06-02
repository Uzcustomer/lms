<?php

namespace App\Services;

use App\Models\CurriculumSubject;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\VedomostSubmission;
use App\Support\WorkdayCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VedomostSubmissionService
{
    /** Yopilish shakllari — vedomost topshirilishi kerak bo'lganlar. */
    public const CLOSING_FORMS_WITH_VEDOMOST = ['oski', 'test', 'oski_test', 'sinov', 'normativ'];

    /** Deadline: base sanadan necha ish kuni keyin. */
    public const DEADLINE_WORKDAYS = 3;

    /**
     * Joriy o'quv yili kodi (HEMIS "current" bayrog'idan).
     */
    public function currentEducationYear(): ?string
    {
        return DB::table('semesters')->where('current', true)->max('education_year');
    }

    /**
     * Har bir o'quv reja uchun joriy semestrni qaytaradi.
     * (ClosingFormController bilan bir xil mantiq — MIN(start_date) asosida,
     * iflos end_date'larga bardoshli.)
     *
     * @return Collection<int, object{curriculum_hemis_id:int, code:string}>
     */
    public function currentSemesters(?string $currentYear = null): Collection
    {
        $currentYear ??= $this->currentEducationYear();
        if (!$currentYear) {
            return collect();
        }

        $today = now()->toDateString();

        $sems = DB::table('semesters as s')
            ->join('curriculum_weeks as w', 'w.semester_hemis_id', '=', 's.semester_hemis_id')
            ->where('s.education_year', $currentYear)
            ->groupBy('s.semester_hemis_id', 's.curriculum_hemis_id', 's.code')
            ->havingRaw('MIN(w.start_date) <= ?', [$today . ' 23:59:59'])
            ->get([
                's.semester_hemis_id',
                's.curriculum_hemis_id',
                's.code',
                DB::raw('MIN(w.start_date) as start_date'),
            ]);

        return $sems->groupBy('curriculum_hemis_id')
            ->map(fn($g) => $g->sortByDesc('start_date')->first())
            ->values();
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

        $semByCurriculum = $this->currentSemesters($currentYear)->keyBy('curriculum_hemis_id');
        if ($semByCurriculum->isEmpty()) {
            return 0;
        }

        $curriculumIds = $semByCurriculum->keys()->all();

        $groups = Group::where('active', true)
            ->whereIn('curriculum_hemis_id', $curriculumIds)
            ->get();

        $count = 0;
        foreach ($groups as $group) {
            $sem = $semByCurriculum->get($group->curriculum_hemis_id);
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

                VedomostSubmission::updateOrCreate(
                    [
                        'group_hemis_id' => $group->group_hemis_id,
                        'subject_id' => $subject->subject_id,
                        'semester_code' => $semCode,
                        'education_year' => $currentYear,
                    ],
                    [
                        'group_name' => $group->name,
                        'curriculum_hemis_id' => $group->curriculum_hemis_id,
                        'curriculum_subject_id' => $subject->id,
                        'subject_name' => $subject->subject_name,
                        'department_hemis_id' => $subject->department_id ?: $group->department_hemis_id,
                        'department_name' => $subject->department_name ?: $group->department_name,
                        'specialty_name' => $group->specialty_name,
                        'closing_form' => $subject->closing_form,
                        'teacher_hemis_id' => $teacher?->employee_id,
                        'teacher_name' => $teacher?->employee_name,
                        'base_type' => $base['type'],
                        'base_date' => $base['date'],
                        'deadline' => $deadline,
                        // status / fayllar / tekshiruv — atayin yangilanmaydi (oqim saqlanadi)
                    ]
                );
                $count++;
            }
        }

        return $count;
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
