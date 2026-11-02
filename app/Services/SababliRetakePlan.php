<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Sababli ariza bo'yicha qayta topshirish jadvali.
 *
 * Talaba bir kunda faqat bitta retake topshiradi: qoldirilgan har bir dars kuni
 * (fan bo'yicha) uchun alohida kun ajratiladi. Fanning OSKI si shu fanning oxirgi
 * retake kuniga, yakuniy testi esa bir kun oraliq bilan undan keyin qo'yiladi.
 * Yakshanba ish kuni hisoblanmaydi.
 *
 * Talaba formasi (create.blade.php, JS) aynan shu tartibda oldindan ko'rsatadi,
 * saqlashda esa sanalar shu yerda qayta hisoblanadi.
 */
class SababliRetakePlan
{
    private const TYPE_ORDER = ['jn' => 0, 'mt' => 1, 'oski' => 2, 'test' => 3];

    /**
     * Sababli davrdagi baholanadigan darslar (ma'ruza va nazoratlardan tashqari):
     * har bir fan + dars kuni — bitta JN retake.
     */
    public static function lessonDays($groupId, Carbon $from, Carbon $to): Collection
    {
        return Schedule::where('group_id', $groupId)
            ->whereDate('lesson_date', '>=', $from)
            ->whereDate('lesson_date', '<=', $to)
            ->whereNotIn('training_type_code', config('app.training_type_code', [11, 99, 100, 101, 102, 103]))
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

    /**
     * Topshirilmagan har bir nazoratga qayta topshirish kunini (makeup_date, Y-m-d) belgilaydi.
     * Fanlar birinchi qoldirilgan sanasi bo'yicha ketma-ket joylashadi.
     * MT kun band qilmaydi — muddati fanning oxirgi retake kuni.
     */
    public static function assign(array $items, Carbon $start): array
    {
        $bySubject = [];
        foreach ($items as $key => $item) {
            $items[$key]['makeup_date'] = null;
            if (!self::isSubmitted($item)) {
                $bySubject[mb_strtolower(trim((string) ($item['subject_name'] ?? '')))][] = $key;
            }
        }

        $firstDates = array_map(
            fn ($keys) => min(array_map(fn ($k) => (string) $items[$k]['original_date'], $keys)),
            $bySubject
        );
        $subjects = array_keys($bySubject);
        usort($subjects, fn ($a, $b) => [$firstDates[$a], $a] <=> [$firstDates[$b], $b]);

        $day = self::firstDay($start->toDateString());
        foreach ($subjects as $subject) {
            $keys = $bySubject[$subject];
            usort($keys, fn ($a, $b) => [self::TYPE_ORDER[$items[$a]['assessment_type']] ?? 9, (string) $items[$a]['original_date']]
                <=> [self::TYPE_ORDER[$items[$b]['assessment_type']] ?? 9, (string) $items[$b]['original_date']]);

            $lastRetake = null;
            $last = null;
            $oskiShared = false;
            foreach ($keys as $k) {
                $type = $items[$k]['assessment_type'];
                if ($type === 'mt') {
                    continue;
                }
                if ($type === 'jn') {
                    $date = $day;
                    $lastRetake = $date;
                    $day = self::nextDay($date);
                } elseif ($type === 'oski' && $lastRetake && !$oskiShared) {
                    $date = $lastRetake;
                    $oskiShared = true;
                } elseif ($type === 'test' && $last) {
                    $date = self::nextDay(self::nextDay($last));
                    $day = self::nextDay($date);
                } else {
                    $date = $day;
                    $day = self::nextDay($date);
                }
                $last = $date;
                $items[$k]['makeup_date'] = $date->format('Y-m-d');
            }

            foreach ($keys as $k) {
                if ($items[$k]['assessment_type'] === 'mt') {
                    $items[$k]['makeup_date'] = ($lastRetake ?? $last ?? $day)->format('Y-m-d');
                }
            }
        }

        return $items;
    }

    private static function nextDay(Carbon $day): Carbon
    {
        $next = $day->copy()->addDay();

        return $next->isSunday() ? $next->addDay() : $next;
    }
}
