<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Istisno — detektor topgan qoidabuzarlik (TZ 3-bo'lim). Bir xil holat uchun
 * `fingerprint` bo'yicha bitta yozuv bo'ladi; holati tarixi comments'da.
 */
class MonitoringIstisno extends Model
{
    use LogsActivity;

    protected static string $activityModule = 'monitoring_istisno';
    protected static array $logOnly = ['status', 'owner_type', 'owner_id', 'due_at', 'closed_at'];

    public const STATUS_OCHIQ = 'ochiq';
    public const STATUS_JAVOB_BERILDI = 'javob_berildi';
    public const STATUS_YOPILDI = 'yopildi';
    public const STATUS_ASOSLI = 'asosli_istisno';
    public const STATUS_QAYTA_OCHILDI = 'qayta_ochildi';

    public const STATUSES = [
        self::STATUS_OCHIQ => 'Ochiq',
        self::STATUS_JAVOB_BERILDI => 'Javob berildi',
        self::STATUS_YOPILDI => 'Yopildi',
        self::STATUS_ASOSLI => 'Asosli istisno',
        self::STATUS_QAYTA_OCHILDI => 'Qayta ochildi',
    ];

    /** Hal bo'lmagan holatlar — mas'ul ishlaydi, detektor yangilaydi yoki yopadi. */
    public const OPEN_STATUSES = [self::STATUS_OCHIQ, self::STATUS_JAVOB_BERILDI, self::STATUS_QAYTA_OCHILDI];

    public const SUBJECT_TYPES = [
        'talaba' => 'Talaba',
        'buyruq' => 'Buyruq',
        'qaydnoma' => 'Qaydnoma',
        'ariza' => 'Ariza',
        'tolov' => "To'lov",
        'guruh_fan' => 'Guruh / fan',
    ];

    protected $table = 'monitoring_istisnos';

    protected $fillable = [
        'detector_id',
        'first_run_id',
        'last_run_id',
        'fingerprint',
        'scope_key',
        'subject_type',
        'subject_ref',
        'subject_label',
        'faculty_hemis_id',
        'department_hemis_id',
        'found_at',
        'last_seen_at',
        'due_at',
        'owner_type',
        'owner_id',
        'assigned_by_type',
        'assigned_by_id',
        'assigned_at',
        'status',
        'warning_only',
        'closed_by_system',
        'closed_at',
        'closed_by_type',
        'closed_by_id',
        'details',
    ];

    protected $casts = [
        'found_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'due_at' => 'datetime',
        'assigned_at' => 'datetime',
        'closed_at' => 'datetime',
        'warning_only' => 'boolean',
        'closed_by_system' => 'boolean',
        'details' => 'array',
    ];

    public function detector(): BelongsTo
    {
        return $this->belongsTo(MonitoringDetector::class, 'detector_id');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function closedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(MonitoringIstisnoComment::class, 'istisno_id')->orderBy('id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(MonitoringIstisnoEvidence::class, 'istisno_id')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at !== null && $this->due_at->isPast();
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_YOPILDI && $this->closed_by_system) {
            return 'Yopildi (tizim)';
        }

        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isOwnedBy(?Model $actor): bool
    {
        return $actor !== null
            && $this->owner_type === $actor->getMorphClass()
            && (int) $this->owner_id === (int) $actor->getKey();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeOwnedBy(Builder $query, Model $actor): Builder
    {
        return $query->where('owner_type', $actor->getMorphClass())->where('owner_id', $actor->getKey());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_at')->where('due_at', '<', now());
    }
}
