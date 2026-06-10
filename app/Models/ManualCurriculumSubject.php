<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualCurriculumSubject extends Model
{
    protected $fillable = [
        'manual_curriculum_id',
        'block',
        'subject_code',
        'subject_name',
        'reference_name',
        'kurs',
        'semester',
        'total_hours',
        'audit_total',
        'lecture',
        'practice',
        'laboratory',
        'seminar',
        'independent',
        'credit',
        'note',
    ];

    protected $casts = [
        'total_hours' => 'decimal:2',
        'audit_total' => 'decimal:2',
        'lecture' => 'decimal:2',
        'practice' => 'decimal:2',
        'laboratory' => 'decimal:2',
        'seminar' => 'decimal:2',
        'independent' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function curriculum()
    {
        return $this->belongsTo(ManualCurriculum::class, 'manual_curriculum_id');
    }
}
