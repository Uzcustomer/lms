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
        'kiosk_stage',
        'kiosk_stage_at',
        'current_attempt_id',
        'kiosk_answered',
        'pending_command',
        'pending_attempt_id',
        'released_fan_testi_id',
        'released_attempt_id',
        'face_bypass_at',
        'face_bypass_by',
    ];

    /** O'qituvchining yuz tekshiruvisiz kirish ruxsati shuncha daqiqa amal qiladi (ishlatilmasa). */
    public const FACE_BYPASS_MINUTES = 30;

    protected $hidden = ['token_hash', 'previous_token_hash'];

    protected $casts = [
        'rotated_at' => 'datetime',
        'last_used_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
        'assigned_at' => 'datetime',
        'kiosk_stage_at' => 'datetime',
        'face_bypass_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /** Shu kompyuterda bir martalik yuz tekshiruvisiz kirish ruxsati hozir faolmi */
    public function hasFaceBypass(): bool
    {
        return $this->face_bypass_at !== null
            && $this->face_bypass_at->gt(now()->subMinutes(self::FACE_BYPASS_MINUTES));
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
