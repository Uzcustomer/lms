<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;

class Teacher extends Authenticatable
{
    use HasFactory, HasRoles;

    protected $guard_name = 'web';

    protected $fillable = [
        'full_name',
        'short_name',
        'first_name',
        'second_name',
        'third_name',
        'employee_id_number',
        'birth_date',
        'image',
        'year_of_enter',
        'specialty',
        'gender',
        'department',
        'employment_form',
        'employment_staff',
        'staff_position',
        'employee_status',
        'employee_type',
        'contract_number',
        'decree_number',
        'contract_date',
        'decree_date',
        'login',
        'password',
        'hemis_id',
        'meta_id',
        'status',
        'department_hemis_id'
        ,
        'role'
    ];

    protected $hidden = [
        'password',
    ];

    public function studentGrades()
    {
        return $this->hasMany(StudentGrade::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_teacher', 'teacher_id', 'group_id');
    }

}