<?php

namespace App\Console\Commands;

use App\Models\TimetableBoard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Doskani bitta ko'chma faylga chiqaradi (jadval tuzilishi bilan bog'liq
 * jadvallar). Fayl orqali xuddi shu doskani boshqa muhitda ochib, joylashtirish
 * va zichlashni aynan takrorlash mumkin — muammoni taxmin qilmasdan tekshirish
 * uchun shu kerak.
 *
 * Shaxsiy ma'lumot chiqmaydi: o'qituvchi ismlari sukut bo'yicha o'chiriladi
 * (id qoladi — joylashtirish mantiqi faqat id bilan ishlaydi).
 *
 * Misol:
 *   php artisan timetable:export
 *   php artisan timetable:export --board=2 --out=/tmp/doska.json.gz
 *   php artisan timetable:export --with-names     # ismlar ham qolsin
 */
class TimetableExport extends Command
{
    protected $signature = 'timetable:export
        {--board= : Doska id (sukut — eng oxirgisi)}
        {--out= : Fayl yo\'li (sukut — storage/app/timetable-export.json.gz)}
        {--with-names : O\'qituvchi ismlarini ham chiqarish}';

    protected $description = 'Doskani ko\'chma faylga chiqaradi (muammoni boshqa muhitda takrorlash uchun)';

    /** Jadval tuzilishiga aloqador jadvallar. */
    private const TABLES = [
        'timetable_grid_settings',
        'timetable_subject_settings',
        'timetable_rules',
        'timetable_cards',
        'timetable_card_overrides',
    ];

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

        $keepNames = (bool) $this->option('with-names');
        $out = $this->option('out') ?: storage_path('app/timetable-export.json.gz');

        $data = ['board' => (array) DB::table('timetable_boards')->find($board->id)];

        $cardIds = DB::table('timetable_cards')->where('board_id', $board->id)->pluck('id');
        foreach (self::TABLES as $table) {
            $query = DB::table($table);
            if ($table === 'timetable_card_overrides') {
                $query->whereIn('card_id', $cardIds);
            } else {
                $query->where('board_id', $board->id);
            }
            $rows = $query->get()->map(function ($row) use ($table, $keepNames) {
                $row = (array) $row;
                if (!$keepNames && $table === 'timetable_cards' && !empty($row['teacher_name'])) {
                    // Joylashtirish faqat teacher_id bilan ishlaydi — ism kerak emas.
                    $row['teacher_name'] = 'O\'qituvchi ' . $row['teacher_id'];
                }
                return $row;
            })->all();
            $data[$table] = $rows;
            $this->line(str_pad($table, 32) . count($rows) . ' qator');
        }

        $data['auditoriums'] = DB::table('auditoriums')->get()->map(fn($r) => (array) $r)->all();
        $this->line(str_pad('auditoriums', 32) . count($data['auditoriums']) . ' qator');

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->error('JSON yaratib bo\'lmadi: ' . json_last_error_msg());
            return self::FAILURE;
        }
        @mkdir(dirname($out), 0775, true);
        file_put_contents($out, gzencode($json, 6));

        $this->line('');
        $this->info('Tayyor: ' . $out . '  (' . $this->humanSize(filesize($out)) . ')');
        $this->line('Shu faylni yuboring — doskangiz aynan shu holatda qayta ochiladi.');

        return self::SUCCESS;
    }

    private function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1) . ' ' . $unit;
            }
            $bytes /= 1024;
        }
        return round($bytes, 1) . ' TB';
    }
}
