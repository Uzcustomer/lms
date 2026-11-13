<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableCardOverride extends Model
{
    protected $fillable = [
        'card_id', 'week', 'day', 'pair', 'start_half', 'cancelled', 'unplaced',
        'auditorium_code', 'auditorium_name', 'mudir_placeholder',
    ];

    protected $casts = [
        'cancelled' => 'boolean',
        // cancelled bilan birga: shu hafta joylanmagan, lekin o'tiladi ("Joylashmagan kartalar" panelida; "o'tilmaydi" emas).
        'unplaced' => 'boolean',
        // "Vaqtincha kafedra mudirida" (shu hafta): mudir, asl xona va o'qituvchi, sabab, kim/qachon.
        'mudir_placeholder' => 'array',
    ];

    public function card()
    {
        return $this->belongsTo(TimetableCard::class, 'card_id');
    }
}
