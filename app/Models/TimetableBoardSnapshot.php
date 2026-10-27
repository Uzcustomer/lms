<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableBoardSnapshot extends Model
{
    protected $fillable = ['board_id', 'reason', 'created_by_name', 'cards_count', 'placed_count', 'overrides_count', 'payload'];

    protected $hidden = ['payload'];

    /** ['cards' => [...], 'overrides' => [...], 'cycle_placements' => [...]] */
    public function data(): array
    {
        $raw = base64_decode((string) $this->payload, true);
        $json = $raw !== false ? @gzdecode($raw) : false;

        return $json !== false ? (array) json_decode($json, true) : ['cards' => [], 'overrides' => [], 'cycle_placements' => []];
    }

    public function board()
    {
        return $this->belongsTo(TimetableBoard::class, 'board_id');
    }
}
