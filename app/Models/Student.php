<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Student extends Authenticatable
{
    use Notifiable, HasRoles, HasApiTokens;

    protected $guard_name = 'web';

    protected $table = 'students';

    protected $fillable = [
        'hemis_id', 'full_name', 'short_name', 'first_name', 'second_name', 'third_name',
        'image', 'student_id_number', 'passport_number', 'passport_serial', 'birth_date', 'avg_gpa', 'avg_grade', 'total_credit',
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
        'exam_language_code',
        'year_of_enter',
        'roommate_count',
        'total_acload',
        'is_graduate',
        'is_five_candidate',
        'other',
        'phone',
        'phone_added_at',
        'telegram_username',
        'telegram_chat_id',
        'telegram_verification_code',
        'telegram_verified_at',
        'login_code',
        'login_code_expires_at',
        'face_id_enabled',
        'is_starosta',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'avg_gpa' => 'decimal:2',
        'avg_grade' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'hemis_created_at' => 'datetime',
        'hemis_updated_at' => 'datetime',
        'local_password' => 'hashed',
        'local_password_expires_at' => 'datetime',
        'must_change_password' => 'boolean',
        'is_five_candidate' => 'boolean',
        'telegram_verified_at' => 'datetime',
        'phone_added_at' => 'datetime',
        'login_code_expires_at' => 'datetime',
        'face_id_enabled'       => 'boolean',
        'is_starosta'           => 'boolean',
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

    public function groupHistory()
    {
        return $this->hasMany(StudentGroupHistory::class, 'student_id')
            ->orderByDesc('started_at');
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
        $educationYearCode = $this->education_year_code;
        return $this->studentGrades()
            ->where('subject_id', $subjectId)
            ->where('semester_code', $this->semester_code)
            ->whereBetween('lesson_date', [$startDate, $endDate])
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhereNull('education_year_code');
            }))
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
        $educationYearCode = $this->education_year_code;
        $grades = $this->studentGrades()
            ->where('subject_id', $subjectId)
            ->where('semester_code', $this->semester_code)
            ->where('training_type_code', "<>", 11)
            ->whereDate('lesson_date', $date)
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhereNull('education_year_code');
            }))
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
        $educationYearCode = $this->education_year_code;
        $grades = $this->studentGrades()
            ->where('training_type_code', "<>", 11)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $this->semester_code)
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhereNull('education_year_code');
            }))
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

    public function visaInfo()
    {
        return $this->hasOne(\App\Models\StudentVisaInfo::class);
    }

    public function disabilityInfo()
    {
        return $this->hasOne(\App\Models\StudentDisabilityInfo::class);
    }

    /**
     * Qayta topshirish (otrabotka) xizmati faqat 1–3-kurslarga. level_code: 11 = 1-kurs … 16 = 6-kurs.
     */
    public function hasOtrabotka(): bool
    {
        $level = (int) $this->level_code;
        $course = $level >= 11 ? $level - 10 : $level;

        return $course >= 1 && $course <= 3;
    }

    public function isDisabled(): bool
    {
        return !empty($this->social_category_name)
            && stripos($this->social_category_name, 'nogiron') !== false;
    }

    public function faceDescriptor()
    {
        return $this->hasOne(\App\Models\FaceIdDescriptor::class);
    }

    public function files()
    {
        return $this->hasMany(\App\Models\StudentFile::class);
    }

    public function admissionData()
    {
        return $this->hasOne(\App\Models\StudentAdmissionData::class);
    }

    public function isProfileComplete(): bool
    {
        return !empty($this->phone);
    }

    /** Xalqaro ta'lim fakulteti talabasimi (fakultet nomi bo'yicha, uch tilda). */
    public function isInternationalFaculty(): bool
    {
        $faculty = mb_strtolower((string) ($this->department_name ?? ''));

        return str_contains($faculty, 'xalqaro')
            || str_contains($faculty, 'international')
            || str_contains($faculty, 'международ');
    }

    /** PIMU o'quv rejalarida shu yildan boshlab qabul qilinganlar — 100 ballik tizim. */
    public const PIMU_HUNDRED_POINT_FROM_YEAR = 2026;

    /**
     * Test markazi natijasi jurnalga 5 ballik (2–5) tizimda yoziladimi. Faqat Xalqaro ta'lim
     * fakultetining PIMU o'quv rejalari (nomida «PIMU»: pd/, pp/, pst/), 2026-yilgacha qabul
     * qilinganlar. Xorijiy talabalar (xd/, General medicine) va PIMU 2026+ qabuli — 100 ballik.
     * Qabul yili — o'quv reja nomidagi birinchi yil («Davolash ishi 2025/2026(PIMU)» → 2025).
     */
    public function usesFivePointScale(): bool
    {
        if (!$this->isInternationalFaculty()) {
            return false;
        }

        static $byGroup = [];
        $groupId = (string) $this->group_id;
        if (!array_key_exists($groupId, $byGroup)) {
            $name = (string) DB::table('groups as g')
                ->join('curricula as c', 'c.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
                ->where('g.group_hemis_id', $groupId)
                ->value('c.name');
            $byGroup[$groupId] = mb_stripos($name, 'pimu') !== false
                && preg_match('/(20\d{2})/', $name, $m)
                && (int) $m[1] < self::PIMU_HUNDRED_POINT_FROM_YEAR;
        }

        return $byGroup[$groupId];
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

        $start = $this->phone_added_at ?? $this->updated_at;
        $deadline = $start->copy()->addDays($days);
        $daysLeft = (int) now()->diffInDays($deadline, false);

        return max($daysLeft, 0);
    }

    public function isTelegramDeadlinePassed(): bool
    {
        return !$this->isTelegramVerified() && $this->phone && $this->telegramDaysLeft() <= 0;
    }
}
