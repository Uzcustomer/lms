<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamHistory extends Model
{
    protected $fillable = [
        'user_id',
        'file_path',
        'file_original_name',
        'errors',
    ];
}