<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClubMembership extends Model
{
    protected $fillable = [
        'student_id',
        'student_hemis_id',
        'student_name',
        'group_name',
        'club_name',
        'club_place',
        'club_day',
        'club_time',
        'kafedra_name',
        'masul_name',
        'department_hemis_id',
        'status',
        'reject_reason',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
