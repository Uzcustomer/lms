<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * M3 (soddalashtirilgan): buyruq bo'yicha ijara kotibi va buxgalteriyaga xabarnoma yozuvi (E-1).
 */
class MonitoringNoticeLog extends Model
{
    public const RECIPIENT_KINDS = [
        'ijara_kotibi' => 'Ijara kotibi',
        'buxgalteriya' => 'Buxgalteriya',
    ];

    public const METHODS = [
        'mark' => 'mark (ichki xabar + Telegram)',
        'qogoz' => "Qog'oz / imzo bilan",
        'boshqa' => 'Boshqa',
    ];

    protected $fillable = [
        'order_id',
        'recipient_kind',
        'recipient_type',
        'recipient_id',
        'recipient_name',
        'sent_at',
        'method',
        'note',
        'evidence_path',
        'recorded_by_type',
        'recorded_by_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(MonitoringOrder::class, 'order_id');
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function recordedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
