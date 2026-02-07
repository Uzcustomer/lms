<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'teacher_id',
        'teacher_name',
        'group_id',
        'group_name',
        'student_hemis_id',
        'student_name',
        'semester_name',
        'subject_name',
        'type',
        'schedule_info',
        'file_path',
        'file_original_name',
        'training_type_name',
        'training_type_code',
        'student_grade_ids',
        'student_hemis_ids'

    ];

    protected $casts = [
        'schedule_info' => 'array',
        'student_grade_ids' => 'array',
        'student_hemis_ids' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }



    public function getTargetNameAttribute()
    {
        return $this->type === 'group' ? $this->group_name : $this->student_name;
    }

    public function getScheduleDatesAttribute()
    {
        return collect($this->schedule_info)->pluck('lesson_date')
            ->map(function($date) {
                return format_date($date);
            })
            ->join(', ');
    }

    public function getLessonPairsAttribute()
    {
        return collect($this->schedule_info)->pluck('lesson_pair_name')->join(', ');
    }

    public function getFileNameAttribute()
    {
        return $this->file_original_name ?? 'Fayl yuklanmagan';
    }

    public static function createFromSchedule($request, $schedule, $filePath = null, $fileOriginalName = null,$studentGradeIds = [])
    {

        $student = null;
        $type = 'group';

        return self::create([
            'user_id' => auth()->id(),
            'teacher_id' => $schedule->employee_id,
            'teacher_name' => $schedule->employee_name,
            'group_id' => $schedule->group_id,
            'group_name' => $schedule->group_name,
            'student_hemis_id' =>  null,
            'student_name' => null,
            'semester_name' => $schedule->semester_name,
            'subject_name' => $schedule->subject_name,
            'training_type_name' => $schedule->training_type_name,
            'training_type_code' => $schedule->training_type_code,
            'type' => $type,
            'schedule_info' => collect($request->schedule_hemis_ids)->map(function($scheduleId) {
                $schedule = Schedule::where('schedule_hemis_id', $scheduleId)->first();
                return [
                    'schedule_hemis_id' => $scheduleId,
                    'lesson_date' => $schedule->lesson_date,
                    'lesson_pair_name' => $schedule->lesson_pair_name,
                    'lesson_pair_start_time' => $schedule->lesson_pair_start_time,
                    'lesson_pair_end_time' => $schedule->lesson_pair_end_time,
                ];
            })->toArray(),
            'file_path' => $filePath,
            'file_original_name' => $fileOriginalName,
            'student_grade_ids' => $studentGradeIds,
            'student_hemis_ids' => $request->student_hemis_ids ?? [],
        ]);

    }

    public function getStudentsAttribute()
    {
        if (!empty($this->student_hemis_ids)) {
            return Student::whereIn('hemis_id', $this->student_hemis_ids)
                ->select('full_name', 'student_id_number')
                ->get();
        }

        return Student::whereIn('hemis_id',
            StudentGrade::whereIn('id', $this->student_grade_ids)
                ->pluck('student_hemis_id')
                ->unique()
        )
            ->select('full_name', 'student_id_number')
            ->get();
    }
}
