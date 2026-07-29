<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dars jadvali qoidasi (aSc "Взаимосвязи" uslubida).
 * @see database/migrations/2026_07_28_120000_create_timetable_rules.php
 */
class TimetableRule extends Model
{
    protected $fillable = [
        'board_id', 'condition', 'subjects', 'scopes', 'params',
        'weight', 'active', 'position', 'note',
    ];

    protected $casts = [
        'subjects' => 'array',
        'scopes'   => 'array',
        'params'   => 'array',
        'active'   => 'boolean',
        'position' => 'integer',
    ];

    /** Qo'llab-quvvatlanadigan shartlar va ularning izohi (UI bilan bir xil). */
    public const CONDITIONS = [
        'not_same_day'            => "Darslar bir kunda bo'lmasin",
        'not_consecutive_same_day' => "Darslar bir kunda ketma-ket kelmasin",
        'week_spread'             => "Darslarni hafta bo'ylab taqsimlash",
        'lecture_week_distribution' => "Ma'ruza haftalarini taqsimlash",
        'two_subjects_same_day'   => "Ikki fan bir kunda bo'lsin",
        'two_subjects_follow'     => "Ikki fan ketma-ket kelsin",
        'no_gap_between_groups'   => "Dars guruhlari orasida tanaffus bo'lmasin",
        'group_same_day'          => "Turli guruhlar darsi bir kunda bo'lsin",
        'split_same_day'          => "Bir fanning bo'lingan darslari bir kunda bo'lsin",
        'start_together'          => "Bu fanlar darslari bir vaqtda boshlansin",
        'same_time_all_groups'    => "Bu fanlar barcha guruhlarda bir vaqtda bo'lsin",
        'same_time_every_day'     => "Bu fan har kuni bir vaqtda bo'lsin",
        'reserve_slot'            => "Tanlangan fanlar uchun joy zaxiralash",
        'first_or_last'           => "Bu fan darslari birinchi yoki oxirgi bo'lsin",
        'afternoon_allowed'       => "Tanlangan fanlar tushdan keyin ham bo'lishi mumkin",
    ];

    public const WEIGHTS = ['majburiy', 'normal', 'yengil'];

    public function board()
    {
        return $this->belongsTo(TimetableBoard::class, 'board_id');
    }

    /** Qoidaning inson o'qiydigan tavsifi (ro'yxatda ko'rsatiladi). */
    public function describe(): string
    {
        if ($this->condition === 'lecture_week_distribution') {
            $params = $this->params ?: [];
            $mode = $params['distribution'] ?? 'auto';
            $labels = [
                'auto'   => 'Avtomatik',
                'spread' => 'Teng taqsimlash',
                'odd'    => 'Toq haftalar',
                'even'   => 'Juft haftalar',
            ];

            return (self::CONDITIONS[$this->condition] ?? $this->condition)
                . ': ' . ($labels[$mode] ?? $mode);
        }

        return self::CONDITIONS[$this->condition] ?? $this->condition;
    }
}
