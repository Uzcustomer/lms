<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableCard extends Model
{
    protected $fillable = [
        'board_id', 'specialty_name', 'course', 'faculty_name', 'oqim_label', 'lang',
        'training_type', 'group_name', 'group_names', 'subject_name',
        'kafedra_name', 'students', 'teacher_id', 'teacher_name',
        'auditorium_code', 'auditorium_name', 'day', 'pair',
    ];

    protected $casts = [
        'group_names' => 'array',
    ];

    public function board()
    {
        return $this->belongsTo(TimetableBoard::class, 'board_id');
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
