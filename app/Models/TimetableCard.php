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
        // Karta necha haftada o'tiladi (ma'ruzali haftada amaliy paralar kamayadi)
        'weeks',
        // Umumiy (klinik) karta: ma'ruza+amaliy bitta kartada; kafedra ajratishi —
        // ma'ruza haftalari va ma'ruza haftalari uchun o'qituvchi/xona
        'is_mixed', 'lecture_weeks',
        'lecture_teacher_id', 'lecture_teacher_name',
        'lecture_auditorium_code', 'lecture_auditorium_name',
        'placement_reason_code', 'placement_reason',
    ];

    protected $casts = [
        'group_names'   => 'array',
        'is_mixed'      => 'boolean',
        'lecture_weeks' => 'array',
    ];

    /**
     * Umumiy (klinik) karta — ma'ruza va amaliy bitta kartada. O'quv bo'limi uni
     * katta jadvalga joylaydi, kafedra esa haftalar bo'yicha ma'ruza/amaliyga ajratadi.
     */
    public function isMixed(): bool
    {
        return (bool) $this->is_mixed;
    }

    /** Kafedra ma'ruza deb belgilagan haftalar; null — hali ajratilmagan. */
    public function lectureWeekList(): ?array
    {
        if (!$this->isMixed()) {
            return null;
        }
        $weeks = $this->lecture_weeks;
        if (!is_array($weeks)) {
            return null;
        }
        $out = array_values(array_unique(array_map('intval', $weeks)));
        sort($out);
        return $out;
    }

    /** Shu haftada karta ma'ruza sifatida o'tiladimi (faqat umumiy kartada). */
    public function isLectureWeek(int $week): bool
    {
        $weeks = $this->lectureWeekList();
        return $weeks !== null && in_array($week, $weeks, true);
    }

    /** Shu haftadagi o'qituvchi: ma'ruza haftasida ma'ruza o'qituvchisi, aks holda asosiy. */
    public function effectiveTeacherId(int $week): ?int
    {
        if ($this->isLectureWeek($week)) {
            return $this->lecture_teacher_id ? (int) $this->lecture_teacher_id : null;
        }
        return $this->teacher_id ? (int) $this->teacher_id : null;
    }

    /** Shu haftadagi auditoriya kodi: ma'ruza haftasida ma'ruza xonasi, aks holda asosiy. */
    public function effectiveAuditoriumCode(int $week): ?string
    {
        if ($this->isLectureWeek($week)) {
            return $this->lecture_auditorium_code ?: null;
        }
        return $this->auditorium_code ?: null;
    }

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
     * Kartaning yarim-slot oralig'i: [boshlanish, tugash) (tugash kirmaydi).
     * Bu modelda `pair` — yarim-slot (grid qatori) indeksi, dars len_half ta
     * yarim-slotni egallaydi. Joylashmagan bo'lsa null.
     */
    public function halfRange(): ?array
    {
        if (!$this->day || !$this->pair) {
            return null;
        }
        $s = (int) $this->pair - 1;
        return [$s, $s + $this->lenHalf()];
    }

    /** Kartochka band qiladigan guruhchalar ro'yxati (konflikt tekshiruvi uchun). */
    public function occupiedGroups(): array
    {
        if ($this->group_names) {
            return $this->group_names ?: [];
        }

        return $this->group_name ? [$this->group_name] : [];
    }
}
