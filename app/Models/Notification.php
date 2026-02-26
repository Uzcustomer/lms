<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sender_id',
        'sender_type',
        'recipient_id',
        'recipient_type',
        'subject',
        'body',
        'type',
        'data',
        'is_read',
        'read_at',
        'is_draft',
        'sent_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_draft' => 'boolean',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'data' => 'array',
    ];

    // Notification types
    const TYPE_SYSTEM = 'system';
    const TYPE_MESSAGE = 'message';
    const TYPE_ALERT = 'alert';
    const TYPE_INFO = 'info';

    public function sender()
    {
        return $this->morphTo();
    }

    public function recipient()
    {
        return $this->morphTo();
    }

    public function scopeInbox($query, $userId, $userType = null)
    {
        return $query->where('recipient_id', $userId)
                     ->where('is_draft', false);
    }

    public function scopeSent($query, $userId, $userType = null)
    {
        return $query->where('sender_id', $userId)
                     ->where('is_draft', false);
    }

    public function scopeDrafts($query, $userId, $userType = null)
    {
        return $query->where('sender_id', $userId)
                     ->where('is_draft', true);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
