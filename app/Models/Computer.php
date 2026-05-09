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
        'is_reserve_pool',
        'notes',
    ];

    protected $casts = [
        'number' => 'integer',
        'grid_column' => 'integer',
        'grid_row' => 'integer',
        'active' => 'boolean',
        'is_reserve_pool' => 'boolean',
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
        if (!$ip) {
            return null;
        }
        $row = static::findByIp($ip);
        if ($row) {
            return $row->number;
        }
        // Fallback: derive from configured prefix/offset (e.g. 196.168.7.103 → 3)
        $prefix = (string) config('services.moodle.computer_ip_prefix', '196.168.7.');
        $offset = (int) config('services.moodle.computer_ip_offset', 100);
        if ($prefix !== '' && str_starts_with($ip, $prefix)) {
            $tail = substr($ip, strlen($prefix));
            if (ctype_digit($tail)) {
                $n = (int) $tail - $offset;
                if ($n >= 1) {
                    return $n;
                }
            }
        }
        return null;
    }

    /**
     * Numbers of computers that are part of the reserve pool. The pool is
     * either explicitly flagged (is_reserve_pool=true) or derived as the
     * last N active computers when no explicit flags exist.
     *
     * @return int[]
     */
    public static function reservePoolNumbers(): array
    {
        $explicit = static::where('active', true)
            ->where('is_reserve_pool', true)
            ->pluck('number')
            ->all();
        if (!empty($explicit)) {
            return array_map('intval', $explicit);
        }
        $count = max(0, (int) config('services.moodle.reserve_computers_count', 5));
        if ($count === 0) {
            return [];
        }
        return static::where('active', true)
            ->orderByDesc('number')
            ->limit($count)
            ->pluck('number')
            ->map(fn($n) => (int) $n)
            ->all();
    }
}
