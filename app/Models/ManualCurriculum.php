<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualCurriculum extends Model
{
    protected $table = 'manual_curricula';

    protected $fillable = [
        'type',
        'name',
        'specialty_code',
        'specialty_name',
        'plan_year',
        'education_period',
        'file_original_name',
        'file_path',
        'notes',
        'created_by',
    ];

    public function subjects()
    {
        return $this->hasMany(ManualCurriculumSubject::class);
    }

    public function typeLabel(): string
    {
        return $this->type === 'namunaviy' ? "Namunaviy o'quv reja" : "Ishchi o'quv reja";
    }
}
