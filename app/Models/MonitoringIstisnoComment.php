<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Istisno tarixi: mas'ul javobi, administrator izohi, holat o'zgarishi, tizim yozuvi.
 */
class MonitoringIstisnoComment extends Model
{
    public const KIND_JAVOB = 'javob';
    public const KIND_IZOH = 'izoh';
    public const KIND_HOLAT = 'holat';
    public const KIND_TIZIM = 'tizim';

    protected $fillable = [
        'istisno_id',
        'author_type',
        'author_id',
        'kind',
        'body',
        'old_status',
        'new_status',
    ];

    public function istisno(): BelongsTo
    {
        return $this->belongsTo(MonitoringIstisno::class, 'istisno_id');
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(MonitoringIstisnoEvidence::class, 'comment_id');
    }

    public function authorName(): string
    {
        if ($this->author_type === null) {
            return 'Tizim';
        }

        $author = $this->author;

        return $author?->full_name ?? $author?->name ?? 'Noma\'lum';
    }
}
