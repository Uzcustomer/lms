<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deadline extends Model
{
    use HasFactory;

    protected $fillable = ['level_code', 'deadline_days', 'retake_by_test_markazi', 'retake_by_oqituvchi'];

    protected $casts = [
        'retake_by_test_markazi' => 'boolean',
        'retake_by_oqituvchi' => 'boolean',
    ];

    public function level()
    {
        return $this->belongsTo(Student::class, 'level_code', 'level_code');
    }
}