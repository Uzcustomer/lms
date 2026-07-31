<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableBoard;
use App\Models\TimetableCardOverride;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Haftalik zichlashni serverda (HTTP'siz) qayta hisoblaydi.
 *
 * Odatda buni avtomatik joylashdan keyin frontend o'zi chaqiradi — har hafta
 * uchun alohida so'rov bilan. Katta doskada (minglab kartochka) o'sha so'rovlar
 * uzilib qolishi mumkin va ba'zi haftalar eski hisobda qolib ketadi. Bu buyruq
 * o'sha ishni bir yo'la, vaqt chegarasisiz bajaradi.
 *
 * Misol:
 *   php artisan timetable:compact                 # barcha haftalar, oxirgi doska
 *   php artisan timetable:compact --week=1
 *   php artisan timetable:compact --board=2 --course=2
 */
class TimetableCompactWeeks extends Command
{
    protected $signature = 'timetable:compact
        {--board= : Doska id (sukut — eng oxirgisi)}
        {--week=* : Qaysi haftalar (sukut — dars o\'tilmaydigan darsi bor barcha haftalar)}
        {--faculty=* : Fakultet nomi bo\'yicha cheklash}
        {--specialty=* : Yo\'nalish nomi bo\'yicha cheklash}
        {--course=* : Kurs bo\'yicha cheklash}';

    protected $description = 'Haftalik zichlashni qayta hisoblaydi (darslarni fan bo\'yicha guruhlab kun boshiga tizadi)';

    public function handle(TimetableController $controller): int
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

        $weeks = array_map('intval', (array) $this->option('week'));
        if (!$weeks) {
            // Faqat o'tilmaydigan darsi bor haftalar qayta hisoblanadi —
            // qolganlari shablonday, ortiqcha istisno yozilmaydi.
            $weeks = TimetableCardOverride::query()
                ->where('cancelled', true)
                ->whereHas('card', fn($q) => $q->where('board_id', $board->id))
                ->distinct()->orderBy('week')->pluck('week')
                ->map(fn($w) => (int) $w)->all();
        }
        if (!$weeks) {
            $this->info('Zichlash kerak bo\'lgan hafta yo\'q.');
            return self::SUCCESS;
        }

        $scope = array_filter([
            'faculty_names' => (array) $this->option('faculty'),
            'specialty_names' => (array) $this->option('specialty'),
            'courses' => array_map('intval', (array) $this->option('course')),
        ]);

        $this->info($board->name);
        $this->line(count($weeks) . ' ta hafta zichlanadi: ' . implode(', ', $weeks));

        $bar = $this->output->createProgressBar(count($weeks));
        $bar->start();
        $moved = 0;
        foreach ($weeks as $week) {
            $response = $controller->compactWeek(
                Request::create('/', 'POST', array_merge($scope, ['week' => $week])),
                $board
            );
            $moved += (int) (json_decode($response->getContent(), true)['moved'] ?? 0);
            $bar->advance();
        }
        $bar->finish();
        $this->line('');
        $this->info('Tayyor — ' . $moved . ' ta ko\'chirish yozildi.');

        return self::SUCCESS;
    }
}
