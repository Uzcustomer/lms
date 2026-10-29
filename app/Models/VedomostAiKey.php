<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Vedomost AI tekshiruvi uchun Gemini API kaliti.
 *
 * Kalit bazada shifrlangan (APP_KEY bilan) saqlanadi va hech qachon to'liq
 * ko'rsatilmaydi — sahifada faqat key_hint (oxirgi 4 belgi) chiqadi.
 */
class VedomostAiKey extends Model
{
    protected $fillable = [
        'label',
        'api_key',
        'key_hint',
        'is_paid',
        'enabled',
        'rpm',
        'daily_limit',
        'last_used_at',
        'last_error',
        'last_error_at',
        'created_by',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'api_key' => 'encrypted',
        'is_paid' => 'boolean',
        'enabled' => 'boolean',
        'rpm' => 'integer',
        'daily_limit' => 'integer',
        'last_used_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public static function hintFor(string $key): string
    {
        return '…' . substr($key, -4);
    }

    public function requests()
    {
        return $this->hasMany(VedomostAiRequest::class);
    }
}
