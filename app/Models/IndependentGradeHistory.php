<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndependentGradeHistory extends Model
{
    use HasFactory;

    protected $table = 'independent_grade_history';

    protected $fillable = [
        'independent_id',
        'student_id',
        'student_hemis_id',
        'grade',
        'submission_number',
        'graded_by',
        'graded_at',
    ];

    protected $casts = [
        'graded_at' => 'datetime',
    ];

    public function independent()
    {
        return $this->belongsTo(Independent::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
