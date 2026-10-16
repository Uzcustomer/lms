<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * M8 (soddalashtirilgan): ko'chirib kelgan talaba yig'majildi cheklisti (E-7).
 * `items` — config('monitoring.transfer_documents') kalitlari bo'yicha
 * {bor: bool, asli_tekshirildi: bool, izoh: ?string}.
 */
class MonitoringTransferChecklist extends Model
{
    protected $fillable = [
        'order_id',
        'student_hemis_id',
        'student_id_number',
        'full_name',
        'arrived_at',
        'items',
        'note',
        'completed_at',
        'updated_by_type',
        'updated_by_id',
    ];

    protected $casts = [
        'arrived_at' => 'date',
        'items' => 'array',
        'completed_at' => 'datetime',
    ];

    public static function documents(): array
    {
        return config('monitoring.transfer_documents', []);
    }

    /**
     * Yetishmayotgan hujjatlar: [kalit => sabab] (sabab: 'yoq' yoki 'asli_tekshirilmagan').
     */
    public function missingDocuments(bool $requireOriginal = true): array
    {
        $missing = [];
        foreach (array_keys(self::documents()) as $key) {
            $item = $this->items[$key] ?? [];
            if (empty($item['bor'])) {
                $missing[$key] = 'yoq';
            } elseif ($requireOriginal && empty($item['asli_tekshirildi'])) {
                $missing[$key] = 'asli_tekshirilmagan';
            }
        }

        return $missing;
    }

    public function isComplete(bool $requireOriginal = true): bool
    {
        return $this->missingDocuments($requireOriginal) === [];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MonitoringOrder::class, 'order_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_hemis_id', 'hemis_id');
    }

    public function updatedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
