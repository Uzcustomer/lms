<?php

namespace App\Services;

use App\Models\FanTesti;
use App\Models\FanTestiAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fan testi urinishining savollar to'plami va o'qituvchi buyrug'i bilan
 * qayta boshlanishi (kiosk va o'qituvchi tomoni uchun umumiy).
 */
class FanTestiAttempts
{
    private const TZ = 'Asia/Tashkent';

    /** Faol savollar; to'plamda belgilangan bo'lsa aralashtiriladi. */
    public static function questionSnapshot(FanTesti $fanTesti): array
    {
        $snapshot = collect($fanTesti->questions ?? [])
            ->filter(fn ($question) => ($question['is_active'] ?? true) !== false)
            ->values();

        if ($fanTesti->shuffle_questions) {
            $snapshot = $snapshot->shuffle()->values();
        }

        return $snapshot->all();
    }

    /** Savollar bo'yicha urinish maydonlari (soni, jami ball, vaqt). */
    public static function freshFields(FanTesti $fanTesti, array $snapshot): array
    {
        return [
            'status' => 'in_progress',
            'started_at' => now(self::TZ),
            'expires_at' => now(self::TZ)->addMinutes(max(1, (int) $fanTesti->duration_minutes)),
            'questions_count' => count($snapshot),
            'total_points' => collect($snapshot)->sum(fn ($question) => max(1, (int) ($question['points'] ?? 1))),
            'questions_snapshot' => $snapshot,
        ];
    }

    /**
     * Urinishni boshidan boshlaydi: javoblar o'chiriladi, savollar qaytadan
     * olinadi va vaqt yangidan hisoblanadi. Urinish (id va raqami) o'sha-o'sha
     * qoladi — bu qayta topshirish emas, yarim qolgan ishni boshidan boshlash.
     */
    public static function restart(FanTestiAttempt $attempt): void
    {
        $fanTesti = $attempt->test;
        $snapshot = self::questionSnapshot($fanTesti);

        $fields = self::freshFields($fanTesti, $snapshot) + [
            'submitted_at' => null,
            'duration_seconds' => null,
            'answers_count' => 0,
            'correct_count' => 0,
            'score' => 0,
            'percent' => null,
            'is_passed' => false,
        ];
        if (Schema::hasColumn('fan_testi_attempts', 'away_count')) {
            $fields += ['face_checks' => 0, 'face_mismatches' => 0, 'away_count' => 0];
        }
        if (Schema::hasColumn('fan_testi_attempts', 'lock_count')) {
            $fields += ['away_streak' => 0, 'lock_count' => 0, 'locked_at' => null, 'ended_reason' => null];
        }

        DB::transaction(function () use ($attempt, $fields) {
            $attempt->answers()->delete();
            $attempt->update($fields);
        });
    }
}
