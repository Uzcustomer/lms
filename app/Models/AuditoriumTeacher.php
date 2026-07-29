<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditoriumTeacher extends Model
{
    protected $table = 'auditorium_teacher';

    protected $fillable = [
        'board_id',
        'auditorium_id',
        'teacher_id',
        'is_general',
    ];

    protected $casts = [
        'is_general' => 'boolean',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
}
