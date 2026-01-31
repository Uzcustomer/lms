<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OskiHistory extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'file_path',
        'file_original_name',
        'errors',
    ];
}