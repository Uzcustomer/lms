<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "LMS ga kirishlar" hisobotining so'rovlari: tanlangan oraliqdagi bakalavr
 * talabalari kirishlari kunlik, haftalik va oylik kesimda, butun universitet
 * bo'yicha.
 *
 * Kirish — activity_logs dagi guard='student', action='login' yozuvi (web va
 * mobil ilova ikkalasi ham shu yerga yozadi). Davr ichida bir talaba necha
 * marta kirganidan qat'i nazar bir marta sanaladi; foiz jami bakalavr
 * talabalariga nisbatan.
 *
 * Faqat bakalavr va faqat o'qiyotganlar (student_status_code = 11).
 * Bakalavr LMS da ikki xil belgilanadi — education_type_code = '11' yoki
 * nomida "bakalavr" — ikkalasi ham qamraladi (TeacherMissedLessons bilan
 * bir xil qoida).
 */
class StudentActivityStatsData
{
    private const ACTIVE_STATUS = '11';

    private const MONTHS = [1 => 'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'];

    /** @var list<object{day:string, at:string, student_id:int}> */
    private ?array $logins = null;

    private ?int $totalStudents = null;

    public function __construct(private string $from, private string $to)
    {
    }

    /** Kunlik */
    public function daily(): array
    {
        return $this->aggregate(fn (string $day) => [$day, Carbon::parse($day)->format('d.m.Y')]);
    }

    /** Haftalik: dushanbadan yakshanbagacha */
    public function weekly(): array
    {
        return $this->aggregate(function (string $day) {
            $d = Carbon::parse($day);
            $start = $d->copy()->startOfWeek(Carbon::MONDAY);
            $end = $d->copy()->endOfWeek(Carbon::SUNDAY);

            return [$start->toDateString(), $start->format('d.m').' – '.$end->format('d.m.Y')];
        });
    }

    /** Oylik */
    public function monthly(): array
    {
        return $this->aggregate(function (string $day) {
            $d = Carbon::parse($day);

            return [$d->format('Y-m'), self::MONTHS[(int) $d->format('n')].' '.$d->format('Y')];
        });
    }

    /**
     * Talaba kesimi: har bir o'qiyotgan bakalavr alohida qator — oraliqda
     * necha marta kirgan, nechta kunda kirgan, oxirgi kirishi. Kirmaganlar ham
     * ro'yxatda (0 bilan) — ular ham kerak. Ko'p kirganlar birinchi.
     *
     * @return list<array<string, mixed>>
     */
    public function perStudent(): array
    {
        // Oraliqdagi kirishlar talaba bo'yicha
        $acc = [];
        foreach ($this->logins() as $row) {
            $acc[$row->student_id] ??= ['logins' => 0, 'days' => [], 'last' => null];
            $acc[$row->student_id]['logins']++;
            $acc[$row->student_id]['days'][$row->day] = true;
            if ($acc[$row->student_id]['last'] === null || $row->at > $acc[$row->student_id]['last']) {
                $acc[$row->student_id]['last'] = $row->at;
            }
        }

        $rows = [];
        DB::table('students as st')
            ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'st.group_id')
            ->where('st.student_status_code', self::ACTIVE_STATUS)
            ->where(fn ($q) => $this->bachelorOnly($q, 'st.'))
            ->select('st.id', 'st.full_name', 'st.student_id_number', 'st.level_name', 'g.name as group_name', 'g.department_name')
            ->orderBy('st.full_name')
            ->cursor()
            ->each(function ($st) use (&$rows, $acc) {
                $a = $acc[$st->id] ?? null;
                $rows[] = [
                    'student' => (string) $st->full_name,
                    'student_id_number' => (string) ($st->student_id_number ?? ''),
                    'faculty' => (string) ($st->department_name ?? ''),
                    'course' => (string) ($st->level_name ?? ''),
                    'group' => (string) ($st->group_name ?? ''),
                    'logins' => $a['logins'] ?? 0,
                    'days' => $a ? count($a['days']) : 0,
                    'last_login' => $a['last'] ?? null,
                ];
            });

        usort($rows, fn ($x, $y) => $y['logins'] <=> $x['logins']
            ?: strcmp(mb_strtolower($x['student']), mb_strtolower($y['student'])));

        return $rows;
    }

    /** Jami bakalavr talabalar — varaq sarlavhasi uchun */
    public function totalStudents(): int
    {
        if ($this->totalStudents !== null) {
            return $this->totalStudents;
        }

        return $this->totalStudents = (int) DB::table('students')
            ->where('student_status_code', self::ACTIVE_STATUS)
            ->where(fn ($q) => $this->bachelorOnly($q))
            ->count();
    }

    /**
     * Davr bo'yicha yig'ish, sana o'sib borish tartibida.
     *
     * @param  callable(string): array{0: string, 1: string}  $period  kun => [tartib kaliti, yorliq]
     * @return list<array<string, mixed>>
     */
    private function aggregate(callable $period): array
    {
        $acc = [];
        foreach ($this->logins() as $row) {
            [$sort, $label] = $period($row->day);
            $acc[$sort] ??= ['period' => $label, 'logins' => 0, 'students' => []];
            $acc[$sort]['logins']++;
            $acc[$sort]['students'][$row->student_id] = true;
        }
        ksort($acc);

        $total = $this->totalStudents();
        $rows = [];
        foreach ($acc as $a) {
            $unique = count($a['students']);
            $rows[] = [
                'period' => $a['period'],
                'logins' => $a['logins'],
                'unique_students' => $unique,
                'total_students' => $total,
                'percent' => $total > 0 ? round($unique * 100 / $total, 1) : 0,
            ];
        }

        return $rows;
    }

    /**
     * Oraliqdagi bakalavr talabalari kirishlari. Bir marta o'qiladi, uch varaq
     * shundan hisoblanadi.
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
            ->where('l.guard', 'student')
            ->where('l.action', 'login')
            ->whereBetween('l.created_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])
            ->where('st.student_status_code', self::ACTIVE_STATUS)
            ->where(fn ($q) => $this->bachelorOnly($q, 'st.'))
            ->select('l.user_id as student_id', 'l.created_at')
            ->orderBy('l.created_at')
            ->cursor()
            ->each(function ($r) use (&$rows) {
                $rows[] = (object) [
                    'student_id' => (int) $r->student_id,
                    'day' => substr((string) $r->created_at, 0, 10),
                    'at' => (string) $r->created_at,
                ];
            });

        return $this->logins = $rows;
    }

    /** Bakalavr sharti — kod yoki nom bo'yicha */
    private function bachelorOnly($query, string $prefix = ''): void
    {
        $query->where($prefix.'education_type_code', '11')
            ->orWhereRaw('LOWER('.$prefix.'education_type_name) LIKE ?', ['%bakalavr%']);
    }
}
