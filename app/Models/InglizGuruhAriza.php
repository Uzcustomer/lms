<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InglizGuruhAriza extends Model
{
    protected $table = 'ingliz_guruh_arizalar';

    protected $fillable = [
        'student_id',
        'student_hemis_id',
        'full_name',
        'faculty_name',
        'specialty_name',
        'course_name',
        'semester_name',
        'group_name',
        'english_level',
        'certificate_pdf_path',
        'status',
        'admin_note',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
