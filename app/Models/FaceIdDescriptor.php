<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceIdDescriptor extends Model
{
    protected $fillable = [
        'student_id',
        'descriptor',
        'source_image_url',
        'enrolled_at',
    ];

    protected $casts = [
        'descriptor'  => 'array',
        'enrolled_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
