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
 * alohida kun ajratiladi (turli fanlarning retake lari bir kunga tushishi mumkin).
 * Fanning OSKI si shu fanning oxirgi retake kuniga, yakuniy testi esa bir kun
 * oraliq bilan undan keyin qo'yiladi. Yakshanba ish kuni hisoblanmaydi.
 *
 * Talaba formasi (create.blade.php, JS) aynan shu tartibda oldindan ko'rsatadi,
 * saqlashda esa sanalar shu yerda qayta hisoblanadi.
 */
class SababliRetakePlan
{
    private const TYPE_ORDER = ['jn' => 0, 'mt' => 1, 'oski' => 2, 'test' => 3];

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

    /**
     * Topshirilmagan har bir nazoratga qayta topshirish kunini (makeup_date, Y-m-d) belgilaydi.
     * Har bir fan boshlanish kunidan o'z kunlarini oladi — fanlar bir-biriga bog'liq emas,
     * bir kunda turli fanlarning retake lari bo'lishi mumkin.
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

        foreach ($bySubject as $keys) {
            $day = self::firstDay($start->toDateString());
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
