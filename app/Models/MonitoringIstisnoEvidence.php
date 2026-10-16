<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Istisnoga biriktirilgan dalil fayli (buyruq nusxasi, xat, skan). Fayl private diskda.
 */
class MonitoringIstisnoEvidence extends Model
{
    protected $table = 'monitoring_istisno_evidences';

    protected $fillable = [
        'istisno_id',
        'comment_id',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by_type',
        'uploaded_by_id',
    ];

    public function istisno(): BelongsTo
    {
        return $this->belongsTo(MonitoringIstisno::class, 'istisno_id');
    }

    public function uploadedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
