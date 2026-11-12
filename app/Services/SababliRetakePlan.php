<?php

namespace App\Services;

use App\Models\AbsenceExcuse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sababli ariza bo'yicha qayta topshirish jadvali.
 *
 * Bir fandan bir kunda faqat bitta retake: fanning NB olgan har bir dars kuni uchun
 * alohida kun ajratiladi; bir kunda ko'pi bilan 3 ta fan (MAX_SUBJECTS_PER_DAY).
 * Fanning OSKI si shu fanning oxirgi retake kuniga, yakuniy testi esa bir kun
 * oraliq bilan undan keyin qo'yiladi. Yakshanba ish kuni hisoblanmaydi.
 *
 * Talaba formasi (create.blade.php, JS) aynan shu tartibda oldindan ko'rsatadi,
 * saqlashda esa sanalar shu yerda qayta hisoblanadi.
 */
class SababliRetakePlan
{
    /**
     * Sababli davrda talaba jurnalda NB olgan darslar (ma'ruza va nazoratlardan tashqari,
     * otrabotkasi hali qo'yilmagan): har bir fan + kun — bitta JN retake.
     * Darsda bo'lib baho olgan kunlarga retake ajratilmaydi.
     */
    public static function nbDays(string $studentHemisId, Carbon $from, Carbon $to): Collection
    {
        return DB::table('student_grades')
            ->where('student_hemis_id', $studentHemisId)
            ->whereDate('lesson_date', '>=', $from)
            ->whereDate('lesson_date', '<=', $to)
            ->where('reason', 'absent')
            ->whereNull('retake_grade')
            ->whereNotIn('training_type_code', config('app.training_type_code', [11, 99, 100, 101, 102, 103]))
            ->whereNull('deleted_at')
            ->get(['subject_id', 'subject_name', 'lesson_date'])
            ->map(fn ($s) => [
                'subject_name' => $s->subject_name,
                'subject_id' => $s->subject_id,
                'assessment_type' => 'jn',
                'assessment_type_code' => '100',
                'original_date' => Carbon::parse($s->lesson_date)->format('Y-m-d'),
            ])
            ->unique(fn ($i) => $i['subject_name'] . '|' . $i['original_date'])
            ->sortBy('original_date')
            ->values();
    }

    /**
     * Davrdagi JN nazoratlaridan talaba darsda bo'lib baho olganlarini chiqarib tashlaydi —
     * qayta topshirish faqat NB uchun.
     */
    public static function withoutAttended(Collection $items, string $studentHemisId, Carbon $from, Carbon $to): Collection
    {
        $graded = [];
        foreach (AbsenceExcuse::gradeConflicts($studentHemisId, $from, $to) as $g) {
            $day = Carbon::parse($g->lesson_date)->format('Y-m-d');
            if ((string) $g->subject_id !== '') {
                $graded['id:' . $g->subject_id . '|' . $day] = true;
            }
            $graded['name:' . mb_strtolower(trim((string) $g->subject_name)) . '|' . $day] = true;
        }
        if (!$graded) {
            return $items;
        }

        return $items->reject(function ($a) use ($graded) {
            if ($a['assessment_type'] !== 'jn') {
                return false;
            }
            $id = (string) ($a['subject_id'] ?? '');

            return ($id !== '' && isset($graded['id:' . $id . '|' . $a['original_date']]))
                || isset($graded['name:' . mb_strtolower(trim((string) $a['subject_name'])) . '|' . $a['original_date']]);
        })->values();
    }

    /** Retake boshlanadigan kun: berilgan sana yoki ertangi kun; yakshanba bo'lsa dushanba */
    public static function firstDay(?string $date = null): Carbon
    {
        $day = $date ? Carbon::parse($date)->startOfDay() : Carbon::tomorrow();

        return $day->isSunday() ? $day->addDay() : $day;
    }

    /** "Topshirilgan" / "O'z vaqtida" yoki MT fayli yuklangan — kun ajratilmaydi */
    public static function isSubmitted(array $item): bool
    {
        return filter_var($item['jn_submitted'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || filter_var($item['already_submitted'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /** Bir kunda qayta topshiriladigan fanlar soni chegarasi. */
    public const MAX_SUBJECTS_PER_DAY = 3;

    /**
     * Topshirilmagan har bir nazoratga qayta topshirish kunini (makeup_date, Y-m-d) belgilaydi:
     * bir fandan kuniga bitta retake, bir kunda ko'pi bilan MAX_SUBJECTS_PER_DAY ta fan.
     * Navbat dars sanasi bo'yicha (oldingi NB oldin joylanadi), kun to'lsa — keyingi ish kuni.
     * OSKI fanning oxirgi retake kunida (fan o'sha kuni allaqachon bor — joy olmaydi), test undan
     * bir kun oraliq bilan. MT kun band qilmaydi — muddati fanning oxirgi retake kuni.
     */
    public static function assign(array $items, Carbon $start): array
    {
        $first = self::firstDay($start->toDateString());
        $pending = ['jn' => [], 'oski' => [], 'test' => [], 'mt' => []];
        foreach ($items as $key => $item) {
            $items[$key]['makeup_date'] = null;
            if (!self::isSubmitted($item)) {
                $type = $item['assessment_type'] ?? 'jn';
                $pending[isset($pending[$type]) ? $type : 'jn'][] = $key;
            }
        }
        $subject = fn ($k) => mb_strtolower(trim((string) ($items[$k]['subject_name'] ?? '')));
        $byDate = fn (array $keys) => collect($keys)
            ->sortBy(fn ($k) => [(string) $items[$k]['original_date'], $subject($k)])->values()->all();

        $busy = [];        // Y-m-d => [fan => true]
        $next = [];        // fan => keyingi bo'sh kuni (fan bo'yicha)
        $lastRetake = [];  // fan => oxirgi JN retake kuni
        $last = [];        // fan => oxirgi band kuni
        $oskiShared = [];
        // $from dan boshlab shu fan yo'q va to'lmagan birinchi kunga joylaydi
        $place = function (string $s, Carbon $from) use (&$busy): Carbon {
            $day = $from->copy();
            while (!isset($busy[$day->toDateString()][$s])
                && count($busy[$day->toDateString()] ?? []) >= self::MAX_SUBJECTS_PER_DAY) {
                $day = self::nextDay($day);
            }
            $busy[$day->toDateString()][$s] = true;

            return $day;
        };

        foreach ($byDate($pending['jn']) as $k) {
            $s = $subject($k);
            $date = $place($s, $next[$s] ?? $first);
            $lastRetake[$s] = $last[$s] = $date;
            $next[$s] = self::nextDay($date);
            $items[$k]['makeup_date'] = $date->format('Y-m-d');
        }
        foreach ($byDate($pending['oski']) as $k) {
            $s = $subject($k);
            if (isset($lastRetake[$s]) && empty($oskiShared[$s])) {
                $date = $lastRetake[$s];
                $oskiShared[$s] = true;
            } else {
                $date = $place($s, $next[$s] ?? $first);
                $next[$s] = self::nextDay($date);
            }
            $last[$s] = $date;
            $items[$k]['makeup_date'] = $date->format('Y-m-d');
        }
        foreach ($byDate($pending['test']) as $k) {
            $s = $subject($k);
            $date = $place($s, isset($last[$s]) ? self::nextDay(self::nextDay($last[$s])) : ($next[$s] ?? $first));
            $last[$s] = $date;
            $next[$s] = self::nextDay($date);
            $items[$k]['makeup_date'] = $date->format('Y-m-d');
        }
        foreach ($pending['mt'] as $k) {
            $s = $subject($k);
            $items[$k]['makeup_date'] = ($lastRetake[$s] ?? $last[$s] ?? $next[$s] ?? $first)->format('Y-m-d');
        }

        return $items;
    }

    /**
     * Talaba formada ko'chirgan jadvalni assign() qoidalari bo'yicha tekshiradi: yakshanba emas,
     * $start dan oldin emas, bir kunda ko'pi bilan MAX_SUBJECTS_PER_DAY ta fan, bir fandan kuniga
     * bitta retake (OSKI shu fanning retake kuni bilan bir kunda bo'lishi mumkin), OSKI fanning
     * oxirgi retake kunidan oldin emas, test esa ulardan bir kun oraliq bilan.
     * create.blade.php dagi planError() bilan bir xil. Xato matni yoki null.
     */
    public static function validate(array $items, Carbon $start): ?string
    {
        $first = self::firstDay($start->toDateString())->toDateString();
        $fmt = fn (string $d) => Carbon::parse($d)->format('d.m.Y');
        $days = [];      // Y-m-d => [fan => [tur => soni]]
        $subjects = [];  // fan => ['name' => ..., 'jn' => [...], 'oski' => [...], 'test' => [...]]
        foreach ($items as $item) {
            $type = $item['assessment_type'] ?? 'jn';
            if (self::isSubmitted($item) || $type === 'mt') {
                continue;
            }
            $type = in_array($type, ['oski', 'test'], true) ? $type : 'jn';
            $name = trim((string) ($item['subject_name'] ?? ''));
            $s = mb_strtolower($name);
            $date = (string) ($item['makeup_date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return "{$name}: qayta topshirish kuni tanlanmagan.";
            }
            if (Carbon::parse($date)->isSunday()) {
                return "{$name}: {$fmt($date)} — yakshanba.";
            }
            if ($date < $first) {
                return "{$name}: {$fmt($date)} — qayta topshirish {$fmt($first)} dan boshlanadi.";
            }
            $days[$date][$s][$type] = ($days[$date][$s][$type] ?? 0) + 1;
            $subjects[$s]['name'] = $name;
            $subjects[$s][$type][] = $date;
        }

        foreach ($days as $date => $bySubject) {
            if (count($bySubject) > self::MAX_SUBJECTS_PER_DAY) {
                return "{$fmt($date)} kuni " . count($bySubject) . ' ta fan — bir kunda ko\'pi bilan ' . self::MAX_SUBJECTS_PER_DAY . ' ta fan.';
            }
            foreach ($bySubject as $s => $c) {
                if (($c['jn'] ?? 0) + ($c['test'] ?? 0) > 1 || ($c['oski'] ?? 0) > 1) {
                    return "{$subjects[$s]['name']}: {$fmt($date)} kuni bir fandan bittadan ortiq retake.";
                }
            }
        }

        foreach ($subjects as $sub) {
            $lastJn = ($sub['jn'] ?? []) ? max($sub['jn']) : null;
            foreach ($sub['oski'] ?? [] as $d) {
                if ($lastJn && $d < $lastJn) {
                    return "{$sub['name']}: OSKI ({$fmt($d)}) oxirgi retake kunidan ({$fmt($lastJn)}) oldin bo'lmasligi kerak.";
                }
            }
            $before = array_merge($sub['jn'] ?? [], $sub['oski'] ?? []);
            if ($before) {
                $min = self::nextDay(self::nextDay(Carbon::parse(max($before))))->toDateString();
                foreach ($sub['test'] ?? [] as $d) {
                    if ($d < $min) {
                        return "{$sub['name']}: test ({$fmt($d)}) {$fmt($min)} dan oldin bo'lmasligi kerak — retake/OSKI dan keyin bir kun oraliq.";
                    }
                }
            }
        }

        return null;
    }

    /**
     * Talaba ko'chirgan (validate() dan o'tgan) jadval: topshirilganlarga kun yo'q, MT muddati —
     * fanning oxirgi retake kuni (assign() dagi kabi).
     */
    public static function manual(array $items, Carbon $start): array
    {
        $first = self::firstDay($start->toDateString())->toDateString();
        $subject = fn (array $i) => mb_strtolower(trim((string) ($i['subject_name'] ?? '')));
        $lastJn = $lastAny = [];
        foreach ($items as $key => $item) {
            $type = $item['assessment_type'] ?? 'jn';
            if (self::isSubmitted($item) || $type === 'mt') {
                $items[$key]['makeup_date'] = null;
                continue;
            }
            $s = $subject($item);
            $d = (string) $item['makeup_date'];
            $lastAny[$s] = max($lastAny[$s] ?? $d, $d);
            if (!in_array($type, ['oski', 'test'], true)) {
                $lastJn[$s] = max($lastJn[$s] ?? $d, $d);
            }
        }
        foreach ($items as $key => $item) {
            if (($item['assessment_type'] ?? '') === 'mt' && !self::isSubmitted($item)) {
                $s = $subject($item);
                $items[$key]['makeup_date'] = $lastJn[$s] ?? $lastAny[$s] ?? $first;
            }
        }

        return $items;
    }

    /**
     * Ariza kechikib tasdiqlansa rejadagi retake kunlari o'tib ketgan bo'ladi (talaba
     * Moodle'da bron qila olmaydi): bajarilmagan retake'lar shu qoida bo'yicha $start
     * dan qayta taqsimlanadi. Birinchi retake kuni hali kelmagan bo'lsa — o'zgarmaydi.
     */
    public static function rescheduleIfLate(AbsenceExcuse $excuse, Carbon $start): bool
    {
        $makeups = $excuse->makeups()->get();
        $pending = $makeups->filter(fn ($m) => $m->status === 'scheduled' && !$m->jn_submitted && $m->makeup_date);
        if ($pending->isEmpty() || $pending->min('makeup_date')->gte($start)) {
            return false;
        }

        $pendingIds = $pending->pluck('id')->all();
        $items = $makeups->mapWithKeys(fn ($m) => [$m->id => [
            'subject_name' => $m->subject_name,
            'assessment_type' => $m->assessment_type,
            'original_date' => $m->original_date?->toDateString(),
            'jn_submitted' => !in_array($m->id, $pendingIds, true),
        ]])->all();

        $planned = self::assign($items, $start);
        foreach ($pending as $makeup) {
            $date = $planned[$makeup->id]['makeup_date'];
            $makeup->update([
                'makeup_date' => $date,
                'makeup_end_date' => $makeup->assessment_type === 'jn' ? $date : null,
            ]);
        }

        return true;
    }

    private static function nextDay(Carbon $day): Carbon
    {
        $next = $day->copy()->addDay();

        return $next->isSunday() ? $next->addDay() : $next;
    }
}
