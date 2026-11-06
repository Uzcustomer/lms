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
 * Aniqlovchi — sikl qatori (oqim yoki guruhcha) tarkibi: qatordagi amaliy kartalarning fakulteti va guruhchalarining o'quv rejasi (cycleMemberCurriculum).
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
     *   'fac' => [fakultet (kichik harf) => kunlar], 'cur' => ["fakultet|o'quv reja" => kunlar],
     *   'lec' => ['general' => ma'ruza sozlamasi | null, 'fac' => [...], 'cur' => [...]] ] — ma'ruza sozlamasi: ['on' => bool,
     *   'hours' => [kunlik soatlar]] (lecture_separate NULL — shu darajada berilmagan, meros).
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
            $hasLec = Schema::hasColumn('timetable_subject_settings', 'lecture_separate');
            foreach (TimetableSubjectSetting::where('board_id', $board->id)->orderBy('id')->get() as $s) {
                $k = self::cycleSettingKey($s->specialty_name, (int) $s->course, $s->subject_name);
                $fac = $scoped ? mb_strtolower(trim((string) $s->faculty_name)) : '';
                $cur = $scoped ? (int) $s->curriculum_hemis_id : 0;
                $days = $s->cycle_days !== null ? max(1, (int) $s->cycle_days) : null;
                $lec = $hasLec && $s->lecture_separate !== null
                    ? ['on' => (bool) $s->lecture_separate, 'hours' => self::cycleLectureHoursList($s->lecture_hours)] : null;
                $out[$k] ??= ['mode' => null, 'days' => null, 'row' => null, 'fac' => [], 'cur' => [],
                    'lec' => ['general' => null, 'fac' => [], 'cur' => []]];
                if ($fac === '' && $cur === 0) {
                    // Umumiy yozuv (eski ma'lumotda nom katta-kichik harfda farqli dublikat bo'lsa — birinchisi, avvalgidek).
                    if ($out[$k]['row'] === null) {
                        $out[$k]['mode'] = (string) $s->mode;
                        $out[$k]['days'] = $days;
                        $out[$k]['row'] = $s;
                        $out[$k]['lec']['general'] = $lec;
                    }
                    continue;
                }
                $slot = $cur > 0 ? 'cur' : 'fac';
                $slotKey = $cur > 0 ? $fac . '|' . $cur : $fac;
                if ($days !== null) {
                    $out[$k][$slot][$slotKey] = $days;
                }
                if ($lec !== null) {
                    $out[$k]['lec'][$slot][$slotKey] = $lec;
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

    /** Guruhchaning o'quv rejasi (groups.curriculum_hemis_id; kartaning fakulteti bo'yicha); aniqlanmasa — 0. */
    private function cycleMemberCurriculum(object $card, string $member): int
    {
        return $this->cardCurriculumId(['group_names' => [$member], 'group_name' => $member, 'faculty_name' => $card->faculty_name ?? null]);
    }

    /**
     * Joylangan blok kunlari soni: qatordagi kartalar bo'yicha hisoblangan oqim qiymati ($g['subs'], ogohlantirish — $g['scope_notes']);
     * joylashuv guruhchaning o'z qatorida saqlangan bo'lsa ($savedRow oqimdan farqli) — shu qator tarkibi bo'yicha (cyclePlaceOne
     * joylashni ham shu qator bilan tekshiradi). Bo'lak qatori va asosiy nomga yig'ilgan bo'lak joylashuvi — oqim qiymati.
     * Qaytaradi: [kunlar (kamida 1), ogohlantirish | null].
     */
    private function cycleBlockDays(TimetableBoard $board, array $g, string $subject, ?string $savedRow, bool $merged): array
    {
        $days = max(1, (int) ($g['subs'][$subject] ?? 1));
        $note = $g['scope_notes'][$subject] ?? null;
        $parent = (string) ($g['parents'][$subject] ?? $g['parent'] ?? $g['name'] ?? '');
        if ($savedRow === null || $merged || !empty($g['variant'])
            || mb_strtolower(trim($savedRow)) === mb_strtolower(trim($parent))) {
            return [$days, $note];
        }
        [$own, $ownNote] = $this->cycleDaysResolve($board, (string) $g['specialty'], (int) $g['course'], $subject, $savedRow);

        return $own !== null ? [max(1, $own), $ownNote] : [$days, $note];
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
            $base = mb_strtolower(trim((string) $c->specialty_name)) . '|' . (int) $c->course . '|';
            $subject = '|' . mb_strtolower(trim((string) $c->subject_name));
            // O'quv reja — har guruhcha bo'yicha ("A + B" umumiy kartasida ham har a'zo alohida): guruhcha qatoriga o'ziniki,
            // oqim qatoriga a'zolarning hammasiniki.
            foreach ($this->cycleCardGroups($c) as $member) {
                $cid = $this->cycleMemberCurriculum($c, $member);
                foreach (array_unique([mb_strtolower(trim((string) $member)), mb_strtolower(trim($this->cycleFlowName($c, $member)))]) as $row) {
                    $k = $base . $row . $subject;
                    $out[$k]['fac'][$fac] = (string) ($c->faculty_name ?? '');
                    if ($cid > 0) {
                        $out[$k]['cur'][$fac . '|' . $cid] = true;
                    } else {
                        $out[$k]['unknown'] = true;
                    }
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
        [$days, $warn] = $this->cycleScopedPick($board, $set['days'] ?? 1, $set['fac'], $set['cur'], $specialty, $course, $subject, $row);
        // "Ma'ruza alohida": sikl kunlari — jami (ma'ruza kunlari + amaliy); amaliy blok — qolgan kunlar (masalan 21 = 5 + 16).
        if ($lec = $this->cycleLectureResolve($board, $specialty, $course, $subject, $row)) {
            $days = max(1, (int) $days - $lec['days']);
        }

        return [$days, $warn];
    }

    /**
     * Aniqlovchili qiymatni tanlash (sikl kunlari, ma'ruza sozlamasi): qator (oqim / guruhcha) tarkibi bo'yicha — bitta fakultet va bitta
     * o'quv reja: (fakultet + reja) → (fakultet) → (umumiy); bir nechta o'quv reja yoki rejasi aniqlanmagan guruh — (fakultet) → (umumiy);
     * bir nechta fakultet yoki qator noma'lum — umumiy. Qaytaradi: [qiymat, ogohlantirish | null].
     */
    private function cycleScopedPick(TimetableBoard $board, $general, array $facMap, array $curMap, $specialty, int $course, $subject, ?string $row): array
    {
        if ($row === null || (!$facMap && !$curMap)) {
            return [$general, null];
        }
        $ctx = $this->cycleRowCtxIndex($board)[mb_strtolower(trim((string) $specialty)) . '|' . $course . '|' . mb_strtolower(trim($row)) . '|'
            . mb_strtolower(trim((string) $subject))] ?? null;
        $facs = array_keys($ctx['fac'] ?? []);
        if (count($facs) !== 1) {
            // Fakultetlar har xil (yoki qator topilmadi) — umumiy; shu fakultetlardan biriga aniqlovchili sozlama berilgan bo'lsa — ogohlantirish.
            $scopedHere = count($facs) > 1 && array_filter($facs, fn ($f) => array_key_exists($f, $facMap)
                || array_filter(array_keys($curMap), fn ($k) => str_starts_with($k, $f . '|')));

            return [$general, $scopedHere ? 'fakultetlar har xil — umumiy sozlama olindi' : null];
        }
        $fac = $facs[0];
        $curs = array_keys($ctx['cur'] ?? []);
        if (count($curs) === 1 && empty($ctx['unknown']) && array_key_exists($curs[0], $curMap)) {
            return [$curMap[$curs[0]], null];
        }
        // Bir nechta o'quv reja (yoki rejasi aniqlanmagan guruh) — fakultet darajasi; ulardan biriga reja sozlamasi berilgan bo'lsa — ogohlantirish.
        $warn = (count($curs) > 1 || (!empty($ctx['unknown']) && $curs)) && array_filter($curs, fn ($k) => array_key_exists($k, $curMap))
            ? "o'quv rejalar har xil — fakultet sozlamasi olindi" : null;

        return [array_key_exists($fac, $facMap) ? $facMap[$fac] : $general, $warn];
    }

    /**
     * "Ma'ruza alohida" (qator tarkibi bo'yicha, sikl kunlari bilan bir xil ustuvorlik): ['hours' => kunlik soatlar, 'days' => ma'ruza
     * kunlari soni, 'note' => ogohlantirish | null]; belgi yo'q (yoki o'chirilgan, ro'yxat bo'sh) yoki fan sikl rejimida emas — null.
     * Ma'ruza bloki oqim qatorida joylanadi ($row — oqim nomi); amaliy blok qatori uchun ham shu (amaliy kunlari = jami − ma'ruza kunlari).
     */
    private function cycleLectureResolve(TimetableBoard $board, $specialty, int $course, $subject, ?string $row): ?array
    {
        $set = $this->cycleSettingIndex($board)[self::cycleSettingKey($specialty, $course, $subject)] ?? null;
        if (!$set || $set['mode'] !== 'cycle') {
            return null;
        }
        $lec = $set['lec'] ?? ['general' => null, 'fac' => [], 'cur' => []];
        [$cfg, $warn] = $this->cycleScopedPick($board, $lec['general'], $lec['fac'], $lec['cur'], $specialty, $course, $subject, $row);
        if (!$cfg || empty($cfg['on']) || !$cfg['hours']) {
            return null;
        }

        return ['hours' => $cfg['hours'], 'days' => count($cfg['hours']), 'note' => $warn];
    }

    /** Ma'ruza ustunlari bormi (migratsiya bajarilgan). */
    private function cycleLectureColumns(): bool
    {
        return Schema::hasTable('timetable_subject_settings') && Schema::hasColumn('timetable_subject_settings', 'lecture_separate');
    }

    /**
     * Saqlash so'rovidagi "ma'ruza alohida" qiymatlari: [ustunlar (lecture_separate, lecture_hours) | null — yuborilmagan, xato | null,
     * ogohlantirishlar]. Tekshiruv: har kun soati 1..kun soatlari (kun to'ri); ma'ruza kunlari sikl kunlaridan kam (amaliyga kamida
     * 1 kun); yig'indi rejadagi ma'ruza soatiga teng bo'lmasa — ogohlantirish (taqiq emas). Umumiy yozuvda "inherit" — o'chirilgan.
     */
    private function cycleLectureInput(TimetableBoard $board, array $data, string $faculty, int $curriculum, ?TimetableSubjectSetting $existing): array
    {
        $mode = $data['lecture'] ?? null;
        if ($mode === null || !$this->cycleLectureColumns()) {
            return [null, null, []];
        }
        $general = $faculty === '' && $curriculum === 0;
        if ($mode === 'inherit') {
            return [['lecture_separate' => $general ? false : null, 'lecture_hours' => null], null, []];
        }
        if ($mode === 'off') {
            return [['lecture_separate' => false, 'lecture_hours' => null], null, []];
        }
        $raw = $data['lecture_hours'] ?? null;
        $tokens = is_array($raw) ? array_values($raw) : preg_split('/[\s,;]+/u', trim((string) $raw), -1, PREG_SPLIT_NO_EMPTY);
        $dayHours = $this->cycleGridHours($board);
        $hours = [];
        foreach ($tokens as $t) {
            if (!is_numeric($t) || (int) $t != (float) $t || (int) $t < 1 || (int) $t > $dayHours) {
                return [null, 'Ma‘ruza kunlik soati 1 dan ' . $dayHours . ' gacha butun son bo‘lishi kerak (kun to‘ri — ' . $dayHours
                    . ' soat): "' . $t . '".', []];
            }
            $hours[] = (int) $t;
        }
        if (!$hours) {
            return [null, 'Ma‘ruza kunlik soatlarini kiriting (soatda, vergul bilan, masalan 8,8,6,6,6).', []];
        }
        if (count($hours) > 60) {
            return [null, 'Ma‘ruza kunlari juda ko‘p (60 dan oshmasin).', []];
        }
        // Shu darajadagi jami sikl kunlari: yuborilgan qiymat, bo'lmasa meros (o'quv reja → fakultet → umumiy).
        $set = $this->cycleSettingIndex($board)[self::cycleSettingKey($data['specialty_name'], (int) $data['course'], $data['subject_name'])] ?? [];
        $facKey = mb_strtolower($faculty);
        $total = $data['cycle_days'] ?? null;
        if ($total === null && !$general) {
            $total = ($curriculum > 0 ? ($set['cur'][$facKey . '|' . $curriculum] ?? null) : null) ?? ($set['fac'][$facKey] ?? null) ?? ($set['days'] ?? null);
        }
        if ($total !== null && count($hours) >= (int) $total) {
            return [null, 'Ma‘ruza kunlari (' . count($hours) . ') sikl kunlaridan (' . (int) $total . ') kam bo‘lishi kerak: sikl kunlari — jami '
                . '(ma‘ruza + amaliy), masalan 21 = 5 + 16.', []];
        }
        $warnings = [];
        $plan = $this->cycleHoursLookup($this->cycleSubjectHoursMap($board), $faculty !== '' ? $faculty : null, $data['specialty_name'],
            (int) $data['course'], $data['subject_name'])['lecture'] ?? null;
        if ($plan !== null && abs(array_sum($hours) - (float) $plan) > 0.01) {
            $fmt = fn ($v) => fmod((float) $v, 1.0) == 0.0 ? (string) (int) $v : (string) round((float) $v, 1);
            $warnings[] = 'Ma‘ruza soatlari yig‘indisi ' . array_sum($hours) . ' — rejada ' . $fmt($plan) . ' soat.';
        }

        return [['lecture_separate' => true, 'lecture_hours' => $hours], null, $warnings];
    }

    /**
     * Alohida ma'ruzali fan bloki uchun kun rejasi kirishlari (cycleDayPlan): [KTR kun rejasi | null, soatlar | null, KTR bormi].
     * - ma'ruza bloki: har kun soati — ro'yxatdan; KTR ma'ruza mavzulari kunlarga tartib bilan, soatlar bo'yicha (8 soatli kunga 4 ta
     *   2 soatlik mavzu);
     * - amaliy bloki: ma'ruzasiz; KTR bo'lsa — amaliy mavzular amaliy kunlariga tartib bilan (kuniga 6 soatgacha), bo'lmasa — reja
     *   soatlari (ma'ruza 0).
     * $lec null (belgi yo'q) — kirishlar o'zgarmaydi (hozirgidek: ma'ruza amaliy blok ichida).
     */
    private function cycleSepDayInputs(?array $lec, string $type, ?array $ktrPlan, ?array $hours, int $days): array
    {
        if ($lec === null) {
            return [$ktrPlan, $hours, $ktrPlan !== null];
        }
        $rows = ['lec' => [], 'prc' => []];
        if ($ktrPlan !== null) {
            ksort($ktrPlan);
            foreach ($ktrPlan as $d) {
                if ((int) ($d['lec'] ?? 0) > 0) {
                    $rows['lec'][] = [(int) $d['lec'], trim((string) ($d['tl'] ?? ''))];
                }
                if ((int) ($d['prc'] ?? 0) > 0) {
                    $rows['prc'][] = [(int) $d['prc'], trim((string) ($d['tp'] ?? ''))];
                }
            }
        }
        // Mavzularni kunlarga soat bo'yicha tartib bilan: kun sig'imi to'lguncha navbatdagi mavzular (oxirgi kunga qolgani ham).
        $fill = function (array $list, array $caps): array {
            $out = [];
            $i = 0;
            $left = $list[0][0] ?? 0;
            $n = count($caps);
            foreach (array_values($caps) as $k => $cap) {
                $topics = [];
                $need = $cap;
                while ($i < count($list) && ($need > 0 || $k === $n - 1)) {
                    if ($list[$i][1] !== '' && end($topics) !== $list[$i][1]) {
                        $topics[] = $list[$i][1];
                    }
                    $take = min($need, $left);
                    $need -= $take;
                    $left -= $take;
                    if ($left <= 0 || ($need <= 0 && $k === $n - 1)) {
                        $i++;
                        $left = $list[$i][0] ?? 0;
                    }
                }
                $out[$k] = implode('; ', $topics);
            }

            return $out;
        };
        if ($type === 'lecture') {
            $caps = array_values($lec['hours']);
            $topics = $fill($rows['lec'], $caps);
            $plan = [];
            foreach ($caps as $k => $h) {
                $plan[$k] = ['lec' => (int) $h, 'prc' => 0, 'tl' => $topics[$k] ?? '', 'tp' => ''];
            }

            return [$plan, null, $ktrPlan !== null];
        }
        $prcHours = $hours !== null ? ['lecture' => 0, 'practice' => (float) ($hours['practice'] ?? 0)] + $hours : null;
        if ($prcHours !== null) {
            $prcHours['lecture'] = 0;
        }
        if ($ktrPlan === null) {
            return [null, $prcHours, false];
        }
        $left = array_sum(array_column($rows['prc'], 0));
        $caps = [];
        for ($k = 0; $k < max(1, $days); $k++) {
            $caps[$k] = min(6, max(0, $left));
            $left -= $caps[$k];
        }
        $topics = $fill($rows['prc'], $caps);
        $plan = [];
        foreach ($caps as $k => $h) {
            $plan[$k] = ['lec' => 0, 'prc' => (int) $h, 'tl' => '', 'tp' => $topics[$k] ?? ''];
        }

        return [$plan, $prcHours, true];
    }

    /** Kunlik ma'ruza soatlari ro'yxati (JSON massiv yoki "8,8,6,6,6" matni) — butun musbat sonlar. */
    private static function cycleLectureHoursList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/[\s,;]+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        }
        $out = [];
        foreach ((array) $value as $h) {
            if (is_numeric($h) && (int) $h >= 1) {
                $out[] = (int) $h;
            }
        }

        return $out;
    }

    /**
     * Joylashuv blokining kunlari soni — turi bo'yicha: "ma'ruza alohida" fanning ma'ruza yozuvi — ma'ruza kunlari (ro'yxat uzunligi);
     * qolganlari — cycleDaysFor (alohida ma'ruzali fanda amaliy — qolgan kunlar). Fan sikl rejimida bo'lmasa — null.
     */
    private function cyclePlacementTypeDays(TimetableBoard $board, $specialty, int $course, $subject, ?string $row, string $type): ?int
    {
        if ($type === 'lecture' && ($lec = $this->cycleLectureResolve($board, $specialty, $course, $subject, $row))) {
            return $lec['days'];
        }

        return $this->cycleDaysFor($board, $specialty, $course, $subject, $row);
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
            $out[(int) $p->id] = ['days' => $this->cyclePlacementTypeDays($board, $specialty, $course, $subject, $row, (string) ($p->training_type ?: 'practice')),
                'row' => $row,
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
            $faculties[$fac] ??= ['name' => $fac, 'curricula' => []];
            foreach ($this->cycleCardGroups($c) as $g) {
                $faculties[$fac]['curricula'][$this->cycleMemberCurriculum($c, $g)]['groups'][$g] = true;
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
                    'days' => (int) $cid > 0 ? ($set['cur'][$facKey . '|' . $cid] ?? null) : null,
                    'lecture' => (int) $cid > 0 ? ($set['lec']['cur'][$facKey . '|' . $cid] ?? null) : null];
            }
            usort($curricula, fn ($a, $b) => strnatcasecmp($a['prefix'], $b['prefix']));
            $planLec = $this->cycleHoursLookup($hoursMap ??= $this->cycleSubjectHoursMap($board), $fac, $data['specialty_name'], (int) $data['course'],
                $data['subject_name'])['lecture'] ?? null;
            $out[] = ['name' => $fac, 'days' => $set['fac'][$facKey] ?? null, 'lecture' => $set['lec']['fac'][$facKey] ?? null,
                'plan_lecture_hours' => $planLec !== null ? (float) $planLec : null, 'curricula' => $curricula];
        }
        usort($out, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        $planGeneral = $this->cycleHoursLookup($hoursMap ?? $this->cycleSubjectHoursMap($board), null, $data['specialty_name'], (int) $data['course'],
            $data['subject_name'])['lecture'] ?? null;

        // Ma'ruza sozlamasi: lecture — ['on', 'hours'] | null (meros); day_hours — kunlik soat chegarasi (kun to'ri).
        return response()->json(['mode' => $set['mode'] ?? 'normal', 'days' => $set['days'] ?? null, 'lecture' => $set['lec']['general'] ?? null,
            'plan_lecture_hours' => $planGeneral !== null ? (float) $planGeneral : null, 'day_hours' => $this->cycleGridHours($board),
            'faculties' => $out]);
    }
}
