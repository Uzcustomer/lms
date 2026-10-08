<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentComplaint extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_RESOLVED = 'resolved';

    /** Rasmlar yopiq diskda: storage/app/student-complaints/... */
    public const DISK = 'local';

    protected $table = 'student_complaints';

    protected $fillable = [
        'student_id',
        'student_hemis_id',
        'student_id_number',
        'student_name',
        'group_name',
        'faculty_name',
        'phone',
        'message',
        'images',
        'status',
        'resolved_at',
        'resolved_by_name',
    ];

    protected $casts = [
        'images' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }
}
