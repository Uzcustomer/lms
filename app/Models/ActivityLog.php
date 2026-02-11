<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'guard',
        'user_id',
        'user_name',
        'role',
        'action',
        'module',
        'description',
        'subject_type',
        'subject_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function subject()
    {
        return $this->morphTo();
    }

    public function scopeByGuard($query, string $guard)
    {
        return $query->where('guard', $guard);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeByUser($query, int $userId, ?string $guard = null)
    {
        $query->where('user_id', $userId);
        if ($guard) {
            $query->where('guard', $guard);
        }
        return $query;
    }

    public function scopeDateBetween($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'create' => 'Yaratish',
            'update' => 'Tahrirlash',
            'delete' => "O'chirish",
            'login' => 'Kirish',
            'logout' => 'Chiqish',
            'export' => 'Eksport',
            'import' => 'Import',
            'upload' => 'Yuklash',
            'view' => "Ko'rish",
            'grade' => 'Baholash',
            default => $this->action,
        };
    }

    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'create' => 'green',
            'update' => 'blue',
            'delete' => 'red',
            'login' => 'indigo',
            'logout' => 'gray',
            'export' => 'purple',
            'import' => 'yellow',
            'upload' => 'cyan',
            'grade' => 'orange',
            default => 'gray',
        };
    }
}
