<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableGridSetting;
use App\Services\HemisSync\SchedulePlanner;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registrator ofisi: LMS ichki dars jadvali + KTR ↔ HEMIS dars jadvali solishtirish.
 * Faqat o'qiydi va taklif beradi (HEMIS'ga yozish — alohida bosqich, registrator tasdig'i bilan).
 */
trait TimetableHemisSync
{
    /** HEMIS'dagi para vaqtlari (lesson_pair): to'liq va yarim paralar. 17:10–17:50 yarim parasi yo'q. */
    public const HEMIS_PAIRS = [
        '08:30-09:50' => 11, '10:00-11:20' => 12, '12:00-13:20' => 13, '13:30-14:50' => 14, '15:00-16:20' => 15, '16:30-17:50' => 16,
        '08:30-09:10' => 25, '09:10-09:50' => 26, '10:00-10:40' => 17, '10:40-11:20' => 22, '12:00-12:40' => 20, '12:40-13:20' => 27,
        '13:30-14:10' => 18, '14:10-14:50' => 24, '15:00-15:40' => 21, '15:40-16:20' => 23, '16:30-17:10' => 19,
    ];

    public function hemisSyncCompare(Request $request, TimetableBoard $board)
    {
        if (!in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true)) {
            abort(403, 'Faqat registrator ofisi uchun.');
        }
        $data = $request->validate([
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'groups'            => 'nullable|array',
            'groups.*'          => 'string|max:255',
            'today'             => 'nullable|date',
            // Faqat shu haftagacha (shu hafta oxirigacha) yoziladi; bo'sh/0 — semestr oxirigacha.
            'until_week'        => 'nullable|integer|min:0|max:60',
            // Hisob davri (haftalar): shu oraliqdagi KTR / HEMIS / LMS yig'indilari. Sukut: 1 — joriy hafta.
            'period_from'       => 'nullable|integer|min:1|max:60',
            'period_to'         => 'nullable|integer|min:1|max:60',
            // Excel: faqat farqi borlar va tanlangan amal turlari.
            'only_diff'         => 'nullable|boolean',
            'ops'               => 'nullable|array',
            // ops_filter=1 — amal turlari tanlangan (bo'sh ro'yxat ham: hech biri); yo'q bo'lsa — filtrsiz.
            'ops_filter'        => 'nullable|boolean',
            'ops.*'             => 'in:create,update,delete,blocked',
            'blk_filter'        => 'nullable|boolean',
            'blk'               => 'nullable|array',
            'blk.*'             => 'in:room,teacher,other',
            // Excel: faqat tanlangan fanlar (subjects_filter=1 bo'lsa).
            'subjects_filter'   => 'nullable|boolean',
            'subjects'          => 'nullable|array',
            'subjects.*'        => 'string|max:255',
            // Excel: faqat tanlangan guruhlar (groups_filter=1 bo'lsa). "groups" — qamrov (hisobot shu guruhlar uchun
            // tuziladi), bu esa faqat chiqishni toraytiradi: guruhlararo to'siqlar oynadagidek qoladi.
            'groups_filter'     => 'nullable|boolean',
            'filter_groups'     => 'nullable|array',
            'filter_groups.*'   => 'string|max:255',
        ]);
        $today = !empty($data['today']) && in_array($this->timetableActiveRole($request), ['admin', 'superadmin'], true)
            ? Carbon::parse($data['today'])->toDateString()
            : now()->toDateString();

        // Butun doska / semestr oxirigacha: hisobot katta (bir necha MB) — serverning 128M chegarasi yetmaydi.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        return response()->json($this->hemisSyncReport($board, $data, $today));
    }

    /**
     * Solishtirishdan oldin (HEMIS'ga so'rovsiz, faqat LMS ma'lumoti): tanlangan qamrovda ichki jadvalda joylanmagan
     * kartalar — ular HEMIS'ga yozilmaydi. Qamrov va "HEMIS'ga yozish" haftasi solishtirishdagi bilan bir xil.
     */
    public function unplacedSummary(Request $request, TimetableBoard $board)
    {
        if (!in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true)) {
            abort(403, 'Faqat registrator ofisi uchun.');
        }
        $data = $request->validate([
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'groups'            => 'nullable|array',
            'groups.*'          => 'string|max:255',
            'until_week'        => 'nullable|integer|min:0|max:60',
            'today'             => 'nullable|date',
        ]);
        $today = !empty($data['today']) && in_array($this->timetableActiveRole($request), ['admin', 'superadmin'], true)
            ? Carbon::parse($data['today'])->toDateString()
            : now()->toDateString();

        return response()->json($this->hemisUnplacedSummary($board, $data, $today));
    }

    /**
     * LMS ichki jadvalida joylanmagan kartalar (HEMIS'ga yozilmaydi) — faqat ertadan "HEMIS'ga yozish" haftasi oxirigacha
     * (until_week: berilmasa — joriy hafta, 0 — semestr oxiri; o'tgan kunlar HEMIS'da yopiq).
     * weekly — haftalik kartalarning karta-haftalari: doskadagi "Doska bo'yicha (haftalik)" va unplacedExport kabi —
     *   bekor qilingan hafta sanalmaydi, shu haftaga ko'chirilgan (kun va parasi bor) yoki shablonda joyi bor karta joylangan.
     * cycle — ichki sikl jadvalida joylanmagan sikl kartalari (cycleUnplacedCards: guruh qatorida — "Guruh bo'yicha" —
     *   joylangani joylangan; butun doska hisoboti bilan umumiy).
     * conflicts — ichki jadvaldagi to'qnashuvlar (internalConflicts: o'qituvchi yoki xona band), ertadan keyingilari.
     * Bitta karta bir nechta guruhda bo'lishi mumkin: count — karta-hafta / karta bo'yicha bir martadan.
     */
    private function hemisUnplacedSummary(TimetableBoard $board, array $scope, string $today): array
    {
        [$facSet, $specSet, $courseSet] = $this->scopeSets($scope);
        $q = TimetableCard::where('board_id', $board->id);
        $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
        $cards = $q->get();
        $cycleKeys = [];
        foreach ($this->subjectSettingsFor($board) as $st) {
            if (($st['mode'] ?? '') === 'cycle') {
                $cycleKeys[$this->specKey($st['specialty_name']) . '|' . (int) $st['course'] . '|' . $this->normSubject((string) $st['subject_name'])] = true;
            }
        }
        $isCycle = function (TimetableCard $c) use ($cycleKeys): bool {
            $k = $this->specKey($c->specialty_name) . '|' . (int) $c->course . '|';
            $base = $this->cycleSubjectVariantBase((string) $c->subject_name);

            return isset($cycleKeys[$k . $this->normSubject((string) $c->subject_name)])
                || ($base !== null && isset($cycleKeys[$k . $this->normSubject($base)]));
        };
        $cleanGroup = fn ($n) => trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', (string) $n));
        $want = [];
        foreach ((array) ($scope['groups'] ?? []) as $g) {
            $want[$this->groupNameKey($g)] = true;
            $want[$this->groupNameKey($cleanGroup($g))] = true;
        }
        $groupOk = fn (string $raw) => !$want || isset($want[$this->groupNameKey($raw)]) || isset($want[$this->groupNameKey($cleanGroup($raw))]);
        $untilWeek = array_key_exists('until_week', $scope) && $scope['until_week'] !== null ? (int) $scope['until_week'] : null;

        $weeklyCards = $cards->reject($isCycle)->values();
        $overrides = Schema::hasTable('timetable_card_overrides') && $weeklyCards->isNotEmpty()
            ? DB::table('timetable_card_overrides')->whereIn('card_id', $weeklyCards->pluck('id'))->get()->groupBy('card_id')
            : collect();
        $weekly = [];
        foreach ($weeklyCards as $c) {
            $groups = array_values(array_filter(array_map(fn ($n) => trim((string) $n), $c->occupiedGroups()), fn ($n) => $n !== '' && $groupOk($n)));
            if (!$groups) {
                continue;
            }
            $g = $this->cardGrid($board, $c);
            $mondays = [];
            $current = 0;
            for ($w = 1; $w <= $g['weeks']; $w++) {
                $mondays[$w] = $this->weekStartDate($board, $c, $w)->toDateString();
                if ($mondays[$w] <= $today) {
                    $current = $w;
                }
            }
            $uw = $untilWeek ?? $current;
            $until = $uw > 0 && $mondays ? Carbon::parse($mondays[min($uw, $g['weeks'])])->addDays(6)->toDateString() : null;
            $ov = ($overrides[$c->id] ?? collect())->keyBy('week');
            $weeks = [];
            foreach ($mondays as $w => $mon) {
                if (Carbon::parse($mon)->addDays(max(1, (int) $g['days']) - 1)->toDateString() <= $today || ($until !== null && $mon > $until)) {
                    continue;
                }
                $o = $ov[$w] ?? null;
                if (($o && $o->cancelled) || ($o && $o->day && $o->pair) || ($c->day && $c->pair)) {
                    continue;
                }
                $weeks[] = $w;
            }
            if (!$weeks) {
                continue;
            }
            foreach (array_unique(array_map($cleanGroup, $groups)) as $name) {
                $weekly[] = ['group' => $name, 'card_id' => $c->id, 'subject' => (string) $c->subject_name,
                    'type' => $c->training_type === 'lecture' ? 'lecture' : 'practice', 'weeks' => $weeks];
            }
        }

        $cycle = [];
        // To'qnashuvli sikl bloklari bu yerda emas — quyidagi "conflicts" bo'limida (ikki marta sanalmasin).
        foreach ($cards->contains($isCycle) ? $this->cycleUnplacedCards($board, $scope, false, false) : [] as $cc) {
            $seen = [];
            foreach ($cc['members'] as $member) {
                $name = $cleanGroup($member);
                if (!$groupOk((string) $member) || isset($seen[$name])) {
                    continue;
                }
                $seen[$name] = true;
                $cycle[] = ['group' => $name, 'key' => $cc['key'], 'subject' => $cc['subject'], 'type' => $cc['type'], 'days' => $cc['days']];
            }
        }

        // Ichki jadvaldagi to'qnashuvlar (o'qituvchi yoki xona band): dars joyida, lekin HEMIS'ga yozilmaydi (solishtirishda —
        // to'siq). Faqat ertadan keyingi darslar; count — darslar (karta-hafta / sikl bloki) soni.
        $conflicts = [];
        foreach ($this->internalConflicts($board, $scope)['items'] as $it) {
            if (($it['to'] ?? $it['date']) <= $today || !array_filter(preg_split('/\s*(?:,|\s\+\s)\s*/u', (string) $it['group']), $groupOk)) {
                continue;
            }
            $conflicts[] = ['lesson' => $it['kind'] === 'weekly' ? $it['card_id'] . '|' . $it['week'] : $it['key'], 'kind' => $it['kind'],
                'group' => $cleanGroup($it['group']), 'subject' => $it['subject'], 'date' => $it['date'],
                'dates' => $it['dates'] ?? null, 'time' => $it['time'], 'type' => $it['type'], 'text' => $it['text']];
        }

        return [
            'weekly' => ['count' => count(array_unique(array_merge([], ...array_map(fn ($u) => array_map(fn ($w) => $u['card_id'] . '|' . $w, $u['weeks']), $weekly)))),
                'items' => $weekly],
            'cycle' => ['count' => count(array_unique(array_column($cycle, 'key'))), 'items' => $cycle],
            'conflicts' => ['count' => count(array_unique(array_column($conflicts, 'lesson'))), 'items' => $conflicts],
        ];
    }

    /**
     * Solishtirishdan oldin: HEMIS jadvali LMS'ga oxirgi marta qachon import qilingani va
     * qo'lda boshlangan importning holati. Solishtirish har doim shu import (LMS bazasi)
     * bilan ishlaydi — HEMIS'ning o'ziga bu tugma murojaat qilmaydi.
     */
    public function hemisSyncImportStatus(Request $request, TimetableBoard $board)
    {
        $this->hemisSyncDenyUnlessRegistrar($request);
        // Oxirgi to'liq va xatosiz import. U hali qayd etilmagan bo'lsa (yangilanishdan keyingi birinchi
        // kechgacha) — yozuvlarning eng so'nggi yangilanishi, "taxminiy" belgisi bilan.
        $full = \Illuminate\Support\Facades\Cache::get(\App\Services\ScheduleImportService::LAST_FULL_IMPORT_KEY);
        $last = $full ?? DB::table('schedules')->whereNull('deleted_at')
            ->where('lesson_date', '>=', now()->subDays(14)->startOfDay())
            ->where('lesson_date', '<=', now()->addDays(120)->endOfDay())
            ->max('updated_at');
        $state = \Illuminate\Support\Facades\Cache::get(\App\Jobs\ImportSchedulesWithProgressJob::PROGRESS_KEY);
        $running = $state && in_array($state['status'] ?? null, ['queued', 'running'], true)
            && !empty($state['updated_at']) && Carbon::parse($state['updated_at'])->gt(now()->subMinutes(10));

        return response()->json([
            'last_import_at' => $last,
            'last_import_approx' => $full === null && $last !== null,
            'job' => $state,
            'running' => $running,
        ]);
    }

    /** HEMIS'dan joriy semestr jadvalini qayta import qilishni boshlash ("Dars jadval mosligi" bilan bir xil job). */
    public function hemisSyncImportStart(Request $request, TimetableBoard $board)
    {
        $this->hemisSyncDenyUnlessRegistrar($request);

        return app(\App\Http\Controllers\Admin\ReportController::class)->startScheduleImport();
    }

    private function hemisSyncDenyUnlessRegistrar(Request $request): void
    {
        if (!in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true)) {
            abort(403, 'Faqat registrator ofisi uchun.');
        }
    }

    /**
     * Davr (haftalar oralig'i) bo'yicha yig'indilar: KTR (shu haftalar qatorlari), HEMIS (shu sanalardagi
     * barcha yozuvlar) va LMS ichki jadvali. Soat — dars turi bo'yicha (LMS — qism bo'yicha), soni — qism
     * bo'yicha alohida kunlar.
     */
    private function hemisPeriodStats(array $u, int $fromWeek, int $toWeek, string $fromDate, string $toDate): array
    {
        $part = fn ($code) => $u['codes'][(string) $code] ?? null;
        $ktrH = [];
        $ktrC = ['L' => 0, 'A' => 0];
        foreach ((array) ($u['ktr'] ?? []) as $i => $row) {
            // Sikl fani: KTR qatori — sikl kuni, davrga sanasi bo'yicha kiradi.
            if (isset($u['ktr_dates'])) {
                $d = $u['ktr_dates'][$i + 1] ?? null;
                if ($d === null || $d < $fromDate || $d > $toDate) {
                    continue;
                }
            } elseif ($i + 1 < $fromWeek || $i + 1 > $toWeek) {
                continue;
            }
            $sum = ['L' => 0, 'A' => 0];
            foreach ((array) $row as $code => $h) {
                if ($p = $part($code)) {
                    $ktrH[(string) $code] = ($ktrH[(string) $code] ?? 0) + (int) $h;
                    $sum[$p] += (int) $h;
                }
            }
            foreach ($sum as $p => $h) {
                if ($h > 0) $ktrC[$p]++;
            }
        }
        $hemH = [];
        $hemDays = ['L' => [], 'A' => []];
        foreach ((array) $u['hemis'] as $r) {
            $p = $part($r['code']);
            if (!$p || $r['date'] < $fromDate || $r['date'] > $toDate) {
                continue;
            }
            [$h1, $m1] = array_map('intval', explode(':', substr($r['start'], 0, 5)));
            [$h2, $m2] = array_map('intval', explode(':', substr($r['end'], 0, 5)));
            $hemH[(string) $r['code']] = ($hemH[(string) $r['code']] ?? 0) + max(1, (int) round((($h2 * 60 + $m2) - ($h1 * 60 + $m1)) / 40));
            $hemDays[$p][$r['date']] = true;
        }
        $lmsH = ['L' => 0, 'A' => 0];
        $lmsDays = ['L' => [], 'A' => []];
        foreach ((array) $u['plan'] as $l) {
            if ($l['date'] < $fromDate || $l['date'] > $toDate || !in_array($l['part'], array_values($u['codes']), true)) {
                continue;
            }
            $lmsH[$l['part']] += max(1, (int) $l['len']);
            $lmsDays[$l['part']][$l['date']] = true;
        }

        return [
            'from_week' => $fromWeek, 'to_week' => $toWeek, 'from' => $fromDate, 'to' => $toDate,
            'ktr_hours' => $ktrH, 'ktr_count' => $ktrC,
            'hemis_hours' => $hemH, 'hemis_count' => array_map('count', $hemDays),
            'lms_hours' => $lmsH, 'lms_count' => array_map('count', $lmsDays),
        ];
    }

    /** Fan "mos": amal yo'q (tanlangan haftadan keyingilari ham), semestr balansi va davr yig'indisi (HEMIS = KTR) to'g'ri. */
    private function hemisUnitMatches(array $u): bool
    {
        if (!empty($u['actions']) || !empty($u['actions_later']) || !empty($u['notes'])) {
            return false;
        }
        foreach (['hours_diff', 'count_diff'] as $k) {
            foreach ((array) ($u['before'][$k] ?? []) as $v) {
                if ((int) $v !== 0) return false;
            }
        }
        $p = $u['period'] ?? null;
        if ($p) {
            foreach ($u['code_names'] as $code => $_) {
                if ((int) ($p['ktr_hours'][$code] ?? 0) !== (int) ($p['hemis_hours'][$code] ?? 0)) return false;
            }
            foreach (['L', 'A'] as $part) {
                if (in_array($part, $u['codes'], true) && (int) ($p['ktr_count'][$part] ?? 0) !== (int) ($p['hemis_count'][$part] ?? 0)) return false;
            }
        }

        return true;
    }

    /**
     * Ichki jadvaldagi dars tashqi jadvaldagi (shablon) joyidan boshqa vaqtga tushgan bo'lsa — sababini
     * butun doska bo'yicha topadi: shablon vaqtida o'qituvchi, xona yoki guruh band bo'lgan dars. Xalaqit
     * bergan dars ham o'z shablonidan surilgan bo'lsa — uning sababi ham (bir daraja) yoziladi.
     * Qaytaradi: callable(array $lesson): ?string.
     */
    private function hemisMoveExplainer(TimetableBoard $board, array $slotTimes, callable $isCycle): callable
    {
        $all = TimetableCard::where('board_id', $board->id)->get()->reject($isCycle)->keyBy('id');
        $ov = Schema::hasTable('timetable_card_overrides')
            ? DB::table('timetable_card_overrides')->whereIn('card_id', $all->keys())->get()->groupBy('card_id')->map(fn ($x) => $x->keyBy('week'))
            : collect();
        $hasOvRoom = Schema::hasColumn('timetable_card_overrides', 'auditorium_code');
        $gset = TimetableGridSetting::where('board_id', $board->id)->get();
        $weeksOf = fn (TimetableCard $c): int => $this->cardGrid($board, $c)['weeks'];
        // Qamrov (fakultet|yo'nalish|kurs) bo'yicha: hafta dushanbasi => hafta raqami. Boshlanish sanasi weekStartDate()
        // dagidek (panjara start_date, bo'lmasa semestr boshi), lekin oldindan yuklangan sozlamalardan — haftalar xotirada sanaladi.
        $hasStart = Schema::hasColumn('timetable_grid_settings', 'start_date');
        $low = fn ($v) => mb_strtolower(trim((string) $v));
        $mondays = [];
        $weekFor = function (TimetableCard $c, string $monday) use (&$mondays, $board, $weeksOf, $gset, $hasStart, $low): ?int {
            $k = $c->faculty_name . '|' . $c->specialty_name . '|' . $c->course . '|' . $this->cardCurriculumId($c);
            if (!isset($mondays[$k])) {
                $grid = $this->cardGrid($board, $c);
                $start = $grid['start_date'] ? Carbon::parse($grid['start_date'])->startOfDay() : $this->weeklySemesterStart($board);
                $mondays[$k] = [];
                foreach ($this->weekMondays($board, $start, $weeksOf($c), $grid['weekly_breaks']) as $i => $m) {
                    $mondays[$k][$m->toDateString()] = $i + 1;
                }
            }

            return $mondays[$k][$monday] ?? null;
        };
        $place = function (TimetableCard $c, int $w) use ($ov, $hasOvRoom): ?array {
            $o = $ov[$c->id][$w] ?? null;
            if ($o && $o->cancelled) {
                return null;
            }
            $day = $o ? $o->day : $c->day;
            $pair = $o ? $o->pair : $c->pair;
            if (!$day || !$pair) {
                return null;
            }
            $room = ($hasOvRoom && $o && $o->auditorium_code) ? [$o->auditorium_code, $o->auditorium_name] : [$c->auditorium_code, $c->auditorium_name];

            return ['day' => (int) $day, 'pair' => (int) $pair, 'len' => $c->lenHalf(), 'room' => $room[0], 'room_name' => $room[1]];
        };
        $byDate = [];
        $busyOn = function (string $date) use (&$byDate, $all, $weekFor, $place): array {
            if (isset($byDate[$date])) {
                return $byDate[$date];
            }
            $d = Carbon::parse($date);
            $monday = $d->copy()->startOfWeek()->toDateString();
            $dow = $d->dayOfWeekIso;
            $out = [];
            foreach ($all as $c) {
                $w = $weekFor($c, $monday);
                if (!$w || !($pl = $place($c, $w)) || $pl['day'] !== $dow) {
                    continue;
                }
                $out[] = ['card' => $c, 'week' => $w] + $pl;
            }

            return $byDate[$date] = $out;
        };
        $time = fn (int $slot) => $slotTimes[$slot][0] ?? ('#' . $slot);
        $gk = fn ($n) => mb_strtolower(trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', (string) $n)));
        $groupsOf = fn (TimetableCard $c) => array_map($gk, $c->occupiedGroups());
        $who = fn (TimetableCard $c) => $c->specialty_name . ' ' . $c->course . '-kurs ' . implode('+', array_map(fn ($g) => trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', (string) $g)), $c->occupiedGroups()))
            . ' «' . $c->subject_name . '»' . ($c->teacher_name ? ' (' . $c->teacher_name . ')' : '');

        // Sikl darslari (alohida jadval) sana bo'yicha — weeklyCycleWarnings() bilan bir xil manba; kerak bo'lgandagina hisoblanadi.
        $cycleBusy = null;
        $cycleOn = function (string $date) use (&$cycleBusy, $board): array {
            $cycleBusy ??= $this->cycleBusyByDate($board);

            return $cycleBusy[$date] ?? [];
        };

        // Shablon joyida (kun, yarim-slotlar) shu kartaga xalaqit beradigan darslar.
        $blockers = function (TimetableCard $me, string $date, int $from, int $len, bool $deep) use (&$blockers, $busyOn, $cycleOn, $time, $groupsOf, $who, $place, $weekFor, $all): array {
            $range = [$from, $from + $len];
            $mine = $groupsOf($me);
            // Shu haftadagi xonasi (haftalik ko'chirishda boshqa xona bo'lishi mumkin), bo'lmasa kartaniki.
            $myWeek = $weekFor($me, Carbon::parse($date)->startOfWeek()->toDateString());
            $myRoom = ($myWeek ? $place($me, $myWeek) : null)['room'] ?? $me->auditorium_code;
            $out = [];
            foreach ($busyOn($date) as $b) {
                $c = $b['card'];
                if ($c->id === $me->id || !($b['pair'] < $range[1] && $range[0] < $b['pair'] + $b['len'])) {
                    continue;
                }
                $why = [];
                if ($me->teacher_id && $c->teacher_id === $me->teacher_id) {
                    $why[] = 'o\'qituvchi ' . $me->teacher_name . ' shu vaqtda (' . $time($b['pair']) . ') ' . $who($c) . ' darsida';
                }
                if ($myRoom && (string) $b['room'] === (string) $myRoom && !$this->isSharedRoom($b['room_name'])) {
                    $why[] = ($b['room_name'] ?: $myRoom) . '-xona ' . $time($b['pair']) . ' da band: ' . $who($c);
                }
                // Guruh nomi fakultetlararo takrorlanadi ("1K-01a") — fakultet ham mos bo'lishi kerak (groupScopeKey kabi).
                if (array_intersect($mine, $groupsOf($c)) && !$this->bolakSiblings($me, $c) && (int) $c->course === (int) $me->course
                    && (string) ($c->faculty_name ?? '') === (string) ($me->faculty_name ?? '')
                    && $this->specKey($c->specialty_name) === $this->specKey($me->specialty_name)) {
                    $why[] = 'guruh ' . $time($b['pair']) . ' da «' . $c->subject_name . '» darsida';
                }
                if (!$why) {
                    continue;
                }
                $line = implode('; ', $why);
                // Xalaqit bergan dars ham o'z shablonidan surilgan bo'lsa — uning sababi (bir daraja).
                if ($deep && $c->ext_day && $c->ext_pair && ((int) $c->ext_day !== $b['day'] || (int) $c->ext_pair !== $b['pair'])) {
                    $sub = $blockers($c, $date, (int) $c->ext_pair, $c->lenHalf(), false);
                    // Shablon kuni boshqa bo'lsa sanani o'sha kunga keltirmaymiz — faqat bir kun ichidagi siljishni tushuntiramiz.
                    if ((int) $c->ext_day === $b['day']) {
                        $line .= ' — u dars ham tashqi jadvalda ' . $time((int) $c->ext_pair) . ' da, lekin ichki jadvalda pastga tushirilgan'
                            . ($sub ? ': ' . implode('; ', $sub) : '');
                    }
                }
                $out[] = $line;
            }
            // Shablon vaqtida o'qituvchi yoki xona sikl darsida band — bu ham sabab (vaqt bo'sh emas).
            foreach ($cycleOn($date) as [$bf, $bt, $tid, $room, $tname, $roomName, $label]) {
                if ($bf >= $range[1] || $range[0] >= $bt) {
                    continue;
                }
                if ($me->teacher_id && $tid && (int) $tid === (int) $me->teacher_id) {
                    $out[] = 'o\'qituvchi ' . $me->teacher_name . ' shu vaqtda (' . $time((int) $bf) . ') sikl darsida: ' . $label;
                }
                if ($myRoom && $room && (string) $room === (string) $myRoom && !$this->isSharedRoom($roomName)) {
                    $out[] = ($roomName ?: $room) . '-xona ' . $time((int) $bf) . ' da sikl darsida band: ' . $label;
                }
            }

            return array_values(array_unique($out));
        };

        // Guruh panjarasi (compactInternalWeek'dagi gkey kabi: fakultet|yo'nalish|kurs) va shu kundagi tashqi jadval
        // yarim-slotlari: guruh => [yarim-slot => true].
        $scope = fn (TimetableCard $c) => ($c->faculty_name ?? '') . '|' . $c->specialty_name . '|' . (int) $c->course;
        $groupTemplate = function (TimetableCard $me, int $day) use ($all, $scope): array {
            $mine = $me->occupiedGroups();
            $tmpl = [];
            foreach ($all as $c) {
                if ((int) $c->ext_day !== $day || !$c->ext_pair || $scope($c) !== $scope($me)) {
                    continue;
                }
                foreach (array_intersect($c->occupiedGroups(), $mine) as $g) {
                    for ($i = 0; $i < $c->lenHalf(); $i++) {
                        $tmpl[$g][(int) $c->ext_pair + $i] = true;
                    }
                }
            }

            return $tmpl;
        };

        // "Haftani zichlash" (compactInternalWeek, blade) darsni faqat guruhning shu kundagi shablon yarim-slotlari
        // bo'ylab va ular bo'sh bo'lsa yuqoriga suradi — o'sha yurishni takrorlab, qaysi yarim-slotgacha chiqara olishini topamiz.
        $compactReach = function (TimetableCard $me, string $date, int $day, int $slot) use ($busyOn, $scope, $groupTemplate): int {
            $mine = $me->occupiedGroups();
            $tmpl = $groupTemplate($me, $day);
            $occ = [];    // guruh => [shu haftada band yarim-slot => true] (o'zidan tashqari)
            foreach ($busyOn($date) as $b) {
                if ($b['card']->id === $me->id || $scope($b['card']) !== $scope($me)) {
                    continue;
                }
                foreach (array_intersect($b['card']->occupiedGroups(), $mine) as $g) {
                    for ($i = 0; $i < $b['len']; $i++) {
                        $occ[$g][$b['pair'] + $i] = true;
                    }
                }
            }
            $len = $me->lenHalf();
            $canStep = function (int $p) use ($mine, $tmpl, $occ, $len): bool {
                foreach ($mine as $g) {
                    if (!isset($tmpl[$g][$p])) {
                        return false;
                    }
                    for ($i = 0; $i < $len; $i++) {
                        if (isset($occ[$g][$p + $i])) {
                            return false;
                        }
                    }
                }

                return true;
            };
            $st = $slot;
            while ($st > 1 && $canStep($st - 1)) {
                $st--;
            }
            if ($len > 1 && ($slot - $st) % 2) {
                $st++;   // butun para darslar para chegarasida qoladi
            }

            return $st;
        };

        // Yuqoriga surilish zichlashga o'xshaydimi: yangi joydan shablon joyigacha bo'lgan har bir yarim-slot guruhning
        // shu kundagi tashqi jadvalida boshqa darsniki (shu hafta o'tilmagan) — aks holda bu oddiy haftalik ko'chirish.
        $looksCompacted = function (TimetableCard $me, int $day, int $slot, int $ext) use ($groupTemplate): bool {
            $tmpl = $groupTemplate($me, $day);
            foreach ($me->occupiedGroups() as $g) {
                for ($p = $slot; $p < $ext; $p++) {
                    if (!isset($tmpl[$g][$p])) {
                        return false;
                    }
                }
            }

            return true;
        };

        return function (array $lesson) use ($all, $blockers, $compactReach, $looksCompacted, $time): ?string {
            $c = $all[$lesson['card_id'] ?? 0] ?? null;
            if (!$c || !$lesson['ext_day'] || !$lesson['ext_pair']) {
                return null;
            }
            if ((int) $lesson['ext_day'] === (int) $lesson['day'] && (int) $lesson['ext_pair'] === (int) $lesson['slot']) {
                return 'LMS\'da tashqi jadval (Excel) bo\'yicha joyida — HEMIS\'dagi vaqt shablondan farq qiladi.';
            }
            if ((int) $lesson['ext_day'] !== (int) $lesson['day']) {
                return 'Ichki jadvalda boshqa kunga ko\'chirilgan (tashqi jadvalda ' . \App\Models\TimetableBoard::DEFAULT_DAY_NAMES[(int) $lesson['ext_day'] - 1] . ', ' . $time((int) $lesson['ext_pair']) . ').';
            }
            $ext = (int) $lesson['ext_pair'];
            if ($lesson['slot'] < $ext) {
                // Yuqoriga: shablon vaqtida xalaqit bo'lsa — o'sha sabab; zichlash belgisi bo'lsa — zichlangan; aks holda haftalik ko'chirish.
                $why = $blockers($c, $lesson['date'], $ext, $c->lenHalf(), true);
                $head = 'Tashqi jadvalda ' . $time($ext) . ' da, ichki jadvalda ' . $time((int) $lesson['slot']) . ' ga yuqoriga ko\'chirilgan';
                if ($why) {
                    return $head . ', chunki ' . implode('; ', $why) . '.';
                }
                if ($looksCompacted($c, (int) $lesson['day'], (int) $lesson['slot'], $ext)) {
                    return 'Zichlangan: tashqi jadvalda ' . $time($ext) . ' da, shu haftada yuqoridagi dars o\'tilmagani uchun ' . $time((int) $lesson['slot']) . ' ga surilgan.';
                }

                return $head . ' (shu hafta uchun ko\'chirilgan; ' . $time($ext) . ' hozir bo\'sh).';
            }
            $why = $blockers($c, $lesson['date'], $ext, $c->lenHalf(), true);
            $head = 'Tashqi jadvalda ' . $time($ext) . ' da, ichki jadvalda ' . $time((int) $lesson['slot']) . ' ga pastga tushirilgan';

            if ($why) {
                return $head . ', chunki ' . implode('; ', $why) . '.';
            }
            $reach = $compactReach($c, $lesson['date'], (int) $lesson['day'], (int) $lesson['slot']);
            if ($reach <= $ext) {
                return $head . '. Hozir ' . $time($ext) . ' bo\'sh — «Haftani zichlash» bilan yuqoriga chiqarish mumkin.';
            }

            return $head . '. Hozir ' . $time($ext) . ' bo\'sh, lekin «Haftani zichlash» uni '
                . ($reach < (int) $lesson['slot'] ? 'faqat ' . $time($reach) . ' gacha chiqaradi' : 'chiqara olmaydi')
                . ' (oradagi vaqt guruhning tashqi jadvalida bo\'sh) — ' . $time($ext) . ' ga qo\'lda ko\'chiring.';
        };
    }

    /** Yarim-slot vaqtlari [slot => [start, end]] doskaning qo'ng'iroq jadvalidan. */
    private function hemisSlotTimes(TimetableBoard $board): array
    {
        $bell = $board->bell_schedule ?: TimetableBoard::defaultBellSchedule((int) $board->pairs_per_day);
        $pairs = array_values(array_filter((array) $bell, fn ($it) => ($it['type'] ?? 'pair') === 'pair' && !empty($it['start']) && !empty($it['end'])));
        $out = [];
        $toMin = fn ($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $fmt = fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        $half = count($pairs) >= 10; // har element allaqachon yarim para
        $i = 1;
        foreach ($pairs as $p) {
            $s = $toMin($p['start']);
            $e = $toMin($p['end']);
            if ($half || $e - $s <= 45) {
                $out[$i++] = [$fmt($s), $fmt($e)];
            } else {
                $out[$i++] = [$fmt($s), $fmt($s + 40)];
                $out[$i++] = [$fmt($s + 40), $fmt($e)];
            }
        }

        return array_slice($out, 0, 12, true);
    }

    /** Fan kaliti: kichik harf, tutuq belgisiz, kirill a/b/c (bo'lak harfi) lotinga. */
    private function hemisSubjectKey(string $name): string
    {
        $n = trim($name);
        if (preg_match('/^(.*?)\s*\(\s*([a-zа-яё])\s*\)\s*$/iu', $n, $m)) {
            $l = mb_strtolower($m[2]);
            $l = ['а' => 'a', 'б' => 'b', 'в' => 'c', 'с' => 'c', 'ц' => 'c'][$l] ?? $l;

            return $this->normSubject($m[1]) . ' (' . $l . ')';
        }

        return $this->normSubject($n);
    }

    private function hemisSubjectBase(string $name): string
    {
        return $this->normSubject((string) preg_replace('/\s*\(\s*[a-zа-яё]\s*\)\s*$/iu', '', trim($name)));
    }

    private function hemisPartOf(string $typeName): ?string
    {
        $n = mb_strtolower($typeName);
        foreach (['mustaqil', 'amaliyot', 'kurs ishi', 'самостоят', 'практика', 'курсов'] as $skip) {
            if (str_contains($n, $skip)) {
                return null;
            }
        }

        return preg_match("/ma['’ʻ`]?ruza|лекц/u", $n) ? 'L' : 'A';
    }

    /**
     * Sikl fanlari (4–6 kurs): LMS ichki sikl jadvalidagi darslar — guruhcha va fan bo'yicha.
     * Manba — "Sikl jadvali" (ichki qatlam) oynasining o'zi (cyclePlan): blok joylashuvi, kafedra surgan
     * kunlar va KTR kunlik ma'ruza/amaliy soatlari. Har kun: ma'ruza — "start" dan, amaliy — undan keyin.
     * O'qituvchi va xona — shu guruhcha va fan kartasidan (ma'ruza — ma'ruza kartasi).
     * Qaytaradi: ["fakultet|guruh kaliti" => [fan kaliti => [dars, ...]]]; dars: date, week (= sikl kuni,
     * KTR qatori), slot, len, part, employee_*, auditorium_*, cycle.
     */
    private function hemisCycleLessons(TimetableBoard $board, array $scope, $cycleCards): array
    {
        if (!Schema::hasTable('timetable_cycle_placements')) {
            return [];
        }
        $params = ['layer' => 'internal'];
        foreach (['faculty_names', 'specialty_names', 'courses'] as $k) {
            if (!empty($scope[$k])) {
                $params[$k] = $scope[$k];
            }
        }
        $plan = $this->cyclePlan(Request::create('/', 'POST', $params), $board)->getData(true);
        $dates = array_column((array) ($plan['dates'] ?? []), 'iso');
        if (!$dates) {
            return [];
        }
        $fk = fn ($f) => mb_strtolower(trim((string) $f));
        $teacherHemis = Teacher::whereIn('id', $cycleCards->pluck('teacher_id')->filter()->unique())->pluck('hemis_id', 'id')->all();
        // Karta: fakultet|guruhcha|fan|tur
        $cardOf = [];
        foreach ($cycleCards as $c) {
            $type = $c->training_type === 'lecture' ? 'lecture' : 'practice';
            foreach ($this->cycleCardGroups($c) as $m) {
                $cardOf[$fk($c->faculty_name) . '|' . $this->groupNameKey($m) . '|' . $this->normSubject((string) $c->subject_name) . '|' . $type] ??= $c;
            }
        }
        $find = function (string $fac, string $member, string $subject, string $type) use ($cardOf, $fk) {
            $k = $fk($fac) . '|' . $this->groupNameKey($member) . '|';
            $base = $this->cycleSubjectVariantBase($subject);

            return $cardOf[$k . $this->normSubject($subject) . '|' . $type]
                ?? ($base !== null ? ($cardOf[$k . $this->normSubject($base) . '|' . $type] ?? null) : null);
        };
        $attrs = function ($card) use ($teacherHemis): array {
            return [
                'employee_id' => $card && $card->teacher_id ? ($teacherHemis[$card->teacher_id] ?? null) : null,
                'employee_name' => $card && $card->teacher_id ? $card->teacher_name : null,
                'auditorium_code' => $card?->auditorium_code ?: null,
                'auditorium_name' => $card?->auditorium_name ?: null,
            ];
        };

        $out = [];
        foreach ((array) ($plan['rows'] ?? []) as $row) {
            foreach ((array) ($row['blocks'] ?? []) as $b) {
                $subject = (string) ($b['subject'] ?? '');
                $from = (int) ($b['from'] ?? 0);
                foreach ((array) ($row['subgroups'] ?? []) as $member) {
                    $gk = $fk($row['faculty'] ?? '') . '|' . $this->groupNameKey((string) $member);
                    $sk = $this->hemisSubjectKey($subject);
                    $lec = $attrs($find((string) ($row['faculty'] ?? ''), (string) $member, $subject, 'lecture'));
                    $prc = $attrs($find((string) ($row['faculty'] ?? ''), (string) $member, $subject, 'practice'));
                    foreach ((array) ($b['day_plan'] ?? []) as $off => $d) {
                        $date = $dates[$from + (int) $off] ?? null;
                        if ($date === null) {
                            continue;
                        }
                        $start = max(1, (int) ($d['start'] ?? 1));
                        $base = ['date' => $date, 'week' => (int) ($d['k'] ?? 0), 'cycle' => true, 'card_id' => null];
                        if ((int) ($d['lec'] ?? 0) > 0) {
                            $out[$gk][$sk][] = $base + ['slot' => $start, 'len' => (int) $d['lec'], 'part' => 'L'] + $lec;
                        }
                        if ((int) ($d['prc'] ?? 0) > 0) {
                            // Amaliy — kun rejasidagi boshlanishi (guruhcha smenasi, masalan 13:30, bilan).
                            $out[$gk][$sk][] = $base + ['slot' => max(1, (int) ($d['ps'] ?? $start + (int) ($d['lec'] ?? 0))), 'len' => (int) $d['prc'], 'part' => 'A'] + $prc;
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * HEMIS guruhi (groups qatori) kartadagi guruh nomi bo'yicha: ['name' => qo'shimchasiz nom, 'raw' => kartadagi nom,
     * 'card' => namuna karta]. Nom fakultetlararo takrorlanadi — avval o'z fakulteti; bir xil nomlilardan faoli, shu semestrda
     * HEMIS jadvali va o'quv rejasida shu semestr fanlari borligi.
     */
    private function hemisGroupResolver(TimetableBoard $board, array $groupNames): \Closure
    {
        $facKey = fn ($f) => mb_strtolower(trim((string) $f));
        $groupRows = DB::table('groups')
            ->whereIn('name', array_values(array_unique(array_merge(array_column($groupNames, 'raw'), array_column($groupNames, 'name')))))
            ->get()->groupBy(fn ($g) => $this->groupNameKey($g->name));
        $hasGroupActive = Schema::hasColumn('groups', 'active');
        $hasCsActive = Schema::hasColumn('curriculum_subjects', 'is_active');
        $parityKuzgi = $board->semester_parity !== 'bahorgi';
        return function (array $g) use ($groupRows, $facKey, $hasGroupActive, $hasCsActive, $parityKuzgi) {
            $cands = $groupRows[$this->groupNameKey($g['raw'])] ?? $groupRows[$this->groupNameKey($g['name'])] ?? collect();
            $own = $cands->filter(fn ($r) => $facKey($r->department_name) === $facKey($g['card']->faculty_name));
            $pool = $own->isNotEmpty() ? $own : ($cands->pluck('group_hemis_id')->unique()->count() === 1 ? $cands : collect());
            if ($pool->count() <= 1) {
                return $pool->first();
            }
            // Bir xil nomli bir nechta guruh (eski / qayta ochilgan): faol, shu semestrda HEMIS jadvali bor,
            // o'quv rejasida shu semestr fanlari bor guruh tanlanadi.
            $course = (int) $g['card']->course;
            $sem = (string) ($parityKuzgi ? 10 + 2 * $course - 1 : 10 + 2 * $course);
            return $pool->sortByDesc(fn ($r) => [
                $hasGroupActive ? (int) (bool) ($r->active ?? 1) : 1,
                (int) DB::table('schedules')->where('group_id', $r->group_hemis_id)->where('semester_code', $sem)->whereNull('deleted_at')->exists(),
                // Fanlar keyingi so'rovdagidek: HEMIS'dan olib tashlanganlari (is_active = false) hisobga olinmaydi.
                (int) DB::table('curriculum_subjects')->where('curricula_hemis_id', $r->curriculum_hemis_id)->where('semester_code', $sem)
                    ->when($hasCsActive, fn ($q) => $q->where('is_active', true))->exists(),
                (int) $r->group_hemis_id,
            ])->first();
        };
    }

    private function hemisSyncReport(TimetableBoard $board, array $scope, string $today): array
    {
        [$facSet, $specSet, $courseSet] = $this->scopeSets($scope);
        $q = TimetableCard::where('board_id', $board->id);
        $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
        $cards = $q->get();

        // Sikl fanlari bu bosqichda yo'q (ularning kunlari sikl kalendarida).
        $cycleSubjects = [];
        foreach ($this->subjectSettingsFor($board) as $st) {
            if (($st['mode'] ?? '') === 'cycle') {
                $cycleSubjects[$this->specKey($st['specialty_name']) . '|' . (int) $st['course'] . '|' . $this->normSubject((string) $st['subject_name'])] = true;
            }
        }
        $isCycle = function (TimetableCard $c) use ($cycleSubjects): bool {
            $k = $this->specKey($c->specialty_name) . '|' . (int) $c->course . '|';
            $base = $this->cycleSubjectVariantBase((string) $c->subject_name);

            return isset($cycleSubjects[$k . $this->normSubject((string) $c->subject_name)])
                || ($base !== null && isset($cycleSubjects[$k . $this->normSubject($base)]));
        };
        $cycleCards = $cards->filter($isCycle);
        $cards = $cards->reject($isCycle)->values();

        $slotTimes = $this->hemisSlotTimes($board);
        $startToSlot = [];
        $endToSlot = [];
        foreach ($slotTimes as $s => [$st, $en]) {
            $startToSlot[$st] = $s;
            $endToSlot[$en] = $s;
        }
        $toSlots = function (string $start, string $end) use ($startToSlot, $endToSlot): array {
            $a = $startToSlot[substr($start, 0, 5)] ?? null;
            $b = $endToSlot[substr($end, 0, 5)] ?? null;

            return ($a && $b && $b >= $a) ? range($a, $b) : [];
        };

        // Haftalar: kartaning panjarasi (boshlanish sanasi, tanaffuslar) bo'yicha.
        $gset = TimetableGridSetting::where('board_id', $board->id)->get();
        $gridFor = fn (TimetableCard $c): array => $this->cardGrid($board, $c);
        $weekCache = [];
        $weekMonday = function (TimetableCard $c, int $w) use ($board, &$weekCache): string {
            $k = $c->faculty_name . '|' . $c->specialty_name . '|' . $c->course . '|' . $this->cardCurriculumId($c) . '|' . $w;

            return $weekCache[$k] ??= $this->weekStartDate($board, $c, $w)->toDateString();
        };

        $overrides = Schema::hasTable('timetable_card_overrides')
            ? DB::table('timetable_card_overrides')->whereIn('card_id', $cards->pluck('id'))->get()->groupBy('card_id')
            : collect();
        $hasOvRoom = Schema::hasColumn('timetable_card_overrides', 'auditorium_code');
        $teacherHemis = Teacher::whereIn('id', $cards->pluck('teacher_id')->filter()->unique())->pluck('hemis_id', 'id')->all();

        // Kartaning haftalik darslari (ichki jadval): [date, week, slot, len, part, employee..., room...].
        $cardLessons = function (TimetableCard $c) use ($overrides, $hasOvRoom, $gridFor, $weekMonday, $teacherHemis): array {
            $ov = ($overrides[$c->id] ?? collect())->keyBy('week');
            $out = [];
            $g = $gridFor($c);
            for ($w = 1; $w <= $g['weeks']; $w++) {
                $o = $ov[$w] ?? null;
                if ($o && $o->cancelled) {
                    continue;
                }
                $day = $o ? $o->day : $c->day;
                $pair = $o ? $o->pair : $c->pair;
                if (!$day || !$pair) {
                    continue;
                }
                $room = ($hasOvRoom && $o && $o->auditorium_code) ? [$o->auditorium_code, $o->auditorium_name] : [$c->auditorium_code, $c->auditorium_name];
                $out[] = [
                    'date' => Carbon::parse($weekMonday($c, $w))->addDays((int) $day - 1)->toDateString(),
                    'week' => $w, 'slot' => (int) $pair, 'len' => $c->lenHalf(),
                    // Sabab tushuntirish uchun: qaysi karta, tashqi (shablon) joyi.
                    'card_id' => $c->id, 'day' => (int) $day, 'ext_day' => (int) $c->ext_day, 'ext_pair' => (int) $c->ext_pair,
                    // Xona qayerdan: shu haftaning ichki jadvalida alohida berilgan yoki kartochkaning o'zida.
                    'room_from' => ($hasOvRoom && $o && $o->auditorium_code) ? 'week' : 'card', 'card_room_name' => $c->auditorium_name,
                    'part' => $c->training_type === 'lecture' ? 'L' : 'A',
                    'employee_id' => $c->teacher_id ? ($teacherHemis[$c->teacher_id] ?? null) : null,
                    'employee_name' => $c->teacher_id ? $c->teacher_name : null,
                    'auditorium_code' => $room[0] ?: null, 'auditorium_name' => $room[1] ?: null,
                ];
            }

            return $out;
        };

        // Guruhlar: kartalardagi guruhcha nomlari. Nom fakultetlararo takrorlanadi ("1K-01a" ikki davolash
        // fakultetida) — kalit fakultet + nom. HEMIS'dagi nom til qo'shimchasi bilan ("… (rus)") ham, usiz ham
        // bo'lishi mumkin: avval to'liq nomi, so'ng qo'shimchasiz nomi bo'yicha; bir xil nomlilardan — o'z fakulteti.
        $cleanGroup = fn ($n) => trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', (string) $n));
        $facKey = fn ($f) => mb_strtolower(trim((string) $f));
        $cardGroups = fn (TimetableCard $c) => array_values(array_filter(array_map(fn ($n) => trim((string) $n), $c->occupiedGroups())));
        $groupKey = fn (TimetableCard $c, string $raw) => $facKey($c->faculty_name) . '|' . $this->groupNameKey($raw);
        $groupNames = [];
        foreach ($cards as $c) {
            foreach ($cardGroups($c) as $raw) {
                $groupNames[$groupKey($c, $raw)] ??= ['name' => $cleanGroup($raw), 'raw' => $raw, 'card' => $c];
            }
        }
        // Sikl fanlari kartalaridagi guruhlar ham (4–6 kursda hamma fan sikl bo'lishi mumkin).
        foreach ($cycleCards as $c) {
            foreach ($cardGroups($c) as $raw) {
                $groupNames[$groupKey($c, $raw)] ??= ['name' => $cleanGroup($raw), 'raw' => $raw, 'card' => $c];
            }
        }
        $cycleLessons = $cycleCards->isNotEmpty() ? $this->hemisCycleLessons($board, $scope, $cycleCards) : [];
        if (!empty($scope['groups'])) {
            $want = [];
            foreach ($scope['groups'] as $g) {
                $want[$this->groupNameKey($g)] = true;
                $want[$this->groupNameKey($cleanGroup($g))] = true;
            }
            $groupNames = array_filter($groupNames, fn ($g) => isset($want[$this->groupNameKey($g['raw'])]) || isset($want[$this->groupNameKey($g['name'])]));
        }
        $resolveGroup = $this->hemisGroupResolver($board, $groupNames);
        $hasCsActive = Schema::hasColumn('curriculum_subjects', 'is_active');

        $kuzgi = $board->semester_parity !== 'bahorgi';
        // "Kafedra mudiri xonasi" (faqat faollari; aynan shu nomlisi ustuvor): avval fanning o'z kafedrasiniki
        // (auditoriums.department_hemis_id), so'ng kafedrasiz umumiy xona, bo'lmasa — istalgan shunday xona.
        // Biriktirilmagan darslar uchun vaqtinchalik chora: HEMIS'da xona ustma-ust tushishiga ruxsat yoqilgan,
        // shuning uchun bu xona hech qachon to'siq emas.
        $mudirRooms = Auditorium::where('name', 'like', '%kafedra mudiri%')->where('active', true)
            ->orderByRaw('LOWER(TRIM(name)) = ? DESC', ['kafedra mudiri xonasi'])->orderBy('id')->get();
        $fallbackRoom = $mudirRooms->first();
        $fallbackRoomFor = function ($deptId) use ($mudirRooms): ?array {
            $room = ($deptId ? $mudirRooms->first(fn ($a) => (string) $a->department_hemis_id === (string) $deptId) : null)
                ?? $mudirRooms->first(fn ($a) => !$a->department_hemis_id)
                ?? $mudirRooms->first();

            return $room ? [$room->code, $room->name] : null;
        };
        // until_week yuborilmasa — joriy hafta oxirigacha; 0 — semestr oxirigacha.
        $untilWeek = array_key_exists('until_week', $scope) && $scope['until_week'] !== null ? (int) $scope['until_week'] : null;
        $weeksOut = [];
        $currentWeekOut = 0;
        $mudirCache = [];
        $mudirFor = function ($deptId) use (&$mudirCache) {
            if (!$deptId) {
                return null;
            }
            if (!array_key_exists($deptId, $mudirCache)) {
                // Faqat mark'dagi rol bo'yicha: "kafedra_mudiri" roli va shu kafedraga biriktirilgan (head_department_hemis_id,
                // bo'lmasa department_hemis_id). HEMIS lavozimi hisobga olinmaydi. HEMIS xodim id'si borlari ustuvor.
                $q = Teacher::headsOfDepartment($deptId)->where('is_active', true);
                $t = (clone $q)->whereNotNull('hemis_id')->where('hemis_id', '>', 0)->first() ?? $q->first();
                // Topildi-yu HEMIS id'si yo'q — [null, ism] (to'siq matnida nomi chiqadi).
                $mudirCache[$deptId] = $t ? [$t->hemis_id ? (int) $t->hemis_id : null, $t->full_name] : null;
            }

            return $mudirCache[$deptId];
        };
        $planner = new SchedulePlanner($slotTimes, $today, array_fill_keys(array_keys(self::HEMIS_PAIRS), true));

        $groupsOut = [];
        $explain = null;
        $missingGroups = [];
        $finals = [];        // barcha guruhlarning yakuniy (rejadagi) yozuvlari — guruhlararo tekshiruv uchun
        $handledIds = [];    // rejada qayta yoziladigan HEMIS yozuvlari (qolganlari tashqi bandlik)
        foreach ($groupNames as $gk => $gInfo) {
            ['name' => $gname, 'card' => $sample] = $gInfo;
            $grp = $resolveGroup($gInfo);
            if (!$grp) {
                $missingGroups[] = $gname;
                continue;
            }
            $course = (int) $sample->course;
            $semCode = (string) ($kuzgi ? 10 + 2 * $course - 1 : 10 + 2 * $course);
            $csQ = DB::table('curriculum_subjects')->where('curricula_hemis_id', $grp->curriculum_hemis_id)->where('semester_code', $semCode);
            if ($hasCsActive) {
                $csQ->where('is_active', true);
            }
            $csRows = $csQ->get();
            $ktrPlans = Schema::hasTable('ktr_plans')
                ? DB::table('ktr_plans')->whereIn('curriculum_subject_id', $csRows->pluck('id'))->get()->keyBy('curriculum_subject_id')
                : collect();
            $hemisAll = DB::table('schedules')->where('group_id', $grp->group_hemis_id)->where('semester_code', $semCode)
                ->whereNull('deleted_at')->whereNotNull('lesson_date')
                ->get(['schedule_hemis_id', 'subject_id', 'subject_name', 'training_type_code', 'training_type_name', 'lesson_date',
                    'lesson_pair_start_time', 'lesson_pair_end_time', 'employee_id', 'employee_name', 'auditorium_code', 'auditorium_name', 'updated_at']);

            $gCards = $cards->filter(fn ($c) => in_array($gk, array_map(fn ($n) => $groupKey($c, $n), $cardGroups($c)), true))->values();
            $usedCards = [];
            $units = [];
            $unitMeta = [];
            $handledSubjects = [];
            // O'tkazilgan fanlar; kind: finished — darslari tugagan (bugun va o'tgan kunlar HEMIS'da tahrirlanmaydi, qiladigan
            // ish yo'q), unplaced — sikl fani ichki jadvalda joylanmagan, no_card — LMS doskasida kartochka yo'q.
            $skipped = [];
            foreach ($csRows as $cs) {
                $details = is_string($cs->subject_details) ? json_decode($cs->subject_details, true) : $cs->subject_details;
                $codes = [];
                $codeNames = [];
                foreach ((array) $details as $d) {
                    $code = (string) ($d['trainingType']['code'] ?? '');
                    $part = $this->hemisPartOf((string) ($d['trainingType']['name'] ?? ''));
                    if ($code !== '' && $part !== null && (int) ($d['academic_load'] ?? 0) > 0) {
                        $codes[$code] = $part;
                        $codeNames[$code] = (string) $d['trainingType']['name'];
                    }
                }
                if (!$codes) {
                    continue;
                }
                $key = $this->hemisSubjectKey((string) $cs->subject_name);
                $base = $this->hemisSubjectBase((string) $cs->subject_name);
                $matched = $gCards->filter(function ($c) use ($key, $base) {
                    $ck = $this->hemisSubjectKey((string) $c->subject_name);
                    if ($ck === $key) {
                        return true;
                    }
                    // Bo'lakli fanning umumiy ma'ruzasi asosiy nom bilan.
                    return $c->training_type === 'lecture' && $this->variantLetter((string) $c->subject_name) === null && $ck === $base;
                });
                $hemisRows = $hemisAll->where('subject_id', $cs->subject_id);
                // Sikl fani: HEMIS LMS ichki sikl jadvaliga aynan moslashtiriladi (KTR balansi — faqat ma'lumot).
                $cyc = $cycleLessons[$gk][$key] ?? [];
                $isCycleSubject = $cycleCards->contains(fn ($c) => $this->hemisSubjectBase((string) $c->subject_name) === $base
                    && in_array($gk, array_map(fn ($n) => $groupKey($c, $n), $cardGroups($c)), true));
                if ($matched->isEmpty() && $isCycleSubject) {
                    if (!$cyc) {
                        $skipped[] = ['subject_name' => $cs->subject_name, 'reason' => 'sikl fani — LMS ichki sikl jadvalida joylanmagan, HEMIS\'ga tegilmaydi', 'kind' => 'unplaced'];
                        continue;
                    }
                    if (!array_filter($cyc, fn ($p) => $p['date'] > $today)) {
                        $skipped[] = ['subject_name' => $cs->subject_name, 'reason' => 'sikl fani — ichki sikl jadvalida ertadan keyin dars yo\'q, HEMIS\'ga tegilmaydi', 'kind' => 'finished'];
                        continue;
                    }
                    $handledSubjects[(int) $cs->subject_id] = ($handledSubjects[(int) $cs->subject_id] ?? []) + $codes;
                    foreach ($hemisRows as $hr) {
                        if (isset($codes[(string) $hr->training_type_code])) {
                            $handledIds[(int) $hr->schedule_hemis_id] = true;
                        }
                    }
                    $kp = $ktrPlans[$cs->id] ?? null;
                    $ktr = null;
                    if ($kp) {
                        $pd = is_string($kp->plan_data) ? json_decode($kp->plan_data, true) : $kp->plan_data;
                        $hours = (array) ($pd['hours'] ?? $pd ?? []);
                        $ktr = [];
                        for ($w = 1; $w <= (int) $kp->week_count; $w++) {
                            $ktr[] = array_map('intval', (array) ($hours[$w] ?? $hours[(string) $w] ?? []));
                        }
                    }
                    // KTR qatori = sikl kuni; "joriy" — bugungacha o'tgan sikl kunlari.
                    $ktrDates = [];
                    $doneDays = 0;
                    foreach ($cyc as $l) {
                        $ktrDates[$l['week']] ??= $l['date'];
                        if ($l['date'] <= $today) {
                            $doneDays = max($doneDays, (int) $l['week']);
                        }
                    }
                    $mudir = $mudirFor($cs->department_id);
                    $units[] = [
                        'key' => $gk . '|' . $cs->id,
                        'subject_id' => (int) $cs->subject_id,
                        'subject_name' => $cs->subject_name,
                        'base' => $base,
                        'codes' => $codes, 'code_names' => $codeNames,
                        'ktr' => $ktr,
                        'ktr_dates' => $ktrDates,
                        'current_week' => $doneDays,
                        'mirror' => true,
                        'hemis' => $hemisRows->map(fn ($r) => [
                            'id' => (int) $r->schedule_hemis_id, 'date' => substr((string) $r->lesson_date, 0, 10),
                            'start' => substr((string) $r->lesson_pair_start_time, 0, 5), 'end' => substr((string) $r->lesson_pair_end_time, 0, 5),
                            'code' => (string) $r->training_type_code, 'employee_id' => $r->employee_id, 'employee_name' => $r->employee_name,
                            'auditorium_code' => $r->auditorium_code, 'auditorium_name' => $r->auditorium_name,
                        ])->values()->all(),
                        'plan' => $cyc,
                        'fallback_employee' => $mudir,
                        'fallback_room' => $fallbackRoomFor($cs->department_id),
                    ];
                    $otherTypes = $hemisRows->filter(fn ($r) => substr((string) $r->lesson_date, 0, 10) > $today && !isset($codes[(string) $r->training_type_code]))->count();
                    $unitMeta[$gk . '|' . $cs->id] = ['kafedra' => $cs->department_name, 'cs_id' => $cs->id, 'mudir' => $mudir[1] ?? null,
                        'other_type_rows' => $otherTypes, 'cycle' => true];
                    continue;
                }
                if ($matched->isEmpty()) {
                    if ($hemisRows->isNotEmpty()) {
                        $skipped[] = ['subject_name' => $cs->subject_name, 'reason' => 'LMS doskasida kartochka yo\'q — HEMIS\'ga tegilmaydi', 'kind' => 'no_card'];
                    }
                    continue;
                }
                foreach ($matched as $c) {
                    $usedCards[$c->id] = true;
                }
                $plan = [];
                $unplacedCard = false;
                foreach ($matched as $c) {
                    $lessons = $cardLessons($c);
                    $unplacedCard = $unplacedCard || !$lessons;
                    array_push($plan, ...$lessons);
                }
                if (!array_filter($plan, fn ($p) => $p['date'] > $today)) {
                    // Ichki jadvalda birorta darsi yo'q (joylanmagan) karta bo'lsa — fan tugamagan, bu haqiqiy muammo.
                    $skipped[] = ['subject_name' => $cs->subject_name, 'reason' => 'LMS ichki jadvalida ertadan keyin dars yo\'q — HEMIS\'ga tegilmaydi',
                        'kind' => $unplacedCard ? 'unplaced' : 'finished'];
                    continue;
                }
                // Faqat shu birlik turlari (kodlari) uning rejasida; boshqa turdagi HEMIS darslari guruh bandligida qoladi.
                $handledSubjects[(int) $cs->subject_id] = ($handledSubjects[(int) $cs->subject_id] ?? []) + $codes;
                foreach ($hemisRows as $hr) {
                    if (isset($codes[(string) $hr->training_type_code])) {
                        $handledIds[(int) $hr->schedule_hemis_id] = true;
                    }
                }
                $kp = $ktrPlans[$cs->id] ?? null;
                $ktr = null;
                if ($kp) {
                    $pd = is_string($kp->plan_data) ? json_decode($kp->plan_data, true) : $kp->plan_data;
                    $hours = (array) ($pd['hours'] ?? $pd ?? []);
                    $ktr = [];
                    for ($w = 1; $w <= (int) $kp->week_count; $w++) {
                        $ktr[] = array_map('intval', (array) ($hours[$w] ?? $hours[(string) $w] ?? []));
                    }
                }
                $first = $matched->first();
                $currentWeek = 0;
                for ($w = 1; $w <= $gridFor($first)['weeks']; $w++) {
                    if ($weekMonday($first, $w) <= $today) {
                        $currentWeek = $w;
                    }
                }
                $mudir = $mudirFor($cs->department_id);
                $units[] = [
                    'key' => $gk . '|' . $cs->id,
                    'subject_id' => (int) $cs->subject_id,
                    'subject_name' => $cs->subject_name,
                    'base' => $base,
                    'codes' => $codes, 'code_names' => $codeNames,
                    'ktr' => $ktr,
                    'current_week' => $currentWeek,
                    'hemis' => $hemisRows->map(fn ($r) => [
                        'id' => (int) $r->schedule_hemis_id, 'date' => substr((string) $r->lesson_date, 0, 10),
                        'start' => substr((string) $r->lesson_pair_start_time, 0, 5), 'end' => substr((string) $r->lesson_pair_end_time, 0, 5),
                        'code' => (string) $r->training_type_code, 'employee_id' => $r->employee_id, 'employee_name' => $r->employee_name,
                        'auditorium_code' => $r->auditorium_code, 'auditorium_name' => $r->auditorium_name,
                    ])->values()->all(),
                    'plan' => $plan,
                    'fallback_employee' => $mudir,
                    'fallback_room' => $fallbackRoomFor($cs->department_id),
                ];
                $otherTypes = $hemisRows->filter(fn ($r) => substr((string) $r->lesson_date, 0, 10) > $today && !isset($codes[(string) $r->training_type_code]))->count();
                $unitMeta[$gk . '|' . $cs->id] = ['kafedra' => $cs->department_name, 'cs_id' => $cs->id, 'mudir' => $mudir[1] ?? null,
                    'other_type_rows' => $otherTypes];
            }

            // Guruhning boshqa bandligi: rejaga kirmagan HEMIS darslari va LMS'da fani topilmagan kartalar.
            $busy = [];
            foreach ($hemisAll as $r) {
                $d = substr((string) $r->lesson_date, 0, 10);
                if ($d <= $today || isset($handledSubjects[(int) $r->subject_id][(string) $r->training_type_code])) {
                    continue;
                }
                foreach ($toSlots((string) $r->lesson_pair_start_time, (string) $r->lesson_pair_end_time) as $s) {
                    $busy[$d][$s] = true;
                }
            }
            $unmatchedCards = [];
            foreach ($gCards as $c) {
                if (isset($usedCards[$c->id])) {
                    continue;
                }
                $unmatchedCards[$c->subject_name] = true;
                foreach ($cardLessons($c) as $l) {
                    for ($i = 0; $i < $l['len']; $i++) {
                        $busy[$l['date']][$l['slot'] + $i] = true;
                    }
                }
            }

            // Ertadan semestr oxirigacha dars kunlari.
            $workDates = [];
            $g = $gridFor($sample);
            for ($w = 1; $w <= $g['weeks']; $w++) {
                $mon = Carbon::parse($weekMonday($sample, $w));
                for ($d = 0; $d < $g['days']; $d++) {
                    $ds = $mon->copy()->addDays($d)->toDateString();
                    if ($ds > $today) {
                        $workDates[$ds] = $w;
                    }
                }
            }

            // Haftalar ro'yxati (oyna uchun) va "shu haftagacha" chegarasi.
            $until = null;
            $gCurrent = 0;
            for ($w = 1; $w <= $g['weeks']; $w++) {
                $mon = $weekMonday($sample, $w);
                $weeksOut[$w] ??= ['week' => $w, 'from' => $mon, 'to' => Carbon::parse($mon)->addDays(6)->toDateString()];
                if ($mon <= $today) {
                    $gCurrent = $w;
                }
            }
            $currentWeekOut = max($currentWeekOut, $gCurrent);
            $uw = $untilWeek ?? $gCurrent;
            if ($uw > 0) {
                $until = Carbon::parse($weekMonday($sample, min($uw, $g['weeks'])))->addDays(6)->toDateString();
            }
            $results = $units ? $planner->planGroup($units, $busy, $workDates) : [];
            // Hisob davri: haftalar oralig'i (sukut 1 — joriy hafta).
            $pFrom = max(1, (int) ($scope['period_from'] ?? 1));
            $pTo = max($pFrom, min($g['weeks'], (int) ($scope['period_to'] ?? max(1, $gCurrent))));
            $pFromDate = $weekMonday($sample, $pFrom);
            $pToDate = Carbon::parse($weekMonday($sample, $pTo))->addDays(6)->toDateString();
            $unitByKey = [];
            foreach ($units as $uu) {
                $unitByKey[$uu['key']] = $uu;
            }
            $explain ??= $this->hemisMoveExplainer($board, $slotTimes, $isCycle);
            foreach ($results as &$r) {
                $r += $unitMeta[$r['key']] ?? [];
                // To'siqni bartaraf etish uchun (hemisCrossCheck): kafedra mudiri va uning xonasi; javobdan olib tashlanadi.
                $r['_fb'] = [$unitByKey[$r['key']]['fallback_employee'] ?? null, $unitByKey[$r['key']]['fallback_room'] ?? null];
                // HEMIS'dagi asl yozuvlar (id bo'yicha) — "o'zgartirish" kerakmi, ismi emas, id bo'yicha hal qilinadi.
                $r['_hemis'] = array_column($unitByKey[$r['key']]['hemis'] ?? [], null, 'id');
                $r['period'] = isset($unitByKey[$r['key']])
                    ? $this->hemisPeriodStats($unitByKey[$r['key']], $pFrom, $pTo, $pFromDate, $pToDate) : null;
                if (!empty($r['other_type_rows'])) {
                    $r['notes'][] = "HEMIS'da rejaga kirmagan turdagi {$r['other_type_rows']} ta kelajak dars bor — o'zgartirilmaydi, vaqti band hisoblanadi.";
                }
                foreach ($r['records'] as $rec) {
                    $finals[] = ['key' => $r['key'], 'label' => $gname . ' · ' . $r['subject_name']] + $rec;
                }
                unset($r['records']);
                $r['actions_later'] = 0;
                if ($until !== null) {
                    $r['actions_later'] = count(array_filter($r['actions'], fn ($a) => $a['date'] > $until));
                    $r['actions'] = array_values(array_filter($r['actions'], fn ($a) => $a['date'] <= $until));
                }
                // Qo'shiladigan / o'zgartiriladigan dars tashqi jadvaldagi (Excel) joyidan boshqa vaqtda bo'lsa — nega.
                $plan = $unitByKey[$r['key']]['plan'] ?? [];
                foreach ($r['actions'] as &$a) {
                    if ($a['op'] === 'delete') {
                        continue;
                    }
                    $slot = $startToSlot[$a['start']] ?? null;
                    $lesson = null;
                    foreach ($plan as $l) {
                        if ($l['date'] === $a['date'] && $slot && $slot >= $l['slot'] && $slot < $l['slot'] + $l['len']) {
                            $lesson = $l;
                            break;
                        }
                    }
                    // To'siq so'rovi (Telegram) uchun: qaysi karta, qaysi hafta.
                    if ($lesson && empty($lesson['cycle'])) {
                        $a['card_id'] = $lesson['card_id'] ?? null;
                        $a['week'] = $lesson['week'] ?? null;
                    }
                    $why = [];
                    $timeChanged = $a['op'] === 'create' || (bool) array_filter($a['changes'] ?? [], fn ($x) => str_starts_with($x, 'vaqt'));
                    if ($timeChanged) {
                        $why[] = !empty($lesson['cycle'])
                            ? 'LMS ichki sikl jadvali bo\'yicha (' . $lesson['week'] . '-sikl kuni).'
                            : ($lesson ? $explain($lesson) : 'KTR balansi uchun qo\'shilgan dars (tuzatish takliflariga qarang).');
                    }
                    if (in_array('mudir', $a['flags'] ?? [], true)) {
                        $why[] = 'LMS kartochkasida o\'qituvchi biriktirilmagan — kafedra mudiri qo\'yiladi.';
                    }
                    if (in_array('mudir_xonasi', $a['flags'] ?? [], true)) {
                        $why[] = 'LMS kartochkasida xona biriktirilmagan — «Kafedra mudiri xonasi» qo\'yiladi.';
                    }
                    // Xona yoki o'qituvchi LMS'da boshqa — qayerda biriktirilgani.
                    $changed = fn (string $p) => (bool) array_filter($a['changes'] ?? [], fn ($x) => str_starts_with($x, $p));
                    if ($changed('xona') && !in_array('mudir_xonasi', $a['flags'] ?? [], true)) {
                        $why[] = ($lesson && ($lesson['room_from'] ?? '') === 'week')
                            ? 'Xona ichki jadvalda shu hafta uchun alohida berilgan: ' . $a['auditorium_name']
                                . ($lesson['card_room_name'] ? ' (kartochkada — ' . $lesson['card_room_name'] . ')' : '') . '.'
                            : 'LMS kartochkasida biriktirilgan xona: ' . $a['auditorium_name'] . ' — HEMIS\'dagidan farq qiladi.';
                    }
                    if ($changed('o\'qituvchi') && !in_array('mudir', $a['flags'] ?? [], true)) {
                        $why[] = 'LMS kartochkasida biriktirilgan o\'qituvchi: ' . $a['employee_name'] . ' — HEMIS\'dagidan farq qiladi.';
                    }
                    if ($changed('tur')) {
                        $why[] = !empty($lesson['cycle']) ? 'Dars turi shu sikl kunining KTR qatoriga qarab belgilangan.'
                            : 'Dars turi shu haftaning KTR qatoriga qarab belgilangan.';
                    }
                    $a['reason'] = implode(' ', array_filter($why)) ?: null;
                }
                unset($a);
            }
            unset($r);

            $groupsOut[] = [
                'group' => $gname,
                'group_hemis_id' => (int) $grp->group_hemis_id,
                'curriculum_hemis_id' => (int) $grp->curriculum_hemis_id,
                'semester_code' => $semCode,
                'education_year' => (int) substr((string) $board->academic_year, 0, 4),
                'until' => $until,
                // Guruhning o'z hisob davri (kalendari boshqacha guruhlarda sukut — o'z joriy haftasi).
                'period_from' => $pFrom,
                'period_to' => $pTo,
                'imported_at' => $hemisAll->max('updated_at'),
                'units' => $results,
                'skipped' => $skipped,
                'cards_without_subject' => array_keys($unmatchedCards),
            ];
        }

        // Band o'qituvchi/xona o'rniga kafedra mudiri — faqat doska sozlamasi yoqilgan bo'lsa (sukut: to'qnashuv — to'siq).
        $this->hemisCrossCheck($groupsOut, $finals, $handledIds, $today, $mudirFor,
            filter_var(($board->settings ?? [])['hemis_mudir_fallback'] ?? false, FILTER_VALIDATE_BOOLEAN));

        return [
            'today' => $today,
            'board_id' => $board->id,
            'fallback_room' => $fallbackRoom ? ['code' => $fallbackRoom->code, 'name' => $fallbackRoom->name] : null,
            'hemis_pairs' => self::HEMIS_PAIRS,
            'until_week' => $untilWeek ?? $currentWeekOut,
            'period_from' => max(1, (int) ($scope['period_from'] ?? 1)),
            'period_to' => (int) ($scope['period_to'] ?? max(1, $currentWeekOut)),
            'current_week' => $currentWeekOut,
            'weeks' => array_values($weeksOut),
            'groups' => $groupsOut,
            'groups_not_in_hemis' => $missingGroups,
            // Joylanmagan kartalar HEMIS'ga yozilmaydi — oyna ogohlantiradi (oldindan ogohlantirish bilan bir xil hisob).
            'unplaced' => $this->hemisUnplacedSummary($board, $scope, $today),
        ];
    }
    /**
     * Guruhlar alohida rejalashtiriladi — qo'shiladigan/o'zgaradigan dars o'qituvchi yoki xona bo'yicha boshqa
     * guruhning yakuniy darsi yoki HEMIS'dagi boshqa (rejaga kirmagan) dars bilan ustma-ust tushsa, amal to'siqli.
     * O'sha o'qituvchi o'sha xonada bo'lsa — bu bitta dars (oqim ma'ruzasi, birlashgan amaliy), to'qnashuv emas.
     * O'qituvchilar ustma-ust tushmaydi; yagona istisno — o'qituvchi biriktirilmagani uchun kafedra mudiriga
     * majburan qo'yilgan darslar (o'rinbosar, yoki HEMIS'dagi dars o'qituvchisi — shu fan kafedrasining mudiri).
     * "Kafedra mudiri xonasi" (vaqtincha ustma-ust tushishiga ruxsat bor) va umumiy (raqamsiz) xonalar tekshirilmaydi.
     * $mudirFallback (doska sozlamasi hemis_mudir_fallback, sukut o'chiq): to'qnashuvda band o'qituvchi o'rniga kafedra
     * mudiri, band xona o'rniga "Kafedra mudiri xonasi" qo'yiladi. O'chiq bo'lsa — almashtirilmaydi: amal to'siqli
     * (JSON'ga kirmaydi), to'siqda kim/qaysi xona, qaysi dars bilan va qaysi vaqtda band ekani yoziladi.
     */
    private function hemisCrossCheck(array &$groupsOut, array $finals, array $handledIds, string $today, callable $mudirFor, bool $mudirFallback = false): void
    {
        $idx = [];   // "e|xodim|sana" / "r|xona|sana" => [[boshlanish, tugash, birlik, matn, xodim, xona], ...]
        $emps = [];
        $rooms = [];
        $freeRoom = fn (array $r) => in_array('mudir_xonasi', $r['flags'] ?? [], true) || $this->isSharedRoom($r['auditorium_name'] ?? null)
            || preg_match('/kafedra\s+mudiri/iu', (string) ($r['auditorium_name'] ?? '')) === 1;
        // $info — bandlik tafsiloti (to'siq oynasi uchun): manba, guruh, fan, o'qituvchi, xona.
        $index = function (string $type, $id, string $date, string $start, string $end, string $unitKey, string $label, $emp, $room, array $info = []) use (&$idx) {
            $idx[$type . '|' . $id . '|' . $date][] = [$start, $end, $unitKey, $label, (string) $emp, (string) $room, $info];
        };
        foreach ($finals as $f) {
            [$fGroup, $fSubj] = array_pad(explode(' · ', (string) $f['label'], 2), 2, '');
            $fInfo = ['source' => 'LMS', 'group' => $fGroup, 'subject' => $fSubj, 'teacher' => $f['employee_name'] ?? null, 'room' => $f['auditorium_name'] ?? null];
            if ($f['employee_id'] && !in_array('mudir', $f['flags'] ?? [], true)) {
                $index('e', $f['employee_id'], $f['date'], $f['start'], $f['end'], $f['key'], $f['label'], $f['employee_id'], $f['auditorium_code'], $fInfo);
                $emps[(string) $f['employee_id']] = true;
            }
            if ($f['auditorium_code'] && !$freeRoom($f)) {
                $index('r', $f['auditorium_code'], $f['date'], $f['start'], $f['end'], $f['key'], $f['label'], $f['employee_id'], $f['auditorium_code'], $fInfo);
                $rooms[(string) $f['auditorium_code']] = true;
            }
        }
        if ($emps || $rooms) {
            $ext = DB::table('schedules')->whereNull('deleted_at')->where('lesson_date', '>', $today . ' 23:59:59')
                ->where(fn ($q) => $q->whereIn('employee_id', array_keys($emps))->orWhereIn('auditorium_code', array_keys($rooms)))
                ->get(['schedule_hemis_id', 'group_name', 'subject_name', 'lesson_date', 'lesson_pair_start_time', 'lesson_pair_end_time',
                    'employee_id', 'employee_name', 'auditorium_code', 'auditorium_name', 'department_id']);
            foreach ($ext as $r) {
                if (isset($handledIds[(int) $r->schedule_hemis_id])) {
                    continue;
                }
                $date = substr((string) $r->lesson_date, 0, 10);
                $start = substr((string) $r->lesson_pair_start_time, 0, 5);
                $end = substr((string) $r->lesson_pair_end_time, 0, 5);
                $label = 'HEMIS: ' . $r->group_name . ' · ' . $r->subject_name;
                $rInfo = ['source' => 'HEMIS', 'group' => $r->group_name, 'subject' => $r->subject_name, 'teacher' => $r->employee_name, 'room' => $r->auditorium_name];
                // Kafedra mudiriga majburan qo'yilgan HEMIS darsi (o'qituvchisi — shu fan kafedrasining mudiri) bandlik emas.
                $forcedMudir = $r->employee_id && (string) (($mudirFor($r->department_id) ?? [null])[0]) === (string) $r->employee_id;
                if ($r->employee_id && isset($emps[(string) $r->employee_id]) && !$forcedMudir) {
                    $index('e', $r->employee_id, $date, $start, $end, '', $label, $r->employee_id, $r->auditorium_code, $rInfo);
                }
                if ($r->auditorium_code && isset($rooms[(string) $r->auditorium_code]) && !$this->isSharedRoom($r->auditorium_name)) {
                    $index('r', $r->auditorium_code, $date, $start, $end, '', $label, $r->employee_id, $r->auditorium_code, $rInfo);
                }
            }
        }
        // To'qnashuv darsning joriy o'qituvchi/xonasi bilan izlanadi: bir tur almashtirilgach ikkinchisi qayta
        // tekshiriladi — asl qiymat bilan "bitta dars" deb o'tkazilgan sherik dars endi to'qnashishi mumkin.
        $findHit = function (array $a, string $unitKey, string $type) use (&$idx, $freeRoom): ?array {
            $emp = (string) ($a['employee_id'] ?? '');
            $room = (string) ($a['auditorium_code'] ?? '');
            [$id, $flag, $msg] = $type === 'e' ? [$emp, 'mudir', "o'qituvchi band"] : [$room, 'mudir_xonasi', 'xona band'];
            if ($id === '' || in_array($flag, $a['flags'] ?? [], true) || ($type === 'r' && $freeRoom($a))) {
                return null;
            }
            foreach ($idx[$type . '|' . $id . '|' . $a['date']] ?? [] as [$s, $e, $k, $label, $oEmp, $oRoom, $info]) {
                if ($k === $unitKey && $s === $a['start']) {
                    continue;   // shu yozuvning o'zi
                }
                if ($emp !== '' && $room !== '' && $oEmp === $emp && $oRoom === $room) {
                    continue;   // bitta dars bir necha guruhga (umumiy karta yoki HEMIS'dagi oqim)
                }
                if ($s < $a['end'] && $a['start'] < $e) {
                    // Kim (o'qituvchi) yoki qaysi xona band, qaysi dars bilan, qaysi vaqtda (va u darsning xonasi/o'qituvchisi).
                    $who = $type === 'e' ? (string) ($a['employee_name'] ?? '') : (string) ($a['auditorium_name'] ?? $id);
                    $where = $type === 'e' ? (($info['room'] ?? null) ? ', xona ' . $info['room'] : '')
                        : (($info['teacher'] ?? null) ? ", o'qituvchi " . $info['teacher'] : '');
                    $text = "{$msg}: " . ($who !== '' ? $who . ' — ' : '') . "{$label} {$s}-{$e}{$where}";

                    return ['type' => $type === 'r' ? 'room' : 'teacher', 'time' => $s . '–' . $e, 'text' => $text,
                        'room' => $type === 'r' ? ($a['auditorium_name'] ?? $id) : ($info['room'] ?? null),
                        'teacher' => $type === 'e' ? ($a['employee_name'] ?? null) : ($info['teacher'] ?? null)] + $info;
                }
            }

            return null;
        };
        // Umumiy dars: bir vaqtda, bitta o'qituvchi bitta xonada bir necha guruhga (oqim ma'ruzasi, birlashgan amaliy).
        // Har guruhda alohida yozuv — lekin to'qnashuvda bitta birlik: bo'linib ketmasin.
        $shareKey = fn (array $r) => (string) ($r['employee_id'] ?? '') !== '' && (string) ($r['auditorium_code'] ?? '') !== ''
            ? $r['date'] . '|' . $r['start'] . '|' . $r['end'] . '|' . $r['employee_id'] . '|' . $r['auditorium_code'] : null;
        $shareSize = [];   // umumiy dars kaliti => guruhlar soni (HEMIS'da o'zgarmaydiganlari ham)
        foreach ($finals as $f) {
            if ($k = $shareKey($f)) {
                $shareSize[$k] = ($shareSize[$k] ?? 0) + 1;
            }
        }
        // Yaratiladigan / o'zgartiriladigan darslar va ularning holati.
        $st = [];
        $fbOf = [];
        $hemisOf = [];
        foreach ($groupsOut as $gi => &$g) {
            foreach ($g['units'] as $ui => &$u) {
                $fbOf[$gi][$ui] = $u['_fb'] ?? [null, null];
                $hemisOf[$gi][$ui] = $u['_hemis'] ?? [];
                unset($u['_fb'], $u['_hemis']);
                foreach ($u['actions'] as $ai => $a) {
                    if (in_array($a['op'], ['create', 'update'], true)) {
                        // emp0/room0 — indeksdagi shu darsning o'z yozuvlari kaliti (asl qiymatlar).
                        $st[] = ['gi' => $gi, 'ui' => $ui, 'ai' => $ai, 'key' => $u['key'], 'share' => $shareKey($a),
                            'emp0' => (string) ($a['employee_id'] ?? ''), 'room0' => (string) ($a['auditorium_code'] ?? ''),
                            'queue' => ['e', 'r'], 'resolved' => [], 'unresolved' => []];
                    }
                }
            }
            unset($u);
        }
        unset($g);
        $byShare = [];
        foreach ($st as $i => $x) {
            if ($x['share'] !== null) {
                $byShare[$x['share']][] = $i;
            }
        }
        // Kamroq guruhli darslar oldin ko'riladi: to'qnashuvda kafedra mudiriga ular o'tadi, umumiy dars o'qituvchida qoladi.
        $size = fn (int $i) => $st[$i]['share'] !== null ? ($shareSize[$st[$i]['share']] ?? 1) : 1;
        $order = array_keys($st);
        usort($order, fn ($i, $j) => [$size($i), $i] <=> [$size($j), $j]);

        // O'rinbosarni qo'yish (o'qituvchi band — kafedra mudiri, xona band — "Kafedra mudiri xonasi").
        $apply = function (int $i, string $type, $fbId, string $fbName, array $b) use (&$st, &$groupsOut, &$idx, $hemisOf) {
            $x = &$st[$i];
            $a = &$groupsOut[$x['gi']]['units'][$x['ui']]['actions'][$x['ai']];
            $field = $type === 'e' ? 'employee' : 'auditorium';
            $what = $type === 'e' ? "o'qituvchi" : 'xona';
            $oldName = (string) ($a[$field . '_name'] ?? '');
            $a[$field . ($type === 'e' ? '_id' : '_code')] = $fbId;
            $a[$field . '_name'] = $fbName;
            // O'zgartirishda: HEMIS'dagi yozuvda allaqachon shu xodim/xona (id bo'yicha) bo'lsa — o'zgarish yo'q.
            $a['changes'] = array_values(array_filter($a['changes'] ?? [], fn ($c) => !str_starts_with($c, $what . ': ')));
            if ($a['op'] === 'update') {
                $was = $hemisOf[$x['gi']][$x['ui']][$a['hemis_id'] ?? 0] ?? [];
                if ((string) ($was[$type === 'e' ? 'employee_id' : 'auditorium_code'] ?? '') !== (string) $fbId) {
                    $a['changes'][] = $what . ': ' . (($was[$field . '_name'] ?? '') ?: '—') . ' → ' . $fbName;
                }
            }
            $a['flags'][] = $type === 'e' ? 'mudir' : 'mudir_xonasi';
            $a['flags'][] = "to'siq sababli";
            $a['resolved'][] = $b;
            // Bu dars endi o'sha o'qituvchi/xonani band qilmaydi: u bilan to'qnashgan ikkinchi LMS darsi
            // (keyinroq tekshiriladi) ham mudirga almashtirilib, o'qituvchi ikkalasidan ham ayrilmasin.
            $own = $type . '|' . ($type === 'e' ? $x['emp0'] : $x['room0']) . '|' . $a['date'];
            $idx[$own] = array_values(array_filter($idx[$own] ?? [], fn ($r) => !($r[2] === $x['key'] && $r[0] === $a['start'])));
            // Ikkinchi indeksdagi yozuvi ham haqiqiy qiymat bilan: "o'sha o'qituvchi o'sha xonada — bitta dars"
            // istisnosi eski o'qituvchi/xona bilan ishlab, keyingi to'qnashuvni yashirmasin.
            $other = ($type === 'e' ? 'r|' . $x['room0'] : 'e|' . $x['emp0']) . '|' . $a['date'];
            foreach ($idx[$other] ?? [] as $ri => $r) {
                if ($r[2] === $x['key'] && $r[0] === $a['start']) {
                    $idx[$other][$ri][$type === 'e' ? 4 : 5] = (string) $fbId;
                }
            }
            $a['reason'] = trim(($a['reason'] ?? '') . ' ' . ($type === 'e'
                ? "LMS'dagi o'qituvchi {$oldName} shu vaqtda band ({$b['source']} · {$b['group']} · «{$b['subject']}» {$b['time']}) — to'siq sababli kafedra mudiri qo'yildi."
                : "LMS'dagi xona {$oldName} shu vaqtda band ({$b['source']} · {$b['group']} · «{$b['subject']}» {$b['time']}) — to'siq sababli «{$fbName}» qo'yildi."));
            $x['resolved'][$type] = true;
            unset($x['unresolved'][$type]);
            // Endi ikkinchi tur yangi qiymat bilan qayta tekshiriladi (hal qilinmagan to'sig'i ham).
            $x['queue'][] = $type === 'e' ? 'r' : 'e';
        };
        // Darsning navbatdagi tekshiruvlari. Mudir/xona topilmasa yoki band o'qituvchining o'zi mudir bo'lsa — to'siq
        // "hal qilinmagan" bo'lib qoladi va oxirida yakuniy bandlik bo'yicha qayta tekshiriladi.
        $drain = function (int $i) use (&$drain, &$st, &$groupsOut, $findHit, $fbOf, $byShare, $shareSize, $apply, $mudirFallback) {
            $touched = [];
            while ($st[$i]['queue']) {
                $type = array_shift($st[$i]['queue']);
                if (isset($st[$i]['resolved'][$type])) {
                    continue;
                }
                unset($st[$i]['unresolved'][$type]);
                $x = $st[$i];
                $a = $groupsOut[$x['gi']]['units'][$x['ui']]['actions'][$x['ai']];
                if (!($b = $findHit($a, $x['key'], $type))) {
                    continue;
                }
                $fb = fn (int $j) => $fbOf[$st[$j]['gi']][$st[$j]['ui']][$type === 'e' ? 0 : 1] ?: [null, null];
                [$fbId, $fbName] = $mudirFallback ? $fb($i) : [null, null];
                if (!$fbId || ($type === 'e' && (string) $fbId === (string) ($a['employee_id'] ?? ''))) {
                    $st[$i]['unresolved'][$type] = $b;
                    continue;
                }
                // Umumiy dars hammasi bilan birga almashadi: barcha guruhlari almashtiriladigan darslar, hali o'zgarmagan va
                // o'rinbosari bir xil bo'lsa (aks holda — faqat shu dars, avvalgidek).
                $with = [$i];
                if ($x['share'] !== null && !$x['resolved'] && count($byShare[$x['share']]) === ($shareSize[$x['share']] ?? 0)) {
                    $mates = array_values(array_filter($byShare[$x['share']], fn ($j) => $j !== $i));
                    if (!array_filter($mates, fn ($j) => $st[$j]['resolved'] || (string) ($fb($j)[0] ?? '') !== (string) $fbId)) {
                        $with = array_merge($with, $mates);
                        array_push($touched, ...$mates);
                    }
                }
                foreach ($with as $j) {
                    $apply($j, $type, $fbId, (string) $fbName, $b);
                }
            }
            foreach ($touched as $j) {
                $drain($j);
            }
        };
        foreach ($order as $i) {
            $drain($i);
        }

        // Yakuniy tekshiruv: hal qilinmagan to'siq hozir ham turgan bo'lsagina yoziladi (band qilgan dars — hozirgisi);
        // HEMIS'da allaqachon shunday (mudir/xona) bo'lgan o'zgartirish tashlab yuboriladi.
        $drop = [];
        foreach ($st as $x) {
            $a = &$groupsOut[$x['gi']]['units'][$x['ui']]['actions'][$x['ai']];
            foreach (array_keys($x['unresolved']) as $type) {
                if ($b = $findHit($a, $x['key'], $type)) {
                    $a['blocked'][] = $b['text'];
                    $a['blocks'][] = $b;
                }
            }
            $a['flags'] = array_values(array_unique($a['flags'] ?? []));
            if ($a['op'] === 'update' && !$a['changes'] && !($a['blocked'] ?? [])) {
                $drop[$x['gi']][$x['ui']][$x['ai']] = true;
            }
            unset($a);
        }
        foreach ($drop as $gi => $units) {
            foreach ($units as $ui => $ais) {
                $groupsOut[$gi]['units'][$ui]['actions'] = array_values(array_diff_key($groupsOut[$gi]['units'][$ui]['actions'], $ais));
            }
        }
    }

    /** Solishtirish natijasi Excelda: "Balans" (fan + guruh) va "Amallar" (HEMIS'da qilinadigan o'zgarishlar). */
    public function hemisSyncExport(Request $request, TimetableBoard $board)
    {
        $resp = $this->hemisSyncCompare($request, $board);
        $rep = $resp->getData(true);
        $onlyDiff = $request->boolean('only_diff');
        $ops = $request->boolean('ops_filter') ? (array) $request->input('ops', []) : null;
        $subjects = $request->boolean('subjects_filter') ? (array) $request->input('subjects', []) : null;
        $groups = $request->boolean('groups_filter') ? (array) $request->input('filter_groups', []) : null;
        // To'siq turi filtri (oynadagi bilan bir xil): room / teacher / other.
        $blk = $request->boolean('blk_filter') ? (array) $request->input('blk', []) : null;
        $blockKinds = function (array $a): array {
            $k = array_values(array_unique(array_map(fn ($b) => $b['type'] ?? 'other', (array) ($a['blocks'] ?? []))));
            if (!$k || count((array) ($a['blocked'] ?? [])) > count((array) ($a['blocks'] ?? []))) {
                $k[] = 'other';
            }

            return $k;
        };
        $opOk = function (array $a) use ($ops, $blk, $blockKinds): bool {
            if (!empty($a['blocked'])) {
                return ($ops === null || in_array('blocked', $ops, true))
                    && ($blk === null || (bool) array_intersect($blockKinds($a), $blk));
            }
            return $ops === null || in_array($a['op'], $ops, true);
        };
        // Amal/to'siq turi bo'yicha filtr tanlangan bo'lsa — shu filtrga tushadigan amali yo'q fanlar Balansga ham chiqmaydi (oynadagidek).
        $narrowed = ($ops !== null && array_diff(['create', 'update', 'delete', 'blocked'], $ops))
            || ($blk !== null && array_diff(['room', 'teacher', 'other'], $blk));

        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $bal = $book->getActiveSheet();
        $bal->setTitle('Balans');
        $bal->fromArray(['Guruh', 'Fan', 'Kafedra', 'Dars turi', 'KTR jami (soat)', 'KTR joriy haftagacha', 'HEMIS bugungacha',
            'Semestr soat farqi hozir (+ kam, − ortiq)', 'Semestr soat farqi takliflardan keyin', 'Qism (Amaliy qism = amaliy, seminar, lab, klinik)',
            'KTR dars kunlari (semestr)', 'HEMIS dars kunlari (bugungacha)', 'Semestr kunlar farqi hozir (+ kam, − ortiq)', 'Semestr kunlar farqi takliflardan keyin',
            'Tuzatishlar', 'Izohlar', 'Davr', 'Davr KTR soati', 'Davr HEMIS soati', 'Davr soat farqi (HEMIS−KTR)', 'Davr KTR kunlari', 'Davr HEMIS kunlari', 'Davr LMS kunlari'], null, 'A1');
        $act = $book->createSheet();
        $act->setTitle('Amallar');
        $act->fromArray(['Guruh', 'Fan', 'Amal', 'Sana', 'Boshlanish', 'Tugash', 'HEMIS para', 'Dars turi', 'O\'qituvchi', 'Xona',
            'HEMIS id', 'O\'zgarishlar', 'Belgilar', 'To\'siq', 'Sabab'], null, 'A1');
        // "To'siqlar" — har bir to'siq alohida qator: kim / nima xalaqit beradi, kimga murojaat qilish kerak.
        $blkSheet = $book->createSheet();
        $blkSheet->setTitle('To\'siqlar');
        $blkSheet->fromArray(['Guruh', 'Fan', 'Kafedra', 'Kafedra mudiri', 'Amal', 'Sana', 'Vaqt', 'Dars turi', 'O\'qituvchi (LMS)', 'Xona (LMS)',
            'To\'siq turi', 'Band qilgan: manba', 'Band qilgan: guruh', 'Band qilgan: fan', 'Band qilgan: o\'qituvchi', 'Band qilgan: xona',
            'Band vaqti', 'To\'siq matni'], null, 'A1');
        $blkName = ['room' => 'Xona band', 'teacher' => 'O\'qituvchi band', 'other' => 'Boshqa'];
        $opName = ['create' => 'Qo\'shish', 'update' => 'O\'zgartirish', 'delete' => 'O\'chirish'];
        $r1 = 2;
        $r2 = 2;
        $r3 = 2;
        foreach ($rep['groups'] as $g) {
            if ($groups !== null && !in_array($g['group'], $groups, true)) {
                continue;
            }
            foreach ($g['units'] as $u) {
                if ($onlyDiff && $this->hemisUnitMatches($u)) {
                    continue;
                }
                if ($subjects !== null && !in_array($u['subject_name'], $subjects, true)) {
                    continue;
                }
                if ($narrowed && !array_filter($u['actions'], $opOk)) {
                    continue;
                }
                $until = $g['until'] ?? null;
                $fixes = implode("\n", array_map(fn ($f) => $f['text'] . (($until && !empty($f['date']) && $f['date'] > $until) ? ' (hozir yozilmaydi)' : ''), $u['fixes']));
                $notes = implode("\n", $u['notes']);
                $first = true;
                foreach ($u['code_names'] as $code => $name) {
                    $part = $u['codes'][$code];
                    $b = $u['before'];
                    $a = $u['after'];
                    $bal->fromArray([$g['group'], $u['subject_name'], $u['kafedra'] ?? '', $name,
                        $b['ktr_hours'][$code] ?? 0, $b['ktr_now_hours'][$code] ?? 0, $b['past_hours'][$code] ?? 0,
                        $b['hours_diff'][$code] ?? 0, $a['hours_diff'][$code] ?? 0,
                        $part === 'L' ? 'Ma\'ruza' : 'Amaliy qism', $b['ktr_count'][$part] ?? 0, $b['past_count'][$part] ?? 0,
                        $b['count_diff'][$part] ?? 0, $a['count_diff'][$part] ?? 0,
                        $first ? $fixes : '', $first ? $notes : '',
                        isset($u['period']) ? $u['period']['from_week'] . '–' . $u['period']['to_week'] . '-hafta' : '',
                        $u['period']['ktr_hours'][$code] ?? 0, $u['period']['hemis_hours'][$code] ?? 0,
                        ($u['period']['hemis_hours'][$code] ?? 0) - ($u['period']['ktr_hours'][$code] ?? 0),
                        $u['period']['ktr_count'][$part] ?? 0, $u['period']['hemis_count'][$part] ?? 0, $u['period']['lms_count'][$part] ?? 0,
                    ], null, 'A' . $r1++);
                    $first = false;
                }
                foreach ($u['actions'] as $x) {
                    if (!$opOk($x)) {
                        continue;
                    }
                    $act->fromArray([$g['group'], $u['subject_name'], $opName[$x['op']] ?? $x['op'], $x['date'], $x['start'], $x['end'],
                        self::HEMIS_PAIRS[$x['start'] . '-' . $x['end']] ?? '', $u['code_names'][$x['code']] ?? $x['code'],
                        $x['employee_name'] ?? '', $x['auditorium_name'] ?? '', $x['hemis_id'] ?? '',
                        implode('; ', $x['changes'] ?? []), implode(', ', $x['flags'] ?? []), implode('; ', $x['blocked'] ?? []), $x['reason'] ?? ''], null, 'A' . $r2++);
                    // Bartaraf etilgan to'siqlar (kafedra mudiri / mudir xonasi qo'yilgan) ham ro'yxatda — kimga murojaat qilish uchun.
                    foreach ((array) ($x['resolved'] ?? []) as $b) {
                        $blkSheet->fromArray([$g['group'], $u['subject_name'], $u['kafedra'] ?? '', $u['mudir'] ?? '', $opName[$x['op']] ?? $x['op'], $x['date'],
                            $x['start'] . '–' . $x['end'], $u['code_names'][$x['code']] ?? $x['code'], $x['employee_name'] ?? '', $x['auditorium_name'] ?? '',
                            ($blkName[$b['type'] ?? 'other'] ?? 'Boshqa') . ' — bartaraf: ' . (($b['type'] ?? '') === 'teacher' ? 'kafedra mudiri qo\'yildi' : 'mudir xonasi qo\'yildi'),
                            $b['source'] ?? '', $b['group'] ?? '', $b['subject'] ?? '', $b['teacher'] ?? '', $b['room'] ?? '', $b['time'] ?? '', $b['text'] ?? ''], null, 'A' . $r3++);
                    }
                    if (!empty($x['blocked'])) {
                        $base = [$g['group'], $u['subject_name'], $u['kafedra'] ?? '', $u['mudir'] ?? '', $opName[$x['op']] ?? $x['op'], $x['date'],
                            $x['start'] . '–' . $x['end'], $u['code_names'][$x['code']] ?? $x['code'], $x['employee_name'] ?? '', $x['auditorium_name'] ?? ''];
                        $blocks = (array) ($x['blocks'] ?? []);
                        foreach ($blocks as $b) {
                            $blkSheet->fromArray(array_merge($base, [$blkName[$b['type'] ?? 'other'] ?? 'Boshqa', $b['source'] ?? '', $b['group'] ?? '',
                                $b['subject'] ?? '', $b['teacher'] ?? '', $b['room'] ?? '', $b['time'] ?? '', $b['text'] ?? '']), null, 'A' . $r3++);
                        }
                        // Tuzilmagan to'siqlar (o'qituvchi/xona topilmadi, para yo'q...) — "Boshqa".
                        foreach (array_diff((array) $x['blocked'], array_column($blocks, 'text')) as $txt) {
                            $blkSheet->fromArray(array_merge($base, ['Boshqa', '', '', '', '', '', '', $txt]), null, 'A' . $r3++);
                        }
                    }
                }
            }
        }
        foreach ([$bal, $act, $blkSheet] as $sh) {
            $sh->getStyle('A1:W1')->getFont()->setBold(true);
            $sh->freezePane('A2');
            foreach (range('A', 'W') as $col) {
                $sh->getColumnDimension($col)->setAutoSize(true);
            }
        }
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);

        // Vaqtinchalik faylga yozib, tayyor fayl yuboriladi (Content-Length bilan) — oqim (stream) o'rtasida
        // xato yoki boshqa sarlavha bo'lsa brauzer "ERR_INVALID_RESPONSE" berardi.
        $tmp = tempnam(sys_get_temp_dir(), 'hemis_xlsx_');
        $writer->save($tmp);

        return response()->download($tmp, 'hemis_solishtirish_' . $rep['today'] . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    /**
     * "Bugungi darslar (HEMIS)": faqat bugun uchun yengil solishtirish — faqat o'zgartirish (o'qituvchi, xona, para vaqti).
     * Bugun HEMIS'ga qo'shilmaydi va o'chirilmaydi (mos kelmaganlari — ma'lumot); davomat olingan yoki baho qo'yilgan
     * darsga tegilmaydi. To'liq solishtirish (hemisSyncReport) chaqirilmaydi: faqat bugungi darslar va qamrov guruhlari.
     */
    public function hemisToday(Request $request, TimetableBoard $board)
    {
        $this->hemisSyncDenyUnlessRegistrar($request);
        $data = $request->validate([
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'groups'            => 'nullable|array',
            'groups.*'          => 'string|max:255',
            'today'             => 'nullable|date',
        ]);
        $today = !empty($data['today']) && in_array($this->timetableActiveRole($request), ['admin', 'superadmin'], true)
            ? Carbon::parse($data['today'])->toDateString()
            : now()->toDateString();
        // Bo'sh ro'yxat — filtr yo'q (cycle-plan dagidek: "hamma yo'nalishlar"), "hech biri" emas.
        foreach (['faculty_names', 'specialty_names', 'courses'] as $k) {
            if (isset($data[$k]) && $data[$k] === []) {
                unset($data[$k]);
            }
        }
        @set_time_limit(180);

        return response()->json($this->hemisTodayReport($board, $data, $today));
    }

    /**
     * Xatcho'p (HEMIS sahifasida) bugungi darsni yozishdan oldin: shu HEMIS yozuvlarida davomat olinganmi yoki baho
     * qo'yilganmi — HEMIS'dan qaytadan (keshsiz). Imzolangan havola (kun oxirigacha amal qiladi; ids — imzodan tashqari),
     * HEMIS sahifasidan chaqiriladi (CORS). Qaytaradi: {locked: {id: sabab}, failed: [tekshirib bo'lmagan id]}.
     */
    public function hemisTodayLocks(Request $request, TimetableBoard $board)
    {
        $origin = (string) $request->headers->get('Origin', '');
        $allowed = parse_url((string) config('services.hemis.web_url', 'https://hemis.ttatf.uz'), PHP_URL_HOST);
        $cors = $origin !== '' && $allowed && parse_url($origin, PHP_URL_HOST) === $allowed ? ['Access-Control-Allow-Origin' => $origin, 'Vary' => 'Origin'] : [];
        if (!\Illuminate\Support\Facades\URL::hasValidSignature($request, true, ['ids'])) {
            return response()->json(['error' => 'Havola eskirgan — mark\'da «Bugungi darslar (HEMIS)» JSON\'ini qayta yuklab oling.'], 403, $cors);
        }
        $date = (string) $request->query('date');
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $request->query('ids', '')))))), 0, 50);
        $rows = $ids ? DB::table('schedules')->whereIn('schedule_hemis_id', $ids)->get(['schedule_hemis_id', 'group_id', 'lesson_date']) : collect();
        $byGroup = [];
        $failed = [];
        foreach ($ids as $id) {
            $r = $rows->firstWhere('schedule_hemis_id', $id);
            if ($r && substr((string) $r->lesson_date, 0, 10) === $date) {
                $byGroup[(int) $r->group_id][] = $id;
            } else {
                $failed[] = $id;   // bugungi dars emas yoki bazada yo'q — yozilmasin
            }
        }
        [$locks, $failedGroups] = $this->hemisTodayLockMap($byGroup, $date);
        foreach ($failedGroups as $g) {
            array_push($failed, ...$byGroup[$g]);
        }

        return response()->json(['locked' => array_map(fn ($l) => $this->hemisTodayLockReason($l), $locks), 'failed' => $failed], 200, $cors);
    }

    private function hemisTodayLockReason(array $l): string
    {
        return implode(', ', array_filter([!empty($l['att']) ? 'davomat olingan' : null, !empty($l['grade']) ? 'baho qo\'yilgan' : null]));
    }

    /**
     * Davomat (attendance-control-list) va baho (student-grade-list) — guruh va kun bo'yicha HEMIS'dan qaytadan, hamda bazadagi
     * attendance_controls / student_grades. $idsByGroup — [HEMIS guruh id => [subject_schedule id, ...]].
     * Qaytaradi: [[id => ['att' => bool, 'grade' => bool]] (faqat belgilanganlari), [HEMIS javob bermagan guruh id, ...]].
     */
    private function hemisTodayLockMap(array $idsByGroup, string $date): array
    {
        $all = array_merge(...array_values($idsByGroup ?: [[]]));
        if (!$all) {
            return [[], []];
        }
        $want = array_flip($all);
        $locks = [];
        $mark = function ($id, string $k) use (&$locks, $want) {
            if (isset($want[(int) $id])) {
                $locks[(int) $id][$k] = true;
            }
        };
        // Davomat — faqat belgilangani (load > 0; load = 0 — yozuv bor, lekin davomat olinmagan), boshqa joylardagidek.
        foreach (['attendance_controls' => 'att', 'student_grades' => 'grade'] as $table => $k) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereIn('subject_schedule_id', $all)
                    ->when(Schema::hasColumn($table, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                    ->when($k === 'att', fn ($q) => $q->where('load', '>', 0))
                    ->distinct()->pluck('subject_schedule_id')->each(fn ($id) => $mark($id, $k));
            }
        }
        // Bazada belgisi yo'q yozuvlar — HEMIS'dan (import kechasi bo'ladi, bugungi davomat hali bazada bo'lmaydi). Baho — faqat
        // davomati ham olinmagan yozuvlar guruhlari uchun.
        $failed = [];
        foreach (['attendance-control-list' => 'att', 'student-grade-list' => 'grade'] as $endpoint => $k) {
            $ask = [];
            foreach ($idsByGroup as $g => $ids) {
                if (!isset($failed[$g]) && array_filter($ids, fn ($id) => !isset($locks[(int) $id]))) {
                    $ask[] = (int) $g;
                }
            }
            // Sana/guruh bo'yicha qayta saralanmaydi: davomat va baho dars yozuviga (_subject_schedule) aynan bog'langan.
            foreach (app(\App\Services\HemisSync\HemisDayClient::class)->dayList($endpoint, $ask, $date) as $g => $items) {
                if ($items === null) {
                    $failed[$g] = true;
                    continue;
                }
                foreach ($items as $it) {
                    // load berilmagan bo'lsa — belgilangan deb (import ham shunday: sukut 2).
                    if ($k === 'att' && (int) ($it['load'] ?? 2) <= 0) {
                        continue;
                    }
                    $mark($it['_subject_schedule'] ?? ($it['subject_schedule']['id'] ?? null), $k);
                }
            }
        }

        return [$locks, array_keys($failed)];
    }

    /**
     * Bugungi darslar hisoboti. LMS: haftalik darslar (shu haftaning shu kuni, ko'chirilgan/bekor qilinganlari bilan) va
     * sikl darslari (bugungi kun rejasi, guruhcha smenasi bilan). HEMIS: shu guruhlarning bugungi jadvali — avval HEMIS'dan
     * qayta import qilinadi. Juftlash: guruh + fan + qism (ma'ruza / amaliy qism) + vaqt (avval aynan, so'ng eng yaqini).
     */
    private function hemisTodayReport(TimetableBoard $board, array $scope, string $today): array
    {
        [$facSet, $specSet, $courseSet] = $this->scopeSets($scope);
        $cards = $this->cardRows($board, fn ($q) => $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet));
        $isCycle = $this->cycleCardTest($this->cycleSubjectKeys($board));
        $weekly = array_filter($cards, fn ($c) => !$isCycle($c));
        $cycleIds = array_keys(array_diff_key($cards, $weekly));
        $teacherHemis = Teacher::whereIn('id', array_values(array_unique(array_filter(array_column($cards, 'teacher_id')))))->pluck('hemis_id', 'id')->all();
        $slotTimes = $this->hemisSlotTimes($board);
        $facKey = fn ($f) => mb_strtolower(trim((string) $f));
        $cleanGroup = fn ($n) => trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', (string) $n));

        // Qamrov guruhlari (to'liq solishtirishdagidek: fakultet + nom; sikl kartalari — guruhcha a'zolari).
        $groupNames = [];
        foreach ($cards as $id => $c) {
            $names = in_array($id, $cycleIds, true) ? $this->cycleCardGroups($c) : $this->cardGroupsOf($c);
            foreach ($names as $raw) {
                $raw = trim((string) $raw);
                if ($raw !== '') {
                    $groupNames[$facKey($c->faculty_name) . '|' . $this->groupNameKey($raw)] ??= ['name' => $cleanGroup($raw), 'raw' => $raw, 'card' => $c];
                }
            }
        }
        if (!empty($scope['groups'])) {
            $want = [];
            foreach ($scope['groups'] as $g) {
                $want[$this->groupNameKey($g)] = true;
                $want[$this->groupNameKey($cleanGroup($g))] = true;
            }
            $groupNames = array_filter($groupNames, fn ($g) => isset($want[$this->groupNameKey($g['raw'])]) || isset($want[$this->groupNameKey($g['name'])]));
        }

        // LMS'dagi bugungi darslar: guruh kaliti => [dars, ...] (slot va len — yarim-para).
        $lms = [];
        $lesson = fn (string $subject, string $part, int $slot, int $len, $emp, $empName, $room, $roomName, bool $cycle) => [
            'subject' => $subject, 'key' => $this->hemisSubjectKey($subject), 'part' => $part, 'slot' => $slot, 'len' => max(1, $len),
            'emp' => $emp ?: null, 'emp_name' => $empName ?: null, 'room' => $room ?: null, 'room_name' => $roomName ?: null, 'cycle' => $cycle,
        ];
        if ($weekly) {
            $state = ['cards' => $weekly, 'ov' => $this->overrideRows($board, fn ($q) => $q->whereIn('o.card_id', array_keys($weekly)))];
            foreach ($this->weeklyOccupancy($board, $state, [$today => true]) as $e) {
                $c = $weekly[$e['card']];
                $l = $lesson((string) $c->subject_name, $c->training_type === 'lecture' ? 'L' : 'A', (int) $e['from'], (int) $e['to'] - (int) $e['from'],
                    $c->teacher_id ? ($teacherHemis[$c->teacher_id] ?? null) : null, $c->teacher_id ? $c->teacher_name : null,
                    $e['room'], $e['room_name'], false);
                foreach ($this->cardGroupsOf($c) as $raw) {
                    $lms[$facKey($c->faculty_name) . '|' . $this->groupNameKey(trim((string) $raw))][] = $l;
                }
            }
        }
        if ($cycleIds) {
            $cycleCards = TimetableCard::whereIn('id', $cycleIds)->get();
            $names = [];
            foreach ($cycleCards as $c) {
                $names[$this->hemisSubjectKey((string) $c->subject_name)] ??= (string) $c->subject_name;
            }
            foreach ($this->hemisCycleLessons($board, $scope, $cycleCards) as $gk => $bySubject) {
                foreach ($bySubject as $sk => $list) {
                    foreach ($list as $x) {
                        if ($x['date'] === $today) {
                            $lms[$gk][] = $lesson($names[$sk] ?? $sk, $x['part'], (int) $x['slot'], (int) $x['len'], $x['employee_id'], $x['employee_name'],
                                $x['auditorium_code'], $x['auditorium_name'], true);
                        }
                    }
                }
            }
        }

        // HEMIS guruhlari; bir HEMIS guruhiga tushgan nomlar birlashadi.
        $resolveGroup = $this->hemisGroupResolver($board, $groupNames);
        $groups = [];
        $missing = [];
        foreach ($groupNames as $gk => $g) {
            $row = $resolveGroup($g);
            if (!$row) {
                if (!empty($lms[$gk])) {
                    $missing[] = $g['name'];
                }
                continue;
            }
            $gid = (int) $row->group_hemis_id;
            $groups[$gid] ??= ['row' => $row, 'lms' => []];
            array_push($groups[$gid]['lms'], ...($lms[$gk] ?? []));
        }
        $date = Carbon::parse($today);
        $import = $groups ? app(\App\Services\ScheduleImportService::class)->importGroupsOnDate(array_keys($groups), $date) : ['failed' => []];
        $importFailed = array_flip($import['failed']);
        $hemisRows = $groups ? DB::table('schedules')->whereIn('group_id', array_keys($groups))->whereNull('deleted_at')
            ->whereBetween('lesson_date', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->orderBy('lesson_pair_start_time')->orderBy('schedule_hemis_id')
            ->get(['schedule_hemis_id', 'group_id', 'group_name', 'subject_id', 'subject_name', 'training_type_code', 'training_type_name',
                'lesson_pair_start_time', 'lesson_pair_end_time', 'employee_id', 'employee_name', 'auditorium_code', 'auditorium_name',
                'department_id', 'semester_code', 'education_year_code'])->groupBy('group_id') : collect();

        $toMin = fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $hm = fn ($t) => substr((string) $t, 0, 5);
        // Hozirgi vaqt: boshlangan / o'tgan darsning vaqti o'zgartirilmaydi (o'tgan kun — hammasi o'tgan, kelgusi — hech biri).
        $nowDate = now()->toDateString();
        $nowHm = $today < $nowDate ? '24:00' : ($today > $nowDate ? '' : now()->format('H:i'));
        $out = [];   // qatorlar
        $hemisOf = [];
        foreach ($groups as $gid => $g) {
            $gname = (string) $g['row']->name;
            // LMS darslari HEMIS yozuvlariga (to'liq yoki yarim para) bo'linadi — SchedulePlanner::records kabi.
            $buckets = [];
            foreach ($g['lms'] as $l) {
                $bk = $this->hemisSubjectBase($l['subject']) . '|' . $l['part'];
                for ($s = $l['slot']; $s < $l['slot'] + $l['len']; $s++) {
                    $buckets[$bk]['slots'][$s] = $l;
                }
            }
            $records = [];
            foreach ($buckets as $bk => $b) {
                ksort($b['slots']);
                $keys = array_keys($b['slots']);
                for ($i = 0; $i < count($keys); $i++) {
                    $s = $keys[$i];
                    $a = $b['slots'][$s];
                    $len = 1;
                    $nx = $keys[$i + 1] ?? null;
                    $same = $nx !== null && (string) $a['emp'] === (string) $b['slots'][$nx]['emp'] && (string) $a['room'] === (string) $b['slots'][$nx]['room'];
                    if ($s % 2 === 1 && $nx === $s + 1 && $same) {
                        $len = 2;
                        $i++;
                    }
                    $records[$bk][] = $a + ['start' => $slotTimes[$s][0] ?? '', 'end' => $slotTimes[$s + $len - 1][1] ?? ''];
                }
            }
            // Juftlash: fan (asosiy nomi — bo'lakli "Fan (a)" ham, umumiy ma'ruza ham) va mashg'ulot turi (ma'ruza / amaliy qism)
            // bo'yicha, so'ng shu tur ichida vaqt tartibida: 1-HEMIS amaliy ↔ 1-LMS amaliy, 2-si ↔ 2-si. Soni farq qilsa — ortiqchasi
            // tartib saqlangan holda, vaqti eng uzoq bo'yicha ("ortiqcha dars" / "yangi dars kerak").
            $hb = [];
            foreach ($hemisRows[$gid] ?? [] as $h) {
                $part = $this->hemisPartOf((string) $h->training_type_name);
                $hemisOf[(int) $h->schedule_hemis_id] = $h;
                if ($part === null) {
                    continue;   // mustaqil ta'lim, amaliyot — solishtirilmaydi
                }
                $hb[$this->hemisSubjectBase((string) $h->subject_name) . '|' . $part][] = $h;
            }
            foreach (array_unique(array_merge(array_keys($records), array_keys($hb))) as $bk) {
                // Ikkalasi ham vaqt tartibida: LMS yozuvlari — yarim-slotlar tartibida, HEMIS — so'rovda boshlanish bo'yicha.
                $R = $records[$bk] ?? [];
                $H = $hb[$bk] ?? [];
                $pairs = $this->hemisTodayAlign(array_map(fn ($r) => [$toMin($r['start']), $toMin($r['end'])], $R),
                    array_map(fn ($h) => [$toMin($hm($h->lesson_pair_start_time)), $toMin($hm($h->lesson_pair_end_time))], $H));
                $usedR = [];
                $usedH = [];
                foreach ($pairs as [$ri, $hi]) {
                    $usedR[$ri] = $usedH[$hi] = true;
                }
                foreach ($pairs as [$ri, $hi]) {
                    $r = $R[$ri];
                    $h = $H[$hi];
                    $x = ['group' => $gname, 'group_hemis_id' => $gid, 'subject' => (string) $h->subject_name, 'type' => (string) $h->training_type_name,
                        'hemis_id' => (int) $h->schedule_hemis_id, 'start' => $hm($h->lesson_pair_start_time), 'end' => $hm($h->lesson_pair_end_time),
                        'employee_id' => $h->employee_id, 'employee_name' => $h->employee_name, 'auditorium_code' => $h->auditorium_code,
                        'auditorium_name' => $h->auditorium_name, 'new_start' => $r['start'], 'new_end' => $r['end'],
                        // Biriktirilmagan (LMS'da o'qituvchi yo'q) — HEMIS'dagisi qoladi; kafedra mudiriga avtomatik almashtirilmaydi.
                        'new_employee_id' => $r['emp'] ?: $h->employee_id, 'new_employee_name' => $r['emp'] ? $r['emp_name'] : $h->employee_name,
                        'new_auditorium_code' => $r['room'] ?: $h->auditorium_code, 'new_auditorium_name' => $r['room'] ? $r['room_name'] : $h->auditorium_name,
                        'teacher_set' => $r['emp_name'] !== null, 'changes' => [], 'blocked' => [], 'status' => 'ok', 'reason' => null,
                        'code' => (string) $h->training_type_code, 'subject_id' => (int) $h->subject_id, 'cycle' => $r['cycle']];
                    // Dars boshlangan yoki o'tgan, yoxud yangi vaqti bugun o'tib ketgan — vaqt o'zgartirilmaydi (belgilanmagan bo'lsa
                    // ham dars o'tib ketgan), faqat o'qituvchi va xona.
                    $timePassed = ($x['new_start'] !== $x['start'] || $x['new_end'] !== $x['end']) && ($nowHm >= $x['start'] || $nowHm >= $x['new_start']);
                    if ($timePassed) {
                        $x['time_passed'] = "{$x['new_start']}–{$x['new_end']}";
                        [$x['new_start'], $x['new_end']] = [$x['start'], $x['end']];
                    }
                    if ($x['new_start'] !== $x['start'] || $x['new_end'] !== $x['end']) {
                        $x['changes'][] = "vaqt: {$x['start']}–{$x['end']} → {$x['new_start']}–{$x['new_end']}";
                    }
                    if ((string) $x['new_employee_id'] !== (string) $x['employee_id']) {
                        $x['changes'][] = "o'qituvchi: " . ($x['employee_name'] ?: '—') . ' → ' . $x['new_employee_name'];
                    }
                    if ((string) $x['new_auditorium_code'] !== (string) $x['auditorium_code']) {
                        $x['changes'][] = 'xona: ' . ($x['auditorium_name'] ?: '—') . ' → ' . $x['new_auditorium_name'];
                    }
                    if ($r['emp_name'] !== null && !$r['emp']) {
                        $x['note'] = "LMS o'qituvchisi {$r['emp_name']} ning HEMIS xodim id'si yo'q — o'qituvchi solishtirilmadi";
                    }
                    if ($timePassed) {
                        $msg = "vaqt o'tgan — faqat o'qituvchi/xona (LMS'da {$x['time_passed']})";
                        $x['note'] = isset($x['note']) ? $x['note'] . '; ' . $msg : $msg;
                        if (!$x['changes']) {
                            $x['status'] = 'info';
                            $x['reason'] = "vaqt o'tgan — faqat o'qituvchi/xona";
                        }
                    }
                    if ($x['changes']) {
                        $x['status'] = 'update';
                    }
                    $out[] = $x;
                }
                foreach ($R as $ri => $r) {
                    if (!isset($usedR[$ri])) {
                        $out[] = ['group' => $gname, 'group_hemis_id' => $gid, 'subject' => $r['subject'], 'type' => $r['part'] === 'L' ? 'Ma\'ruza' : 'Amaliy',
                            'hemis_id' => null, 'start' => $r['start'], 'end' => $r['end'], 'employee_name' => $r['emp_name'], 'auditorium_name' => $r['room_name'],
                            'status' => 'info', 'changes' => [], 'blocked' => [], 'reason' => 'yangi dars kerak — bugun qo\'shilmaydi', 'cycle' => $r['cycle']];
                    }
                }
                foreach ($H as $hi => $h) {
                    if (!isset($usedH[$hi])) {
                        $out[] = ['group' => $gname, 'group_hemis_id' => $gid, 'subject' => (string) $h->subject_name, 'type' => (string) $h->training_type_name,
                            'hemis_id' => (int) $h->schedule_hemis_id, 'start' => $hm($h->lesson_pair_start_time), 'end' => $hm($h->lesson_pair_end_time),
                            'employee_name' => $h->employee_name, 'auditorium_name' => $h->auditorium_name, 'status' => 'info', 'changes' => [], 'blocked' => [],
                            'reason' => 'ortiqcha dars (LMS\'da bugun yo\'q) — bugun o\'chirilmaydi'];
                    }
                }
            }
        }

        // Davomat / baho: o'zgaradigan darslar uchun (bazada, so'ng HEMIS'dan qaytadan).
        $askIds = [];
        foreach ($out as $i => $x) {
            if ($x['status'] === 'update') {
                if (isset($importFailed[$x['group_hemis_id']])) {
                    $out[$i]['status'] = 'blocked';
                    $out[$i]['blocked'][] = 'HEMIS jadvali yangilanmadi (javob bermadi) — bugungi holat noma\'lum';
                    continue;
                }
                $askIds[$x['group_hemis_id']][] = $x['hemis_id'];
            }
        }
        [$locks, $lockFailed] = $this->hemisTodayLockMap($askIds, $today);
        $lockFailed = array_flip($lockFailed);
        foreach ($out as $i => $x) {
            if ($x['status'] !== 'update') {
                continue;
            }
            if (isset($locks[$x['hemis_id']])) {
                $out[$i]['status'] = 'locked';
                $out[$i]['reason'] = $this->hemisTodayLockReason($locks[$x['hemis_id']]) . ' — tegilmaydi';
            } elseif (isset($lockFailed[$x['group_hemis_id']])) {
                $out[$i]['status'] = 'blocked';
                $out[$i]['blocked'][] = 'HEMIS\'dan davomat/baho tekshirib bo\'lmadi (javob bermadi)';
            }
        }
        $this->hemisTodayBlocks($out, $hemisRows, $today);

        // Xatcho'p uchun JSON: faqat to'siqsiz o'zgartirishlar (today: true).
        $jsonGroups = [];
        foreach ($out as $x) {
            if ($x['status'] !== 'update') {
                continue;
            }
            $gid = $x['group_hemis_id'];
            $h = $hemisOf[$x['hemis_id']];
            $jsonGroups[$gid] ??= ['group' => $x['group'], 'group_hemis_id' => $gid, 'curriculum_hemis_id' => (int) $groups[$gid]['row']->curriculum_hemis_id,
                'education_year' => (int) $h->education_year_code, 'semester_code' => (string) $h->semester_code, 'units' => []];
            $uk = $x['subject_id'];
            $jsonGroups[$gid]['units'][$uk] ??= ['subject_name' => $x['subject'], 'subject_id' => $x['subject_id'], 'code_names' => [], 'actions' => []];
            $jsonGroups[$gid]['units'][$uk]['code_names'][$x['code']] = $x['type'];
            // gseq — yozish navbati (guruhlararo ham): o'rni bo'shaydigan dars avval suriladi.
            $jsonGroups[$gid]['units'][$uk]['actions'][] = ['op' => 'update', 'today' => true, 'date' => $today, 'hemis_id' => $x['hemis_id'], 'gseq' => $x['gseq'] ?? 0,
                'start' => $x['new_start'], 'end' => $x['new_end'], 'code' => $x['code'],
                'employee_id' => $x['new_employee_id'], 'employee_name' => $x['new_employee_name'],
                'auditorium_code' => $x['new_auditorium_code'], 'auditorium_name' => $x['new_auditorium_name'], 'changes' => $x['changes'], 'blocked' => []];
        }
        foreach ($jsonGroups as &$jg) {
            $jg['units'] = array_values($jg['units']);
            foreach ($jg['units'] as &$ju) {
                usort($ju['actions'], fn ($a, $b) => $a['gseq'] <=> $b['gseq']);
            }
            unset($ju);
        }
        unset($jg);
        usort($out, fn ($a, $b) => [$a['group'], $a['start'], $a['subject']] <=> [$b['group'], $b['start'], $b['subject']]);
        $count = fn (string $s) => count(array_filter($out, fn ($x) => $x['status'] === $s));
        $rows = array_map(fn ($x) => array_diff_key($x, array_flip(['employee_id', 'new_employee_id', 'teacher_set', 'code', 'subject_id', 'time_passed'])), $out);

        return [
            'today' => $today,
            'counts' => ['lessons' => count($out), 'updates' => $count('update'), 'locked' => $count('locked'), 'blocked' => $count('blocked'),
                'info' => $count('info'), 'ok' => $count('ok')],
            'rows' => $rows,
            'missing_groups' => array_values(array_unique($missing)),
            'import_failed' => count($importFailed),
            'json' => [
                'mode' => 'today', 'today' => $today, 'hemis_pairs' => self::HEMIS_PAIRS,
                'lock_check_url' => \Illuminate\Support\Facades\URL::temporarySignedRoute('hemis-today-locks', $this->hemisTodayLockExpiry($today),
                    ['board' => $board->id, 'date' => $today]),
                'groups' => array_values($jsonGroups),
            ],
        ];
    }

    /**
     * Tartibni saqlab juftlash (darslar [boshlanish, tugash] daqiqada, vaqt tartibida): kamroq tomonning har biri ko'proq
     * tomonning bittasiga, tartib buzilmaydi; ortiqchalari shunday tanlanadiki, vaqt farqlari yig'indisi eng kichik bo'lsin
     * (aynan mos dars ustun). Soni teng bo'lsa — 1-chi ↔ 1-chi, 2-chi ↔ 2-chi. Qaytaradi: [[$a indeksi, $b indeksi], ...].
     */
    private function hemisTodayAlign(array $a, array $b): array
    {
        if (count($a) > count($b)) {
            return array_map(fn ($p) => [$p[1], $p[0]], $this->hemisTodayAlign($b, $a));
        }
        [$n, $m] = [count($a), count($b)];
        $dp = [array_fill(0, $m + 1, 0)];
        for ($i = 1; $i <= $n; $i++) {
            $dp[$i] = array_fill(0, $m + 1, PHP_INT_MAX);
            for ($j = $i; $j <= $m; $j++) {
                $cost = abs($a[$i - 1][0] - $b[$j - 1][0]) + abs($a[$i - 1][1] - $b[$j - 1][1]);
                $dp[$i][$j] = min($dp[$i - 1][$j - 1] + $cost, $dp[$i][$j - 1]);
            }
        }
        $pairs = [];
        for ($i = $n, $j = $m; $i > 0;) {
            if ($j > $i && $dp[$i][$j] === $dp[$i][$j - 1]) {
                $j--;   // b[j-1] ortiqcha
                continue;
            }
            $pairs[] = [--$i, --$j];
        }

        return array_reverse($pairs);
    }

    /** Qulf tekshiruvi havolasi shu kun oxirigacha amal qiladi (ertasi kun — eskirgan JSON). */
    private function hemisTodayLockExpiry(string $today): Carbon
    {
        return max(Carbon::parse($today)->endOfDay(), now()->addMinutes(5));
    }

    /**
     * O'zgartirishning to'siqlari (bugun): HEMIS'da yangi para vaqti yo'q; vaqt o'zgarsa — guruhning boshqa bugungi darsi bilan
     * ustma-ust; o'qituvchi yoki xona band (HEMIS'dagi bugungi darslar va qolgan o'zgartirishlardan keyingi joylar).
     * Yangi joy boshqa o'zgartiriladigan darsning hozirgi joyi bo'lsa — to'siq emas, navbat: u avval ko'chadi (gseq). Ikkalasi
     * bir-birining joyini kutsa (sof almashinuv) — ikkalasi to'siqli: "joy almashish — HEMIS'da qo'lda"; ko'chmaydigan darsni
     * kutayotgani ham to'siqli. Bir dars bir necha guruhga (bitta o'qituvchi, bitta xona) — bandlik emas; kafedra mudiriga
     * majburan qo'yilgan dars (LMS'da o'qituvchi biriktirilmagan) va "Kafedra mudiri xonasi" / umumiy xona — to'siq emas.
     */
    private function hemisTodayBlocks(array &$out, $hemisRows, string $today): void
    {
        $cand = array_keys(array_filter($out, fn ($x) => $x['status'] === 'update'));
        if (!$cand) {
            return;
        }
        $hm = fn ($t) => substr((string) $t, 0, 5);
        $freeRoom = fn ($name) => $this->isSharedRoom($name) || preg_match('/kafedra\s+mudiri/iu', (string) $name) === 1;
        // Bandlik joylari: [boshi, oxiri, hemis id, guruh, fan, o'qituvchi id, o'qituvchi, xona kodi, xona, kafedra, majburiy mudir].
        $spots = [];
        $mine = [];
        foreach ($out as $x) {
            if (!empty($x['hemis_id']) && array_key_exists('new_start', $x)) {
                $mine[$x['hemis_id']] = $x;
            }
        }
        $emps = [];
        $rooms = [];
        foreach ($cand as $i) {
            if ($out[$i]['new_employee_id']) {
                $emps[(string) $out[$i]['new_employee_id']] = true;
            }
            if ($out[$i]['new_auditorium_code']) {
                $rooms[(string) $out[$i]['new_auditorium_code']] = true;
            }
        }
        $day = Carbon::parse($today);
        $ext = DB::table('schedules')->whereNull('deleted_at')->whereBetween('lesson_date', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->where(fn ($q) => $q->whereIn('employee_id', array_keys($emps))->orWhereIn('auditorium_code', array_keys($rooms)))
            ->get(['schedule_hemis_id', 'group_id', 'group_name', 'subject_name', 'lesson_pair_start_time', 'lesson_pair_end_time',
                'employee_id', 'employee_name', 'auditorium_code', 'auditorium_name', 'department_id']);
        $all = collect($hemisRows)->flatten(1)->keyBy('schedule_hemis_id');
        foreach ($ext as $r) {
            $all[$r->schedule_hemis_id] ??= $r;
        }
        $mudirIds = [];
        $isMudir = function ($deptId, $emp) use (&$mudirIds): bool {
            if (!$deptId || !$emp) {
                return false;
            }
            $mudirIds[$deptId] ??= array_map('strval', Teacher::headsOfDepartment($deptId)->whereNotNull('hemis_id')->pluck('hemis_id')->all());

            return in_array((string) $emp, $mudirIds[$deptId], true);
        };
        $candOf = [];
        foreach ($cand as $i) {
            $candOf[(int) $out[$i]['hemis_id']] = $i;
        }
        foreach ($all as $id => $r) {
            $base = ['id' => (int) $id, 'group_id' => (int) $r->group_id, 'group' => $r->group_name, 'subject' => $r->subject_name, 'dept' => $r->department_id];
            $x = $mine[(int) $id] ?? null;
            // LMS'da o'qituvchisi biriktirilmagan dars — HEMIS'dagi o'qituvchisi (kafedra mudiri) bandlik hisoblanmaydi.
            $forced = $x ? !$x['teacher_set'] : $isMudir($r->department_id, $r->employee_id);
            // moving — hozirgi joy, lekin dars o'zgartiriladi (shu nomzod indeksi): u ko'chgach bo'shaydi.
            $spots[] = $base + ['s' => $hm($r->lesson_pair_start_time), 'e' => $hm($r->lesson_pair_end_time), 'emp' => (string) $r->employee_id,
                'emp_name' => $r->employee_name, 'room' => (string) $r->auditorium_code, 'room_name' => $r->auditorium_name, 'forced' => $forced,
                'moving' => $candOf[(int) $id] ?? null];
            if ($x && $x['status'] === 'update') {
                $spots[] = $base + ['s' => $x['new_start'], 'e' => $x['new_end'], 'emp' => (string) $x['new_employee_id'], 'emp_name' => $x['new_employee_name'],
                    'room' => (string) $x['new_auditorium_code'], 'room_name' => $x['new_auditorium_name'], 'forced' => $forced, 'moving' => null];
            }
        }
        $deps = [];      // nomzod => [kutadigan nomzod => sabab]: u o'z joyidan avval ko'chishi kerak
        $blockedBy = []; // nomzod => [sabab, ...]
        foreach ($cand as $i) {
            $x = $out[$i];
            [$s, $e] = [$x['new_start'], $x['new_end']];
            $timeChanged = $s !== $x['start'] || $e !== $x['end'];
            $emp = (string) $x['new_employee_id'];
            $room = (string) $x['new_auditorium_code'];
            $blocked = [];
            if (!isset(self::HEMIS_PAIRS[$s . '-' . $e])) {
                $blocked[] = "HEMIS'da {$s}-{$e} para vaqti yo'q";
            }
            // HEMIS formasi o'qituvchi va xonasiz saqlanmaydi (LMS'da ham, HEMIS'da ham yo'q bo'lsa).
            if ($emp === '') {
                $blocked[] = "o'qituvchi yo'q (LMS'da biriktirilmagan, HEMIS'da ham yo'q)";
            }
            if ($room === '') {
                $blocked[] = "xona yo'q (LMS'da biriktirilmagan, HEMIS'da ham yo'q)";
            }
            $checkEmp = $emp !== '' && $x['teacher_set'] && ($timeChanged || $emp !== (string) $x['employee_id']);
            $checkRoom = $room !== '' && !$freeRoom($x['new_auditorium_name']) && ($timeChanged || $room !== (string) $x['auditorium_code']);
            $seen = [];
            foreach ($spots as $o) {
                if ($o['id'] === $x['hemis_id'] || !($o['s'] < $e && $s < $o['e'])) {
                    continue;
                }
                $label = $o['group'] . ' · ' . $o['subject'] . ' ' . $o['s'] . '–' . $o['e'];
                $shared = $emp !== '' && $room !== '' && $o['emp'] === $emp && $o['room'] === $room;
                $text = null;
                if ($timeChanged && $o['group_id'] === (int) $x['group_hemis_id']) {
                    $text = "guruhning boshqa darsi bilan ustma-ust: {$o['subject']} {$o['s']}–{$o['e']}";
                } elseif ($checkEmp && !$shared && !$o['forced'] && $o['emp'] === $emp) {
                    $text = "o'qituvchi band: {$x['new_employee_name']} — {$label}";
                } elseif ($checkRoom && !$shared && $o['room'] === $room && !$freeRoom($o['room_name'])) {
                    $text = "xona band: {$x['new_auditorium_name']} — {$label}";
                }
                if ($text === null) {
                    continue;
                }
                if ($o['moving'] !== null) {
                    $deps[$i][$o['moving']] ??= $text;   // hozirgi joyi — u ko'chgach bo'shaydi
                } elseif (!isset($seen[$text])) {
                    $seen[$text] = true;
                    $blocked[] = $text;
                }
            }
            if ($blocked) {
                $blockedBy[$i] = $blocked;
            }
        }
        // Navbat: kutadigan darsi yo'qlari avval, so'ng ular bo'shatgan joyga ko'chadiganlari. Navbatga tushmay qolganlar — bir-birini
        // kutadi (sof almashinuv) yoki ko'chmaydigan (to'siqli) yoxud almashinuvdagi darsni kutadi — sababi o'sha bandlik.
        $live = array_values(array_filter($cand, fn ($i) => !isset($blockedBy[$i])));
        usort($live, fn ($a, $b) => [$out[$a]['group'], $out[$a]['start'], $a] <=> [$out[$b]['group'], $out[$b]['start'], $b]);
        $done = [];
        do {
            $more = false;
            foreach ($live as $i) {
                if (!isset($done[$i]) && !array_diff_key($deps[$i] ?? [], $done)) {
                    $done[$i] = count($done) + 1;
                    $more = true;
                }
            }
        } while ($more);
        $reaches = function (int $from, int $to) use ($deps): bool {
            $stack = array_keys($deps[$from] ?? []);
            $seen = [];
            while ($stack) {
                $k = array_pop($stack);
                if ($k === $to) {
                    return true;
                }
                if (!isset($seen[$k])) {
                    $seen[$k] = true;
                    array_push($stack, ...array_keys($deps[$k] ?? []));
                }
            }

            return false;
        };
        foreach ($live as $i) {
            if (!isset($done[$i])) {
                $waits = array_diff_key($deps[$i], $done);
                $blockedBy[$i] = [$reaches($i, $i) ? "joy almashish — HEMIS'da qo'lda" : reset($waits)];
            }
        }
        foreach ($blockedBy as $i => $texts) {
            $out[$i]['status'] = 'blocked';
            $out[$i]['blocked'] = array_merge($out[$i]['blocked'], $texts);
        }
        foreach ($done as $i => $n) {
            $out[$i]['gseq'] = $n;
        }
    }
}
