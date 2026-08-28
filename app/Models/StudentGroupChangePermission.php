<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGroupChangePermission extends Model
{
    protected $fillable = ['student_id', 'enabled', 'enabled_by_id', 'enabled_at'];

    protected $casts = [
        'student_id' => 'integer', 'enabled' => 'boolean',
        'enabled_by_id' => 'integer', 'enabled_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
