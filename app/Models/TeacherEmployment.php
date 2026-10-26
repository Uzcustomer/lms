<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Xodimning HEMIS dagi bitta ish joyi (asosiy, o'rindoshlik yoki soatbay). */
class TeacherEmployment extends Model
{
    protected $table = 'teacher_employments';

    protected $fillable = [
        'teacher_id',
        'meta_id',
        'department_hemis_id',
        'department_name',
        'staff_position',
        'employment_form',
        'employment_staff',
        'employee_status',
        'is_primary',
        'is_working',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_working' => 'boolean',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}
