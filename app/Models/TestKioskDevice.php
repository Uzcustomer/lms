<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestKioskDevice extends Model
{
    protected $table = 'test_kiosk_devices';

    protected $fillable = [
        'name',
        'token_hash',
        'previous_token_hash',
        'rotated_at',
        'last_used_at',
        'registered_by_user_id',
        'registered_by_name',
        'revoked_at',
        'revoked_reason',
    ];

    protected $hidden = ['token_hash', 'previous_token_hash'];

    protected $casts = [
        'rotated_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
