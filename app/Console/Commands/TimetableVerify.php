<?php

namespace App\Console\Commands;

use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Butun doskani qoidalarga muvofiqligi bo'yicha tekshiradi.
 *
 * Bitta fan/guruhni ko'rish (timetable:diagnose) "menimcha ishlayapti" dan
 * nariga o'tolmaydi — 7000 kartali doskada bitta misol hech narsani isbotlamaydi.
 * Bu buyruq HAR bir guruh × HAR bir hafta × HAR bir kun bo'yicha yuradi va
 * buzilishlarni sanaydi. "Kafolat" shu raqam nolga tushgani bilan o'lchanadi.
 *
 * Tekshiriladigan qoidalar:
 *   1. To'qnashuv bo'lmasin — bitta guruh / o'qituvchi / auditoriya bir vaqtda
 *      ikkita darsda turmasin. Bu eng og'ir buzilish: jadval yaroqsiz bo'ladi.
 *   2. Fan uzilmasin — guruh kunida bir fanning bo'laklari orasiga boshqa fan
 *      kirmasin (bo'laklar bitta uzluksiz blok bo'lsin).
 *   3. Oyna bo'lmasin — guruh kuni birinchi darsdan oxirgisigacha uzluksiz.
 *
 * Misol:
 *   php artisan timetable:verify
 *   php artisan timetable:verify --week=2 --limit=20
 */
class TimetableVerify extends Command
{
    protected $signature = 'timetable:verify
        {--board= : Doska id (sukut — eng oxirgisi)}
        {--week=* : Qaysi haftalar (sukut — barchasi)}
        {--limit=10 : Har turdagi nechta misol ko\'rsatilsin}';

    protected $description = 'Doskani tekshiradi: fan uzilgan yoki kunda oyna qolgan joylarni sanaydi';

    private const DAY_NAMES = [1 => 'Dush', 2 => 'Sesh', 3 => 'Chor', 4 => 'Pay', 5 => 'Juma', 6 => 'Shan', 7 => 'Yak'];

    public function handle(): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $board = $this->option('board')
            ? TimetableBoard::find((int) $this->option('board'))
            : TimetableBoard::latest('id')->first();
        if (!$board) {
            $this->error('Doska topilmadi.');
            return self::FAILURE;
        }

        $weeks = array_map('intval', (array) $this->option('week'))
            ?: range(1, max(1, (int) $board->weeks));
        $limit = max(1, (int) $this->option('limit'));

        $cards = TimetableCard::where('board_id', $board->id)
            ->whereNotNull('day')->whereNotNull('pair')->get();
        if ($cards->isEmpty()) {
            $this->warn('Doskada joylashgan karta yo\'q.');
            return self::SUCCESS;
        }

        $this->info($board->name);
        $this->line($cards->count() . ' ta joylashgan karta · ' . count($weeks) . ' ta hafta tekshiriladi');

        $overrides = DB::table('timetable_card_overrides')
            ->whereIn('card_id', $cards->pluck('id'))
            ->get(['card_id', 'week', 'day', 'pair', 'cancelled', 'auditorium_code'])
            ->groupBy('week');

        $splitSubjects = [];   // fan uzilgan holatlar
        $dayGaps = [];         // kunda oyna
        $clashes = [];         // guruh / o'qituvchi / auditoriya to'qnashuvi
        $checkedDays = 0;

        $bar = $this->output->createProgressBar(count($weeks));
        $bar->start();
        foreach ($weeks as $week) {
            $weekOverrides = ($overrides[$week] ?? collect())->keyBy('card_id');

            // "fakultet|yo'nalish|kurs|guruh|kun" => [yarim-slot => fan]
            $layout = [];
            // To'qnashuv uchun: band qiluvchi => [slot => [karta tavsifi, ...]]
            $groupSlots = [];
            $teacherSlots = [];
            $roomSlots = [];
            foreach ($cards as $card) {
                $override = $weekOverrides->get($card->id);
                if ($override && $override->cancelled) {
                    continue;
                }
                $day = ($override && $override->day) ? (int) $override->day : (int) $card->day;
                $pair = ($override && $override->day) ? (int) $override->pair : (int) $card->pair;
                if (!$day || !$pair) {
                    continue;
                }
                $room = ($override && $override->auditorium_code)
                    ? $override->auditorium_code
                    : $card->auditorium_code;
                // Guruh nomi fakultetlar bo'ylab takrorlanadi — kalitga fakultet,
                // yo'nalish va kurs ham kiradi, aks holda turli fakultetlarning
                // jadvali ustma-ust tushib, soxta "buzilish" chiqadi.
                $scope = ($card->faculty_name ?? '') . '|' . $card->specialty_name . '|' . (int) $card->course;
                $label = (string) $card->subject_name . ' #' . $card->id;
                for ($i = 0; $i < $card->lenHalf(); $i++) {
                    $slot = $day . ':' . ($pair + $i);
                    foreach ($card->occupiedGroups() as $group) {
                        $key = $scope . '|' . $group . '|' . $day;
                        $layout[$key][$pair + $i] = (string) $card->subject_name;
                        $groupSlots[$scope . '|' . $group][$slot][] = $label;
                    }
                    if ($card->teacher_id) {
                        $teacherSlots['T' . $card->teacher_id][$slot][] = $label;
                    }
                    if ($room) {
                        $roomSlots['R' . $room][$slot][] = $label;
                    }
                }
            }

            // ── To'qnashuv: bitta slotda ikkitadan ortiq dars ────────────────
            foreach ([['Guruh', $groupSlots], ['O\'qituvchi', $teacherSlots],
                      ['Auditoriya', $roomSlots]] as [$kind, $map]) {
                foreach ($map as $owner => $slots) {
                    foreach ($slots as $slot => $labels) {
                        // Bir karta bir necha guruhga tegishli bo'lsa (oqim
                        // ma'ruzasi) o'zi bilan o'zi to'qnashmasin.
                        $unique = array_values(array_unique($labels));
                        if (count($unique) > 1) {
                            $clashes[] = ['week' => $week, 'kind' => $kind,
                                'owner' => $owner, 'slot' => $slot, 'labels' => $unique];
                        }
                    }
                }
            }

            foreach ($layout as $key => $slots) {
                $checkedDays++;
                ksort($slots);
                $positions = array_keys($slots);

                // ── Oyna: birinchi darsdan oxirgisigacha uzluksizmi ──────────
                $span = max($positions) - min($positions) + 1;
                if ($span !== count($positions)) {
                    $dayGaps[] = ['week' => $week, 'key' => $key,
                        'gaps' => $span - count($positions), 'slots' => $positions];
                }

                // ── Fan uzilgan: bo'laklari orasiga boshqa fan kirganmi ──────
                $bySubject = [];
                foreach ($slots as $slot => $subject) {
                    $bySubject[$subject][] = $slot;
                }
                foreach ($bySubject as $subject => $own) {
                    $ownSpan = max($own) - min($own) + 1;
                    if ($ownSpan !== count($own)) {
                        $splitSubjects[] = ['week' => $week, 'key' => $key,
                            'subject' => $subject, 'slots' => $own];
                    }
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->line('');
        $this->line('');

        $this->line('Tekshirildi: ' . $checkedDays . ' ta (guruh × kun × hafta)');
        $this->report('To\'qnashuv (bir vaqtda ikki dars)', $clashes, $limit, function ($r) {
            [$day, $half] = explode(':', $r['slot']);
            $owner = explode('|', $r['owner']);
            return $r['week'] . '-hafta · ' . (self::DAY_NAMES[(int) $day] ?? $day)
                . ' · ' . $half . '-yarim-slot · ' . $r['kind'] . ': ' . end($owner)
                . ' — ' . implode(' + ', array_map(fn($l) => mb_substr($l, 0, 28), $r['labels']));
        });
        $this->report('Fan uzilgan (orasiga boshqa fan kirgan)', $splitSubjects, $limit,
            fn($r) => $this->describe($r) . ' · ' . mb_substr($r['subject'], 0, 32)
                . ' — yarim-slotlar: ' . implode(',', $r['slots']));
        $this->report('Kunda oyna (bo\'sh para)', $dayGaps, $limit,
            fn($r) => $this->describe($r) . ' · ' . $r['gaps'] . ' ta bo\'sh · band: '
                . implode(',', $r['slots']));

        $total = count($clashes) + count($splitSubjects) + count($dayGaps);
        $this->line('');
        if ($total === 0) {
            $this->info('Buzilish topilmadi.');
        } else {
            $this->warn('Jami buzilish: ' . $total);
        }

        return self::SUCCESS;
    }

    private function report(string $title, array $rows, int $limit, callable $line): void
    {
        $this->line('');
        if (!$rows) {
            $this->info('✓ ' . $title . ': 0');
            return;
        }
        $this->warn('✗ ' . $title . ': ' . count($rows));
        foreach (array_slice($rows, 0, $limit) as $row) {
            $this->line('    ' . $line($row));
        }
        if (count($rows) > $limit) {
            $this->line('    … yana ' . (count($rows) - $limit) . ' ta');
        }
    }

    /** "fakultet|yo'nalish|kurs|guruh|kun" kalitini o'qishga qulay ko'rinishga keltiradi. */
    private function describe(array $row): string
    {
        $parts = explode('|', $row['key']);
        $day = (int) array_pop($parts);
        $group = array_pop($parts);
        $course = array_pop($parts);
        $specialty = array_pop($parts);
        $faculty = array_pop($parts);

        // Guruh nomi fakultetlar bo'ylab takrorlanadi — fakultetsiz ikki xil
        // guruh bir xil ko'rinib, ro'yxat takrorlanayotgandek tuyulardi.
        return $row['week'] . '-hafta · ' . (self::DAY_NAMES[$day] ?? $day)
            . ' · ' . $group . ' (' . ($faculty ? $faculty . ' · ' : '')
            . $specialty . ' ' . $course . '-kurs)';
    }
}
