<?php

namespace App\Services;

use App\Models\LessonOpening;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registrator ofisi hisoboti: har bir o'qituvchi joriy semestrda necha marta
 * baho qo'ymay qoldirgan va shulardan nechtasi uchun dars ochish arizasi
 * orqali tasdiq olib baho qo'ygan.
 *
 * "Baho qo'yilmagan holat" — bitta dars kuni (guruh + fan + sana). Qoida
 * o'qituvchining dashboard popupi bilan bir xil (TeacherMissedLessons): o'tgan
 * amaliy dars kunida kamida bitta juftlikda na baho, na NB qo'yilmagan. Kunga
 * ariza yuborilgan bo'lsa, baho keyin qo'yilgan bo'lsa ham u kun "o'tkazib
 * yuborilgan" hisoblanadi — ariza aynan shu uchun yuboriladi.
 *
 * Har bir kun bitta holatga tushadi, o'qituvchining jami soni ularning yig'indisi:
 *   graded     — ariza tasdiqlangan va baho qo'yilgan
 *   approved   — ariza tasdiqlangan, lekin baho hali qo'yilmagan
 *   pending    — ariza ko'rib chiqilmoqda
 *   rejected   — ariza rad etilgan
 *   no_request — ariza yuborilmagan va baho yo'q
 *
 * Kun kimga yoziladi: ariza bor bo'lsa — uni yuborgan o'qituvchiga (teacher_id);
 * teacher_id yozilmagan eski arizalarda shu kuni jadvalda turgan o'qituvchilarga.
 * Ariza yo'q kunda — o'sha kuni o'z juftligiga baho qo'ymagan har bir o'qituvchiga.
 * Davr — joriy semestr boshidan kechagacha (LessonOpening::periodStart).
 */
class LessonOpeningTeacherReport
{
    public const GRADED = 'graded';

    public const APPROVED = 'approved';

    public const PENDING = 'pending';

    public const REJECTED = 'rejected';

    public const NO_REQUEST = 'no_request';

    /** Holatlar tartibi va ko'rsatiladigan nomi */
    public const LABELS = [
        self::GRADED => "Ariza orqali tasdiq olib, baho qo'ygan",
        self::APPROVED => "Tasdiqlangan, lekin baho qo'yilmagan",
        self::PENDING => 'Ariza kutilmoqda',
        self::REJECTED => 'Ariza rad etilgan',
        self::NO_REQUEST => 'Ariza yubormagan',
    ];

    /** Ariza yozuvida o'qituvchi ham, jadval qatori ham topilmasa */
    private const UNKNOWN = '?';

    public function __construct(private TeacherMissedLessons $missed) {}

    /**
     * @return array{
     *     from: Carbon, to: Carbon, totals: array<string, int>,
     *     teachers: list<array<string, mixed>>, days: list<array<string, mixed>>
     * }
     */
    public function build(?string $fromDate = null, ?string $toDate = null): array
    {
        // Faqat o'tgan kunlar hisobga olinadi (bugun va kelajak "o'tkazib
        // yuborilgan" bo'la olmaydi), shuning uchun yuqori chegara kechagacha.
        $yesterday = now('Asia/Tashkent')->subDay()->toDateString();
        $from = $fromDate ?: LessonOpening::periodStart()->toDateString();
        $to = $toDate ?: $yesterday;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        if ($to > $yesterday) {
            $to = $yesterday;   // kelajakdagi sana tanlansa — kechagacha
        }
        // pastSlots/markedPairs yuqori chegarani ISTISNOLI (< $upper) oladi
        $upper = Carbon::parse($to)->addDay()->toDateString();

        $slots = $this->missed->pastSlots($from, $upper);
        $openings = LessonOpening::query()
            ->whereDate('lesson_date', '>=', $from)
            ->whereDate('lesson_date', '<=', $to)
            ->get(['id', 'group_hemis_id', 'subject_id', 'semester_code', 'lesson_date', 'teacher_id', 'teacher_name', 'request_number', 'status', 'created_at']);

        // Faqat bakalavr: faol bakalavr talabasi bor guruhlar bilan cheklaymiz;
        // magistr/ordinatura guruhlari hisobotга kirmaydi.
        $candidateGroups = $slots->pluck('group_id')->merge($openings->pluck('group_hemis_id'))->unique()->values()->all();
        $active = $candidateGroups ? $this->missed->groupsWithActiveStudents($candidateGroups, bachelorOnly: true) : [];
        $slots = $slots->filter(fn ($s) => isset($active[(string) $s->group_id]))->values();
        $openings = $openings->filter(fn ($o) => isset($active[(string) $o->group_hemis_id]))->values();

        $groupIds = array_keys($active);
        $subjectIds = $slots->pluck('subject_id')->merge($openings->pluck('subject_id'))->unique()->values()->all();
        $marked = $groupIds ? $this->missed->markedPairs($groupIds, $subjectIds, $from, $upper) : [];

        // Kunda kamida bitta juftlik hisobga olinganmi (o'qituvchi jadvalda topilmasa kerak)
        $dayMarked = [];
        foreach (array_keys($marked) as $pairKey) {
            $dayMarked[substr($pairKey, 0, strrpos($pairKey, '|'))] = true;
        }

        // kun kaliti => [xodim HEMIS id => ['name', 'group', 'subject', 'date', 'unmarked']]
        $byDay = [];
        foreach ($slots as $slot) {
            $dayKey = $this->missed->key($slot->group_id, $slot->subject_id, $slot->semester_code, $slot->lesson_day);
            $employee = (string) $slot->employee_id;
            $byDay[$dayKey][$employee] ??= [
                'name' => (string) $slot->employee_name,
                'group' => (string) $slot->group_name,
                'subject' => (string) $slot->subject_name,
                'date' => $slot->lesson_day,
                'unmarked' => false,
                'pairs' => [],   // baho qo'yilmagan juftliklar: kod => ['label', 'time']
            ];

            $pairKey = $this->missed->pairKey($slot->group_id, $slot->subject_id, $slot->semester_code, $slot->lesson_day, $slot->lesson_pair_code);
            if (! isset($marked[$pairKey]) && isset($active[(string) $slot->group_id])) {
                $byDay[$dayKey][$employee]['unmarked'] = true;
                $code = (string) $slot->lesson_pair_code;
                $byDay[$dayKey][$employee]['pairs'][$code] ??= [
                    'label' => $this->pairLabel($slot),
                    'time' => $this->pairTime($slot),
                ];
            }
        }

        $employeeIds = $slots->pluck('employee_id')->map(fn ($id) => (string) $id)->unique()->values()->all();
        $teacherIds = $openings->pluck('teacher_id')->filter()->unique()->values()->all();
        $teachers = ($employeeIds || $teacherIds)
            ? Teacher::query()
                ->where(fn ($query) => $query->whereIn('hemis_id', $employeeIds)->orWhereIn('id', $teacherIds))
                ->get(['id', 'hemis_id', 'full_name', 'department'])
            : collect();
        $byHemis = $teachers->filter(fn ($t) => ! empty($t->hemis_id))->keyBy(fn ($t) => (string) $t->hemis_id);
        $byId = $teachers->keyBy('id');

        // Kim: 'h<HEMIS id>' — jadval/HEMIS bo'yicha; 't<id>' — HEMIS id'siz o'qituvchi;
        // '?' — aniqlab bo'lmadi. Bir odam ikkala manbada ham bir kalitga tushadi.
        $people = [];
        $person = function (string $who, string $fallbackName = '') use (&$people, $byHemis, $byId): string {
            if (! isset($people[$who])) {
                $teacher = match (true) {
                    str_starts_with($who, 'h') => $byHemis[substr($who, 1)] ?? null,
                    str_starts_with($who, 't') => $byId[(int) substr($who, 1)] ?? null,
                    default => null,
                };
                $name = trim((string) ($teacher->full_name ?? '')) ?: trim($fallbackName);
                $people[$who] = [
                    'name' => $name !== '' ? $name : ($who === self::UNKNOWN ? "O'qituvchi aniqlanmagan" : '—'),
                    'department' => trim((string) ($teacher->department ?? '')),
                ];
            }

            return $who;
        };

        // Jadvalda kuni topilmagan arizalar uchun guruh va fan nomlari
        $dayKeyOf = fn (LessonOpening $o): string => $this->missed->key($o->group_hemis_id, $o->subject_id, $o->semester_code, $o->lesson_date?->format('Y-m-d'));
        $unlisted = $openings->filter(fn (LessonOpening $o) => ! isset($byDay[$dayKeyOf($o)]));
        $groupNames = $unlisted->isEmpty() ? collect() : DB::table('groups')
            ->whereIn('group_hemis_id', $unlisted->pluck('group_hemis_id')->unique()->values())
            ->pluck('name', 'group_hemis_id');
        $subjectNames = $unlisted->isEmpty() ? collect() : DB::table('schedules')
            ->whereIn('subject_id', $unlisted->pluck('subject_id')->unique()->values())
            ->whereNull('deleted_at')
            ->select('subject_id', DB::raw('MAX(subject_name) as subject_name'))
            ->groupBy('subject_id')
            ->pluck('subject_name', 'subject_id');

        $records = [];
        $requested = [];   // arizasi bor kunlar

        // 1) Arizasi bor kunlar — har biri arizaning holati bo'yicha
        foreach ($openings as $opening) {
            $dayKey = $dayKeyOf($opening);
            $requested[$dayKey] = true;
            $onDay = $byDay[$dayKey] ?? [];
            $first = $onDay ? reset($onDay) : null;

            if ($opening->teacher_id) {
                $teacher = $byId[$opening->teacher_id] ?? null;
                $whos = [$person(
                    $teacher && ! empty($teacher->hemis_id) ? 'h'.$teacher->hemis_id : 't'.$opening->teacher_id,
                    (string) $opening->teacher_name
                )];
            } elseif ($onDay) {
                $whos = [];
                foreach ($onDay as $employee => $entry) {
                    $whos[] = $person('h'.$employee, $entry['name']);
                }
            } else {
                $whos = [$person(self::UNKNOWN)];
            }

            foreach ($whos as $who) {
                $own = str_starts_with($who, 'h') ? ($onDay[substr($who, 1)] ?? null) : null;
                // O'z juftliklari jadvalda bo'lsa — hammasi hisobga olinganmi; bo'lmasa kunda biror belgi bormi
                $graded = $own !== null ? ! $own['unmarked'] : isset($dayMarked[$dayKey]);

                $records[] = [
                    'who' => $who,
                    'status' => match ($opening->status) {
                        LessonOpening::STATUS_REJECTED => self::REJECTED,
                        LessonOpening::STATUS_ACTIVE, LessonOpening::STATUS_EXPIRED => $graded ? self::GRADED : self::APPROVED,
                        default => self::PENDING,
                    },
                    'group' => $first['group'] ?? (string) ($groupNames[$opening->group_hemis_id] ?? ''),
                    'subject' => $first['subject'] ?? (string) ($subjectNames[$opening->subject_id] ?? ''),
                    'date' => $opening->lesson_date?->format('Y-m-d'),
                    'request_number' => $opening->request_number,
                    'requested_at' => $opening->created_at,
                    // Aynan shu o'qituvchining shu kundagi baho qo'yilmagan juftliklari
                    'pairs' => $own['pairs'] ?? [],
                ];
            }
        }

        // 2) Arizasiz kunlar — o'z juftligiga baho qo'ymagan har bir o'qituvchiga
        foreach ($byDay as $dayKey => $onDay) {
            if (isset($requested[$dayKey])) {
                continue;
            }
            foreach ($onDay as $employee => $entry) {
                if (! $entry['unmarked']) {
                    continue;
                }
                $records[] = [
                    'who' => $person('h'.$employee, $entry['name']),
                    'status' => self::NO_REQUEST,
                    'group' => $entry['group'],
                    'subject' => $entry['subject'],
                    'date' => $entry['date'],
                    'request_number' => null,
                    'requested_at' => null,
                    'pairs' => $entry['pairs'],
                ];
            }
        }

        return $this->assemble($records, $people, Carbon::parse($from), Carbon::parse($to));
    }

    /** Juftlik yorlig'i: jadvaldagi nomi, bo'lmasa "N-juftlik". */
    private function pairLabel(object $slot): string
    {
        $name = trim((string) ($slot->lesson_pair_name ?? ''));
        if ($name !== '') {
            return $name;
        }
        $code = trim((string) ($slot->lesson_pair_code ?? ''));

        return $code !== '' ? $code.'-juftlik' : '';
    }

    /** Juftlik vaqti: "08:30-09:50" (jadvaldagi boshlanish va tugash soati). */
    private function pairTime(object $slot): string
    {
        $hm = fn ($v) => ($v = trim((string) $v)) !== '' ? substr($v, 0, 5) : '';
        $start = $hm($slot->lesson_pair_start_time ?? '');
        $end = $hm($slot->lesson_pair_end_time ?? '');
        if ($start === '' && $end === '') {
            return '';
        }

        return $end !== '' ? $start.'-'.$end : $start;
    }

    /**
     * Yozuvlarni o'qituvchilar bo'yicha yig'ish va tartiblash.
     *
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, array{name: string, department: string}>  $people
     */
    private function assemble(array $records, array $people, Carbon $from, Carbon $to): array
    {
        $blank = array_fill_keys(array_keys(self::LABELS), 0) + ['total' => 0];

        $counts = [];
        foreach ($records as $record) {
            $counts[$record['who']] ??= $blank;
            $counts[$record['who']][$record['status']]++;
            $counts[$record['who']]['total']++;
        }

        $teachers = [];
        foreach ($counts as $who => $row) {
            $teachers[] = ['name' => $people[$who]['name'], 'department' => $people[$who]['department']] + $row;
        }
        // Ko'p qoldirganlar birinchi, teng bo'lsa ism bo'yicha
        usort($teachers, fn ($a, $b) => $b['total'] <=> $a['total']
            ?: strcmp(mb_strtolower($a['name']), mb_strtolower($b['name'])));

        $totals = $blank;
        foreach ($teachers as $teacher) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $teacher[$key];
            }
        }

        // Har bir kun uchun baho qo'yilmagan juftliklar alohida qatorga chiqadi
        // (qaysi sanada, qaysi guruhda, qaysi juftlikda, qaysi soatda). Juftlik
        // ma'lumoti bo'lmasa (masalan baho qo'yib bo'lingan ariza) — bitta qator.
        $days = [];
        foreach ($records as $record) {
            $base = [
                'teacher' => $people[$record['who']]['name'],
                'department' => $people[$record['who']]['department'],
                'group' => $record['group'],
                'subject' => $record['subject'],
                'date' => $record['date'],
                'status' => $record['status'],
                'request_number' => $record['request_number'],
                'requested_at' => $record['requested_at'],
            ];
            $pairs = $record['pairs'] ?? [];
            if ($pairs) {
                foreach ($pairs as $pair) {
                    $days[] = $base + ['pair' => $pair['label'], 'time' => $pair['time']];
                }
            } else {
                $days[] = $base + ['pair' => '', 'time' => ''];
            }
        }
        usort($days, fn ($a, $b) => strcmp(mb_strtolower($a['teacher']), mb_strtolower($b['teacher']))
            ?: strcmp((string) $a['date'], (string) $b['date'])
            ?: strcmp((string) $a['pair'], (string) $b['pair']));

        return [
            'from' => $from,
            'to' => $to,
            'totals' => $totals,
            'teachers' => $teachers,
            'days' => $days,
        ];
    }
}
