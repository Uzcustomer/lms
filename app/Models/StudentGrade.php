<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class StudentGrade extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected static string $activityModule = 'student_grade';
    protected static array $logOnly = [
        'grade', 'status', 'reason', 'retake_grade', 'retake_graded_at',
        'retake_file_path', 'retake_by', 'graded_by_user_id',
    ];

    protected $fillable = [
        'hemis_id',
        'student_id',
        'student_hemis_id',
        'semester_code',
        'semester_name',
        'education_year_code',
        'education_year_name',
        'subject_schedule_id',
        'subject_id',
        'subject_name',
        'subject_code',
        'training_type_code',
        'training_type_name',
        'employee_id',
        'employee_name',
        'lesson_pair_code',
        'lesson_pair_name',
        'lesson_pair_start_time',
        'lesson_pair_end_time',
        'grade',
        'lesson_date',
        'created_at_api',
        'reason',
        'deadline',
        'status',
        'retake_grade',
        'graded_by_user_id',
        'retake_graded_at',
        'retake_file_path',
        'retake_by',
        'independent_id',
        'oraliq_nazorat_id',
        "oski_id",
        "test_id",
        "quiz_result_id",
        "is_yn_locked",
        "is_final",
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_hemis_id', 'hemis_id');
    }


    public function gradedByUser()
    {
        return $this->belongsTo(User::class, 'graded_by_user_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'employee_id', 'hemis_id');
    }

    public function subject()
    {
        return $this->belongsTo(CurriculumSubject::class, 'subject_id', 'subject_id');
    }
    public function curriculumSubject()
    {
        return CurriculumSubject::where('curricula_hemis_id', $this->student->curriculum_id)
            ->where('subject_id', $this->subject_id)
            ->where('semester_code', $this->semester_code)
            ->first();
    }
    public function curriculum()
    {
        return Curriculum::where('curricula_hemis_id', $this->student->curriculum_id)
            ->first();
    }

    public function getLessonDateTashkentAttribute()
    {
        return Carbon::parse($this->lesson_date)->format('Y-m-d');
    }

    public function oski()
    {
        return $this->hasOne(Oski::class, 'id', 'oski_id');
    }

    public function examTest()
    {
        return $this->hasOne(ExamTest::class, 'id', 'test_id');
    }
}
