<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\TimetableBoard;
use App\Models\TimetableSubjectSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Sikl fan sozlamasi fakultet va o'quv reja bo'yicha (timetable_subject_settings: faculty_name, curriculum_hemis_id).
 *
 * Bir yo'nalish va kursdagi fan turli fakultet / o'quv rejada turli sikl kunlari soniga ega bo'lishi mumkin. Qidiruv ustuvorligi:
 * (fakultet + o'quv reja) → (fakultet) → (umumiy). Umumiy yozuv — ('', 0); fan rejimi (sikl / hafta almashinuvi) faqat unda.
 * Aniqlovchi — sikl qatori (oqim yoki guruhcha) tarkibi: qatordagi amaliy kartalarning fakulteti va o'quv rejasi (cardCurriculumId).
 * Bir qatorda bir nechta o'quv reja (yoki rejasi aniqlanmagan guruh) — fakultet darajasi; bir nechta fakultet — umumiy.
 */
trait TimetableCycleSettingScope
{
    /** "yo'nalish|kurs|fan" — kichik harf, chetlari kesilgan (sozlama va karta nomlari katta-kichik harfda farq qilishi mumkin). */
    private static function cycleSettingKey($specialty, int $course, $subject): string
    {
        return mb_strtolower(trim((string) $specialty)) . '|' . $course . '|' . mb_strtolower(trim((string) $subject));
    }

    /**
     * Doska sozlamalari indeksi (so'rov davomida bir marta): kalit (cycleSettingKey) => [
     *   'mode' => umumiy yozuv rejimi | null, 'days' => umumiy cycle_days | null, 'row' => umumiy yozuv (model) | null,
     *   'fac' => [fakultet (kichik harf) => kunlar], 'cur' => ["fakultet|o'quv reja" => kunlar] ].
     */
    private function cycleSettingIndex(TimetableBoard $board): array
    {
        $memo = 'timetable.cycle_setting_index.' . $board->id;
        if (request()->attributes->has($memo)) {
            return request()->attributes->get($memo);
        }
        $out = [];
        if (Schema::hasTable('timetable_subject_settings')) {
            $scoped = Schema::hasColumn('timetable_subject_settings', 'faculty_name');
            foreach (TimetableSubjectSetting::where('board_id', $board->id)->orderBy('id')->get() as $s) {
                $k = self::cycleSettingKey($s->specialty_name, (int) $s->course, $s->subject_name);
                $fac = $scoped ? mb_strtolower(trim((string) $s->faculty_name)) : '';
                $cur = $scoped ? (int) $s->curriculum_hemis_id : 0;
                $days = $s->cycle_days !== null ? max(1, (int) $s->cycle_days) : null;
                $out[$k] ??= ['mode' => null, 'days' => null, 'row' => null, 'fac' => [], 'cur' => []];
                if ($fac === '' && $cur === 0) {
                    // Umumiy yozuv (eski ma'lumotda nom katta-kichik harfda farqli dublikat bo'lsa — birinchisi, avvalgidek).
                    if ($out[$k]['row'] === null) {
                        $out[$k]['mode'] = (string) $s->mode;
                        $out[$k]['days'] = $days;
                        $out[$k]['row'] = $s;
                    }
                } elseif ($days !== null) {
                    if ($cur > 0) {
                        $out[$k]['cur'][$fac . '|' . $cur] = $days;
                    } else {
                        $out[$k]['fac'][$fac] = $days;
                    }
                }
            }
        }
        request()->attributes->set($memo, $out);

        return $out;
    }

    /** Sozlama saqlangandan keyin — so'rov keshlari (indeks va qatorlar tarkibi) qayta quriladi. */
    private function cycleSettingForget(TimetableBoard $board): void
    {
        request()->attributes->remove('timetable.cycle_setting_index.' . $board->id);
        request()->attributes->remove('timetable.cycle_row_ctx.' . $board->id);
        request()->attributes->remove('timetable.cycle_units.' . $board->id);
    }

    /**
     * Og'ir sikl hisoblari (cyclePlan, cycleInternalUnits) boshida sozlamalar indeksi va qatorlar tarkibi bazadan qayta quriladi —
     * shu so'rovda avval kartalar / sozlamalar o'zgargan bo'lsa ham (masalan HEMIS sinxroni, bir so'rovda ketma-ket hisoblar) eski
     * kesh ishlatilmaydi. Ichma-ich chaqiriqlar (cycleInternalUnits → cyclePlan) va hisob ichidagi yengil chaqiriqlar keshni bo'lishadi.
     */
    private function cycleSettingFresh(TimetableBoard $board, \Closure $fn)
    {
        $open = 'timetable.cycle_setting_open.' . $board->id;
        if (request()->attributes->has($open)) {
            return $fn();
        }
        request()->attributes->remove('timetable.cycle_setting_index.' . $board->id);
        request()->attributes->remove('timetable.cycle_row_ctx.' . $board->id);
        request()->attributes->set($open, true);
        try {
            return $fn();
        } finally {
            request()->attributes->remove($open);
        }
    }

    /** Fan sikl rejimidami (umumiy yozuv bo'yicha). */
    private function cycleSettingIsCycle(TimetableBoard $board, $specialty, int $course, $subject): bool
    {
        return ($this->cycleSettingIndex($board)[self::cycleSettingKey($specialty, $course, $subject)]['mode'] ?? null) === 'cycle';
    }

    /**
     * Sikl qatorlari tarkibi (so'rov davomida bir marta): "yo'nalish|kurs|qator|fan" (qator — oqim nomi yoki guruhcha, kichik harf) =>
     * ['fac' => [fakultet (kichik harf) => asl nomi], 'cur' => ["fakultet|o'quv reja" => true], 'unknown' => o'quv rejasi aniqlanmagan
     * guruh bor].
     * Barcha amaliy kartalardan — qamrov filtridan qat'i nazar (blok uzunligi filtrga bog'liq bo'lmasin).
     */
    private function cycleRowCtxIndex(TimetableBoard $board): array
    {
        $memo = 'timetable.cycle_row_ctx.' . $board->id;
        if (request()->attributes->has($memo)) {
            return request()->attributes->get($memo);
        }
        $out = [];
        foreach ($this->cardRows($board, fn ($q) => $q->where('training_type', 'practice')->whereNotNull('group_name')) as $c) {
            $fac = mb_strtolower(trim((string) ($c->faculty_name ?? '')));
            $cid = $this->cardCurriculumId($c);
            $base = mb_strtolower(trim((string) $c->specialty_name)) . '|' . (int) $c->course . '|';
            $subject = '|' . mb_strtolower(trim((string) $c->subject_name));
            $rows = [];
            foreach ($this->cycleCardGroups($c) as $member) {
                $rows[mb_strtolower(trim((string) $member))] = true;
                $rows[mb_strtolower(trim($this->cycleFlowName($c, $member)))] = true;
            }
            foreach (array_keys($rows) as $row) {
                $k = $base . $row . $subject;
                $out[$k]['fac'][$fac] = (string) ($c->faculty_name ?? '');
                if ($cid > 0) {
                    $out[$k]['cur'][$fac . '|' . $cid] = true;
                } else {
                    $out[$k]['unknown'] = true;
                }
            }
        }
        request()->attributes->set($memo, $out);

        return $out;
    }

    /**
     * Sikl kunlari soni (kamida 1) — qator (oqim / guruhcha; $row) tarkibi bo'yicha: bitta fakultet va bitta o'quv reja —
     * (fakultet + reja) → (fakultet) → (umumiy); bir nechta o'quv reja yoki rejasi aniqlanmagan guruh bor — (fakultet) → (umumiy);
     * bir nechta fakultet yoki qator noma'lum ($row null) — umumiy. Fan sikl rejimida bo'lmasa — null.
     */
    private function cycleDaysFor(TimetableBoard $board, $specialty, int $course, $subject, ?string $row): ?int
    {
        return $this->cycleDaysResolve($board, $specialty, $course, $subject, $row)[0];
    }

    /**
     * cycleDaysFor + ogohlantirish: qatordagi o'quv rejalar (yoki fakultetlar) har xil bo'lgani uchun ulardan biriga berilgan aniqlovchili
     * sozlama qo'llanmay qolsa — "o'quv rejalar har xil — fakultet sozlamasi olindi" (fakultetlar har xil — "… umumiy sozlama olindi").
     * Qaytaradi: [kunlar | null, ogohlantirish | null].
     */
    private function cycleDaysResolve(TimetableBoard $board, $specialty, int $course, $subject, ?string $row): array
    {
        $set = $this->cycleSettingIndex($board)[self::cycleSettingKey($specialty, $course, $subject)] ?? null;
        if (!$set || $set['mode'] !== 'cycle') {
            return [null, null];
        }
        $general = $set['days'] ?? 1;
        if ($row === null || (!$set['fac'] && !$set['cur'])) {
            return [$general, null];
        }
        $ctx = $this->cycleRowCtxIndex($board)[mb_strtolower(trim((string) $specialty)) . '|' . $course . '|' . mb_strtolower(trim($row)) . '|'
            . mb_strtolower(trim((string) $subject))] ?? null;
        $facs = array_keys($ctx['fac'] ?? []);
        if (count($facs) !== 1) {
            // Fakultetlar har xil (yoki qator topilmadi) — umumiy; shu fakultetlardan biriga aniqlovchili sozlama berilgan bo'lsa — ogohlantirish.
            $scopedHere = count($facs) > 1 && array_filter($facs, fn ($f) => isset($set['fac'][$f])
                || array_filter(array_keys($set['cur']), fn ($k) => str_starts_with($k, $f . '|')));

            return [$general, $scopedHere ? 'fakultetlar har xil — umumiy sozlama olindi' : null];
        }
        $fac = $facs[0];
        $curs = array_keys($ctx['cur'] ?? []);
        if (count($curs) === 1 && empty($ctx['unknown']) && isset($set['cur'][$curs[0]])) {
            return [$set['cur'][$curs[0]], null];
        }
        // Bir nechta o'quv reja (yoki rejasi aniqlanmagan guruh) — fakultet darajasi; ulardan biriga reja sozlamasi berilgan bo'lsa — ogohlantirish.
        $warn = (count($curs) > 1 || (!empty($ctx['unknown']) && $curs)) && array_filter($curs, fn ($k) => isset($set['cur'][$k]))
            ? "o'quv rejalar har xil — fakultet sozlamasi olindi" : null;

        return [$set['fac'][$fac] ?? $general, $warn];
    }

    /**
     * Fanning joylangan bloklari va ularning hozirgi kunlar soni: [joylashuv id => ['days', 'row' (qator nomi), 'faculty', 'layer']] —
     * sozlama saqlashdan oldin / keyin solishtirish uchun (cycleSettingSave: "N ta joylangan blok uzunligi o'zgaradi").
     */
    private function cycleSettingPlacedDays(TimetableBoard $board, string $specialty, int $course, string $subject): array
    {
        if (!Schema::hasTable('timetable_cycle_placements')) {
            return [];
        }
        $ctx = $this->cycleRowCtxIndex($board);
        $out = [];
        $spec = mb_strtolower(trim($specialty));
        $subj = mb_strtolower(trim($subject));
        foreach (\App\Models\TimetableCyclePlacement::where('board_id', $board->id)->where('course', $course)->whereNotNull('start_index')->get() as $p) {
            if (mb_strtolower(trim((string) $p->specialty_name)) !== $spec || mb_strtolower(trim((string) $p->subject_name)) !== $subj) {
                continue;
            }
            $row = (string) $p->group_name;
            $facs = array_values(array_filter($ctx[$spec . '|' . $course . '|' . mb_strtolower(trim($row)) . '|' . $subj]['fac'] ?? []));
            $out[(int) $p->id] = ['days' => $this->cycleDaysFor($board, $specialty, $course, $subject, $row), 'row' => $row,
                'faculty' => $this->cycleRowFaculty($row) ?? ($facs ? implode(', ', array_unique($facs)) : ''),
                'type' => (string) ($p->training_type ?: 'practice')];
        }

        return $out;
    }

    /**
     * "Darslar" oynasi — fan uchun fakultet / o'quv reja ro'yxati (shu yo'nalish, kurs va fan kartalaridan): har fakultet va uning o'quv
     * rejalari (nomi, doska semestri, guruh prefiksi, guruhlar soni) hamda saqlangan kunlar soni (bo'sh — yuqori daraja).
     */
    public function cycleSettingScopes(\Illuminate\Http\Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'specialty_name' => 'required|string|max:255',
            'course'         => 'required|integer|min:1|max:7',
            'subject_name'   => 'required|string|max:255',
        ]);
        $spec = mb_strtolower(trim($data['specialty_name']));
        $subj = mb_strtolower(trim($data['subject_name']));
        $set = $this->cycleSettingIndex($board)[self::cycleSettingKey($data['specialty_name'], (int) $data['course'], $data['subject_name'])] ?? null;
        // Shu fan kartalari (bo'lmasa — yo'nalish va kursning barcha amaliy kartalari): fakultet → o'quv reja → guruhlar.
        $cards = array_values(array_filter($this->cardRows($board, fn ($q) => $q->where('course', (int) $data['course'])
            ->where('training_type', 'practice')->whereNotNull('group_name')), fn ($c) => mb_strtolower(trim((string) $c->specialty_name)) === $spec));
        $mine = array_values(array_filter($cards, fn ($c) => mb_strtolower(trim((string) $c->subject_name)) === $subj));
        $faculties = [];
        foreach ($mine ?: $cards as $c) {
            $fac = trim((string) ($c->faculty_name ?? ''));
            $cid = $this->cardCurriculumId($c);
            $faculties[$fac] ??= ['name' => $fac, 'curricula' => []];
            foreach ($this->cycleCardGroups($c) as $g) {
                $faculties[$fac]['curricula'][$cid]['groups'][$g] = true;
            }
        }
        $codes = $this->boardSemesterCodes($board);
        $ids = array_values(array_filter(array_unique(array_merge(...array_map(fn ($f) => array_keys($f['curricula']), array_values($faculties) ?: [['curricula' => []]])))));
        $names = $ids && Schema::hasTable('curricula')
            ? \Illuminate\Support\Facades\DB::table('curricula')->whereIn('curricula_hemis_id', $ids)->pluck('name', 'curricula_hemis_id')->all() : [];
        $semesters = [];
        if ($ids && Schema::hasTable('semesters')) {
            foreach (\Illuminate\Support\Facades\DB::table('semesters')->whereIn('curriculum_hemis_id', $ids)->get(['curriculum_hemis_id', 'code', 'name']) as $r) {
                if (isset($codes[(int) $r->curriculum_hemis_id]) && (string) $codes[(int) $r->curriculum_hemis_id] === (string) $r->code) {
                    $semesters[(int) $r->curriculum_hemis_id] = (string) $r->name;
                }
            }
        }
        $out = [];
        foreach ($faculties as $fac => $f) {
            $facKey = mb_strtolower($fac);
            $curricula = [];
            foreach ($f['curricula'] as $cid => $x) {
                $groups = array_keys($x['groups']);
                sort($groups, SORT_NATURAL);
                // Guruh prefiksi: "xd/22-02a" → "xd/22" (bir nechta bo'lsa — vergul bilan).
                $prefixes = array_values(array_unique(array_map(fn ($g) => preg_replace('/-[^-]*$/u', '', (string) $g), $groups)));
                $curricula[] = ['id' => (int) $cid, 'name' => (int) $cid > 0 ? (string) ($names[$cid] ?? ('O‘quv reja #' . $cid)) : 'O‘quv rejasi aniqlanmagan',
                    'semester' => $semesters[$cid] ?? null, 'prefix' => implode(', ', $prefixes), 'groups' => count($groups),
                    'days' => (int) $cid > 0 ? ($set['cur'][$facKey . '|' . $cid] ?? null) : null];
            }
            usort($curricula, fn ($a, $b) => strnatcasecmp($a['prefix'], $b['prefix']));
            $out[] = ['name' => $fac, 'days' => $set['fac'][$facKey] ?? null, 'curricula' => $curricula];
        }
        usort($out, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return response()->json(['mode' => $set['mode'] ?? 'normal', 'days' => $set['days'] ?? null, 'faculties' => $out]);
    }
}
