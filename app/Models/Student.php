<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Student extends Authenticatable
{
    use Notifiable, HasRoles;

    protected $guard_name = 'web';

    protected $table = 'students';

    protected $fillable = [
        'hemis_id', 'full_name', 'short_name', 'first_name', 'second_name', 'third_name',
        'image', 'student_id_number', 'birth_date', 'avg_gpa', 'avg_grade', 'total_credit',
        'university_code', 'university_name', 'gender_code', 'gender_name',
        'department_id', 'department_name', 'department_code',
        'specialty_id', 'specialty_name', 'specialty_code',
        'group_id', 'group_name', 'education_year_code', 'education_year_name',
        'country_code', 'country_name', 'province_code', 'province_name',
        'district_code', 'district_name', 'terrain_code', 'terrain_name',
        'citizenship_code', 'citizenship_name', 'semester_id', 'semester_code', 'semester_name',
        'level_code', 'level_name', 'education_form_code', 'education_form_name',
        'education_type_code', 'education_type_name', 'payment_form_code', 'payment_form_name',
        'student_type_code', 'student_type_name', 'social_category_code', 'social_category_name',
        'accommodation_code', 'accommodation_name', 'student_status_code', 'student_status_name',
        'curriculum_id', 'hemis_created_at', 'hemis_updated_at', 'hash',
        'token', 'token_expires_at', 'local_password', 'local_password_expires_at', 'must_change_password', 'language_code',
        'language_name',
        'year_of_enter',
        'roommate_count',
        'total_acload',
        'is_graduate',
        'other',
        'phone',
        'telegram_username',
        'telegram_chat_id',
        'telegram_verification_code',
        'telegram_verified_at',
        'login_code',
        'login_code_expires_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'avg_gpa' => 'decimal:2',
        'avg_grade' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'hemis_created_at' => 'datetime',
        'hemis_updated_at' => 'datetime',
        'local_password_expires_at' => 'datetime',
        'must_change_password' => 'boolean',
        'telegram_verified_at' => 'datetime',
        'login_code_expires_at' => 'datetime',
    ];

    protected $hidden = [
        'local_password',
        'token',
        'telegram_verification_code',
        'login_code',
    ];

    public function studentGrades()
    {
        return $this->hasMany(StudentGrade::class, 'student_hemis_id', 'hemis_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_hemis_id');
    }

    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_id', 'curricula_hemis_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_hemis_id');
    }


    public function getAverageGradeForWeek($subjectId, $startDate, $endDate)
    {
        return $this->studentGrades()
            ->where('subject_id', $subjectId)
            ->whereBetween('lesson_date', [$startDate, $endDate])
            ->avg('grade');
    }

//    public function getAverageGradeForWeek($subjectId, $startDate, $endDate)
//    {
//        $grades = $this->studentGrades()
//            ->where('subject_id', $subjectId)
//            ->whereBetween('lesson_date', [$startDate, $endDate])
//            ->get();
//
//        $totalGrade = 0;
//        $gradeCount = 0;
//        $absentDays = 0;
//        $totalLessons = 0;
//
//        foreach ($grades as $gradeRecord) {
//            $totalLessons++;
//            switch ($gradeRecord->status) {
//                case 'recorded':
//                    $totalGrade += $gradeRecord->grade;
//                    $gradeCount++;
//                    break;
//
//                case 'pending':
//                    if ($gradeRecord->reason == 'absent') {
//                        $absentDays++;
//                        $totalGrade += 0;
//                        $gradeCount++;
//                    } elseif ($gradeRecord->reason == 'low_grade') {
//                        $totalGrade += $gradeRecord->grade;
//                        $gradeCount++;
//                    }
//                    break;
//
//                case 'closed':
//                    if ($gradeRecord->reason == 'low_grade') {
//                        $totalGrade += $gradeRecord->grade;
//                        $gradeCount++;
//                    }
//                    break;
//
//                case 'retake':
//                    $totalGrade += $gradeRecord->retake_grade;
//                    $gradeCount++;
//                    break;
//
//                default:
//                    break;
//            }
//        }
//
//        if ($absentDays == $totalLessons && $totalGrade == 0 && $gradeCount == 0) {
//            return 'Nb';
//        }
//
//        if ($gradeCount > 0) {
//            return $totalGrade / $gradeCount;
//        }
//
//        return null;
//    }


//    public function getGradeForDate($subjectId, $date)
//    {
//        return $this->studentGrades()
//            ->where('subject_id', $subjectId)
//            ->whereDate('lesson_date', $date)
//            ->avg('grade');
//    }

// In app/Models/Student.php

    public function getGradeForDate($subjectId, $date): float|string|null
    {
        $grades = $this->studentGrades()
            ->where('subject_id', $subjectId)
            ->where('training_type_code', "<>", 11)
            ->whereDate('lesson_date', $date)
            ->get();

        $totalGrade = 0;
        $gradeCount = 0;
        $absent = 0;

        foreach ($grades as $gradeRecord) {
            switch ($gradeRecord->status) {
                case 'recorded':
                    $totalGrade += $gradeRecord->grade;
                    $gradeCount++;
                    break;

                case 'pending':
                    if ($gradeRecord->reason == 'absent') {
                        $absent++;
                        $totalGrade += 0;
                        $gradeCount++;
                    } elseif ($gradeRecord->reason == 'low_grade') {
                        $totalGrade += $gradeRecord->grade;
                        $gradeCount++;
                    }
                    break;

                case 'closed':
                    if ($gradeRecord->reason == 'low_grade') {
                        $totalGrade += $gradeRecord->grade;
                        $gradeCount++;
                    }
                    break;

                case 'retake':
                    if ($gradeRecord->reason == 'absent') {
                        $absent--;
                    }
                    $totalGrade += $gradeRecord->retake_grade;
                    $gradeCount++;
                    break;

                default:
                    break;
            }
        }

        if ($absent == $gradeCount) {
            return 'Nb';
        }

        if ($gradeCount > 0) {
            return round($totalGrade / $gradeCount, 2);
        }

        return null;
    }

    public function getAverageGradeForSubject($subjectId): float|string|null
    {
        $grades = $this->studentGrades()
            ->where('training_type_code', "<>", 11)
            ->where('subject_id', $subjectId)
            ->get();

        $totalGrade = 0;
        $gradeCount = 0;
        $absentCount = 0;
        $totalLessons = $grades->count();

        foreach ($grades as $gradeRecord) {
            switch ($gradeRecord->status) {
                case 'recorded':
                    $totalGrade += $gradeRecord->grade;
                    $gradeCount++;
                    break;

                case 'pending':
                    if ($gradeRecord->reason == 'absent') {
                        $absentCount++;
                    } elseif ($gradeRecord->reason == 'low_grade') {
                        $totalGrade += $gradeRecord->grade;
                        $gradeCount++;
                    }
                    break;

                case 'closed':
                    if ($gradeRecord->reason == 'low_grade') {
                        $totalGrade += $gradeRecord->grade;
                        $gradeCount++;
                    }
                    break;

                case 'retake':
                    $totalGrade += $gradeRecord->retake_grade;
                    $gradeCount++;
                    break;

                default:
                    break;
            }
        }

        if ($totalLessons > 0 && $absentCount == $totalLessons) {
            return 'Nb';
        }

        if ($gradeCount > 0) {
            return round($totalGrade / $gradeCount, 2);
        }

        return null;
    }

    public function grades()
    {
        return $this->hasMany(StudentGrade::class, 'student_hemis_id', 'hemis_id');
    }

    public function isProfileComplete(): bool
    {
        return !empty($this->phone);
    }

    public function isTelegramVerified(): bool
    {
        return $this->telegram_verified_at !== null;
    }

    public function telegramDaysLeft(): int
    {
        if ($this->isTelegramVerified()) {
            return 0;
        }

        $days = (int) Setting::get('telegram_deadline_days', 7);

        if (!$this->phone) {
            return $days;
        }

        $deadline = $this->updated_at->copy()->addDays($days);
        $daysLeft = (int) now()->diffInDays($deadline, false);

        return max($daysLeft, 0);
    }

    public function isTelegramDeadlinePassed(): bool
    {
        return !$this->isTelegramVerified() && $this->phone && $this->telegramDaysLeft() <= 0;
    }
}
