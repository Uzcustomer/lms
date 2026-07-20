<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContingentProjection extends Model
{
    protected $fillable = [
        'academic_year',
        'specialty_code',
        'specialty_name',
        'level_code',
        'lang',
        'department_id',
        'department_name',
        'expected_count',
        'updated_by',
    ];
}
