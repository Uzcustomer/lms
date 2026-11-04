<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\TimetableBoard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doska davrida o'tiladigan semestr — o'quv reja SANALARI bo'yicha (juft/toqlik bo'yicha emas). Bitta doskada 7- va 8-semestr
 * guruhlari (masalan bahorgi qabul — juft semestr kuzgi davrda) birga chiqadi: har guruh o'z o'quv rejasi va o'z semestri.
 *
 * - Doska davri: sozlamadagi semester_start dan haftalar soni × 7 kun; sozlama yo'q bo'lsa — kuzgi: 1-sentyabr – 31-yanvar,
 *   bahorgi: 1-fevral – 30-iyun (academic_year boshidan).
 * - O'quv reja semestri oralig'i: curriculum_weeks (semester_hemis_id) MIN(start_date) – MAX(end_date). Doska davri bilan eng ko'p
 *   kunga kesishgan semestr; teng bo'lsa — doska juft/toqligiga mosi. education_year qaralmaydi (HEMIS'da xato kiritilgan holatlar
 *   bor). semesters.current ham emas (ta'til kunlari false bo'lishi mumkin).
 * - Haftalari yo'q o'quv reja — eski qoida (education_year + juft/toqlik).
 */
trait TimetableBoardSemesters
{
    /** Doska davri: [boshi, oxiri] ('Y-m-d'). */
    public function boardPeriod(TimetableBoard $board): array
    {
        $y = (int) substr((string) $board->academic_year, 0, 4);
        $start = (string) (($board->settings ?? [])['semester_start'] ?? '');
        if ($start !== '' && strtotime($start)) {
            $weeks = max(1, (int) ($board->weeks ?: 15));

            return [Carbon::parse($start)->toDateString(), Carbon::parse($start)->addDays($weeks * 7 - 1)->toDateString()];
        }

        return $board->semester_parity === 'bahorgi'
            ? [sprintf('%04d-02-01', $y + 1), sprintf('%04d-06-30', $y + 1)]
            : [sprintf('%04d-09-01', $y), sprintf('%04d-01-31', $y + 1)];
    }

    /**
     * Har o'quv reja uchun doska davridagi semestr: [curriculum_hemis_id => semester_code]. 'detail' => [id => [code, from, to, days,
     * source: dates|legacy]], 'missing' => [id, ...] — haftalari bor, lekin davr bilan kesishgan semestri yo'q. So'rov davomida bir marta.
     */
    public function boardSemesterInfo(TimetableBoard $board): array
    {
        // Kesh — joriy HTTP so'rov davomida (kontroller nusxasi so'rovlar orasida qayta ishlatilishi mumkin — eski semestrlar qolmasin).
        $memoKey = 'timetable.board_semesters.' . $board->id;
        if (request()->attributes->has($memoKey)) {
            return request()->attributes->get($memoKey);
        }
        [$from, $to] = $this->boardPeriod($board);
        $y = (int) substr((string) $board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;
        $codes = [];
        $detail = [];
        $withWeeks = [];
        if (Schema::hasTable('semesters') && Schema::hasTable('curriculum_weeks')) {
            $rows = DB::table('semesters as s')->join('curriculum_weeks as w', 'w.semester_hemis_id', '=', 's.semester_hemis_id')
                ->groupBy('s.curriculum_hemis_id', 's.code')
                ->selectRaw('s.curriculum_hemis_id, s.code, MIN(w.start_date) as d1, MAX(w.end_date) as d2')->get();
            $best = [];
            foreach ($rows as $r) {
                $cid = (int) $r->curriculum_hemis_id;
                $withWeeks[$cid] = true;
                $a = substr((string) $r->d1, 0, 10);
                $b = substr((string) $r->d2, 0, 10);
                $s = max($a, $from);
                $e = min($b, $to);
                $days = $s <= $e ? (int) Carbon::parse($s)->diffInDays(Carbon::parse($e)) + 1 : 0;
                if ($days < 1) {
                    continue;
                }
                $parityOk = ((int) $r->code - 10) % 2 === $parityRem;
                $rank = [$days, (int) $parityOk];
                if (!isset($best[$cid]) || $rank > $best[$cid]['rank']) {
                    $best[$cid] = ['rank' => $rank, 'code' => (string) $r->code, 'from' => $a, 'to' => $b, 'days' => $days];
                }
            }
            foreach ($best as $cid => $b) {
                $codes[$cid] = $b['code'];
                $detail[$cid] = ['code' => $b['code'], 'from' => $b['from'], 'to' => $b['to'], 'days' => $b['days'], 'source' => 'dates'];
            }
        }
        // Haftalari yo'q o'quv reja — eski qoida (orqaga moslik).
        if (Schema::hasTable('semesters')) {
            foreach (DB::table('semesters')->where('education_year', (string) $y)
                ->whereRaw('MOD(CAST(code AS UNSIGNED) - 10, 2) = ?', [$parityRem])->get(['curriculum_hemis_id', 'code']) as $r) {
                $cid = (int) $r->curriculum_hemis_id;
                if (!isset($withWeeks[$cid]) && !isset($codes[$cid])) {
                    $codes[$cid] = (string) $r->code;
                    $detail[$cid] = ['code' => (string) $r->code, 'from' => null, 'to' => null, 'days' => null, 'source' => 'legacy'];
                }
            }
        }
        $missing = array_values(array_diff(array_keys($withWeeks), array_keys($codes)));

        $info = ['codes' => $codes, 'detail' => $detail, 'missing' => $missing, 'period' => [$from, $to]];
        request()->attributes->set($memoKey, $info);

        return $info;
    }

    /** [curriculum_hemis_id => semester_code] — doska davrida o'tiladigan semestr. */
    public function boardSemesterCodes(TimetableBoard $board): array
    {
        return $this->boardSemesterInfo($board)['codes'];
    }

    /**
     * Eski qoida (taqqoslash uchun): [curriculum_hemis_id => semester_code] — education_year + juft/toqlik.
     */
    public function boardSemesterCodesLegacy(TimetableBoard $board): array
    {
        $y = (int) substr((string) $board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;
        $out = [];
        if (Schema::hasTable('semesters')) {
            foreach (DB::table('semesters')->where('education_year', (string) $y)
                ->whereRaw('MOD(CAST(code AS UNSIGNED) - 10, 2) = ?', [$parityRem])->orderBy('code')->get(['curriculum_hemis_id', 'code']) as $r) {
                $out[(int) $r->curriculum_hemis_id] ??= (string) $r->code;
            }
        }

        return $out;
    }

    /**
     * HEMIS so'rovi: curriculum_subjects (cs) faqat doska davridagi semestri bo'yicha — (o'quv reja, semestr) juftligi.
     * $q — 'cs' alias bilan; juftliklar soni ko'p bo'lsa ham bitta OR guruhi (o'quv reja bo'yicha indeksli ustun).
     */
    private function whereBoardSemester($q, TimetableBoard $board, string $cs = 'cs'): void
    {
        $codes = $this->boardSemesterCodes($board);
        $q->where(function ($w) use ($codes, $cs) {
            if (!$codes) {
                $w->whereRaw('1 = 0');

                return;
            }
            $byCode = [];
            foreach ($codes as $cid => $code) {
                $byCode[(string) $code][] = (int) $cid;
            }
            foreach ($byCode as $code => $ids) {
                $w->orWhere(fn ($o) => $o->where($cs . '.semester_code', (string) $code)->whereIn($cs . '.curricula_hemis_id', $ids));
            }
        });
    }

    /**
     * Ishchi reja (manual_curricula mc, manual_curriculum_subjects s): HEMIS o'quv rejasiga bog'langan (mc.curricula_hemis_id) va
     * semestri sanalar bo'yicha topilgan bo'lsa — shu semestr (s.semester = kod − 10); aks holda eski qoida (juft/toqlik + reja yili).
     */
    private function whereManualSemester($q, TimetableBoard $board): void
    {
        $codes = $this->boardSemesterCodes($board);
        $start = (int) substr((string) $board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;
        $linked = array_map('intval', DB::table('manual_curricula')->whereNotNull('curricula_hemis_id')->pluck('curricula_hemis_id')->unique()->all());
        $mine = array_intersect_key($codes, array_flip($linked));
        $q->where(function ($w) use ($mine, $start, $parityRem) {
            $bySem = [];
            foreach ($mine as $cid => $code) {
                $bySem[(int) $code - 10][] = (int) $cid;
            }
            foreach ($bySem as $sem => $ids) {
                $w->orWhere(fn ($o) => $o->where('s.semester', $sem)->whereIn('mc.curricula_hemis_id', $ids));
            }
            $w->orWhere(function ($o) use ($mine, $start, $parityRem) {
                // Semestri sanalar bo'yicha topilmagan rejalar (bog'lanmagan yoki o'quv rejasi semestri topilmagan) — eski qoida;
                // hech biri topilmagan bo'lsa ($mine bo'sh) — barcha rejalar eski qoida bilan (bog'langanlari ham).
                $o->when($mine, fn ($x) => $x->where(fn ($n) => $n->whereNull('mc.curricula_hemis_id')->orWhereNotIn('mc.curricula_hemis_id', array_keys($mine))))
                    ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
                    ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + (CASE WHEN CAST(mc.level_code AS UNSIGNED) >= 11 THEN CAST(mc.level_code AS UNSIGNED) - 10 ELSE CAST(mc.level_code AS UNSIGNED) END) - 1) = ?", [$start]);
            });
        });
    }

    /**
     * Faol talabasi bor guruh, o'quv rejasida shu davrga kesishgan semestr yo'q — ogohlantirishlar:
     * "{guruh} ({o'quv reja}): shu davrda semestr yo'q — HEMIS'da semestr sanalarini tekshiring".
     */
    public function boardSemesterWarnings(TimetableBoard $board): array
    {
        $missing = $this->boardSemesterInfo($board)['missing'];
        if (!$missing || !Schema::hasTable('groups')) {
            return [];
        }
        $q = DB::table('groups as g')->whereIn('g.curriculum_hemis_id', $missing)
            ->leftJoin('curricula as c', 'c.curricula_hemis_id', '=', 'g.curriculum_hemis_id');
        if (Schema::hasTable('students')) {
            $q->whereExists(fn ($e) => $e->selectRaw('1')->from('students as st')->whereColumn('st.group_name', 'g.name')->where('st.student_status_code', 11));
        }
        $out = [];
        foreach ($q->orderBy('g.name')->get(['g.name', 'c.name as plan']) as $g) {
            $out[] = $g->name . ' (' . ($g->plan ?: "o'quv reja") . "): shu davrda semestr yo'q — HEMIS'da semestr sanalarini tekshiring";
        }

        return array_values(array_unique($out));
    }
}
