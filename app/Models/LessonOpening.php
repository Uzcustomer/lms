<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'lesson_date' => 'date',
        'deadline' => 'datetime',
        'reviewed_at' => 'datetime',
        'registrar_at' => 'datetime',
        'department_at' => 'datetime',
        'needs_registrar' => 'boolean',
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

    /** Bosqich shu raqamdan boshlab qo'shiladi (registrator — har doim) */
    private const STAGE_FROM_NUMBER = [
        self::STAGE_REGISTRAR => 1,
        self::STAGE_DEPARTMENT => 2,
        self::STAGE_PROREKTOR => 3,
    ];

    /**
     * O'qituvchi semestr ichida o'zi yubora oladigan so'rovlar soni.
     * Undan keyingilarini faqat admin yuboradi.
     */
    public const TEACHER_REQUEST_LIMIT = 2;

    /** Shu raqamdan boshlab tushuntirish xati majburiy */
    public const STRICT_FROM_NUMBER = 2;

    /**
     * N-so'rovni kimlar tasdiqlaydi: 1 — registrator ofisi; 2 — u va o'quv
     * bo'limi boshlig'i; 3 va undan keyin — ular va o'quv prorektori.
     */
    public static function stagesFor(?int $number): array
    {
        $number = max(1, (int) $number);

        return array_keys(array_filter(self::STAGE_FROM_NUMBER, fn ($from) => $number >= $from));
    }

    public function requiredStages(): array
    {
        return static::stagesFor($this->request_number);
    }

    public static function statusColumn(string $stage): string
    {
        return self::STAGE_COLUMNS[$stage][0];
    }

    public function stageStatus(string $stage): ?string
    {
        return $this->{self::STAGE_COLUMNS[$stage][0]};
    }

    /** Bosqich qarorini yozish (saqlamaydi) */
    public function setStageDecision(string $stage, string $decision, array $reviewer): void
    {
        [$status, $id, $name, $guard, $at] = self::STAGE_COLUMNS[$stage];

        $this->fill([
            $status => $decision,
            $id => $reviewer['id'] ?? null,
            $name => $reviewer['name'] ?? null,
            $guard => $reviewer['guard'] ?? null,
            $at => now(),
        ]);
    }

    /** Barcha bosqich qarorlarini tozalash (so'rov qayta yuborilganda) */
    public static function emptyStageDecisions(): array
    {
        $empty = [];
        foreach (self::STAGE_COLUMNS as $columns) {
            foreach ($columns as $column) {
                $empty[$column] = null;
            }
        }

        return $empty;
    }

    /**
     * Ko'rsatish uchun bosqichlar: kerakli yoki qaror bergan har biri.
     * [['stage', 'label', 'status', 'name', 'at'], ...]
     */
    public function stageDecisions(): array
    {
        $required = $this->requiredStages();
        $list = [];
        foreach (self::STAGE_COLUMNS as $stage => [$status, , $name, , $at]) {
            if (!in_array($stage, $required, true) && $this->{$status} === null) {
                continue;
            }
            $list[] = [
                'stage' => $stage,
                'label' => self::STAGE_LABELS[$stage],
                'status' => $this->{$status},
                'name' => $this->{$name},
                'at' => $this->{$at}?->format('d.m.Y H:i'),
            ];
        }

        return $list;
    }

    /** Hali yakuniy qaror chiqmagan */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Shu bosqich qarori kutilmoqdami */
    public function awaits(string $stage): bool
    {
        return $this->isPending()
            && in_array($stage, $this->requiredStages(), true)
            && $this->stageStatus($stage) === null;
    }

    /** Shu bosqich rad etgan edi — fikrini o'zgartirib tasdiqlashi mumkin */
    public function canReapprove(string $stage): bool
    {
        return $this->status === self::STATUS_REJECTED
            && in_array($stage, $this->requiredStages(), true)
            && $this->stageStatus($stage) === self::DECISION_REJECTED;
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

    /** Shu bosqich tasdiqlasa hali kimlar qoladi (tasdiqlamaganlar) */
    public function remainingStagesAfter(string $stage): array
    {
        return array_values(array_filter(
            $this->requiredStages(),
            fn ($s) => $s !== $stage && $this->stageStatus($s) !== self::DECISION_APPROVED
        ));
    }

    /** Bosqich ko'radigan so'rovlar: o'quv bo'limi — 2-dan, prorektor — 3-dan */
    public function scopeVisibleToStage($query, ?string $stage)
    {
        $from = self::STAGE_FROM_NUMBER[$stage] ?? 1;
        if ($from > 1) {
            $query->whereRaw('COALESCE(request_number, 1) >= ?', [$from]);
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
     * Joriy semestr boshlanishi: kuzgi — 1-sentabr (yanvar ham shunga
     * kiradi), bahorgi — 1-fevral. So'rovlar soni shu sanadan hisoblanadi.
     */
    public static function periodStart(): \Carbon\Carbon
    {
        $now = \Carbon\Carbon::now('Asia/Tashkent');

        if ($now->month >= 9) {
            return $now->copy()->setDate($now->year, 9, 1)->startOfDay();
        }
        if ($now->month === 1) {
            return $now->copy()->setDate($now->year - 1, 9, 1)->startOfDay();
        }

        return $now->copy()->setDate($now->year, 2, 1)->startOfDay();
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
