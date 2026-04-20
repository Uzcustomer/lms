<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFile extends Model
{
    protected $fillable = [
        'student_id',
        'name',
        'original_name',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
