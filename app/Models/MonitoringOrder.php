<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Buyruqlar reyestri (talaba bo'yicha buyruqlar). HEMIS buyruqlari mark'ka
 * sinxronlanmaydi — reyestr Excel/CSV importi orqali to'ldiriladi.
 */
class MonitoringOrder extends Model
{
    protected $fillable = [
        'data_import_id',
        'dedupe_key',
        'hemis_id',
        'order_number',
        'order_date',
        'order_type',
        'order_type_raw',
        'student_hemis_id',
        'student_id_number',
        'full_name',
        'basis',
    ];

    protected $casts = [
        'order_date' => 'date',
    ];

    public static function typeLabels(): array
    {
        return collect(config('monitoring.order_types', []))->map(fn ($t) => $t['label'])->all();
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->order_type] ?? $this->order_type;
    }

    public static function makeDedupeKey(string $number, string $date, $studentHemisId, ?string $idNumber, ?string $fullName): string
    {
        $who = $studentHemisId ?: ($idNumber ?: mb_strtolower(trim((string) $fullName)));

        return sha1(mb_strtolower(trim($number)) . '|' . $date . '|' . $who);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_hemis_id', 'hemis_id');
    }

    public function noticeLogs(): HasMany
    {
        return $this->hasMany(MonitoringNoticeLog::class, 'order_id');
    }
}
