<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KtrPlan extends Model
{
    protected $fillable = [
        'curriculum_subject_id',
        'week_count',
        'plan_data',
        'created_by',
    ];

    protected $casts = [
        'plan_data' => 'array',
    ];

    public function curriculumSubject()
    {
        return $this->belongsTo(CurriculumSubject::class);
    }
}
