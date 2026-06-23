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
        'phone_number',
        'faculty_name',
        'specialty_name',
        'course_name',
        'semester_name',
        'group_name',
        'english_level',
        'rejection_reason_code',
        'certificate_pdf_path',
        'status',
        'admin_note',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'Qabul qilingan',
            'rejected' => 'Rad etilgan',
            default => 'Kutilmoqda',
        };
    }

    public function getRejectionReasonLabelAttribute(): ?string
    {
        return match ($this->rejection_reason_code) {
            'interview_failed' => "Suhbatdan o'ta olmadi",
            default => null,
        };
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
