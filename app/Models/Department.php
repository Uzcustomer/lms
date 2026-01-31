<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_hemis_id',
        'name',
        'code',
        'structure_type_code',
        'structure_type_name',
        'locality_type_code',
        'locality_type_name',
        'parent_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function specialties()
    {
        return $this->hasMany(Specialty::class, 'department_hemis_id', 'department_hemis_id');
    }

    public function curricula()
    {
        return $this->hasMany(Curriculum::class, 'department_hemis_id', 'department_hemis_id');
    }

    public function groups()
    {
        return $this->hasMany(Group::class, 'department_hemis_id', 'department_hemis_id');
    }


}
