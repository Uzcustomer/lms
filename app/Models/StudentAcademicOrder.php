<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAcademicOrder extends Model
{
    protected $table = 'student_academic_orders';

    protected $fillable = [
        'student_id',
        'farmoyish_number',
        'farmoyish_date',
        'farmoyish_file_path',
        'farmoyish_file_original_name',
        'qabul_number',
        'qabul_date',
        'qabul_file_path',
        'qabul_file_original_name',
        'updated_by',
    ];

    protected $casts = [
        'farmoyish_date' => 'date',
        'qabul_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
