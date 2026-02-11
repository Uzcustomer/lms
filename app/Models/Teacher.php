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
        'is_active',
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

    /**
     * Accessor for admin layout compatibility (sidebar uses Auth::user()->name)
     */
    public function getNameAttribute()
    {
        return $this->full_name ?? $this->short_name;
    }

    /**
     * Accessor for admin layout compatibility (sidebar uses Auth::user()->email)
     */
    public function getEmailAttribute()
    {
        return $this->login;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isProfileComplete(): bool
    {
        return !empty($this->phone);
    }

    public function isTelegramVerified(): bool
    {
        return $this->telegram_verified_at !== null;
    }

    public function telegramDaysLeft(): int
    {
        if ($this->isTelegramVerified()) {
            return 0;
        }

        $days = (int) Setting::get('telegram_deadline_days', 7);

        if (!$this->phone) {
            return $days;
        }

        $deadline = $this->updated_at->copy()->addDays($days);
        $daysLeft = (int) now()->diffInDays($deadline, false);

        return max($daysLeft, 0);
    }

    public function isTelegramDeadlinePassed(): bool
    {
        return !$this->isTelegramVerified() && $this->phone && $this->telegramDaysLeft() <= 0;
    }

    public function studentGrades()
    {
        return $this->hasMany(StudentGrade::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_teacher', 'teacher_id', 'group_id');
    }

    /**
     * Dekan sifatida bog'langan fakultetlar (ko'p-ko'p)
     */
    public function deanFaculties()
    {
        return $this->belongsToMany(Department::class, 'dean_faculties', 'teacher_id', 'department_hemis_id', 'id', 'department_hemis_id')
            ->withTimestamps();
    }

    /**
     * Dekan fakultetlarining department_hemis_id ro'yxati
     */
    public function getDeanFacultyIdsAttribute(): array
    {
        return $this->deanFaculties()->pluck('departments.department_hemis_id')->toArray();
    }

}