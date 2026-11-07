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
