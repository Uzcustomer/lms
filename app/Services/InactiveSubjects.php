<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * O'quv rejada nofaol (curriculum_subjects.is_active = 0) fanlar — baho
 * qo'yilmaganlar hisobotlari (Telegram, "Dars belgilash", tyutor, o'qituvchi
 * o'tkazib yuborgan darslar) jurnal kabi ularni ko'rsatmaydi.
 *
 * Darslar HEMIS jadvalidan olinadi, shuning uchun filtr (guruh o'quv rejasi,
 * semestr kodi, fan) uchligi bo'yicha. Jadvaldagi subject_id HEMIS subject_id
 * yoki curriculum_subject_hemis_id bo'lishi mumkin — ikkalasi ham qo'shiladi.
 * Shu reja/semestrda fan faol qatori ham bo'lsa (dublikat) — faol hisoblanadi.
 * O'quv rejada umuman topilmagan fan (import qilinmagan) ko'rsatilaveradi.
 */
class InactiveSubjects
{
    /** Navbat ishchisi uzoq yashaydi — ro'yxat eskirmasin */
    private const CACHE_TTL = 600;

    /** @var list<array{0:string,1:string,2:string}>|null */
    private static ?array $tuples = null;

    private static int $loadedAt = 0;

    /**
     * Nofaol (o'quv reja, semestr kodi, fan) uchliklari.
     *
     * @return list<array{0:string,1:string,2:string}>
     */
    public static function tuples(): array
    {
        if (self::$tuples !== null && time() - self::$loadedAt < self::CACHE_TTL) {
            return self::$tuples;
        }

        $inactive = DB::table('curriculum_subjects')
            ->where('is_active', false)
            ->whereNotNull('curricula_hemis_id')
            ->whereNotNull('semester_code')
            ->get(['curricula_hemis_id', 'semester_code', 'subject_id', 'curriculum_subject_hemis_id']);

        $active = [];
        if ($inactive->isNotEmpty()) {
            DB::table('curriculum_subjects')
                ->where('is_active', true)
                ->whereIn('curricula_hemis_id', $inactive->pluck('curricula_hemis_id')->unique()->all())
                ->get(['curricula_hemis_id', 'semester_code', 'subject_id', 'curriculum_subject_hemis_id'])
                ->each(function ($row) use (&$active) {
                    foreach ([$row->subject_id, $row->curriculum_subject_hemis_id] as $sid) {
                        if ($sid !== null) {
                            $active[$row->curricula_hemis_id.'|'.$row->semester_code.'|'.$sid] = true;
                        }
                    }
                });
        }

        $tuples = [];
        foreach ($inactive as $row) {
            foreach ([$row->subject_id, $row->curriculum_subject_hemis_id] as $sid) {
                if ($sid === null) {
                    continue;
                }
                $key = $row->curricula_hemis_id.'|'.$row->semester_code.'|'.$sid;
                if (! isset($active[$key]) && ! isset($tuples[$key])) {
                    $tuples[$key] = [(string) $row->curricula_hemis_id, (string) $row->semester_code, (string) $sid];
                }
            }
        }

        self::$tuples = array_values($tuples);
        self::$loadedAt = time();

        return self::$tuples;
    }

    /**
     * Jadval (schedules) so'roviga nofaol fanlarni chiqarib tashlash sharti.
     * $groupAlias — groups jadvali taxallusi (curriculum_hemis_id uchun).
     */
    public static function exclude($query, string $groupAlias = 'g', string $scheduleAlias = 'sch'): void
    {
        $tuples = self::tuples();
        if ($tuples === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($tuples), '(?,?,?)'));
        // Rejasi yo'q guruh — NOT IN NULL qaytarib qatorni yo'qotmasin
        $query->whereRaw(
            "({$groupAlias}.curriculum_hemis_id IS NULL OR ({$groupAlias}.curriculum_hemis_id, {$scheduleAlias}.semester_code, {$scheduleAlias}.subject_id) NOT IN ({$placeholders}))",
            array_merge(...$tuples)
        );
    }
}
