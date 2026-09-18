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
    ];

    protected $casts = [
        'lesson_date' => 'date',
        'deadline' => 'datetime',
        'reviewed_at' => 'datetime',
        'registrar_at' => 'datetime',
        'needs_registrar' => 'boolean',
    ];

    /**
     * Holatlar: pending — prorektor tasdig'ini kutmoqda; active — ochiq,
     * o'qituvchi baho qo'ya oladi; expired — muddati tugagan;
     * rejected — prorektor rad etgan.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REJECTED = 'rejected';

    /** Bosqich qarorlari (prorektor_status / registrar_status) */
    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';

    /**
     * O'qituvchi semestr ichida o'zi yubora oladigan so'rovlar soni.
     * Undan keyingilarini faqat admin yuboradi.
     */
    public const TEACHER_REQUEST_LIMIT = 2;

    /** Shu raqamdan boshlab tushuntirish xati va registrator tasdig'i kerak */
    public const STRICT_FROM_NUMBER = 2;

    /** Hali yakuniy qaror chiqmagan (bir yoki ikki bosqich kutilmoqda) */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAwaitingProrektor(): bool
    {
        return $this->isPending() && $this->prorektor_status === null;
    }

    public function isAwaitingRegistrar(): bool
    {
        return $this->isPending() && $this->needs_registrar && $this->registrar_status === null;
    }

    /** Kerakli barcha bosqichlar tasdiqladimi — dars ochilishi mumkin */
    public function allApprovalsGiven(): bool
    {
        return $this->prorektor_status === self::DECISION_APPROVED
            && (!$this->needs_registrar || $this->registrar_status === self::DECISION_APPROVED);
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
