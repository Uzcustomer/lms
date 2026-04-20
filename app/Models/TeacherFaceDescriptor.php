<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherFaceDescriptor extends Model
{
    protected $fillable = ['teacher_id', 'descriptor', 'source_image_url', 'enrolled_at'];

    protected $casts = [
        'descriptor'  => 'array',
        'enrolled_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}
