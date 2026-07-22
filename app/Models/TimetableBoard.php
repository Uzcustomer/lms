<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableBoard extends Model
{
    protected $fillable = [
        'name', 'institution_name', 'academic_year', 'semester_parity', 'kind',
        'faculty_id', 'faculty_name', 'days', 'pairs_per_day', 'weeks',
        'bell_schedule', 'day_names', 'settings', 'status', 'created_by',
    ];

    protected $casts = [
        'bell_schedule' => 'array',
        'day_names'     => 'array',
        'settings'      => 'array',
    ];

    public function cards()
    {
        return $this->hasMany(TimetableCard::class, 'board_id');
    }

    /** Sukut kun nomlari (Dush–Yak). */
    public const DEFAULT_DAY_NAMES = ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba', 'Yakshanba'];

    /**
     * Sukut qo'ng'iroqlar jadvali — para (juftlik) va tanaffuslar ketma-ketligi.
     * Har element: type (pair|break), name, abbr, start, end, print.
     * Para vaqtlari tibbiyot universiteti standarti (80 daqiqalik juftlik).
     */
    public static function defaultBellSchedule(int $pairs = 6): array
    {
        $pairTimes = [
            ['08:30', '09:50'], ['10:00', '11:20'], ['12:00', '13:20'],
            ['13:30', '14:50'], ['15:00', '16:20'], ['16:30', '17:50'],
            ['18:00', '19:20'], ['19:30', '20:50'],
        ];
        $roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];
        $out = [];
        for ($i = 0; $i < $pairs; $i++) {
            [$s, $e] = $pairTimes[$i] ?? ['', ''];
            $out[] = [
                'type' => 'pair', 'no' => $i + 1,
                'name' => ($roman[$i] ?? ($i + 1)) . '-para',
                'abbr' => $roman[$i] ?? (string) ($i + 1),
                'start' => $s, 'end' => $e, 'print' => true,
            ];
            // Juftliklar orasidagi tanaffus (oxirgisidan keyin qo'yilmaydi)
            if ($i < $pairs - 1) {
                $next = $pairTimes[$i + 1][0] ?? '';
                $out[] = [
                    'type' => 'break', 'no' => null,
                    'name' => 'Tanaffus', 'abbr' => '—',
                    'start' => $e, 'end' => $next, 'print' => false,
                ];
            }
        }
        return $out;
    }

    /**
     * Panjaradagi yarim-slot (grid qatori) soni bir kunda. Qo'ng'iroq jadvalidagi
     * "pair" elementlar sonidan olinadi (bir "pair" = bir yarim-slot). Jadval yo'q
     * bo'lsa — pairs_per_day ustuniga qaytamiz.
     */
    public function pairCount(): int
    {
        $sched = $this->bell_schedule;
        if (is_array($sched) && count($sched)) {
            $n = count(array_filter($sched, fn($it) => ($it['type'] ?? 'pair') === 'pair'));
            if ($n > 0) {
                return $n;
            }
        }
        return max(1, (int) $this->pairs_per_day);
    }
}
