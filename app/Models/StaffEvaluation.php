<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffEvaluation extends Model
{
    protected $fillable = [
        'user_id',
        'student_id',
        'rating',
        'comment',
        'ip_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
