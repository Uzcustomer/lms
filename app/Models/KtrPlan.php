<?php

namespace App\Models;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class KtrPlan extends Model
{
    protected $fillable = [
        'curriculum_subject_id',
        'week_count',
        'plan_data',
        'created_by',
        'created_by_guard',
    ];

    protected $casts = [
        'plan_data' => 'array',
    ];

    public function curriculumSubject()
    {
        return $this->belongsTo(CurriculumSubject::class);
    }

    /**
     * Tuzuvchi (yaratuvchi) xodimni olish
     */
    public function getCreatorAttribute()
    {
        if (!$this->created_by) {
            return null;
        }

        if ($this->created_by_guard === 'teacher') {
            return Teacher::find($this->created_by);
        }

        return User::find($this->created_by);
    }

    /**
     * Tuzuvchi ismini olish
     */
    public function getCreatorNameAttribute(): string
    {
        $creator = $this->creator;
        if (!$creator) {
            return '';
        }

        if ($creator instanceof Teacher) {
            return $creator->full_name ?? $creator->name ?? '';
        }

        return $creator->name ?? '';
    }
}
