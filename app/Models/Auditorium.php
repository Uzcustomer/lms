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
        'department_hemis_id',
        'department_name',
        'created_by_teacher_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'volume' => 'integer',
        'department_hemis_id' => 'integer',
        'created_by_teacher_id' => 'integer',
    ];
}
