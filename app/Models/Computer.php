<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Computer extends Model
{
    protected $fillable = [
        'number',
        'ip_address',
        'mac_address',
        'hostname',
        'label',
        'grid_column',
        'grid_row',
        'active',
        'notes',
    ];

    protected $casts = [
        'number' => 'integer',
        'grid_column' => 'integer',
        'grid_row' => 'integer',
        'active' => 'boolean',
    ];

    public static function findByIp(?string $ip): ?self
    {
        if (!$ip) {
            return null;
        }
        return static::where('ip_address', $ip)->where('active', true)->first();
    }

    public static function numberByIp(?string $ip): ?int
    {
        $c = static::findByIp($ip);
        return $c?->number;
    }
}
