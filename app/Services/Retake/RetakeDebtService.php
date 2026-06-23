<?php

namespace App\Services\Retake;

use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Talabaning akademik qarzdor fanlari ro'yxati.
 *
 * Mantiq:
 * - student_subjects: talabaga biriktirilgan fanlar
 * - curriculum_subjects: talabaning curriculumidagi aktiv fanlar
 * - academic_records: HEMIS'dan kelgan natijalar
 *
 * Qarzdorlikni hisoblashda student_subjects va curriculum_subjects birlashtiriladi,
 * keyin academic_records bilan solishtirib bahosi yo'q, past yoki retraining
 * holatdagi fanlar qaytariladi.
 */
class RetakeDebtService
{
    /**
     * Talaba qarzdor bo'lgan fanlar ro'yxati.
     *
     * @return Collection<\stdClass>
     */
    public function debts(Student $student): Collection
    {
        $currentSemesterId = $student->semester_id ? (string) $student->semester_id : null;

        $academicRecords = DB::table('academic_records')
            ->where('student_id', $student->hemis_id)
            ->select([
                'id',
                'subject_id',
                'subject_name',
                'semester_id',
                'semester_name',
                'credit',
                'grade',
                'retraining_status',
            ])
            ->orderByDesc('id')
            ->get();

        $subjects = $this->plannedSubjects($student)
            ->concat(
                $academicRecords
                    ->filter(fn ($record) => $this->isDebtRecord($record))
                    ->map(function ($record) {
                        return (object) [
                            'subject_id' => trim((string) ($record->subject_id ?? '')),
                            'subject_name' => $record->subject_name,
                            'semester_id' => trim((string) ($record->semester_id ?? '')),
                            'curriculum_subject_hemis_id' => null,
                            'semester_name' => $record->semester_name,
                            'credit' => $record->credit !== null ? (float) $record->credit : 0.0,
                        ];
                    })
            )
            ->when($currentSemesterId !== null, fn (Collection $items) => $items->where('semester_id', '!=', $currentSemesterId))
            ->filter(fn ($row) => $row->subject_id !== '' && $row->semester_id !== '')
            ->groupBy(fn ($row) => $row->subject_id . '|' . $row->semester_id)
            ->map(function (Collection $group) {
                $picked = $group->firstWhere('curriculum_subject_hemis_id', '!=', null) ?? $group->first();

                foreach ($group as $candidate) {
                    $picked->subject_name = $picked->subject_name ?: $candidate->subject_name;
                    $picked->semester_name = $picked->semester_name ?: $candidate->semester_name;
                    $picked->credit = $picked->credit ?: $candidate->credit;
                    $picked->curriculum_subject_hemis_id = $picked->curriculum_subject_hemis_id ?: $candidate->curriculum_subject_hemis_id;
                }

                return $picked;
            })
            ->values();

        if ($subjects->isEmpty()) {
            return collect();
        }

        $subjectIds = $subjects->pluck('subject_id')->filter()->unique()->values()->all();
        $semesterIds = $subjects->pluck('semester_id')->filter()->unique()->values()->all();

        $academicRecords = $academicRecords
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('semester_id', $semesterIds)
            ->values();

        $academicLookup = [];
        foreach ($academicRecords as $record) {
            $key = (string) $record->subject_id . '|' . (string) $record->semester_id;
            $academicLookup[$key] ??= $record;
        }

        $rows = $subjects
            ->map(function ($subject) use ($academicLookup) {
                $key = $subject->subject_id . '|' . $subject->semester_id;
                $record = $academicLookup[$key] ?? null;

                return (object) [
                    'subject_id' => $subject->subject_id,
                    'subject_name' => $subject->subject_name,
                    'semester_id' => $subject->semester_id,
                    'curriculum_subject_hemis_id' => $subject->curriculum_subject_hemis_id,
                    'semester_name' => $record->semester_name ?? $subject->semester_name,
                    'ar_id' => $record->id ?? null,
                    'credit' => $record->credit ?? $subject->credit,
                    'grade' => $record->grade ?? null,
                    'retraining_status' => $record->retraining_status ?? null,
                ];
            })
            ->filter(function ($row) {
                return !$row->ar_id
                    || $row->grade === null
                    || $row->grade === ''
                    || $this->isFailingAcademicGrade($row->grade)
                    || (bool) $row->retraining_status;
            })
            ->sortBy([
                ['semester_id', 'asc'],
                ['subject_name', 'asc'],
            ])
            ->values();

        return $rows->map(function ($row) {
            if (!$row->ar_id) {
                $row->debt_reason = 'no_record';
            } elseif ($row->grade === null || $row->grade === '') {
                $row->debt_reason = 'no_grade';
            } elseif ($this->isFailingAcademicGrade($row->grade)) {
                $row->debt_reason = 'low_grade';
            } elseif ((bool) $row->retraining_status) {
                $row->debt_reason = 'retraining';
            } else {
                $row->debt_reason = 'unknown';
            }

            $row->credit = $row->credit !== null ? (float) $row->credit : 0.0;

            return $row;
        });
    }

    /**
     * Talaba berilgan fan bo'yicha hali ham qarzdormi.
     */
    public function isStillDebtor(int $studentHemisId, string $subjectId, string $semesterId): bool
    {
        $record = DB::table('academic_records')
            ->where('student_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_id', $semesterId)
            ->first(['grade', 'retraining_status']);

        if ($record) {
            return $this->isDebtRecord($record);
        }

        $inStudentSubjects = DB::table('student_subjects')
            ->where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_id', $semesterId)
            ->exists();

        if ($inStudentSubjects) {
            return true;
        }

        $student = Student::query()
            ->where('hemis_id', $studentHemisId)
            ->first(['curriculum_id', 'group_name']);

        if (!$student?->curriculum_id) {
            return false;
        }

        $curriculumRows = DB::table('curriculum_subjects')
            ->where('curricula_hemis_id', $student->curriculum_id)
            ->where('is_active', true)
            ->where('subject_code', 'not like', '%/%')
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterId)
            ->select(['subject_name'])
            ->get();

        return $this->filterSubjectsByGroupSuffix($curriculumRows, (string) ($student->group_name ?? ''))->isNotEmpty();
    }

    private function isDebtRecord(object $record): bool
    {
        return $record->grade === null
            || $record->grade === ''
            || $this->isFailingAcademicGrade($record->grade)
            || (bool) ($record->retraining_status ?? false);
    }

    private function isFailingAcademicGrade($grade): bool
    {
        if ($grade === null || $grade === '' || !is_numeric($grade)) {
            return false;
        }

        $numericGrade = round((float) $grade, 2);

        return $numericGrade === 0.0 || $numericGrade === 2.0;
    }

    private function plannedSubjects(Student $student): Collection
    {
        $studentSubjects = DB::table('student_subjects as ss')
            ->leftJoin('curriculum_subjects as cs', 'cs.curriculum_subject_hemis_id', '=', 'ss.curriculum_subject_hemis_id')
            ->leftJoin('semesters as sem', 'sem.code', '=', 'ss.semester_id')
            ->where('ss.student_hemis_id', $student->hemis_id)
            ->select([
                'ss.subject_id',
                'ss.subject_name',
                DB::raw('CAST(ss.semester_id as char) as semester_id'),
                'ss.curriculum_subject_hemis_id',
                DB::raw('COALESCE(sem.name, cs.semester_name) as semester_name'),
                DB::raw('COALESCE(cs.credit, 0) as credit'),
                DB::raw('CASE WHEN cs.curricula_hemis_id = ' . (int) $student->curriculum_id . ' AND cs.is_active = 1 THEN 1 ELSE 0 END as is_own_curriculum'),
            ])
            ->get();

        $curriculumSubjects = collect();
        if (!empty($student->curriculum_id)) {
            $curriculumSubjects = DB::table('curriculum_subjects')
                ->where('curricula_hemis_id', $student->curriculum_id)
                ->where('is_active', true)
                ->where('subject_code', 'not like', '%/%')
                ->select([
                    'subject_id',
                    'subject_name',
                    DB::raw('CAST(semester_code as char) as semester_id'),
                    'curriculum_subject_hemis_id',
                    'semester_name',
                    'credit',
                    DB::raw('1 as is_own_curriculum'),
                ])
                ->orderBy('semester_code')
                ->orderBy('subject_name')
                ->get();

            $curriculumSubjects = $this->filterSubjectsByGroupSuffix($curriculumSubjects, (string) ($student->group_name ?? ''));
        }

        return $studentSubjects
            ->concat($curriculumSubjects)
            ->map(function ($row) {
                return (object) [
                    'subject_id' => trim((string) ($row->subject_id ?? '')),
                    'subject_name' => $row->subject_name,
                    'semester_id' => trim((string) ($row->semester_id ?? '')),
                    'curriculum_subject_hemis_id' => $row->curriculum_subject_hemis_id,
                    'semester_name' => $row->semester_name,
                    'credit' => $row->credit !== null ? (float) $row->credit : 0.0,
                    'is_own_curriculum' => (bool) ($row->is_own_curriculum ?? false),
                ];
            })
            ->filter(fn ($row) => $row->subject_id !== '' && $row->semester_id !== '')
            ->groupBy(fn ($row) => $row->subject_id . '|' . $row->semester_id)
            ->map(function (Collection $group) {
                $picked = $group->firstWhere('is_own_curriculum', true)
                    ?? $group->firstWhere('curriculum_subject_hemis_id', '!=', null)
                    ?? $group->first();

                foreach ($group as $candidate) {
                    $picked->subject_name = $picked->subject_name ?: $candidate->subject_name;
                    $picked->semester_name = $picked->semester_name ?: $candidate->semester_name;
                    $picked->curriculum_subject_hemis_id = $picked->curriculum_subject_hemis_id ?: $candidate->curriculum_subject_hemis_id;

                    if (!$picked->is_own_curriculum && $candidate->is_own_curriculum) {
                        $picked->credit = $candidate->credit;
                        $picked->is_own_curriculum = true;
                    } elseif (!$picked->credit) {
                        $picked->credit = $candidate->credit;
                    }
                }

                return $picked;
            })
            ->values();
    }

    private function filterSubjectsByGroupSuffix(Collection $records, string $groupName): Collection
    {
        if ($groupName === '') {
            return $records;
        }

        $groupSuffix = '';
        if (preg_match('/(\d+)([a-zA-Z])$/', trim($groupName), $matches)) {
            $groupSuffix = mb_strtolower($matches[2]);
        }

        if ($groupSuffix === '') {
            return $records;
        }

        return $records->filter(function ($record) use ($groupSuffix) {
            $name = $record->subject_name ?? '';
            if (preg_match('/\(([a-zA-Z])\)\s*$/u', $name, $matches)) {
                return mb_strtolower($matches[1]) === $groupSuffix;
            }

            return true;
        })->values();
    }
}
