<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Monitoring manba ma'lumotlari importi (buyruqlar, tavsiya etilganlar): kim, qachon, nechta qator, xatolar.
 */
class MonitoringDataImport extends Model
{
    public const KIND_ORDERS = 'buyruqlar';
    public const KIND_RECOMMENDATIONS = 'tavsiya_etilganlar';

    public const KINDS = [
        self::KIND_ORDERS => 'Buyruqlar reyestri',
        self::KIND_RECOMMENDATIONS => "Kontrakt bo'yicha tavsiya etilganlar (mandat)",
    ];

    public const STATUS_PENDING = 'kutilmoqda';
    public const STATUS_DONE = 'bajarildi';
    public const STATUS_ERROR = 'xato';

    protected $fillable = [
        'kind',
        'source',
        'status',
        'file_name',
        'file_path',
        'evidence_path',
        'rows_total',
        'rows_ok',
        'rows_error',
        'errors',
        'meta',
        'imported_by_type',
        'imported_by_id',
    ];

    protected $casts = [
        'errors' => 'array',
        'meta' => 'array',
    ];

    public function importedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
