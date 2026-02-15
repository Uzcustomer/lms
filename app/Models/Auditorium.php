<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Auditorium extends Model
{
    use HasFactory;

    protected $table = 'auditoriums';

    protected $fillable = [
        'code',
        'name',
        'volume',
        'active',
        'building_id',
        'building_name',
        'auditorium_type_code',
        'auditorium_type_name',
    ];

    protected $casts = [
        'active' => 'boolean',
        'volume' => 'integer',
    ];
}
