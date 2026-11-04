<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Auditorium;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bo'lak qismlari — bitta mashg'ulot. 3 guruhchali guruhda nazariy fan (a)/(b) bo'laklarga bo'linadi: (a) = 01a hammasi + 01c
 * yarmi, (b) = 01b hammasi + 01c qolgani. Bitta bo'lakning guruhchalardagi qismlari har doim bir vaqtda, bitta xonada, bitta
 * o'qituvchi bilan — bitta birlashgan karta (group_names = guruhchalar, group_name = "01a + 01c", students = yig'indi, fan nomi
 * o'zgarmaydi: "Farmakologiya (a)").
 *
 * Qism kaliti: bo'lakli fan nomi (asosiy nom + harf) · mashg'ulot turi · qamrov (fakultet, yo'nalish, kurs) · oqim · til · guruh
 * (guruhcha nomidan oxirgi harf olib tashlangani — baseGroupKey). Ma'ruza, sikl fanlari va bir guruhchali bo'lak — kirmaydi.
 * Kartalar yaratilganda (practiceBundles) qismlar boshidanoq birlashgan holda yaratiladi; mavjud kartalar — merge-bolak-parts.
 */
trait TimetableBolakParts
{
    /** POST boards/{board}/merge-bolak-parts {faculty_names?, specialty_names?, courses?, dry_run?}. */
    public function mergeBolakParts(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'dry_run'           => 'nullable|boolean',
        ]);
        if (!in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true)) {
            return response()->json(['error' => 'Bu amal faqat admin va registrator ofisi uchun.'], 403);
        }
        $dry = $request->boolean('dry_run');
        if (!$dry) {
            $this->snapshotBefore($request, $board, 'before_merge_bolak_parts');
        }
        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $q = TimetableCard::where('board_id', $board->id)->where('training_type', '!=', 'lecture');
        $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
        $cycleKeys = $this->cycleSubjectKeys($board);
        $lc = fn ($v) => mb_strtolower(trim((string) $v));
        // To'plamlar: kalit => [guruhcha to'plami ("02a" yoki "02a+02c") => [karta, ...]].
        $sets = [];
        foreach ($q->orderBy('id')->get() as $c) {
            $subject = (string) $c->subject_name;
            if ($this->cycleSubjectVariantBase($subject) === null
                || isset($cycleKeys[$this->specKey((string) $c->specialty_name) . '|' . (int) $c->course . '|' . $this->normSubject($subject)])) {
                continue;
            }
            $groups = array_values($c->occupiedGroups());
            $bases = array_unique(array_map(fn ($g) => $this->baseGroupKey((string) $g), $groups));
            if (!$groups || count($bases) !== 1) {
                continue;   // turli guruhlarni qamragan aralash karta — bu amalga kirmaydi
            }
            natcasesort($groups);
            $key = implode('|', [$lc($c->faculty_name), $this->specKey((string) $c->specialty_name), (int) $c->course, $lc($c->oqim_label), $lc($c->lang),
                (string) $c->training_type, $this->normSubject($subject), $bases[0]]);
            $sets[$key][implode('+', $groups)][] = $c;
        }
        $hasOv = Schema::hasTable('timetable_card_overrides');
        $rooms = Auditorium::pluck('volume', 'code')->all();
        $list = [];
        $merged = 0;
        $removed = 0;
        DB::beginTransaction();
        try {
            foreach ($sets as $parts) {
                if (count($parts) < 2) {
                    continue;   // bir guruhchali bo'lak (qismi yo'q) yoki allaqachon birlashgan
                }
                uksort($parts, 'strnatcasecmp');
                $groups = [];
                foreach (array_keys($parts) as $gk) {
                    array_push($groups, ...explode('+', $gk));
                }
                $groups = array_values(array_unique($groups));
                natcasesort($groups);
                $groups = array_values($groups);
                $sample = reset($parts)[0];
                $subject = (string) $sample->subject_name;
                $letter = (string) ($this->variantLetter($subject) ?? '');
                $label = $subject . ' · ' . self::jointShortLabel($groups);
                $row = ['subject' => $subject, 'letter' => $letter, 'groups' => self::jointShortLabel($groups), 'cards_from' => [], 'card_to' => [],
                    'placed_at' => [], 'warning' => null];
                // Haftalik bo'laklar (len_half, weeks) har qismda bir xil bo'lishi kerak: 1-karta 1-karta bilan, 2-karta 2-karta bilan.
                $sig = [];
                foreach ($parts as $gk => $cs) {
                    usort($cs, fn ($a, $b) => [(int) $a->len_half, (int) $a->weeks, $a->id] <=> [(int) $b->len_half, (int) $b->weeks, $b->id]);
                    $parts[$gk] = $cs;
                    $sig[$gk] = implode('+', array_map(fn ($x) => $x->lenHalf() . ($x->weeks ? '×' . (int) $x->weeks : ''), $cs));
                    array_push($row['cards_from'], ...array_map(fn ($x) => $x->id, $cs));
                }
                if (count(array_unique($sig)) > 1) {
                    $row['warning'] = $label . ': kartalar soni/soati mos emas ('
                        . implode(', ', array_map(fn ($gk) => self::jointShortLabel(explode('+', $gk)) . ': ' . $sig[$gk], array_keys($sig))) . ') — birlashtirilmadi';
                    $list[] = $row;
                    continue;
                }
                // O'qituvchi: biriktirilganlari bir xil bo'lishi kerak (biriktirilmagan qism — to'sqinlik qilmaydi).
                $teachers = [];
                foreach ($parts as $cs) {
                    foreach ($cs as $x) {
                        if ($x->teacher_id) {
                            $teachers[(int) $x->teacher_id] = (string) $x->teacher_name;
                        }
                    }
                }
                if (count($teachers) > 1) {
                    $row['warning'] = $label . ': o\'qituvchilar har xil (' . implode(', ', array_unique($teachers)) . ') — birlashtirilmadi';
                    $list[] = $row;
                    continue;
                }
                $notes = [];
                $n = count(reset($parts));
                for ($i = 0; $i < $n; $i++) {
                    // Qoldiriladigan karta: birinchi qism (guruhcha nomi bo'yicha alifbo) — joylanmagan bo'lsa, keyingi joylangan qism.
                    $piece = array_map(fn ($cs) => $cs[$i], $parts);
                    $keep = collect($piece)->first(fn ($x) => $x->day && $x->pair) ?? reset($piece);
                    $students = array_sum(array_map(fn ($x) => (int) $x->students, $piece));
                    $withTeacher = collect($piece)->first(fn ($x) => $x->teacher_id);
                    $withRoom = $keep->auditorium_code ? $keep : collect($piece)->first(fn ($x) => $x->auditorium_code);
                    $keep->update(['group_name' => implode(' + ', $groups), 'group_names' => $groups, 'students' => max(1, $students)]
                        + ($withTeacher && !$keep->teacher_id ? ['teacher_id' => $withTeacher->teacher_id, 'teacher_name' => $withTeacher->teacher_name] : [])
                        + ($withRoom && !$keep->auditorium_code ? ['auditorium_code' => $withRoom->auditorium_code, 'auditorium_name' => $withRoom->auditorium_name] : []));
                    foreach ($piece as $x) {
                        if ($x->id === $keep->id) {
                            continue;
                        }
                        if ($hasOv) {
                            TimetableCardOverride::where('card_id', $x->id)->delete();
                        }
                        $x->delete();
                        $removed++;
                    }
                    $row['card_to'][] = $keep->id;
                    $keep = $keep->fresh();
                    // Xona sig'imi: talabalar yig'indisi — ogohlantirish (birlashtiriladi).
                    $cap = (int) ($rooms[$keep->auditorium_code] ?? 0);
                    if ($keep->auditorium_code && $cap > 0 && $students > $cap) {
                        $notes[] = $label . ': xona ' . ($keep->auditorium_name ?: $keep->auditorium_code) . " sig'maydi — {$cap} o'rin, {$students} talaba";
                    }
                    // Joy (shablon va hafta bo'yicha ko'chirilganlari): to'qnashuv bo'lsa — panelga qaytadi.
                    $why = $keep->day && $keep->pair ? $this->findConflicts($keep, (int) $keep->day, (int) $keep->pair, (int) $keep->start_half) : [];
                    $moved = $hasOv ? TimetableCardOverride::where('card_id', $keep->id)->where('cancelled', false)->whereNotNull('day')->whereNotNull('pair')->get() : collect();
                    foreach ($moved as $ov) {
                        foreach ($this->findWeekConflicts($keep, (int) $ov->week, (int) $ov->day, (int) $ov->pair, (int) $ov->start_half) as $e) {
                            $why[] = $ov->week . '-hafta: ' . $e;
                        }
                    }
                    if ($why) {
                        $keep->update(['day' => null, 'pair' => null, 'start_half' => 0]);
                        foreach ($moved as $ov) {
                            $ov->delete();
                        }
                        $notes[] = $label . ': joyida to\'qnashuv: ' . implode(' · ', array_slice(array_values(array_unique($why)), 0, 3)) . ' — panelga qaytarildi';
                        $row['placed_at'][] = null;
                    } else {
                        $row['placed_at'][] = $keep->day && $keep->pair ? ['day' => (int) $keep->day, 'pair' => (int) $keep->pair] : null;
                    }
                }
                $merged++;
                $row['warning'] = $notes ? implode('; ', array_values(array_unique($notes))) : null;
                $list[] = $row;
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        // Sinov (dry_run) — amal tranzaksiyada bajarilib qaytariladi (to'qnashuv va sig'im tekshiruvi ham haqiqiy).
        $dry ? DB::rollBack() : DB::commit();

        return response()->json(['ok' => true, 'dry_run' => $dry, 'merged' => $merged, 'removed' => $dry ? 0 : $removed, 'list' => array_slice($list, 0, 500)]);
    }
}
