<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Detektorning bitta ishga tushishi: nechta istisno topildi, ochildi, yopildi, xato bo'ldimi.
 */
class MonitoringRun extends Model
{
    public const STATUS_RUNNING = 'ishlamoqda';
    public const STATUS_OK = 'ok';
    public const STATUS_ERROR = 'xato';

    protected $fillable = [
        'detector_id',
        'status',
        'started_at',
        'finished_at',
        'found_count',
        'opened_count',
        'updated_count',
        'closed_count',
        'reopened_count',
        'error_message',
        'params_snapshot',
        'triggered_by_type',
        'triggered_by_id',
        'data_import_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'params_snapshot' => 'array',
    ];

    public function detector(): BelongsTo
    {
        return $this->belongsTo(MonitoringDetector::class, 'detector_id');
    }

    public function triggeredBy(): MorphTo
    {
        return $this->morphTo();
    }
}
