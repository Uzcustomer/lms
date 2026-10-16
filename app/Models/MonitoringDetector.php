<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Nazorat detektori (docs/detectors.yaml katalogidan). Muddat va chegaralar `params` da —
 * paneldan o'zgartiriladi, o'zgarish activity_logs ga yoziladi.
 */
class MonitoringDetector extends Model
{
    use LogsActivity;

    protected static string $activityModule = 'monitoring_detector';
    protected static array $logOnly = ['params', 'enabled', 'warning_mode', 'due_workdays', 'default_owner_type', 'default_owner_id', 'severity'];

    public const SEVERITIES = [
        'past' => 'Past',
        'orta' => "O'rta",
        'yuqori' => 'Yuqori',
    ];

    protected $fillable = [
        'code',
        'group_name',
        'title',
        'rule_text',
        'legal_basis',
        'detection_text',
        'catalog_status',
        'params',
        'severity',
        'enabled',
        'pilot',
        'warning_mode',
        'due_workdays',
        'default_owner_role',
        'default_owner_type',
        'default_owner_id',
        'schedule',
        'last_run_at',
    ];

    protected $casts = [
        'params' => 'array',
        'enabled' => 'boolean',
        'pilot' => 'boolean',
        'warning_mode' => 'boolean',
        'due_workdays' => 'integer',
        'last_run_at' => 'datetime',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(MonitoringRun::class, 'detector_id');
    }

    public function lastRun(): HasOne
    {
        return $this->hasOne(MonitoringRun::class, 'detector_id')->latestOfMany('started_at');
    }

    public function istisnolar(): HasMany
    {
        return $this->hasMany(MonitoringIstisno::class, 'detector_id');
    }

    public function defaultOwner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isImplemented(): bool
    {
        return isset(config('monitoring.detectors', [])[$this->code]);
    }

    public function param(string $key, $default = null)
    {
        return data_get($this->params ?? [], $key, $default);
    }

    public function scopeRunnable($query)
    {
        return $query->where('enabled', true)
            ->whereIn('code', array_keys(config('monitoring.detectors', [])));
    }
}
