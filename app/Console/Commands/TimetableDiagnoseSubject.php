<?php

namespace App\Console\Commands;

use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use Illuminate\Console\Command;

/**
 * Bitta fanning kartalari qayerga joylashganini ko'rsatadi va ma'ruzani
 * almashtiruvchi amaliy ma'ruza slotida turibdimi — shuni tekshiradi.
 *
 * Misol:
 *   php artisan timetable:diagnose --subject=Mikrobiolog --group="d1/d25-01a" --course=2
 */
class TimetableDiagnoseSubject extends Command
{
    protected $signature = 'timetable:diagnose
        {--subject= : Fan nomining boshi (majburiy)}
        {--group= : Guruh nomi (majburiy)}
        {--course= : Kurs}
        {--board= : Doska id (sukut — eng oxirgisi)}
        {--week=1 : Qaysi hafta bo\'yicha holat ko\'rsatilsin}';

    protected $description = 'Dars jadvali tashxisi: bir fan/guruhning kartalari qaysi kun va parada turibdi';

    private const DAY_NAMES = [1 => 'Dush', 2 => 'Sesh', 3 => 'Chor', 4 => 'Pay', 5 => 'Juma', 6 => 'Shanba', 7 => 'Yakshanba'];

    public function handle(): int
    {
        $subject = (string) $this->option('subject');
        $group = (string) $this->option('group');
        if ($subject === '' || $group === '') {
            $this->error('--subject va --group majburiy. Masalan: --subject=Mikrobiolog --group="d1/d25-01a"');
            return self::FAILURE;
        }

        $board = $this->option('board')
            ? TimetableBoard::find((int) $this->option('board'))
            : TimetableBoard::latest('id')->first();
        if (!$board) {
            $this->error('Doska topilmadi.');
            return self::FAILURE;
        }

        $query = TimetableCard::where('board_id', $board->id)
            ->where('subject_name', 'like', $subject . '%');
        if ($this->option('course')) {
            $query->where('course', (int) $this->option('course'));
        }
        // Ma'ruza guruhlari group_names (JSON) da, amaliyniki group_name da —
        // shuning uchun filtrlash PHP tomonida, occupiedGroups() orqali.
        // Moslik QISMIY: bazadagi nom "d1/d25-01a (o'z)" ko'rinishida — til
        // qo'shimchasi bilan, foydalanuvchi esa odatda "d1/d25-01a" deb yozadi.
        $subjectCards = $query->get();
        $cards = $subjectCards
            ->filter(fn(TimetableCard $c) => $this->matchesGroup($c, $group))
            ->sortBy(fn(TimetableCard $c) => [$c->training_type === 'lecture' ? 0 : 1, -(int) $c->weeks])
            ->values();

        $this->line('');
        $this->info($board->name . '  |  ' . $subject . ' · ' . $group);

        if ($cards->isEmpty()) {
            $this->warn('Bu qamrovda karta topilmadi.');
            $this->showAvailable($subjectCards, $subject);
            return self::SUCCESS;
        }

        $this->table(
            ['tur', 'hafta', 'kun', 'para', 'uzunlik', "o'qituvchi", 'xona'],
            $cards->map(fn(TimetableCard $c) => [
                $c->training_type,
                $c->weeks,
                $this->dayName($c->day),
                $c->pair ?? '—',
                $c->len_half . ' yarim-slot',
                $c->teacher_id ?? '—',
                $c->auditorium_code ?? '—',
            ])->all()
        );

        $unplaced = $cards->filter(fn(TimetableCard $c) => !$c->day || !$c->pair);
        if ($unplaced->isNotEmpty()) {
            $this->warn($unplaced->count() . ' ta karta JOYLASHMAGAN (kun/para bo\'sh).');
        }

        $this->checkAlternating($board, $cards);
        $this->showWeek($cards, max(1, (int) $this->option('week')));
        $this->showBoardSummary($board);

        return self::SUCCESS;
    }

    /** Butun doska bo'yicha qisqa hisob — karta joylashmagan bo'lsa sabab shu yerda ko'rinadi. */
    private function showBoardSummary(TimetableBoard $board): void
    {
        $total = TimetableCard::where('board_id', $board->id)->count();
        $placed = TimetableCard::where('board_id', $board->id)
            ->whereNotNull('day')->whereNotNull('pair')->count();

        $this->line(sprintf(
            'Doska bo\'yicha: %d karta · %d joylashgan · %d joylashmagan',
            $total, $placed, $total - $placed
        ));
        if ($placed === 0 && $total > 0) {
            $this->warn('Doskada birorta karta joylashmagan — "Avtomatik joylash" ishga tushirilmagan bo\'lishi mumkin.');
        }
        $this->line('');
    }

    /** Karta shu guruhga tegishlimi (til qo'shimchasiz yozilgan nom ham mos keladi). */
    private function matchesGroup(TimetableCard $card, string $group): bool
    {
        foreach ($card->occupiedGroups() as $name) {
            if (mb_stripos((string) $name, $group) !== false) {
                return true;
            }
        }
        return false;
    }

    /** Hech narsa topilmasa — qaysi fan/guruh nomlari borligini ko'rsatamiz. */
    private function showAvailable($subjectCards, string $subject): void
    {
        if ($subjectCards->isEmpty()) {
            $this->line('"' . $subject . '" bilan boshlanadigan fan umuman yo\'q. --subject ni tekshiring.');
            return;
        }

        $subjects = $subjectCards->pluck('subject_name')->unique()->sort()->values();
        $this->line('');
        $this->line('Topilgan fanlar:');
        foreach ($subjects as $name) {
            $this->line('  · ' . $name);
        }

        $groups = $subjectCards->flatMap(fn(TimetableCard $c) => $c->occupiedGroups())
            ->unique()->sort()->values();
        $this->line('');
        $this->line('Shu fandagi guruh nomlari (--group uchun):');
        foreach ($groups->take(40) as $name) {
            $this->line('  · ' . $name);
        }
        if ($groups->count() > 40) {
            $this->line('  … jami ' . $groups->count() . ' ta');
        }
        $this->line('');
    }

    /** Ma'ruzani almashtiruvchi amaliy ma'ruza slotida turibdimi? */
    private function checkAlternating(TimetableBoard $board, $cards): void
    {
        $lecture = $cards->firstWhere('training_type', 'lecture');
        if (!$lecture) {
            $this->line("Ma'ruza kartasi yo'q — almashtiruvchi amaliy ham kutilmaydi.");
            return;
        }

        $totalWeeks = max(1, (int) $board->weeks);
        $expect = $totalWeeks - (int) $lecture->weeks;
        $this->line(sprintf(
            "Ma'ruza: %s / %s   (%d hafta; almashtiruvchi amaliy %d haftalik bo'lishi kerak)",
            $this->dayName($lecture->day), $lecture->pair ?? '—', (int) $lecture->weeks, $expect
        ));

        $alt = $cards->first(fn(TimetableCard $c) => $c->training_type === 'practice' && (int) $c->weeks === $expect);
        if (!$lecture->day || !$lecture->pair) {
            // Joylashmagan kartalarni solishtirib bo'lmaydi — ikkalasi ham bo'sh
            // bo'lsa "bir xil" chiqadi va soxta OK berardi.
            $this->warn("Ma'ruza joylashmagan — slot solishtirib bo'lmaydi.");
        } elseif (!$alt) {
            $this->line("Almashtiruvchi amaliy ({$expect} hafta) yo'q — ma'ruza o'rnini bosuvchi amaliy yaratilmagan.");
        } elseif (!$alt->day || !$alt->pair) {
            $this->warn("Almashtiruvchi amaliy ({$expect} hafta) joylashmagan.");
        } elseif ($alt->day === $lecture->day && $alt->pair === $lecture->pair) {
            $this->info("OK: almashtiruvchi amaliy ma'ruza slotida turibdi.");
        } else {
            // Ma'ruza sloti boshqa fanning ma'ruzasi bilan bo'lishilgan bo'lishi
            // mumkin (biri toq, ikkinchisi juft haftalarda) — u holda o'rin
            // bosuvchi amaliy u yerga tusha olmaydi va bu kutilgan holat.
            $sharedWith = $this->slotSharedWith($lecture);
            if ($sharedWith !== null) {
                $this->line(sprintf(
                    "Almashtiruvchi amaliy %s / %s da — ma'ruza sloti «%s» bilan bo'lishilgan, "
                    . "shuning uchun u yerga tusha olmaydi. Hafta ichidagi tartib zichlash bilan to'g'rilanadi.",
                    $this->dayName($alt->day), $alt->pair ?? '—', $sharedWith
                ));
            } else {
                $this->warn(sprintf(
                    "MUAMMO: almashtiruvchi amaliy %s / %s da — ma'ruza sloti emas. "
                    . "'Qaytadan joylash' bilan avtomatik joylashni qayta ishga tushiring.",
                    $this->dayName($alt->day), $alt->pair ?? '—'
                ));
            }
        }
    }

    /** Ma'ruza slotini boshqa qaysi fan bilan bo'lishgan (bo'lishmagan bo'lsa null). */
    private function slotSharedWith(TimetableCard $lecture): ?string
    {
        $groups = $lecture->occupiedGroups();
        $end = (int) $lecture->pair + $lecture->lenHalf() - 1;

        $others = TimetableCard::where('board_id', $lecture->board_id)
            ->where('id', '!=', $lecture->id)
            ->where('day', $lecture->day)
            ->where('subject_name', '!=', $lecture->subject_name)
            ->whereNotNull('pair')
            ->get();

        foreach ($others as $other) {
            $otherEnd = (int) $other->pair + $other->lenHalf() - 1;
            if ((int) $other->pair > $end || $otherEnd < (int) $lecture->pair) {
                continue;   // vaqt bo'yicha kesishmaydi
            }
            if (array_intersect($groups, $other->occupiedGroups())) {
                return $other->subject_name;
            }
        }

        return null;
    }

    /** Tanlangan haftadagi haqiqiy holat (hafta istisnolari bilan). */
    private function showWeek($cards, int $week): void
    {
        $overrides = TimetableCardOverride::whereIn('card_id', $cards->pluck('id'))
            ->where('week', $week)->get()->keyBy('card_id');

        $this->line('');
        $this->line($week . '-haftada:');
        foreach ($cards as $c) {
            $ov = $overrides->get($c->id);
            if ($ov && $ov->cancelled) {
                $state = "o'tilmaydi";
            } elseif ($ov && $ov->day) {
                $state = $this->dayName($ov->day) . ' / ' . $ov->pair . '  (individual)';
            } else {
                $state = $this->dayName($c->day) . ' / ' . ($c->pair ?? '—');
            }
            $this->line('  ' . str_pad($c->training_type . ' · ' . $c->weeks . ' hafta', 26) . $state);
        }
        $this->line('');
    }

    private function dayName($day): string
    {
        return $day ? (self::DAY_NAMES[(int) $day] ?? (string) $day) : '—';
    }
}
