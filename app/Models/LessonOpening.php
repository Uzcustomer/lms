<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LessonOpening extends Model
{
    protected $fillable = [
        'group_hemis_id',
        'subject_id',
        'semester_code',
        'teacher_id',
        'teacher_name',
        'request_number',
        'lesson_date',
        'file_path',
        'file_original_name',
        'explanation_file_path',
        'explanation_file_original_name',
        'opened_by_id',
        'opened_by_name',
        'opened_by_guard',
        'deadline',
        'status',
        'request_note',
        'reviewed_by_id',
        'reviewed_by_name',
        'reviewed_by_guard',
        'reviewed_at',
        'review_comment',
        'needs_registrar',
        'prorektor_status',
        'registrar_status',
        'registrar_id',
        'registrar_name',
        'registrar_guard',
        'registrar_at',
        'department_status',
        'department_id',
        'department_name',
        'department_guard',
        'department_at',
        'prorektor_approvals',
        'explanation_notice_at',
    ];

    protected $casts = [
        'lesson_date' => 'date',
        'deadline' => 'datetime',
        'reviewed_at' => 'datetime',
        'registrar_at' => 'datetime',
        'department_at' => 'datetime',
        'needs_registrar' => 'boolean',
        'prorektor_approvals' => 'array',
        'explanation_notice_at' => 'datetime',
    ];

    /**
     * Holatlar: pending — tasdiqlar kutilmoqda; active — ochiq, o'qituvchi
     * baho qo'ya oladi; expired — muddati tugagan; rejected — kamida bitta
     * tasdiqlovchi rad etgan.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REJECTED = 'rejected';

    /** Bosqich qarorlari */
    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';

    /** Tasdiqlash bosqichlari */
    public const STAGE_REGISTRAR = 'registrar';
    public const STAGE_DEPARTMENT = 'department';
    public const STAGE_PROREKTOR = 'prorektor';

    public const STAGE_LABELS = [
        self::STAGE_REGISTRAR => 'Registrator ofisi',
        self::STAGE_DEPARTMENT => "O'quv bo'limi boshlig'i",
        self::STAGE_PROREKTOR => "O'quv prorektori",
    ];

    /** Har bosqich qarori saqlanadigan ustunlar: [holat, id, ism, guard, vaqt] */
    private const STAGE_COLUMNS = [
        self::STAGE_REGISTRAR => ['registrar_status', 'registrar_id', 'registrar_name', 'registrar_guard', 'registrar_at'],
        self::STAGE_DEPARTMENT => ['department_status', 'department_id', 'department_name', 'department_guard', 'department_at'],
        self::STAGE_PROREKTOR => ['prorektor_status', 'reviewed_by_id', 'reviewed_by_name', 'reviewed_by_guard', 'reviewed_at'],
    ];

    /** Bosqich qaysi so'rov raqamlarida qatnashadi: [eng kichik, eng katta yoki null] */
    private const STAGE_NUMBERS = [
        self::STAGE_REGISTRAR => [1, null],
        self::STAGE_DEPARTMENT => [2, null],
        self::STAGE_PROREKTOR => [3, null],
    ];

    /** Bosqichni qaysi roldagilar tasdiqlaydi */
    public const STAGE_ROLES = [
        self::STAGE_REGISTRAR => 'registrator_ofisi',
        self::STAGE_DEPARTMENT => 'oquv_bolimi_boshligi',
        self::STAGE_PROREKTOR => 'oquv_prorektori',
    ];

    /** Shu raqamdan boshlab tushuntirish xati o'quv bo'limiga topshiriladi */
    public const EXPLANATION_FROM_NUMBER = 2;

    /** Prorektorlar roli — 3-so'rovdan boshlab har biri alohida tasdiqlaydi */
    public const PROREKTOR_ROLE = 'oquv_prorektori';

    /**
     * N-so'rovni kimlar tasdiqlaydi: 1 — registrator ofisi; 2 — u va o'quv
     * bo'limi boshlig'i; 3 va undan keyin — ularning ikkalasi va prorektorlar
     * (prorektorlarning har biri alohida tasdiqlaydi).
     */
    public static function stagesFor(?int $number): array
    {
        $number = max(1, (int) $number);

        return array_keys(array_filter(
            self::STAGE_NUMBERS,
            fn ($range) => $number >= $range[0] && ($range[1] === null || $number <= $range[1])
        ));
    }

    public function requiredStages(): array
    {
        return static::stagesFor($this->request_number);
    }

    /** So'rov tegishli fan nomi — dars jadvalidan. */
    public function subjectName(): string
    {
        return (string) (DB::table('schedules')
            ->where('group_id', $this->group_hemis_id)
            ->where('subject_id', $this->subject_id)
            ->whereNull('deleted_at')
            ->value('subject_name') ?: '');
    }

    /** So'rov tegishli guruh nomi. */
    public function groupName(): string
    {
        return (string) (DB::table('groups')
            ->where('group_hemis_id', $this->group_hemis_id)
            ->value('name') ?: '');
    }

    public static function statusColumn(string $stage): string
    {
        return self::STAGE_COLUMNS[$stage][0];
    }

    public function stageStatus(string $stage): ?string
    {
        if ($stage === self::STAGE_PROREKTOR) {
            return $this->prorektorStatus();
        }

        return $this->{self::STAGE_COLUMNS[$stage][0]};
    }

    /**
     * Prorektorlar bosqichi: har bir prorektor alohida tasdiqlaydi.
     * Bittasi rad etsa — bosqich rad etilgan; hammasi tasdiqlagachgina
     * tasdiqlangan hisoblanadi. Qarorlar prorektor_approvals da:
     * ["teacher:12" => ['name' => ..., 'decision' => ..., 'at' => ...]].
     */
    public function prorektorStatus(): ?string
    {
        $decisions = $this->prorektor_approvals ?? [];

        foreach ($decisions as $decision) {
            if (($decision['decision'] ?? null) === self::DECISION_REJECTED) {
                return self::DECISION_REJECTED;
            }
        }

        $approvers = static::prorektorApprovers();
        if ($approvers->isEmpty()) {
            return null;
        }

        foreach ($approvers as $key => $approver) {
            if (($decisions[$key]['decision'] ?? null) !== self::DECISION_APPROVED) {
                return null;
            }
        }

        return self::DECISION_APPROVED;
    }

    /**
     * Bosqichni tasdiqlaydigan xodimlar: ["guard:id" => ism].
     * Rolga ega barcha faol xodimlar — kim rolda bo'lsa, o'sha tasdiqlaydi.
     */
    public static function stageApprovers(string $stage): \Illuminate\Support\Collection
    {
        static $cached = [];
        if (isset($cached[$stage])) {
            return $cached[$stage];
        }

        $role = self::STAGE_ROLES[$stage] ?? null;
        if (!$role) {
            return collect();
        }

        $hasRole = fn ($query) => $query->where('name', $role);

        $teachers = Teacher::query()
            ->whereHas('roles', $hasRole)
            ->where('is_active', true)
            ->get(['id', 'full_name'])
            ->mapWithKeys(fn ($t) => ['teacher:' . $t->id => $t->full_name]);

        $users = User::query()
            ->whereHas('roles', $hasRole)
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($u) => ['web:' . $u->id => $u->name]);

        return $cached[$stage] = $teachers->merge($users);
    }

    /** Tasdiqlashi kerak bo'lgan prorektorlar: ["guard:id" => ism]. */
    public static function prorektorApprovers(): \Illuminate\Support\Collection
    {
        return static::stageApprovers(self::STAGE_PROREKTOR);
    }

    /** Qaror bergan shaxs kaliti: "teacher:12" / "web:3" */
    public static function reviewerKey(array $reviewer): string
    {
        return ($reviewer['guard'] ?? 'web') . ':' . ($reviewer['id'] ?? 0);
    }

    /** Shu prorektorning qarori: 'approved' | 'rejected' | null */
    public function prorektorDecisionOf(array $reviewer): ?string
    {
        $key = static::reviewerKey($reviewer);

        return ($this->prorektor_approvals[$key] ?? null)['decision'] ?? null;
    }

    /** Bosqich qarorini bergan shaxs va vaqti: ['name' => ?string, 'at' => ?Carbon] */
    public function stageDecider(string $stage): array
    {
        [, , $name, , $at] = self::STAGE_COLUMNS[$stage];
        $time = $this->{$at};

        return [
            'name' => $this->{$name},
            'at' => $time ? \Carbon\Carbon::parse($time) : null,
        ];
    }

    /** Bosqich qarorini yozish (saqlamaydi) */
    public function setStageDecision(string $stage, string $decision, array $reviewer): void
    {
        [$status, $id, $name, $guard, $at] = self::STAGE_COLUMNS[$stage];

        if ($stage === self::STAGE_PROREKTOR) {
            // Har bir prorektor qarori alohida saqlanadi; ustunlarda esa
            // oxirgi qaror va bosqichning umumiy holati turadi.
            $decisions = $this->prorektor_approvals ?? [];
            $decisions[static::reviewerKey($reviewer)] = [
                'name' => $reviewer['name'] ?? null,
                'decision' => $decision,
                'at' => now()->toDateTimeString(),
            ];
            $this->prorektor_approvals = $decisions;
        }

        $this->fill([
            $status => $decision,
            $id => $reviewer['id'] ?? null,
            $name => $reviewer['name'] ?? null,
            $guard => $reviewer['guard'] ?? null,
            $at => now(),
        ]);

        if ($stage === self::STAGE_PROREKTOR) {
            // Ustundagi holat — bosqichning yig'ma holati (hamma tasdiqladimi)
            $this->{$status} = $this->prorektorStatus();
        }
    }

    /** Barcha bosqich qarorlarini tozalash (so'rov qayta yuborilganda) */
    public static function emptyStageDecisions(): array
    {
        $empty = ['prorektor_approvals' => null];
        foreach (self::STAGE_COLUMNS as $columns) {
            foreach ($columns as $column) {
                $empty[$column] = null;
            }
        }

        return $empty;
    }

    /**
     * Ko'rsatish uchun bosqichlar: kerakli yoki qaror bergan har biri.
     * 'name' — qaror bergan shaxs; qaror yo'q bo'lsa 'expected' da shu rolda
     * kim borligi turadi, shunda kim tasdiqlashi kerakligi ko'rinib turadi.
     * [['stage', 'label', 'status', 'name', 'at', 'expected'], ...]
     */
    public function stageDecisions(): array
    {
        $required = $this->requiredStages();
        $list = [];
        foreach (self::STAGE_COLUMNS as $stage => [$status, , $name, , $at]) {
            if (!in_array($stage, $required, true) && $this->{$status} === null) {
                continue;
            }

            // Prorektorlar har biri alohida qator bo'lib ko'rinadi
            if ($stage === self::STAGE_PROREKTOR) {
                $decisions = $this->prorektor_approvals ?? [];
                foreach (static::prorektorApprovers() as $key => $approverName) {
                    $decision = $decisions[$key] ?? null;
                    $list[] = [
                        'stage' => $stage,
                        'label' => self::STAGE_LABELS[$stage],
                        'status' => $decision['decision'] ?? null,
                        'name' => $decision['name'] ?? $approverName,
                        'at' => isset($decision['at']) ? \Carbon\Carbon::parse($decision['at'])->format('d.m.Y H:i') : null,
                        'expected' => $approverName,
                    ];
                }
                continue;
            }

            $list[] = [
                'stage' => $stage,
                'label' => self::STAGE_LABELS[$stage],
                'status' => $this->{$status},
                'name' => $this->{$name},
                'at' => $this->{$at}?->format('d.m.Y H:i'),
                'expected' => static::stageApprovers($stage)->values()->implode(', '),
            ];
        }

        return $list;
    }

    /** Bosqichlar bo'yicha tasdiqlovchilar ismlari: ['registrar' => 'A, B', ...] */
    public static function approverNames(): array
    {
        $names = [];
        foreach (array_keys(self::STAGE_ROLES) as $stage) {
            $names[$stage] = static::stageApprovers($stage)->values()->all();
        }

        return $names;
    }

    /** Hali yakuniy qaror chiqmagan */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Shu bosqich qarori kutilmoqdami. Prorektorlar bosqichida qaror shaxsiy:
     * $reviewer berilsa, aynan o'sha prorektor hali qaror bermaganmi.
     */
    public function awaits(string $stage, ?array $reviewer = null): bool
    {
        if (!$this->isPending() || !in_array($stage, $this->requiredStages(), true)) {
            return false;
        }

        if ($stage === self::STAGE_PROREKTOR && $reviewer) {
            return $this->prorektorDecisionOf($reviewer) === null;
        }

        return $this->stageStatus($stage) === null;
    }

    /** Shu bosqich rad etgan edi — fikrini o'zgartirib tasdiqlashi mumkin */
    public function canReapprove(string $stage, ?array $reviewer = null): bool
    {
        if ($this->status !== self::STATUS_REJECTED || !in_array($stage, $this->requiredStages(), true)) {
            return false;
        }

        if ($stage === self::STAGE_PROREKTOR && $reviewer) {
            return $this->prorektorDecisionOf($reviewer) === self::DECISION_REJECTED;
        }

        return $this->stageStatus($stage) === self::DECISION_REJECTED;
    }

    /**
     * Ochilgan darsni tasdiqlagan bosqich qaytarib olishi mumkin — adashib
     * tasdiqlanganlarni rad etish uchun. Muddati tugagani yopilgan hisoblanadi.
     */
    public function canRevoke(string $stage, ?array $reviewer = null): bool
    {
        if ($this->status !== self::STATUS_ACTIVE || !in_array($stage, $this->requiredStages(), true)) {
            return false;
        }

        if ($stage === self::STAGE_PROREKTOR && $reviewer) {
            return $this->prorektorDecisionOf($reviewer) === self::DECISION_APPROVED;
        }

        return $this->stageStatus($stage) === self::DECISION_APPROVED;
    }

    public function anyStageRejected(): bool
    {
        foreach ($this->requiredStages() as $stage) {
            if ($this->stageStatus($stage) === self::DECISION_REJECTED) {
                return true;
            }
        }

        return false;
    }

    /** Kerakli barcha bosqichlar tasdiqladimi — dars ochilishi mumkin */
    public function allApprovalsGiven(): bool
    {
        foreach ($this->requiredStages() as $stage) {
            if ($this->stageStatus($stage) !== self::DECISION_APPROVED) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hali tasdiqlamagan bosqichlar. Prorektorlar bosqichi bir prorektor
     * tasdiqlagach ham ro'yxatda qoladi — qolgan prorektorlar kutilmoqda.
     */
    public function remainingStagesAfter(string $stage): array
    {
        return array_values(array_filter(
            $this->requiredStages(),
            fn ($s) => $this->stageStatus($s) !== self::DECISION_APPROVED
        ));
    }

    /**
     * Bosqich ko'radigan so'rovlar: registrator — 1 va 2-so'rov; o'quv bo'limi —
     * 2-dan boshlab; prorektor — 3-dan boshlab.
     */
    public function scopeVisibleToStage($query, ?string $stage)
    {
        $range = self::STAGE_NUMBERS[$stage] ?? null;
        if (!$range) {
            return $query;
        }

        [$from, $to] = $range;
        if ($from > 1) {
            $query->whereRaw('COALESCE(request_number, 1) >= ?', [$from]);
        }
        if ($to !== null) {
            $query->whereRaw('COALESCE(request_number, 1) <= ?', [$to]);
        }

        return $query;
    }

    /** Shu bosqich qarorini kutayotgan so'rovlar */
    public function scopeAwaitingStage($query, string $stage)
    {
        return $query->visibleToStage($stage)
            ->where('status', self::STATUS_PENDING)
            ->whereNull(self::STAGE_COLUMNS[$stage][0]);
    }

    /**
     * Joriy semestr boshlanishi. So'rovlar soni (o'qituvchi limiti) va
     * so'rovlar sahifasidagi ro'yxat shu sanadan hisoblanadi.
     *
     * Kalendar bo'yicha: kuzgi — 1-sentabr (yanvar ham shunga kiradi),
     * bahorgi — 1-fevral. Sozlamalarda aniq sana berilgan bo'lsa
     * (lesson_opening_period_start, masalan 14-sentabr) — o'sha olinadi, lekin
     * faqat shu kalendar semestri ichida: keyingi semestrda eski sana o'zi
     * eskirib, yana kalendar sanasi ishlaydi.
     */
    public static function periodStart(): \Carbon\Carbon
    {
        $now = \Carbon\Carbon::now('Asia/Tashkent');

        if ($now->month >= 9) {
            $start = $now->copy()->setDate($now->year, 9, 1)->startOfDay();
        } elseif ($now->month === 1) {
            $start = $now->copy()->setDate($now->year - 1, 9, 1)->startOfDay();
        } else {
            $start = $now->copy()->setDate($now->year, 2, 1)->startOfDay();
        }

        $configured = trim((string) Setting::get('lesson_opening_period_start', ''));
        if ($configured !== '') {
            try {
                $custom = \Carbon\Carbon::parse($configured, 'Asia/Tashkent')->startOfDay();
                if ($custom->greaterThanOrEqualTo($start) && $custom->lessThanOrEqualTo($now)) {
                    return $custom;
                }
            } catch (\Throwable $e) {
                // Noto'g'ri yozilgan sana — kalendar sanasi ishlaydi
            }
        }

        return $start;
    }

    /** Faqat joriy semestrda yuborilgan so'rovlar */
    public function scopeCurrentPeriod($query)
    {
        return $query->where('created_at', '>=', static::periodStart());
    }

    /**
     * O'qituvchining joriy semestrdagi oldingi so'rovlari soni.
     * Rad etilganlar hisoblanmaydi — ular dars ochmagan.
     */
    public static function priorRequestCount(int $teacherId, ?int $exceptId = null): int
    {
        return static::where('teacher_id', $teacherId)
            ->where('status', '!=', self::STATUS_REJECTED)
            ->where('created_at', '>=', static::periodStart())
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->count();
    }

    /** Bir nechta o'qituvchi uchun bittada: [teacher_id => soni] */
    public static function priorRequestCounts(array $teacherIds): array
    {
        if (!$teacherIds) {
            return [];
        }

        return static::whereIn('teacher_id', $teacherIds)
            ->where('status', '!=', self::STATUS_REJECTED)
            ->where('created_at', '>=', static::periodStart())
            ->selectRaw('teacher_id, COUNT(*) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Berilgan guruh+fan+semestr uchun faol dars ochilishlarini olish
     */
    public static function getActiveOpenings(string $groupHemisId, string $subjectId, string $semesterCode): array
    {
        return static::where('group_hemis_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('status', 'active')
            ->where('deadline', '>', now())
            ->pluck('lesson_date')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->toArray();
    }

    /**
     * Berilgan guruh+fan+semestr uchun barcha dars ochilishlarini olish (faol va muddati o'tgan)
     */
    public static function getAllOpenings(string $groupHemisId, string $subjectId, string $semesterCode): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('group_hemis_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->get();
    }

    /**
     * Muddati o'tgan ochilishlarni expired qilish
     */
    public static function expireOverdue(): int
    {
        return static::where('status', self::STATUS_ACTIVE)
            ->whereNotNull('deadline')
            ->where('deadline', '<=', now())
            ->update(['status' => self::STATUS_EXPIRED]);
    }

    /**
     * Shu ochilish hali faolmi
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->deadline !== null
            && $this->deadline > now();
    }
}
