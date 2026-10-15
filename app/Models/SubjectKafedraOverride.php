<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SubjectKafedraOverride extends Model
{
    protected $fillable = [
        'norm_name',
        'sample_name',
        'kafedra_name',
        'department_id',
        'practice_group_size',
        'updated_by',
    ];

    /** Fan nomini normallashtirish (CurriculumCheckController/TimetableController::normSubject bilan bir xil). */
    public static function normName(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(["'", '’', 'ʻ', 'ʼ', '`', '´'], '', $s);
        $s = preg_replace('/[.,;:()\-\–\/]/u', ' ', $s);
        $s = preg_replace('/\b\d+([.,]\d+)?\b/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);

        return trim($s);
    }

    /**
     * Dars jadvalidagi mavjud kartalarning kafedrasini shu fan uchun yangilaydi
     * (kartalar kafedra nomini yaratilganda saqlaydi). Kafedra bo'sh bo'lsa —
     * o'quv rejadagi eng ko'p uchraydigan kafedraga qaytariladi.
     *
     * @return int yangilangan kartalar soni
     */
    public static function relabelTimetableCards(string $norm, ?string $kafedra): int
    {
        $kafedra = trim((string) $kafedra);
        if ($kafedra === '') {
            $counts = [];
            DB::table('curriculum_subjects')
                ->whereNotNull('department_name')->where('department_name', '!=', '')
                ->selectRaw('subject_name, department_name, COUNT(*) as c')
                ->groupBy('subject_name', 'department_name')->get()
                ->each(function ($r) use ($norm, &$counts) {
                    if (self::normName((string) $r->subject_name) === $norm) {
                        $counts[$r->department_name] = ($counts[$r->department_name] ?? 0) + (int) $r->c;
                    }
                });
            arsort($counts);
            $kafedra = (string) array_key_first($counts);
            if ($kafedra === '') {
                return 0;
            }
        }

        $subjects = DB::table('timetable_cards')->distinct()->pluck('subject_name')
            ->filter(fn ($name) => self::normName((string) $name) === $norm)
            ->values()->all();
        if ($subjects === []) {
            return 0;
        }

        return DB::table('timetable_cards')
            ->whereIn('subject_name', $subjects)
            ->where(fn ($q) => $q->whereNull('kafedra_name')->orWhere('kafedra_name', '!=', $kafedra))
            ->update(['kafedra_name' => $kafedra, 'updated_at' => now()]);
    }
}
