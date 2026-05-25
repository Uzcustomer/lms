<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SinovTestGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'semester_code',
        'group_hemis_id',
        'student_hemis_id',
        'default_grade',
        'override_grade',
        'is_locked',
        'overridden_by_user_id',
        'overridden_at',
    ];

    protected $casts = [
        'default_grade' => 'decimal:2',
        'override_grade' => 'decimal:2',
        'is_locked' => 'boolean',
        'overridden_at' => 'datetime',
    ];

    public function effectiveGrade(): ?float
    {
        if ($this->override_grade !== null) {
            return (float) $this->override_grade;
        }
        if ($this->default_grade !== null) {
            return (float) $this->default_grade;
        }
        return null;
    }
}
