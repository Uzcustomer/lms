<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RetakeWindowSession extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'is_closed',
        'created_by_user_id',
        'created_by_name',
        'closed_at',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public function windows(): HasMany
    {
        return $this->hasMany(RetakeApplicationWindow::class, 'session_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('is_closed', false);
    }

    /**
     * Kanonik sessiya kodi (YYYY-YYYY-fasl) — Moodle quiz nomidagi
     * suffiks bilan moslashtirish uchun. `code` ustuni bo'sh bo'lsa
     * nom matnidan chiqariladi.
     */
    public function resolvedCode(): ?string
    {
        return \App\Services\Retake\RetakeSessionCode::fromSession($this);
    }
}
