<?php

namespace App\Models;

use App\Enums\RetakeGroupStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetakeGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject_id',
        'subject_name',
        'semester_id',
        'semester_name',
        'start_date',
        'end_date',
        'teacher_id',
        'max_students',
        'status',
        'created_by',
        'created_by_guard',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'semester_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'max_students' => 'integer',
        'status' => RetakeGroupStatus::class,
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(RetakeApplication::class, 'retake_group_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            RetakeGroupStatus::SCHEDULED->value,
            RetakeGroupStatus::IN_PROGRESS->value,
        ]);
    }
}
