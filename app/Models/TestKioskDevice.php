<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestKioskDevice extends Model
{
    /** Kutish ekrani shuncha soniya ichida so'ragan bo'lsa — onlayn. */
    public const ONLINE_SECONDS = 30;

    protected $table = 'test_kiosk_devices';

    protected $fillable = [
        'name',
        'room',
        'token_hash',
        'previous_token_hash',
        'rotated_at',
        'last_used_at',
        'last_seen_at',
        'registered_by_user_id',
        'registered_by_name',
        'revoked_at',
        'revoked_reason',
        'assigned_fan_testi_id',
        'assigned_at',
        'assigned_by_name',
    ];

    protected $hidden = ['token_hash', 'previous_token_hash'];

    protected $casts = [
        'rotated_at' => 'datetime',
        'last_used_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
        'assigned_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subSeconds(self::ONLINE_SECONDS));
    }

    public function assignedTest()
    {
        return $this->belongsTo(FanTesti::class, 'assigned_fan_testi_id');
    }

    /** Xona nomi (bo'sh bo'lsa "Xonasiz") — guruhlash uchun. */
    public function roomLabel(): string
    {
        $room = trim((string) $this->room);

        return $room !== '' ? $room : 'Xonasiz';
    }
}
