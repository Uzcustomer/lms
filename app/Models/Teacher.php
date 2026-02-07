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
        'department_hemis_id',
        'role',
        'must_change_password',
        'phone',
        'telegram_username',
        'telegram_chat_id',
        'telegram_verification_code',
        'telegram_verified_at',
    ];

    protected $hidden = [
        'password',
        'telegram_verification_code',
    ];

    protected $casts = [
        'telegram_verified_at' => 'datetime',
    ];

    public function isProfileComplete(): bool
    {
        return !empty($this->phone) && !empty($this->telegram_username) && $this->telegram_verified_at !== null;
    }

    public function studentGrades()
    {
        return $this->hasMany(StudentGrade::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_teacher', 'teacher_id', 'group_id');
    }

}