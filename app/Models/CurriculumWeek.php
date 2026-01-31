<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurriculumWeek extends Model
{
    use HasFactory;

    protected $fillable = [
        'curriculum_week_hemis_id',
        'semester_hemis_id',
        'current',
        'start_date',
        'end_date',
        'start_date_formatted',
        'end_date_formatted',
    ];

    protected $casts = [
        'current' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_hemis_id', 'semester_hemis_id');
    }
}
