<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetakeApplicationPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'specialty_id',
        'course',
        'semester_id',
        'start_date',
        'end_date',
        'created_by',
        'created_by_guard',
    ];

    protected $casts = [
        'specialty_id' => 'integer',
        'course' => 'integer',
        'semester_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(RetakeApplication::class, 'period_id');
    }

    /**
     * Faollik avtomatik aniqlanadi: start_date <= today <= end_date
     */
    public function getIsActiveAttribute(): bool
    {
        $today = CarbonImmutable::today();
        return $this->start_date->lessThanOrEqualTo($today)
            && $this->end_date->greaterThanOrEqualTo($today);
    }

    public function getIsUpcomingAttribute(): bool
    {
        return $this->start_date->greaterThan(CarbonImmutable::today());
    }

    public function getIsClosedAttribute(): bool
    {
        return $this->end_date->lessThan(CarbonImmutable::today());
    }

    public function getDaysLeftAttribute(): int
    {
        if (! $this->is_active) {
            return 0;
        }

        return (int) CarbonImmutable::today()->diffInDays($this->end_date, false);
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = CarbonImmutable::today()->toDateString();
        return $query->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today);
    }

    public function scopeForStudent(Builder $query, int $specialtyId, int $course, int $semesterId): Builder
    {
        return $query->where('specialty_id', $specialtyId)
            ->where('course', $course)
            ->where('semester_id', $semesterId);
    }
}
