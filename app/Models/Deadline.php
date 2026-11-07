<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deadline extends Model
{
    use HasFactory;

    protected $fillable = ['level_code', 'deadline_days', 'retake_by_test_markazi', 'retake_by_oqituvchi'];

    protected $casts = [
        'retake_by_test_markazi' => 'boolean',
        'retake_by_oqituvchi' => 'boolean',
    ];

    /**
     * NB / past bahoni qayta topshirish muddati: dars kunidan keyingi $days kun,
     * yakshanbalar sanalmaydi (dam olish kuni). Shu kun oxirigacha.
     */
    public static function retakeDeadline($lessonDate, int $days): Carbon
    {
        $date = Carbon::parse($lessonDate)->startOfDay();
        for ($left = max(0, $days); $left > 0;) {
            $date->addDay();
            if (!$date->isSunday()) {
                $left--;
            }
        }

        return $date->endOfDay();
    }

    /**
     * Semestr kurs darajasida o'qituvchi otrabotka / sababli bahosini qo'ya oladimi
     * (Sozlamalar → "O'qituvchi"; odatda 4–6 kurs. 1–3 kursda test markazi yuklaydi).
     */
    public static function teacherCanRetake(?string $semesterCode): bool
    {
        $levelCode = $semesterCode
            ? \Illuminate\Support\Facades\DB::table('semesters')->where('code', $semesterCode)->value('level_code')
            : null;

        return $levelCode
            && (bool) static::where('level_code', $levelCode)->value('retake_by_oqituvchi');
    }

    public function level()
    {
        return $this->belongsTo(Student::class, 'level_code', 'level_code');
    }
}