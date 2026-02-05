<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_hemis_id',
        'name',
        'department_hemis_id',
        'department_name',
        'department_code',
        'department_structure_type_code',
        'department_structure_type_name',
        'department_locality_type_code',
        'department_locality_type_name',
        'department_active',
        'active',
        'specialty_hemis_id',
        'specialty_code',
        'specialty_name',
        'education_lang_code',
        'education_lang_name',
        'curriculum_hemis_id',
    ];

    protected $casts = [
        'department_active' => 'boolean',
        'active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_hemis_id', 'department_hemis_id');
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class, 'specialty_hemis_id', 'specialty_hemis_id');
    }

    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_hemis_id', 'curricula_hemis_id');
    }

    public function getRouteKeyName()
    {
        return 'id';
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'group_teacher', 'group_id', 'teacher_id');
    }

}
