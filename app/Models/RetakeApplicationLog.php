<?php

namespace App\Models;

use App\Enums\RetakeLogAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetakeApplicationLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'actor_id',
        'actor_guard',
        'action',
        'note',
        'created_at',
    ];

    protected $casts = [
        'action' => RetakeLogAction::class,
        'created_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(RetakeApplication::class, 'application_id');
    }

    /**
     * Aktor — Teacher yoki User (guard'ga qarab).
     */
    public function actor(): ?Model
    {
        if ($this->actor_id === null) {
            return null;
        }

        return match ($this->actor_guard) {
            'teacher' => Teacher::find($this->actor_id),
            'web' => Teacher::find($this->actor_id) ?? User::find($this->actor_id),
            default => null,
        };
    }
}
