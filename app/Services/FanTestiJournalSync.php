<?php

namespace App\Services;

use App\Models\FanTestiAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Qayta topshirish testi natijasini jurnalga otrabotka bahosi sifatida yozish.
 *
 * Qoida: N-mavzu testi = guruhning shu fandagi N-amaliy dars KUNI (bir kunda ikki
 * juftlik bo'lsa ham bitta mavzu). Talaba o'sha kuni NB yoki 60 dan past baho olgan
 * bo'lsa, test foizi shu kunning amaliy kataklariga retake_grade bo'lib tushadi —
 * jurnaldagi qo'lda otrabotka qoidasi bilan bir xil (JournalController::saveRetakeGrade):
 * sababli NB — 100%, sababsiz NB — 80%, past baho (low_grade) — 80%, boshqa <60 — 100%.
 * 60 va undan yuqori bahoga tegilmaydi.
 *
 * Yozilmaydi: asosiy (qayta topshirish bo'lmagan) testlar, YN ga yuborilgan,
 * otrabotka allaqachon qo'yilgan, muddati o'tgan, shu juftlikda 60+ baho bor
 * yoki yangi baho jurnaldagidan past bo'lgan kataklar. Natija urinishning
 * journal_sync_note maydoniga yoziladi (Test jurnalida ko'rinadi).
 */
class FanTestiJournalSync
{
    /** Ma'ruza va yakuniy nazorat turlari — amaliy darslar hisobiga kirmaydi */
    private const NON_PRACTICE = [11, 99, 100, 101, 102, 103];

    /** Jurnalda "Baho qo'ydi" sifatida ko'rinadi */
    public const GRADED_BY = 'Kafedra';

    private const PASS = 60;

    public static function sync(FanTestiAttempt $attempt): ?string
    {
        if (!Schema::hasColumn('fan_testi_attempts', 'journal_sync_note')) {
            return null;
        }
        if (!$attempt->is_retake || $attempt->status === 'in_progress' || $attempt->percent === null) {
            return null;
        }

        try {
            $note = self::write($attempt);
        } catch (\Throwable $e) {
            Log::error('[FanTestiJournalSync] xato', ['attempt' => $attempt->id, 'error' => $e->getMessage()]);
            $note = 'Xato: ' . mb_substr($e->getMessage(), 0, 200);
        }

        $attempt->forceFill(['journal_synced_at' => now(), 'journal_sync_note' => mb_substr($note, 0, 255)])->save();

        return $note;
    }

    private static function write(FanTestiAttempt $attempt): string
    {
        $test = $attempt->test()->with('subject')->first();
        $topic = (int) ($test->topic_number ?? 0);
        $subject = $test?->subject;
        if (!$test || !$subject) {
            return "Yozilmadi: test to'plamiga fan biriktirilmagan";
        }
        if ($topic < 1) {
            return "Yozilmadi: test to'plamida mavzu raqami yo'q";
        }

        // Guruhning shu fandagi amaliy dars kunlari (joriy o'quv yili), sana bo'yicha
        $groupId = $attempt->group_id ?: DB::table('students')->where('id', $attempt->student_id)->value('group_id');
        $days = DB::table('schedules')
            ->where('group_id', $groupId)
            ->where('subject_id', $subject->subject_id)
            ->where('education_year_current', true)
            ->whereNull('deleted_at')
            ->whereNotNull('lesson_date')
            ->whereNotIn('training_type_code', self::NON_PRACTICE)
            ->selectRaw('DATE(lesson_date) as d')
            ->groupBy(DB::raw('DATE(lesson_date)'))
            ->orderBy('d')
            ->pluck('d')
            ->values();

        if ($days->count() < $topic) {
            return "Yozilmadi: guruhda {$topic}-amaliy dars kuni topilmadi (jami {$days->count()} kun)";
        }
        $date = (string) $days[$topic - 1];
        $dateLabel = \Carbon\Carbon::parse($date)->format('d.m.Y');

        // Shu kunning NB va 60 dan past baholi kataklari
        $dayCells = DB::table('student_grades')
            ->where('student_hemis_id', $attempt->student_hemis_id)
            ->where('subject_id', $subject->subject_id)
            ->whereDate('lesson_date', $date)
            ->whereNull('deleted_at')
            ->whereNotIn('training_type_code', self::NON_PRACTICE)
            ->get();
        $cells = $dayCells->filter(fn ($c) => $c->reason === 'absent'
            || ($c->grade !== null && round((float) $c->grade) < self::PASS));

        if ($cells->isEmpty()) {
            return "Yozilmadi: {$topic}-mavzu ({$dateLabel}) kunida talabada NB yoki 60 dan past baho yo'q";
        }

        $percent = (float) $attempt->percent;
        $hasSababli = Schema::hasColumn('student_grades', 'retake_was_sababli');
        $marker = "urinish #{$attempt->id}";
        $written = [];
        $skipped = [];

        foreach ($cells as $cell) {
            // Shu urinish avval yozgan katak (buyruq qayta ishga tushirilganda) — yozilgan hisoblanadi
            if ($cell->retake_grade !== null && str_contains((string) ($cell->retake_comment ?? ''), $marker)) {
                $written[] = (float) $cell->retake_grade;
                continue;
            }
            if (!empty($cell->is_yn_locked)) {
                $skipped[] = 'YN ga yuborilgan';
                continue;
            }
            if ($cell->retake_grade !== null) {
                $skipped[] = 'otrabotka allaqachon qo\'yilgan (' . (float) $cell->retake_grade . ')';
                continue;
            }
            if ($cell->deadline && now()->greaterThan($cell->deadline)) {
                $skipped[] = 'otrabotka muddati o\'tgan (' . \Carbon\Carbon::parse($cell->deadline)->format('d.m.Y') . ')';
                continue;
            }
            // Jurnaldagi kabi: shu juftlikda boshqa yozuvda o'tgan (60+) baho bor — otrabotka o'rinsiz
            $passingTwin = $dayCells->first(fn ($c) => $c->id !== $cell->id
                && (string) $c->lesson_pair_code === (string) $cell->lesson_pair_code
                && $c->grade !== null && (float) $c->grade >= self::PASS
                && in_array($c->status, ['recorded', 'closed'], true));
            if ($passingTwin) {
                $skipped[] = "shu juftlikda {$passingTwin->grade} baho bor";
                continue;
            }

            $isAbsent = $cell->reason === 'absent';
            $excused = $isAbsent && self::isExcused($cell);
            $factor = $isAbsent ? ($excused ? 1.0 : 0.8) : ($cell->reason === 'low_grade' ? 0.8 : 1.0);
            $grade = round($percent * $factor, 2);

            // Past baho: qayta topshirish natijasi jurnaldagidan past bo'lsa talabaga zarar — yozilmaydi
            if (!$isAbsent && $grade <= (float) $cell->grade) {
                $skipped[] = "qayta topshirish bahosi ({$grade}) jurnaldagidan ({$cell->grade}) past";
                continue;
            }

            DB::table('student_grades')->where('id', $cell->id)->update([
                'retake_grade' => $grade,
                'status' => 'retake',
                'retake_graded_at' => now(),
                'retake_by' => self::GRADED_BY,
                'retake_comment' => mb_substr("Qayta topshirish testi: {$topic}-mavzu, {$test->name} — " . rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.') . "%, {$marker}", 0, 1000),
                'updated_at' => now(),
            ] + ($hasSababli ? ['retake_was_sababli' => $excused] : []));
            $written[] = $grade;
        }

        if ($written) {
            return "Jurnalga yozildi: {$topic}-mavzu ({$dateLabel}) — " . implode(', ', array_unique($written))
                . ($skipped ? ' · qolgani: ' . implode('; ', array_unique($skipped)) : '');
        }

        return "Yozilmadi: {$topic}-mavzu ({$dateLabel}) — " . implode('; ', array_unique($skipped));
    }

    /** Jurnaldagi saveRetakeGrade bilan bir xil: tasdiqlangan sababli ariza yoki HEMIS sababli soat */
    private static function isExcused(object $cell): bool
    {
        // lesson_date vaqti bilan keladi ("2026-10-19 08:30:00") — sana bilan solishtirish uchun kun qoldiriladi
        $day = substr((string) $cell->lesson_date, 0, 10);

        $excused = DB::table('absence_excuses as ae')
            ->join('absence_excuse_makeups as aem', 'aem.absence_excuse_id', '=', 'ae.id')
            ->where('ae.student_hemis_id', $cell->student_hemis_id)
            ->where('ae.status', 'approved')
            ->whereDate('ae.start_date', '<=', $day)
            ->whereDate('ae.end_date', '>=', $day)
            ->where('aem.subject_id', $cell->subject_id)
            ->exists();

        return $excused || DB::table('attendances')
            ->where('student_hemis_id', $cell->student_hemis_id)
            ->where('subject_id', $cell->subject_id)
            ->whereDate('lesson_date', $day)
            ->where('lesson_pair_code', $cell->lesson_pair_code)
            ->where('absent_on', '>', 0)
            ->exists();
    }
}
