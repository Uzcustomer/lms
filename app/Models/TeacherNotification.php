<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherNotification extends Model
{
    protected $fillable = [
        'teacher_id',
        'type',
        'title',
        'message',
        'link',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
