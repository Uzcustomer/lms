<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGroupHistory extends Model
{
    protected $table = 'student_group_history';

    protected $fillable = [
        'student_id',
        'group_hemis_id',
        'group_name',
        'specialty_name',
        'education_year_name',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
