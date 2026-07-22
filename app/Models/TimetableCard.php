<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableCard extends Model
{
    protected $fillable = [
        'board_id', 'specialty_name', 'course', 'faculty_name', 'oqim_label', 'lang',
        'training_type', 'group_name', 'group_names', 'subject_name',
        'kafedra_name', 'students', 'teacher_id', 'teacher_name',
        'auditorium_code', 'auditorium_name', 'day', 'pair', 'start_half', 'len_half',
    ];

    protected $casts = [
        'group_names' => 'array',
    ];

    public function board()
    {
        return $this->belongsTo(TimetableBoard::class, 'board_id');
    }

    /** Uzunlik yarim-para birligida (1=0.5 para, 2=1 para, 3=1.5, 4=2). Sukut 2. */
    public function lenHalf(): int
    {
        return max(1, (int) ($this->len_half ?? 2));
    }

    /**
     * Kartaning mutlaq yarim-slot oralig'i: [boshlanish, tugash) (tugash kirmaydi).
     * Slot indeksi = (pair-1)*2 + start_half. Joylashmagan bo'lsa null.
     */
    public function halfRange(): ?array
    {
        if (!$this->day || !$this->pair) {
            return null;
        }
        $s = ((int) $this->pair - 1) * 2 + (int) ($this->start_half ?? 0);
        return [$s, $s + $this->lenHalf()];
    }

    /** Kartochka band qiladigan guruhchalar ro'yxati (konflikt tekshiruvi uchun). */
    public function occupiedGroups(): array
    {
        if ($this->training_type === 'lecture') {
            return $this->group_names ?: [];
        }
        return $this->group_name ? [$this->group_name] : [];
    }
}
