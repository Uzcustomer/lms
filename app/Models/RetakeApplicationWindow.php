<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class RetakeApplicationWindow extends Model
{
    protected $fillable = [
        'specialty_id',
        'specialty_name',
        'level_code',
        'level_name',
        'semester_code',
        'semester_name',
        'start_date',
        'end_date',
        'created_by_user_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function applicationGroups()
    {
        return $this->hasMany(RetakeApplicationGroup::class, 'window_id');
    }

    public function getStatusAttribute(): string
    {
        $today = Carbon::today();

        if ($this->start_date->gt($today)) {
            return 'upcoming';
        }
        if ($this->end_date->lt($today)) {
            return 'closed';
        }
        return 'active';
    }

    public function getRemainingDaysAttribute(): int
    {
        $today = Carbon::today();
        if ($this->end_date->lt($today)) {
            return 0;
        }
        return $today->diffInDays($this->end_date);
    }

    public function isOpen(): bool
    {
        return $this->status === 'active';
    }

    public function scopeForStudent($query, int $specialtyId, string $levelCode)
    {
        return $query->where('specialty_id', $specialtyId)
            ->where('level_code', $levelCode);
    }

    public function scopeActive($query)
    {
        $today = Carbon::today();
        return $query->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today);
    }
}
