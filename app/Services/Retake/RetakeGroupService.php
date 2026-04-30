<?php

namespace App\Services\Retake;

use App\Models\RetakeApplication;
use App\Models\RetakeApplicationGroup as ApplicationGroup;
use App\Models\RetakeApplicationLog;
use App\Models\RetakeGroup;
use App\Models\RetakeSetting;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetakeGroupService
{
    public function __construct(
        private RetakeApplicationService $applicationService,
    ) {}

    /**
     * Tasdiqlanishi mumkin bo'lgan arizalarni fan + semestr bo'yicha guruhlash.
     *
     * @return Collection<array{subject_id, subject_name, semester_id, semester_name, count, applications}>
     */
    public function pendingAggregations(): Collection
    {
        $apps = RetakeApplication::query()
            ->where('dean_status', 'approved')
            ->where('registrar_status', 'approved')
            ->where('academic_dept_status', 'pending')
            ->where('final_status', 'pending')
            ->whereNull('retake_group_id')
            ->whereHas('group', function ($q) {
                $q->whereNotNull('payment_uploaded_at');
            })
            ->with('group.student')
            ->orderBy('subject_name')
            ->get();

        return $apps->groupBy(fn (RetakeApplication $a) => $a->subject_id . '|' . $a->semester_id)
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'key' => $first->subject_id . '|' . $first->semester_id,
                    'subject_id' => $first->subject_id,
                    'subject_name' => $first->subject_name,
                    'semester_id' => $first->semester_id,
                    'semester_name' => $first->semester_name,
                    'count' => $group->count(),
                    'applications' => $group->values(),
                ];
            })
            ->values();
    }

    /**
     * Berilgan fan + semestr uchun arizalar (modal'da ko'rsatish uchun).
     */
    public function applicationsForSubject(string $subjectId, string $semesterId): Collection
    {
        return RetakeApplication::query()
            ->where('subject_id', $subjectId)
            ->where('semester_id', $semesterId)
            ->where('dean_status', 'approved')
            ->where('registrar_status', 'approved')
            ->where('academic_dept_status', 'pending')
            ->where('final_status', 'pending')
            ->whereNull('retake_group_id')
            ->whereHas('group', function ($q) {
                $q->whereNotNull('payment_uploaded_at');
            })
            ->with('group.student')
            ->get();
    }

    /**
     * Yangi guruh yaratish (forming yoki scheduled holatida).
     */
    public function createGroup(array $data, array $applicationIds, Teacher $actor, bool $publish): RetakeGroup
    {
        $this->validateData($data);

        if (count($applicationIds) < 1) {
            throw ValidationException::withMessages([
                'applications' => 'Eng kamida 1 ta talaba tanlanishi kerak',
            ]);
        }

        $minSize = RetakeSetting::minGroupSize();
        if (count($applicationIds) < $minSize) {
            throw ValidationException::withMessages([
                'applications' => "Guruh uchun eng kamida {$minSize} ta talaba kerak",
            ]);
        }

        // Tanlangan arizalarni yuklash va validatsiya
        $apps = RetakeApplication::whereIn('id', $applicationIds)
            ->where('subject_id', $data['subject_id'])
            ->where('semester_id', $data['semester_id'])
            ->where('dean_status', 'approved')
            ->where('registrar_status', 'approved')
            ->where('academic_dept_status', 'pending')
            ->where('final_status', 'pending')
            ->whereNull('retake_group_id')
            ->get();

        if ($apps->count() !== count($applicationIds)) {
            throw ValidationException::withMessages([
                'applications' => 'Ba\'zi arizalar guruhga qo\'shilishga yaroqsiz holatda',
            ]);
        }

        $teacher = Teacher::find($data['teacher_id']);
        if (!$teacher) {
            throw ValidationException::withMessages([
                'teacher_id' => 'O\'qituvchi topilmadi',
            ]);
        }

        return DB::transaction(function () use ($data, $apps, $teacher, $actor, $publish) {
            $group = RetakeGroup::create([
                'name' => $data['name'],
                'subject_id' => $data['subject_id'],
                'subject_name' => $data['subject_name'],
                'subject_code' => $data['subject_code'] ?? null,
                'semester_id' => $data['semester_id'],
                'semester_name' => $data['semester_name'],
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->full_name,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'max_students' => $data['max_students'] ?? null,
                'status' => $publish ? RetakeGroup::STATUS_SCHEDULED : RetakeGroup::STATUS_FORMING,
                'created_by_user_id' => $actor->id,
                'created_by_name' => $actor->full_name,
            ]);

            if ($publish) {
                // Tasdiqlash — har arizani academicApprove() orqali
                foreach ($apps as $app) {
                    $this->applicationService->academicApprove($app, $actor, $group->id);
                }
            } else {
                // Forming — arizalarni guruhga bog'lash, lekin tasdiqlamaslik
                foreach ($apps as $app) {
                    $app->update(['retake_group_id' => $group->id]);
                    RetakeApplicationLog::create([
                        'application_id' => $app->id,
                        'group_id' => $app->group_id,
                        'user_id' => $actor->id,
                        'user_type' => 'teacher',
                        'user_name' => $actor->full_name,
                        'action' => RetakeApplicationLog::ACTION_GROUP_ASSIGNED,
                        'metadata' => ['retake_group_id' => $group->id, 'draft' => true],
                    ]);
                }
            }

            return $group->refresh();
        });
    }

    /**
     * Forming holatidagi guruhni "publish" qilish — talabalarni tasdiqlash.
     */
    public function publish(RetakeGroup $group, Teacher $actor): RetakeGroup
    {
        if ($group->status !== RetakeGroup::STATUS_FORMING) {
            throw ValidationException::withMessages([
                'group' => 'Faqat shakllantirilayotgan guruhni tasdiqlash mumkin',
            ]);
        }

        return DB::transaction(function () use ($group, $actor) {
            $group->update(['status' => RetakeGroup::STATUS_SCHEDULED]);

            $apps = RetakeApplication::where('retake_group_id', $group->id)
                ->where('final_status', 'pending')
                ->get();

            foreach ($apps as $app) {
                $this->applicationService->academicApprove($app, $actor, $group->id);
            }

            return $group->refresh();
        });
    }

    /**
     * Guruh tafsilotlarini yangilash (faqat forming/scheduled da, sanalar
     * scheduled'da admin override talab qiladi).
     */
    public function updateGroup(RetakeGroup $group, array $data, Teacher $actor, bool $isAdmin = false): RetakeGroup
    {
        if (in_array($group->status, [RetakeGroup::STATUS_IN_PROGRESS, RetakeGroup::STATUS_COMPLETED]) && !$isAdmin) {
            throw ValidationException::withMessages([
                'group' => 'Boshlangan/tugagan guruhni faqat super-admin o\'zgartira oladi',
            ]);
        }

        $this->validateData($data, partial: true);

        $update = [];
        foreach (['name', 'start_date', 'end_date', 'max_students'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (!empty($data['teacher_id']) && $data['teacher_id'] !== $group->teacher_id) {
            $teacher = Teacher::find($data['teacher_id']);
            if (!$teacher) {
                throw ValidationException::withMessages(['teacher_id' => 'O\'qituvchi topilmadi']);
            }
            $update['teacher_id'] = $teacher->id;
            $update['teacher_name'] = $teacher->full_name;
        }

        if (!empty($update)) {
            $group->update($update);
        }

        return $group->refresh();
    }

    /**
     * Cron: scheduled → in_progress va in_progress → completed avto o'tishi.
     *
     * @return array{transitioned_to_in_progress:int, transitioned_to_completed:int}
     */
    public function autoTransitionStatuses(): array
    {
        $today = today();

        $toInProgress = RetakeGroup::query()
            ->where('status', RetakeGroup::STATUS_SCHEDULED)
            ->whereDate('start_date', '<=', $today)
            ->update(['status' => RetakeGroup::STATUS_IN_PROGRESS]);

        $toCompleted = RetakeGroup::query()
            ->where('status', RetakeGroup::STATUS_IN_PROGRESS)
            ->whereDate('end_date', '<', $today)
            ->update(['status' => RetakeGroup::STATUS_COMPLETED]);

        return [
            'transitioned_to_in_progress' => $toInProgress,
            'transitioned_to_completed' => $toCompleted,
        ];
    }

    /**
     * Monitoring: scheduled bo'lib turgan guruhda boshlanish sanasi 1 kundan
     * ortiq o'tib ketganlar (cron buzilgan bo'lishi mumkin).
     *
     * @return EloquentCollection<RetakeGroup>
     */
    public function staleScheduledGroups(): EloquentCollection
    {
        return RetakeGroup::query()
            ->where('status', RetakeGroup::STATUS_SCHEDULED)
            ->whereDate('start_date', '<', today()->subDay())
            ->get();
    }

    private function validateData(array $data, bool $partial = false): void
    {
        $required = $partial ? [] : ['name', 'subject_id', 'subject_name', 'semester_id', 'semester_name', 'teacher_id', 'start_date', 'end_date'];

        foreach ($required as $f) {
            if (empty($data[$f])) {
                throw ValidationException::withMessages([$f => "{$f} majburiy"]);
            }
        }

        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            if (strtotime($data['start_date']) > strtotime($data['end_date'])) {
                throw ValidationException::withMessages([
                    'end_date' => 'Tugash sanasi boshlanish sanasidan keyin bo\'lishi kerak',
                ]);
            }
        }
    }
}
