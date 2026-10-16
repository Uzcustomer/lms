<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentRating;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "5 ga da'vogar" talabalar (students.is_five_candidate) va ularning baholari:
 * talabalar reytingi sahifasidagi ro'yxat va Excel eksporti uchun.
 */
class FiveCandidateGrades
{
    /** Kunlik bo'lmagan baho turlari (student_grades.training_type_code). */
    public const TYPE_NAMES = [99 => 'MT', 100 => 'ON', 101 => 'OSKI', 102 => 'Test', 103 => 'Quiz'];

    /** Ma'ruza — baho qo'yilmaydi. */
    private const LECTURE = 11;

    /** Sukut davr: joriy o'quv semestri boshidan (kuz — 1-sentabr, bahor — 1-fevral) bugungacha. */
    public static function defaultPeriod(): array
    {
        $now = now('Asia/Tashkent');
        $from = in_array($now->month, [2, 3, 4, 5, 6, 7, 8], true)
            ? Carbon::create($now->year, 2, 1)
            : Carbon::create($now->month === 1 ? $now->year - 1 : $now->year, 9, 1);

        return [$from->toDateString(), $now->toDateString()];
    }

    /** Da'vogar talabalar (chetlashtirilganlarsiz), fakultet / guruh / ism bo'yicha. */
    public static function students(?string $faculty = null): Collection
    {
        return Student::query()
            ->where('is_five_candidate', true)
            ->where(fn ($q) => $q->whereNull('student_status_code')->orWhere('student_status_code', '!=', '60'))
            ->when($faculty, fn ($q) => $q->where('department_name', $faculty))
            ->orderBy('department_name')->orderBy('level_code')->orderBy('group_name')->orderBy('full_name')
            ->get(['hemis_id', 'full_name', 'student_id_number', 'group_name', 'department_name', 'specialty_name',
                'level_name', 'semester_name', 'semester_code']);
    }

    /** Davrdagi baholar (ma'ruzadan tashqari barcha turlar), student_hemis_id bo'yicha guruhlangan. */
    public static function grades(array $hemisIds, string $from, string $to): Collection
    {
        if (!$hemisIds) {
            return collect();
        }
        $fromTs = $from . ' 00:00:00';
        $toTs = $to . ' 23:59:59';

        return DB::table('student_grades')
            ->whereIn('student_hemis_id', $hemisIds)
            ->whereNull('deleted_at')
            ->where('training_type_code', '!=', self::LECTURE)
            ->where(function ($q) use ($fromTs, $toTs) {
                $q->whereBetween('lesson_date', [$fromTs, $toTs])
                    ->orWhere(fn ($q) => $q->whereNull('lesson_date')->whereBetween('created_at', [$fromTs, $toTs]));
            })
            ->orderBy('subject_name')->orderBy('lesson_date')->orderBy('lesson_pair_code')
            ->get(['student_hemis_id', 'subject_id', 'subject_name', 'semester_name', 'training_type_code',
                'training_type_name', 'lesson_date', 'created_at', 'lesson_pair_name', 'grade', 'retake_grade',
                'status', 'reason', 'employee_name', 'attempt'])
            ->groupBy('student_hemis_id');
    }

    /** Baho turi nomi: MT / ON / OSKI / Test yoki kunlik dars turi (Amaliy, Seminar...). */
    public static function typeName($g): string
    {
        return self::TYPE_NAMES[(int) $g->training_type_code] ?? ($g->training_type_name ?: 'Joriy');
    }

    public static function isDaily($g): bool
    {
        return !isset(self::TYPE_NAMES[(int) $g->training_type_code]);
    }

    /** Ko'rsatiladigan baho: qayta topshirilgan bo'lsa — yangi baho; sababsiz kelmagan — "NB". */
    public static function value($g)
    {
        if ($g->status === 'retake' && $g->retake_grade !== null) {
            return (float) $g->retake_grade;
        }
        if ($g->status === 'pending' && $g->reason === 'absent') {
            return 'NB';
        }

        return $g->grade !== null ? (float) $g->grade : '';
    }

    public static function note($g): string
    {
        $parts = [];
        if ($g->status === 'retake') {
            $parts[] = 'qayta topshirgan' . ($g->grade !== null ? ' (avval: ' . (float) $g->grade . ')' : '');
        } elseif ($g->status === 'pending') {
            $parts[] = $g->reason === 'absent' ? 'kelmagan' : 'kutilmoqda';
        }
        if ((int) ($g->attempt ?? 1) > 1) {
            $parts[] = $g->attempt . '-urinish';
        }

        return implode(', ', $parts);
    }

    /**
     * Fanlar bo'yicha joriy (kunlik) o'rtacha — reyting bilan bir xil usul: har kunning
     * o'rtachasi, so'ng kunlar o'rtachasi (shu kun hammasi "NB" bo'lsa — 0).
     * @return list<array{name:string, average:float, days:int}>
     */
    public static function subjectAverages(Collection $grades): array
    {
        $out = [];
        foreach ($grades->filter(fn ($g) => self::isDaily($g))->groupBy('subject_id') as $rows) {
            $total = 0;
            $days = 0;
            foreach ($rows->groupBy(fn ($g) => substr((string) $g->lesson_date, 0, 10)) as $day) {
                $sum = 0;
                $absent = 0;
                foreach ($day as $g) {
                    $v = self::value($g);
                    $v === 'NB' ? $absent++ : $sum += (float) $v;
                }
                $total += $absent === $day->count() ? 0 : round($sum / $day->count());
                $days++;
            }
            $out[] = ['name' => (string) $rows->first()->subject_name, 'average' => $days ? round($total / $days, 1) : 0, 'days' => $days];
        }
        usort($out, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * Fan bo'yicha tafsilot (accordion uchun): o'rtacha, kunlik baholar sanasi bilan va
     * boshqa turdagi baholar (MT, ON, OSKI, Test).
     * @return list<array{name:string, average:float, days:int, daily:list<array>, others:list<array>}>
     */
    public static function subjectDetails(Collection $grades): array
    {
        $avg = collect(self::subjectAverages($grades))->keyBy('name');
        $out = [];
        foreach ($grades->groupBy('subject_name') as $name => $rows) {
            $daily = [];
            $others = [];
            foreach ($rows as $g) {
                $date = $g->lesson_date ?: $g->created_at;
                $item = [
                    'date' => $date ? date('d.m', strtotime($date)) : '',
                    'full_date' => $date ? date('d.m.Y', strtotime($date)) : '',
                    'value' => self::value($g),
                    'type' => self::typeName($g),
                    'note' => self::note($g),
                ];
                self::isDaily($g) ? $daily[] = $item : $others[] = $item;
            }
            $a = $avg->get($name);
            $out[] = ['name' => (string) $name, 'average' => $a['average'] ?? null, 'days' => $a['days'] ?? 0,
                'daily' => $daily, 'others' => $others];
        }
        usort($out, fn ($x, $y) => strcmp($x['name'], $y['name']));

        return $out;
    }

    /** student_hemis_id => JN reyting o'rtachasi (student_ratings). */
    public static function ratings(array $hemisIds): array
    {
        return $hemisIds
            ? StudentRating::whereIn('student_hemis_id', $hemisIds)->pluck('jn_average', 'student_hemis_id')->all()
            : [];
    }
}
