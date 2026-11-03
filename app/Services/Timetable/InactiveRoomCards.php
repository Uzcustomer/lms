<?php

namespace App\Services\Timetable;

use App\Models\TimetableBoard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dars jadvali kartalari nofaol xonada (auditoriums.active = false): kartaning o'z xonasi yoki haftalik yozuvdagi (shu hafta) xona.
 * Auditoriya sinxronidan keyin hisobot ("N ta karta nofaol xonada") va Excel ro'yxati uchun.
 */
class InactiveRoomCards
{
    /** Doskadagi qatorlar: [card_id, faculty, specialty, course, group, subject, type, teacher, room_code, room_name, where]. */
    public static function rows(TimetableBoard $board): array
    {
        if (!Schema::hasColumn('auditoriums', 'active')) {
            return [];
        }
        $off = DB::table('auditoriums')->where('active', false)->pluck('name', 'code')->all();
        if (!$off) {
            return [];
        }
        $out = [];
        $cardCols = ['id', 'faculty_name', 'specialty_name', 'course', 'group_name', 'group_names', 'oqim_label', 'subject_name', 'training_type',
            'teacher_name', 'auditorium_code'];
        foreach (array_chunk(array_keys($off), 1000) as $codes) {
            foreach (DB::table('timetable_cards')->where('board_id', $board->id)->whereIn('auditorium_code', $codes)->orderBy('id')->get($cardCols) as $c) {
                $out[] = self::row($c, (string) $c->auditorium_code, $off, 'karta');
            }
            if (Schema::hasTable('timetable_card_overrides') && Schema::hasColumn('timetable_card_overrides', 'auditorium_code')) {
                $q = DB::table('timetable_card_overrides as o')->join('timetable_cards as c', 'c.id', '=', 'o.card_id')
                    ->where('c.board_id', $board->id)->whereIn('o.auditorium_code', $codes)->where(fn ($w) => $w->whereNull('o.cancelled')->orWhere('o.cancelled', false))
                    ->orderBy('c.id')->orderBy('o.week');
                foreach ($q->get(array_merge(array_map(fn ($k) => 'c.' . $k, array_diff($cardCols, ['auditorium_code'])), ['o.week', 'o.auditorium_code'])) as $c) {
                    $out[] = self::row($c, (string) $c->auditorium_code, $off, $c->week . '-hafta');
                }
            }
        }

        return $out;
    }

    /** Doskalar bo'yicha soni (faqat nofaol xonali kartasi borlari): [[board_id, board_name, count], ...]. */
    public static function summary(): array
    {
        $out = [];
        foreach (TimetableBoard::orderByDesc('id')->get(['id', 'name']) as $b) {
            $n = count(array_unique(array_column(self::rows($b), 0)));
            if ($n > 0) {
                $out[] = ['board_id' => $b->id, 'board_name' => (string) $b->name, 'count' => $n];
            }
        }

        return $out;
    }

    private static function row(object $c, string $code, array $off, string $where): array
    {
        $groups = is_string($c->group_names ?? null) ? (json_decode($c->group_names, true) ?: []) : (array) ($c->group_names ?? []);

        return [(int) $c->id, (string) $c->faculty_name, (string) $c->specialty_name, (int) $c->course,
            (string) ($c->group_name ?: ($groups ? implode(', ', $groups) : $c->oqim_label)), (string) $c->subject_name,
            $c->training_type === 'lecture' ? "Ma'ruza" : 'Amaliy', (string) $c->teacher_name, $code, (string) ($off[$code] ?? $code), $where];
    }

    /** Excel (xlsx) fayli — vaqtinchalik yo'l. */
    public static function xlsx(TimetableBoard $board): string
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $book->getActiveSheet();
        $sh->setTitle('Nofaol xonalar');
        $sh->fromArray([['Karta ID', 'Fakultet', "Yo'nalish", 'Kurs', 'Guruh / oqim', 'Fan', 'Tur', "O'qituvchi", 'Xona kodi', 'Xona', 'Qayerda']], null, 'A1');
        $rows = self::rows($board);
        if ($rows) {
            $sh->fromArray($rows, null, 'A2');
        }
        $sh->getStyle('A1:K1')->getFont()->setBold(true);
        foreach (range('A', 'K') as $col) {
            $sh->getColumnDimension($col)->setAutoSize(true);
        }
        $path = tempnam(sys_get_temp_dir(), 'tt-inactive-') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);

        return $path;
    }
}
