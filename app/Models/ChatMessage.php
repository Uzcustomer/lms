<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function sender()
    {
        return $this->belongsTo(Student::class, 'sender_id', 'id');
    }

    public function receiver()
    {
        return $this->belongsTo(Student::class, 'receiver_id', 'id');
    }
}
