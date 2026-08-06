<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * timetable:export chiqargan faylni joriy bazaga yuklaydi. Doska aynan o'sha
 * holatda ochiladi va joylashtirish/zichlashni takrorlash mumkin bo'ladi.
 *
 * DIQQAT: yuklashda shu doskaning mavjud kartalari o'chiriladi. Ishlab turgan
 * bazada ishlatmang — bu ishlab chiqish/tekshirish uchun.
 *
 * Misol:
 *   php artisan timetable:import /tmp/doska.json.gz
 */
class TimetableImport extends Command
{
    protected $signature = 'timetable:import
        {file : timetable:export chiqargan .json.gz fayl}
        {--force : Tasdiqlashsiz yuklash}';

    protected $description = 'timetable:export faylini joriy bazaga yuklaydi (tekshirish uchun)';

    public function handle(): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $file = (string) $this->argument('file');
        if (!is_file($file)) {
            $this->error('Fayl topilmadi: ' . $file);
            return self::FAILURE;
        }

        $raw = file_get_contents($file);
        $json = str_starts_with($raw, "\x1f\x8b") ? gzdecode($raw) : $raw;
        $data = json_decode((string) $json, true);
        if (!is_array($data) || empty($data['board'])) {
            $this->error('Fayl formati noto\'g\'ri.');
            return self::FAILURE;
        }

        $boardId = (int) $data['board']['id'];
        if (!$this->option('force')
            && !$this->confirm("«{$data['board']['name']}» (id {$boardId}) yuklansinmi? Shu doskaning mavjud kartalari o'chiriladi.")) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($data, $boardId) {
            $cardIds = DB::table('timetable_cards')->where('board_id', $boardId)->pluck('id');
            DB::table('timetable_card_overrides')->whereIn('card_id', $cardIds)->delete();
            foreach (['timetable_cards', 'timetable_rules', 'timetable_subject_settings', 'timetable_grid_settings'] as $table) {
                DB::table($table)->where('board_id', $boardId)->delete();
            }
            DB::table('timetable_boards')->where('id', $boardId)->delete();

            DB::table('timetable_boards')->insert($data['board']);
            foreach (['timetable_grid_settings', 'timetable_subject_settings', 'timetable_rules',
                      'timetable_cards', 'timetable_card_overrides', 'auditoriums'] as $table) {
                $rows = $data[$table] ?? [];
                if (!$rows) {
                    continue;
                }
                if ($table === 'auditoriums') {
                    DB::table('auditoriums')->delete();
                }
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
                $this->line(str_pad($table, 32) . count($rows) . ' qator');
            }
        });

        $this->info('Yuklandi. Doska id: ' . $boardId);

        return self::SUCCESS;
    }
}
