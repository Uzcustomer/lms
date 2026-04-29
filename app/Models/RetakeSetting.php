<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetakeSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
        'updated_by_user_id',
        'updated_by_name',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(Teacher::class, 'updated_by_user_id');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::where('key', $key)->first();
        return $row?->value ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int) static::get($key, $default);
    }

    public static function getDecimal(string $key, float $default = 0.0): float
    {
        return (float) static::get($key, $default);
    }

    public static function set(string $key, string $value, ?int $userId = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by_user_id' => $userId]
        );
    }

    public static function creditPrice(): float
    {
        return static::getDecimal('credit_price', 175000);
    }

    public static function minGroupSize(): int
    {
        return static::getInt('min_group_size', 1);
    }

    public static function receiptMaxMb(): int
    {
        return static::getInt('receipt_max_mb', 5);
    }

    public static function rejectReasonMinLength(): int
    {
        return static::getInt('reject_reason_min_length', 10);
    }
}
