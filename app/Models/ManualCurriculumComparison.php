<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualCurriculumComparison extends Model
{
    protected $table = 'manual_curriculum_comparisons';

    protected $fillable = [
        'reference_id',
        'working_id',
        'created_by',
    ];

    public function reference()
    {
        return $this->belongsTo(ManualCurriculum::class, 'reference_id');
    }

    public function working()
    {
        return $this->belongsTo(ManualCurriculum::class, 'working_id');
    }
}
