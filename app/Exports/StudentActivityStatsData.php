<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "LMS ga kirishlar" hisobotining so'rovlari: tanlangan oraliqdagi talaba
 * kirishlari kunlik, haftalik va oylik kesimda, fakultet va kurs bo'yicha.
 *
 * Kirish — activity_logs dagi guard='student', action='login' yozuvi (web va
 * mobil ilova ikkalasi ham shu yerga yozadi). Har kirish alohida sanaladi,
 * noyob talabalar soni ham beriladi.
 *
 * Faqat o'qiyotgan talabalar (student_status_code = 11): chetlashgan yoki
 * ta'tildagi talabaning kirishi hisobga olinmaydi, ammo faol talabalar soni
 * foiz uchun maxraj bo'ladi.
 */
class StudentActivityStatsData
{
    private const ACTIVE_STATUS = '11';

    /** @var list<object{faculty:string, course:string, day:string, student_id:int}> */
    private ?array $logins = null;

    /** @var array<string, int> "fakultet|kurs" => faol talabalar soni */
    private ?array $activeStudents = null;

    public function __construct(private string $from, private string $to)
    {
    }

    /** Kunlik: fakultet + kurs + kun */
    public function daily(): array
    {
        return $this->aggregate(fn (string $day) => [$day, Carbon::parse($day)->format('d.m.Y')]);
    }

    /** Haftalik: fakultet + kurs + hafta (dushanbadan boshlanadi) */
    public function weekly(): array
    {
        return $this->aggregate(function (string $day) {
            $d = Carbon::parse($day);
            $start = $d->copy()->startOfWeek(Carbon::MONDAY);
            $end = $d->copy()->endOfWeek(Carbon::SUNDAY);

            return [$start->toDateString(), $start->format('d.m').' – '.$end->format('d.m.Y')];
        });
    }

    /** Oylik: fakultet + kurs + oy */
    public function monthly(): array
    {
        return $this->aggregate(function (string $day) {
            $d = Carbon::parse($day);

            return [$d->format('Y-m'), self::MONTHS[(int) $d->format('n')].' '.$d->format('Y')];
        });
    }

    private const MONTHS = [1 => 'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'];

    /**
     * Umumiy yig'uvchi. $period kunni [tartib kaliti, ko'rsatiladigan yorliq]
     * juftligiga aylantiradi; natija fakultet → kurs → davr bo'yicha saralanadi.
     *
     * @param  callable(string): array{0: string, 1: string}  $period
     * @return list<array<string, mixed>>
     */
    private function aggregate(callable $period): array
    {
        $acc = [];
        foreach ($this->logins() as $row) {
            [$sort, $label] = $period($row->day);
            $key = $row->faculty.'|'.$row->course.'|'.$sort;
            $acc[$key] ??= [
                'faculty' => $row->faculty,
                'course' => $row->course,
                'period_sort' => $sort,
                'period' => $label,
                'logins' => 0,
                'students' => [],
            ];
            $acc[$key]['logins']++;
            $acc[$key]['students'][$row->student_id] = true;
        }

        uksort($acc, fn ($a, $b) => strcmp($a, $b));

        $active = $this->activeStudents();
        $rows = [];
        foreach ($acc as $a) {
            $total = $active[$a['faculty'].'|'.$a['course']] ?? 0;
            $unique = count($a['students']);
            $rows[] = [
                'faculty' => $a['faculty'],
                'course' => $a['course'],
                'period' => $a['period'],
                'logins' => $a['logins'],
                'unique_students' => $unique,
                'active_students' => $total,
                'percent' => $total > 0 ? round($unique * 100 / $total, 1) : 0,
            ];
        }

        return $rows;
    }

    /**
     * Oraliqdagi barcha talaba kirishlari, fakultet va kurs bilan. Bir marta
     * o'qiladi, uch varaq shundan hisoblanadi.
     *
     * @return list<object>
     */
    private function logins(): array
    {
        if ($this->logins !== null) {
            return $this->logins;
        }

        $rows = [];
        DB::table('activity_logs as l')
            ->join('students as st', 'st.id', '=', 'l.user_id')
            ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'st.group_id')
            ->where('l.guard', 'student')
            ->where('l.action', 'login')
            ->whereBetween('l.created_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])
            ->where('st.student_status_code', self::ACTIVE_STATUS)
            ->select('l.user_id as student_id', 'l.created_at', 'g.department_name', 'st.level_name')
            ->orderBy('l.created_at')
            ->cursor()
            ->each(function ($r) use (&$rows) {
                $rows[] = (object) [
                    'student_id' => (int) $r->student_id,
                    'day' => substr((string) $r->created_at, 0, 10),
                    'faculty' => trim((string) ($r->department_name ?? '')) ?: "Noma'lum",
                    'course' => trim((string) ($r->level_name ?? '')) ?: "Noma'lum",
                ];
            });

        return $this->logins = $rows;
    }

    /**
     * Fakultet + kurs bo'yicha faol talabalar soni — foiz uchun maxraj.
     *
     * @return array<string, int>
     */
    private function activeStudents(): array
    {
        if ($this->activeStudents !== null) {
            return $this->activeStudents;
        }

        $out = [];
        DB::table('students as st')
            ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'st.group_id')
            ->where('st.student_status_code', self::ACTIVE_STATUS)
            ->groupBy('g.department_name', 'st.level_name')
            ->select('g.department_name', 'st.level_name', DB::raw('COUNT(*) as cnt'))
            ->get()
            ->each(function ($r) use (&$out) {
                $f = trim((string) ($r->department_name ?? '')) ?: "Noma'lum";
                $c = trim((string) ($r->level_name ?? '')) ?: "Noma'lum";
                $out[$f.'|'.$c] = (int) $r->cnt;
            });

        return $this->activeStudents = $out;
    }
}
