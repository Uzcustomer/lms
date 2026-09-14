<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PresenceLog extends Model
{
    protected $fillable = ['student_id', 'beacon_id', 'rssi', 'seen_at', 'source'];

    protected $casts = ['seen_at' => 'datetime', 'rssi' => 'integer'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function beacon()
    {
        return $this->belongsTo(Beacon::class);
    }
}
