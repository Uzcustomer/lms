<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "LMS ga kirishlar" hisobotining so'rovlari: tanlangan oraliqdagi bakalavr
 * talabalari kirishlari kunlik kesimda, butun universitet bo'yicha, hamda har
 * bir talaba kesimida.
 *
 * Kirish — activity_logs dagi guard='student', action='login' yozuvi (web va
 * mobil ilova ikkalasi ham shu yerga yozadi). Davr ichida bir talaba necha
 * marta kirganidan qat'i nazar bir marta sanaladi; foiz jami bakalavr
 * talabalariga nisbatan.
 *
 * XOTIRA. Olti oyda yuz minglab kirish bo'ladi; ularni ro'yxat qilib saqlash
 * 128 MB ni oshirib yuborardi (o'lchangan: 239 ming talaba-kun = 76 MB).
 * Shuning uchun baza talaba-kun bo'yicha guruhlab beradi va har qator
 * kelishi bilan ikkita yig'uvchiga qo'shilib, tashlab yuboriladi. Xotirada
 * faqat natija qoladi: kunlar va talabalar soni qadar.
 *
 * Faqat bakalavr va faqat o'qiyotganlar (student_status_code = 11).
 * Bakalavr LMS da ikki xil belgilanadi — education_type_code = '11' yoki
 * nomida "bakalavr" — ikkalasi ham qamraladi (TeacherMissedLessons bilan
 * bir xil qoida).
 */
class StudentActivityStatsData
{
    private const ACTIVE_STATUS = '11';

    /** @var array<string, array{period:string, logins:int, students:array<int,true>}> */
    private array $byDay = [];

    /** @var array<int, array{logins:int, days:int, first:string|null, last:string|null}> */
    private array $byStudent = [];

    private bool $loaded = false;

    private ?int $totalStudents = null;

    public function __construct(private string $from, private string $to)
    {
    }

    public function daily(): array
    {
        $this->load();

        return $this->finish($this->byDay);
    }

    /**
     * Talaba kesimi: har bir o'qiyotgan bakalavr alohida qator — oraliqda
     * necha marta kirgan, nechta kunda, oraliqdagi birinchi va oxirgi kirishi,
     * hamda UMUMAN oxirgi kirishi (oraliqdan tashqarida bo'lsa ham — shunda
     * "qachondan beri kirmaydi" ko'rinadi). Kirmaganlar ham ro'yxatda (0 bilan).
     * Ko'p kirganlar birinchi.
     *
     * @return list<array<string, mixed>>
     */
    public function perStudent(): array
    {
        $this->load();

        // Umumiy oxirgi kirish — oraliqsiz, faqat bakalavrlar uchun
        $lastEver = [];
        DB::table('activity_logs as l')
            ->join('students as st', 'st.id', '=', 'l.user_id')
            ->where('l.guard', 'student')
            ->where('l.action', 'login')
            ->where('st.student_status_code', self::ACTIVE_STATUS)
            ->where(fn ($q) => $this->bachelorOnly($q, 'st.'))
            ->groupBy('l.user_id')
            ->select('l.user_id', DB::raw('MAX(l.created_at) as last_at'))
            ->cursor()
            ->each(function ($r) use (&$lastEver) {
                $lastEver[(int) $r->user_id] = (string) $r->last_at;
            });

        $rows = [];
        DB::table('students as st')
            ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'st.group_id')
            ->where('st.student_status_code', self::ACTIVE_STATUS)
            ->where(fn ($q) => $this->bachelorOnly($q, 'st.'))
            ->select('st.id', 'st.full_name', 'st.student_id_number', 'st.level_name', 'g.name as group_name', 'g.department_name')
            ->orderBy('st.full_name')
            ->cursor()
            ->each(function ($st) use (&$rows, $lastEver) {
                $a = $this->byStudent[$st->id] ?? null;
                $rows[] = [
                    'student' => (string) $st->full_name,
                    'student_id_number' => (string) ($st->student_id_number ?? ''),
                    'faculty' => (string) ($st->department_name ?? ''),
                    'course' => (string) ($st->level_name ?? ''),
                    'group' => (string) ($st->group_name ?? ''),
                    'logins' => $a['logins'] ?? 0,
                    'days' => $a['days'] ?? 0,
                    'first_login' => $a['first'] ?? null,
                    'last_login' => $a['last'] ?? null,
                    'last_ever' => $lastEver[$st->id] ?? null,
                ];
            });

        usort($rows, fn ($x, $y) => $y['logins'] <=> $x['logins']
            ?: strcmp(mb_strtolower($x['student']), mb_strtolower($y['student'])));

        return $rows;
    }

    /** Jami bakalavr talabalar — varaq sarlavhasi va foiz maxraji */
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
     * Bitta o'tishda ikkita yig'uvchini to'ldirish. Baza talaba-kun bo'yicha
     * guruhlab beradi (kirishlar soni, birinchi va oxirgi vaqt bilan); har
     * qator kelishi bilan yig'iladi, saqlanmaydi.
     */
    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        DB::table('activity_logs as l')
            ->join('students as st', 'st.id', '=', 'l.user_id')
            ->where('l.guard', 'student')
            ->where('l.action', 'login')
            ->whereBetween('l.created_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])
            ->where('st.student_status_code', self::ACTIVE_STATUS)
            ->where(fn ($q) => $this->bachelorOnly($q, 'st.'))
            ->groupBy('l.user_id', DB::raw('DATE(l.created_at)'))
            ->select(
                'l.user_id as student_id',
                DB::raw('DATE(l.created_at) as day'),
                DB::raw('COUNT(*) as logins'),
                DB::raw('MIN(l.created_at) as first_at'),
                DB::raw('MAX(l.created_at) as last_at')
            )
            ->cursor()
            ->each(function ($r) {
                $sid = (int) $r->student_id;
                $day = (string) $r->day;
                $n = (int) $r->logins;
                $d = Carbon::parse($day);

                $this->bump($this->byDay, $day, $d->format('d.m.Y'), $sid, $n);

                $s = &$this->byStudent[$sid];
                $s ??= ['logins' => 0, 'days' => 0, 'first' => null, 'last' => null];
                $s['logins'] += $n;
                $s['days']++;
                $first = (string) $r->first_at;
                $last = (string) $r->last_at;
                if ($s['first'] === null || $first < $s['first']) {
                    $s['first'] = $first;
                }
                if ($s['last'] === null || $last > $s['last']) {
                    $s['last'] = $last;
                }
                unset($s);
            });
    }

    private function bump(array &$acc, string $key, string $label, int $studentId, int $logins): void
    {
        $acc[$key] ??= ['period' => $label, 'logins' => 0, 'students' => []];
        $acc[$key]['logins'] += $logins;
        $acc[$key]['students'][$studentId] = true;
    }

    /**
     * Yig'uvchini qatorlarga aylantirish: sana o'sib borish tartibida, noyob
     * talabalar soni va foiz bilan.
     *
     * @return list<array<string, mixed>>
     */
    private function finish(array $acc): array
    {
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

    /** Bakalavr sharti — kod yoki nom bo'yicha */
    private function bachelorOnly($query, string $prefix = ''): void
    {
        $query->where($prefix.'education_type_code', '11')
            ->orWhereRaw('LOWER('.$prefix.'education_type_name) LIKE ?', ['%bakalavr%']);
    }
}
