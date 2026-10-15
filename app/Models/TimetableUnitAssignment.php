<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Dars birligining o'qituvchi/xona biriktirishi (kartalardan alohida saqlanadi). */
class TimetableUnitAssignment extends Model
{
    protected $fillable = [
        'board_id', 'unit_key', 'faculty_name', 'specialty_name', 'course', 'subject_name',
        'training_type', 'scope_label', 'teacher_id', 'teacher_name', 'auditorium_code',
        'auditorium_name', 'updated_by',
    ];
}
