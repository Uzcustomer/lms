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
            ->where('academic_dept_status', 'approved')
            ->where('final_status', 'pending')
            ->whereNull('retake_group_id')
            ->whereHas('group', function ($q) {
                $q->whereNotNull('payment_uploaded_at')
                  ->where('payment_verification_status', 'approved');
            })
            ->with('group.student')
            ->orderBy('subject_name')
            ->get();

        // Normalize qilingan kalit: subject_name + semester_name (kichik harf, trim)
        // Shu orqali bir xil nomli ammo turli curriculumdagi (subject_id farqli)
        // fanlar bitta agregatsiyaga tushadi.
        $normalize = fn (?string $s) => mb_strtolower(trim((string) $s));

        return $apps->groupBy(fn (RetakeApplication $a) => $normalize($a->subject_name) . '|' . $normalize($a->semester_name))
            ->map(function ($group) {
                // Majority subject_id/semester_id — guruh yaratilganda asos sifatida ishlatiladi
                $majSubjectId = $group->pluck('subject_id')->countBy()->sortDesc()->keys()->first();
                $majSemesterId = $group->pluck('semester_id')->countBy()->sortDesc()->keys()->first();
                $first = $group->first();
                $variants = $group->pluck('subject_id')->unique()->values()->all();
                return [
                    'key' => mb_strtolower(trim($first->subject_name)) . '|' . mb_strtolower(trim($first->semester_name)),
                    'subject_id' => $majSubjectId,
                    'subject_name' => $first->subject_name,
                    'subject_id_variants' => $variants,
                    'semester_id' => $majSemesterId,
                    'semester_name' => $first->semester_name,
                    'count' => $group->count(),
                    'applications' => $group->values(),
                ];
            })
            ->values();
    }

    /**
     * Berilgan fan + semestr uchun arizalar (modal'da ko'rsatish uchun).
     * subject_name + semester_name bo'yicha qidiradi — turli subject_id'lar
     * (turli curriculum) bo'lsa ham birlashadi.
     */
    public function applicationsForSubject(string $subjectName, string $semesterName): Collection
    {
        $normSubject = mb_strtolower(trim($subjectName));
        $normSemester = mb_strtolower(trim($semesterName));

        return RetakeApplication::query()
            ->whereRaw('LOWER(TRIM(subject_name)) = ?', [$normSubject])
            ->whereRaw('LOWER(TRIM(semester_name)) = ?', [$normSemester])
            ->where('dean_status', 'approved')
            ->where('registrar_status', 'approved')
            ->where('academic_dept_status', 'approved')
            ->where('final_status', 'pending')
            ->whereNull('retake_group_id')
            ->whereHas('group', function ($q) {
                $q->whereNotNull('payment_uploaded_at')
                  ->where('payment_verification_status', 'approved');
            })
            ->with('group.student')
            ->get();
    }

    /**
     * Yangi guruh yaratish (forming yoki scheduled holatida).
     */
    public function createGroup(array $data, array $applicationIds, Teacher $actor, bool $publish): RetakeGroup
    {
        if (count($applicationIds) < 1) {
            throw ValidationException::withMessages([
                'applications' => 'Eng kamida 1 ta talaba tanlanishi kerak',
            ]);
        }

        // Sanalar bo'sh kelsa — tanlangan arizalardagi qabul oynalaridan
        // (RetakeApplicationWindow) ko'pchilik bo'yicha auto-fill qilamiz.
        if (empty($data['start_date']) || empty($data['end_date'])) {
            $appGroupIds = RetakeApplication::whereIn('id', $applicationIds)->pluck('group_id');
            $windowIds = \App\Models\RetakeApplicationGroup::whereIn('id', $appGroupIds)->pluck('window_id')->filter()->unique();
            if ($windowIds->isNotEmpty()) {
                $win = \App\Models\RetakeApplicationWindow::whereIn('id', $windowIds)
                    ->orderByDesc('start_date')
                    ->first();
                if ($win) {
                    $data['start_date'] = $data['start_date'] ?: $win->start_date->format('Y-m-d');
                    $data['end_date'] = $data['end_date'] ?: $win->end_date->format('Y-m-d');
                }
            }
        }

        $this->validateData($data);

        $minSize = RetakeSetting::minGroupSize();
        if (count($applicationIds) < $minSize) {
            throw ValidationException::withMessages([
                'applications' => "Guruh uchun eng kamida {$minSize} ta talaba kerak",
            ]);
        }

        // Tanlangan arizalarni yuklash va validatsiya.
        // subject_name va semester_name tekshiruvi olib tashlangan — turli
        // fan/semestrdagi arizalarni bitta guruhga birlashtirish uchun
        // (turli curriculum talabalari bir xil mavzuni qayta o'qishi mumkin).
        $apps = RetakeApplication::whereIn('id', $applicationIds)
            ->where('dean_status', 'approved')
            ->where('registrar_status', 'approved')
            ->where('academic_dept_status', 'approved')
            ->where('final_status', 'pending')
            ->whereNull('retake_group_id')
            ->get();

        if ($apps->count() !== count($applicationIds)) {
            throw ValidationException::withMessages([
                'applications' => "Ba'zi arizalar guruhga qo'shilishga yaroqsiz holatda",
            ]);
        }

        // Majority subject_id/semester_id (turli curriculum'dan kelgan arizalar bo'lsa)
        $data['subject_id'] = $apps->pluck('subject_id')->countBy()->sortDesc()->keys()->first() ?: ($data['subject_id'] ?? null);
        $data['semester_id'] = $apps->pluck('semester_id')->countBy()->sortDesc()->keys()->first() ?: ($data['semester_id'] ?? null);

        // Baholash turi (OSKE/TEST/OSKE+TEST/SINOV) — O'quv bo'limi qo'lda tanlaydi.
        // assessment_type majburiy — validateAssessment() tekshiradi.

        $teacher = Teacher::find($data['teacher_id']);
        if (!$teacher) {
            throw ValidationException::withMessages([
                'teacher_id' => 'O\'qituvchi topilmadi',
            ]);
        }

        $this->validateAssessment($data);

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
                'assessment_type' => $data['assessment_type'] ?? null,
                'oske_date' => $data['oske_date'] ?? null,
                'test_date' => $data['test_date'] ?? null,
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
     * Mavjud guruhga qo'shimcha talaba (ariza)larni qo'shish.
     *
     * Guruh `forming` bo'lsa — arizalar oddiy attach (draft) qilinadi.
     * Guruh `scheduled`/`in_progress` bo'lsa — academicApprove orqali
     * to'liq tasdiqlanadi (final_status='approved').
     *
     * @return int Qo'shilgan arizalar soni
     */
    public function addApplicationsToGroup(RetakeGroup $group, array $applicationIds, Teacher $actor): int
    {
        if ($group->status === RetakeGroup::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'group' => "Tugagan guruhga talaba qo'shib bo'lmaydi",
            ]);
        }

        $apps = RetakeApplication::whereIn('id', $applicationIds)
            ->where('dean_status', 'approved')
            ->where('registrar_status', 'approved')
            ->where('academic_dept_status', 'approved')
            ->where('final_status', 'pending')
            ->whereNull('retake_group_id')
            ->get();

        if ($apps->isEmpty()) {
            throw ValidationException::withMessages([
                'application_ids' => "Tanlangan arizalar yaroqsiz yoki allaqachon guruhga biriktirilgan",
            ]);
        }

        $shouldApprove = $group->status !== RetakeGroup::STATUS_FORMING;

        DB::transaction(function () use ($group, $apps, $actor, $shouldApprove) {
            foreach ($apps as $app) {
                if ($shouldApprove) {
                    $this->applicationService->academicApprove($app, $actor, $group->id);
                } else {
                    $app->update(['retake_group_id' => $group->id]);
                    RetakeApplicationLog::create([
                        'application_id' => $app->id,
                        'group_id' => $app->group_id,
                        'user_id' => $actor->id,
                        'user_type' => 'teacher',
                        'user_name' => $actor->full_name,
                        'action' => RetakeApplicationLog::ACTION_GROUP_ASSIGNED,
                        'metadata' => ['retake_group_id' => $group->id, 'draft' => true, 'added_later' => true],
                    ]);
                }
            }
        });

        return $apps->count();
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
        foreach (['name', 'start_date', 'end_date', 'max_students', 'assessment_type', 'oske_date', 'test_date'] as $field) {
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

        // Baholash turini tekshirish (faqat o'zgartirilayotgan bo'lsa)
        if (array_key_exists('assessment_type', $data)) {
            $merged = array_merge([
                'assessment_type' => $group->assessment_type,
                'oske_date' => optional($group->oske_date)->format('Y-m-d'),
                'test_date' => optional($group->test_date)->format('Y-m-d'),
            ], $update);
            $this->validateAssessment($merged);
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

    /**
     * Baholash turi va sanalar tekshirish:
     *  - oske       → oske_date majburiy
     *  - test       → test_date majburiy
     *  - oske_test  → ikkala sana ham majburiy + test_date >= oske_date
     *  - sinov_fan  → qo'shimcha sana talab qilmaydi
     */
    /**
     * Tanlangan arizalardagi has_oske/has_test/has_sinov flaglardan
     * (registrator belgilagan) ko'pchilik bo'yicha guruhning baholash turini aniqlash.
     */
    private function deriveAssessmentType(\Illuminate\Support\Collection $apps): string
    {
        // Har bir ariza uchun flaglar tasnifi
        $sinov = $apps->filter(fn ($a) => (bool) $a->has_sinov)->count();
        $bothOskeTest = $apps->filter(fn ($a) => (bool) $a->has_oske && (bool) $a->has_test)->count();
        $oskeOnly = $apps->filter(fn ($a) => (bool) $a->has_oske && !(bool) $a->has_test)->count();
        $testOnly = $apps->filter(fn ($a) => (bool) $a->has_test && !(bool) $a->has_oske)->count();

        // Eng ko'p uchragan tipni tanlaymiz
        $counts = [
            RetakeGroup::ASSESSMENT_OSKE_TEST => $bothOskeTest,
            RetakeGroup::ASSESSMENT_OSKE => $oskeOnly,
            RetakeGroup::ASSESSMENT_TEST => $testOnly,
            RetakeGroup::ASSESSMENT_SINOV_FAN => $sinov,
        ];
        arsort($counts);
        $type = array_key_first($counts);
        if (!$type || $counts[$type] === 0) {
            // Hech qaysi flag yoqilmagan bo'lsa default sinov fan
            return RetakeGroup::ASSESSMENT_SINOV_FAN;
        }
        return $type;
    }

    private function validateAssessment(array $data): void
    {
        $type = $data['assessment_type'] ?? null;
        if (!$type) {
            throw ValidationException::withMessages([
                'assessment_type' => 'Baholash turini tanlang (OSKE, TEST, OSKE+TEST yoki Sinov fan)',
            ]);
        }

        $allowed = [
            RetakeGroup::ASSESSMENT_OSKE,
            RetakeGroup::ASSESSMENT_TEST,
            RetakeGroup::ASSESSMENT_OSKE_TEST,
            RetakeGroup::ASSESSMENT_SINOV_FAN,
        ];
        if (!in_array($type, $allowed, true)) {
            throw ValidationException::withMessages([
                'assessment_type' => 'Noto\'g\'ri baholash turi',
            ]);
        }

        $needsOske = in_array($type, [RetakeGroup::ASSESSMENT_OSKE, RetakeGroup::ASSESSMENT_OSKE_TEST], true);
        $needsTest = in_array($type, [RetakeGroup::ASSESSMENT_TEST, RetakeGroup::ASSESSMENT_OSKE_TEST], true);

        if ($needsOske && empty($data['oske_date'])) {
            throw ValidationException::withMessages([
                'oske_date' => 'OSKE sanasini belgilang',
            ]);
        }

        if ($needsTest && empty($data['test_date'])) {
            throw ValidationException::withMessages([
                'test_date' => 'TEST sanasini belgilang',
            ]);
        }

        // OSKE+TEST holatida TEST sanasi OSKE sanasidan oldin bo'lishi mumkin emas
        if ($type === RetakeGroup::ASSESSMENT_OSKE_TEST
            && !empty($data['oske_date']) && !empty($data['test_date'])
            && strtotime($data['test_date']) < strtotime($data['oske_date'])
        ) {
            throw ValidationException::withMessages([
                'test_date' => 'TEST sanasi OSKE sanasidan oldin bo\'lishi mumkin emas (avval OSKE topshiriladi, keyin TEST)',
            ]);
        }
    }
}
