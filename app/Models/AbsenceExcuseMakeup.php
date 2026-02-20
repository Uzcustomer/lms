<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsenceExcuseMakeup extends Model
{
    const ASSESSMENT_TYPES = [
        'jn' => ['label' => 'Joriy Nazorat', 'code' => '100'],
        'mt' => ['label' => 'Mustaqil ta\'lim', 'code' => '99'],
        'oski' => ['label' => 'OSKI', 'code' => '101'],
        'test' => ['label' => 'Yakuniy test', 'code' => '102'],
    ];

    protected $fillable = [
        'absence_excuse_id',
        'student_id',
        'subject_name',
        'subject_id',
        'assessment_type',
        'assessment_type_code',
        'original_date',
        'makeup_date',
        'status',
    ];

    protected $casts = [
        'original_date' => 'date',
        'makeup_date' => 'date',
    ];

    public function absenceExcuse()
    {
        return $this->belongsTo(AbsenceExcuse::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function getAssessmentTypeLabelAttribute(): string
    {
        return self::ASSESSMENT_TYPES[$this->assessment_type]['label'] ?? $this->assessment_type;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Kutilmoqda',
            'scheduled' => 'Rejalashtirilgan',
            'completed' => 'Bajarilgan',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'scheduled' => 'blue',
            'completed' => 'green',
            default => 'gray',
        };
    }
}
