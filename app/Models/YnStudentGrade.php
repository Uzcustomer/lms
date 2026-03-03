<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YnStudentGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'yn_submission_id',
        'student_hemis_id',
        'jn',
        'mt',
        'source',
    ];

    public function ynSubmission()
    {
        return $this->belongsTo(YnSubmission::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_hemis_id', 'hemis_id');
    }

    /**
     * Har bir talaba uchun eng oxirgi snapshotni olish
     */
    public function scopeLatestPerStudent($query, $ynSubmissionId)
    {
        return $query->where('yn_submission_id', $ynSubmissionId)
            ->whereIn('id', function ($sub) use ($ynSubmissionId) {
                $sub->selectRaw('MAX(id)')
                    ->from('yn_student_grades')
                    ->where('yn_submission_id', $ynSubmissionId)
                    ->groupBy('student_hemis_id');
            });
    }
}
