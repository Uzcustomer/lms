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
        $cards = $query->get()
            ->filter(fn(TimetableCard $c) => in_array($group, $c->occupiedGroups(), true))
            ->sortBy(fn(TimetableCard $c) => [$c->training_type === 'lecture' ? 0 : 1, -(int) $c->weeks])
            ->values();

        $this->line('');
        $this->info($board->name . '  |  ' . $subject . ' · ' . $group);

        if ($cards->isEmpty()) {
            $this->warn('Bu qamrovda karta topilmadi. --subject / --group / --course ni tekshiring.');
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

        $this->checkAlternating($board, $cards);
        $this->showWeek($cards, max(1, (int) $this->option('week')));

        return self::SUCCESS;
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
        if (!$alt) {
            $this->line("Almashtiruvchi amaliy ({$expect} hafta) yo'q — ma'ruza o'rnini bosuvchi amaliy yaratilmagan.");
        } elseif ($alt->day === $lecture->day && $alt->pair === $lecture->pair) {
            $this->info("OK: almashtiruvchi amaliy ma'ruza slotida turibdi.");
        } else {
            $this->warn(sprintf(
                "MUAMMO: almashtiruvchi amaliy %s / %s da — ma'ruza sloti emas. "
                . "'Qaytadan joylash' bilan avtomatik joylashni qayta ishga tushiring.",
                $this->dayName($alt->day), $alt->pair ?? '—'
            ));
        }
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
