<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OskiStudent extends Model
{
    use HasFactory;
    protected $fillable = [
        'oski_id',
        'student_hemis_id',
        'file_path',
        'file_original_name',
    ];
}