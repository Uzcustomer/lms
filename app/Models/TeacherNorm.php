<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherNorm extends Model
{
    protected $fillable = ['position', 'annual_hours', 'sort'];
}
