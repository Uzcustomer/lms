<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherDashboardSnapshot extends Model
{
    protected $fillable = [
        'scope',
        'teacher_hemis_id',
        'payload',
        'generated_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'generated_at' => 'datetime',
    ];

    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_TEACHER = 'teacher';

    public static function global(): ?self
    {
        return static::where('scope', self::SCOPE_GLOBAL)->first();
    }

    public static function forTeacher(string $hemisId): ?self
    {
        return static::where('scope', self::SCOPE_TEACHER)
            ->where('teacher_hemis_id', $hemisId)
            ->first();
    }
}
