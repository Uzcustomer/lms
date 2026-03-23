<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPassport extends Model
{
    protected $table = 'graduate_student_passports';

    protected $fillable = [
        'student_id',
        'full_name_uz',
        'full_name_en',
        'passport_series',
        'passport_number',
        'passport_front_path',
        'passport_back_path',
        'foreign_passport_path',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
