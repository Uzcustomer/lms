<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableCardOverride extends Model
{
    protected $fillable = [
        'card_id', 'week', 'day', 'pair', 'cancelled',
    ];

    protected $casts = [
        'cancelled' => 'boolean',
    ];

    public function card()
    {
        return $this->belongsTo(TimetableCard::class, 'card_id');
    }
}
