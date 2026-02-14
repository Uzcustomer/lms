<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deadline extends Model
{
    use HasFactory;

    protected $fillable = ['level_code', 'deadline_days'];
    public function level()
    {
        return $this->belongsTo(Student::class, 'level_code', 'level_code');
    }
}