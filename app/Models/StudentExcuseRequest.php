<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentExcuseRequest extends Model
{
    protected $fillable = [
        'student_id',
        'student_hemis_id',
        'student_name',
        'group_name',
        'type',
        'subject_name',
        'reason',
        'file_path',
        'file_original_name',
        'status',
        'admin_comment',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
