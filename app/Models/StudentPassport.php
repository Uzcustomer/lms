<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPassport extends Model
{
    protected $table = 'graduate_student_passports';

    protected $fillable = [
        'student_id',
        'first_name',
        'last_name',
        'father_name',
        'full_name_uz',
        'full_name_en',
        'passport_series',
        'passport_number',
        'jshshir',
        'passport_front_path',
        'passport_back_path',
        'foreign_passport_path',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
