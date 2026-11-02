<?php

namespace App\Http\Controllers\Admin\Concerns;

/**
 * To'qnashuvni ko'rsatish ("Ko'rsatish" havolasi) uchun tuzilgan ma'lumot — matn bilan birga qaytadi, front uni parse qilmaydi:
 *   kind (teacher_busy | room_busy | group_busy | ...), teacher_id, teacher_name, room_code, groups [{group, faculty, specialty,
 *   course}] (to'qnashuvdagi barcha guruh(cha)lar — qamrovi bilan: nomlar fakultet/yo'nalish/kurslar bo'ylab takrorlanadi), subject,
 *   dates [from, to], hour_from / hour_to (haftalik — yarim-slot (para) indekslari, sikl — kun soatlari; to — oxiridan keyingi),
 *   card_ids, view (week — haftalik jadval, cycle — sikl jadvali), week (haftalik).
 * Qaysi guruhlarni ko'rsatish (rol qamrovi) — frontda: foydalanuvchi yuklay oladigan qamrovdagilar; qolganlari faqat matnda.
 */
trait TimetableConflictFocus
{
    /** Kartaning guruhlari qamrovi bilan (oqim ma'ruzasi — barcha guruhchalari; "A + B" — har biri). */
    private function focusCardGroups(object $c): array
    {
        $out = [];
        foreach ($this->cardGroupsOf($c) as $g) {
            foreach (preg_split('/\s*\+\s*/u', trim((string) $g)) as $name) {
                if ($name !== '') {
                    $out[] = ['group' => $name, 'faculty' => (string) ($c->faculty_name ?? ''), 'specialty' => (string) ($c->specialty_name ?? ''),
                        'course' => (int) ($c->course ?? 0)];
                }
            }
        }

        return $out;
    }

    /** Sikl birligi guruh(cha)lari qamrovi bilan: qator nomi ("A + B" — har biri) va a'zolari (bo'lak qatori guruhchalari). */
    private function focusUnitGroups(array $u): array
    {
        $names = array_merge(preg_split('/\s*\+\s*/u', trim((string) ($u['group'] ?? ''))), array_map('strval', (array) ($u['members'] ?? [])));
        $out = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name !== '') {
                $out[] = ['group' => $name, 'faculty' => (string) ($u['faculty'] ?? ''), 'specialty' => (string) ($u['specialty'] ?? ''),
                    'course' => (int) ($u['course'] ?? 0)];
            }
        }

        return $out;
    }

    /** Bandlik yozuvi (weeklyOccupancy / cycleOccupancy) guruhlari: haftalik — "fakultet|yo'nalish|kurs|guruh", sikl — birlik. */
    private function focusEntryGroups(array $e, array $units): array
    {
        if (($e['kind'] ?? '') === 'cycle') {
            return isset($units[$e['unit']]) ? $this->focusUnitGroups($units[$e['unit']]) : [];
        }
        $out = [];
        foreach ((array) ($e['groups'] ?? []) as $g) {
            $p = explode('|', (string) $g, 4);
            if (count($p) === 4) {
                foreach (preg_split('/\s*\+\s*/u', trim($p[3])) as $name) {
                    if ($name !== '') {
                        $out[] = ['group' => $name, 'faculty' => $p[0], 'specialty' => $p[1], 'course' => (int) $p[2]];
                    }
                }
            }
        }

        return $out;
    }

    /** Yagona ko'rinish: guruhlar va kartalar takrorlanmaydi, sanalar tartiblangan. */
    private function focusShow(array $x): array
    {
        $groups = [];
        foreach ((array) ($x['groups'] ?? []) as $g) {
            $k = mb_strtolower(trim($g['faculty'] . '|' . $g['specialty'] . '|' . $g['course'] . '|' . $g['group']));
            $groups[$k] ??= $g;
        }
        $dates = array_values(array_filter((array) ($x['dates'] ?? [])));
        sort($dates);

        return [
            'kind' => (string) ($x['kind'] ?? ''),
            'teacher_id' => !empty($x['teacher_id']) ? (int) $x['teacher_id'] : null,
            'teacher_name' => $x['teacher_name'] ?? null,
            'room_code' => !empty($x['room_code']) ? (string) $x['room_code'] : null,
            'groups' => array_values($groups),
            'subject' => $x['subject'] ?? null,
            'dates' => $dates ? [$dates[0], end($dates)] : [],
            'hour_from' => isset($x['hour_from']) ? (int) $x['hour_from'] : null,
            'hour_to' => isset($x['hour_to']) ? (int) $x['hour_to'] : null,
            'card_ids' => array_values(array_unique(array_map('intval', array_filter((array) ($x['card_ids'] ?? []))))),
            'view' => ($x['view'] ?? 'week') === 'cycle' ? 'cycle' : 'week',
            'week' => isset($x['week']) ? (int) $x['week'] : null,
        ];
    }

    /** Takliflar (weekClashes yozuvi): karta va to'sib turgan dars, o'sha hafta / kun / para. */
    private function focusFromClash(\App\Models\TimetableCard $c, array $x, \App\Models\TimetableBoard $board): array
    {
        $o = $x['card'];
        $errs = (array) ($x['errors'] ?? []);
        $first = (string) ($errs[0] ?? '');
        $kind = str_starts_with($first, "O'qituvchi band") ? 'teacher_busy' : (str_starts_with($first, 'Auditoriya band') ? 'room_busy' : 'group_busy');
        $date = $this->weekStartDate($board, $o, (int) $x['week'])->addDays(max(0, (int) $x['day'] - 1))->toDateString();

        return $this->focusShow(['kind' => $kind, 'teacher_id' => $kind === 'teacher_busy' ? $c->teacher_id : null,
            'teacher_name' => $kind === 'teacher_busy' ? $c->teacher_name : null, 'room_code' => $kind === 'room_busy' ? ($x['room'] ?? null) : null,
            'groups' => array_merge($this->focusCardGroups($c), $this->focusCardGroups($o)), 'subject' => $o->subject_name,
            'dates' => [$date], 'hour_from' => (int) $x['pair'], 'hour_to' => (int) $x['pair'] + $o->lenHalf(),
            'card_ids' => [$c->id, $o->id], 'view' => 'week', 'week' => (int) $x['week']]);
    }
}
