<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableBoard extends Model
{
    protected $fillable = [
        'name', 'academic_year', 'semester_parity', 'kind',
        'faculty_id', 'faculty_name', 'days', 'pairs_per_day', 'weeks',
        'status', 'created_by',
    ];

    public function cards()
    {
        return $this->hasMany(TimetableCard::class, 'board_id');
    }
}
