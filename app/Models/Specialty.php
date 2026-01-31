<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    use HasFactory;

    use HasFactory;

    protected $fillable = [
        'specialty_hemis_id',
        'code',
        'name',
        'department_hemis_id',
        'department_name',
        'department_code',
        'locality_type_code',
        'locality_type_name',
        'education_type_code',
        'education_type_name',
        'bachelor_specialty_code',
        'bachelor_specialty_name',
        'master_specialty_code',
        'master_specialty_name',
        'doctorate_specialty_code',
        'doctorate_specialty_name',
        'ordinature_specialty_code',
        'ordinature_specialty_name',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_hemis_id', 'department_hemis_id');
    }

    public function curricula()
    {
        return $this->hasMany(Curriculum::class, 'specialty_hemis_id', 'specialty_hemis_id');
    }

    public function groups()
    {
        return $this->hasMany(Group::class, 'specialty_hemis_id', 'specialty_hemis_id');
    }
}
