<?php

namespace App\Http\Controllers;

use App\Models\FanTesti;
use App\Models\FanTestiAttempt;
use App\Models\FanTestiAttemptAnswer;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Talaba testni ishlaydigan public sahifa (kiosk).
 *
 * Login talab qilinmaydi: o'qituvchi havolani sinf kompyuterlarida ochib
 * qo'yadi, talaba faqat o'z ID raqamini kiritadi. Tashqi kirishdan himoya
 * veb-server darajasida (nginx allow/deny) qilinadi — /tv/jadval bilan bir xil
 * yondashuv. Kelajakda bu yerga Face ID tekshiruvi qo'shiladi.
 */
class FanTestiKioskController extends Controller
{
    private const TZ = 'Asia/Tashkent';

    public function show(FanTesti $fanTesti)
    {
        $this->assertReady($fanTesti);

        return view('kiosk.fan-testi.start', [
            'test' => $fanTesti->load('subject'),
        ]);
    }

    public function start(Request $request, FanTesti $fanTesti)
    {
        $this->assertReady($fanTesti);

        $data = $request->validate([
            'student_id_number' => ['required', 'string', 'max:64'],
        ], [], ['student_id_number' => 'Talaba ID']);

        $student = $this->findStudent($data['student_id_number']);
        if (!$student) {
            throw ValidationException::withMessages([
                'student_id_number' => 'Bunday ID raqamli talaba topilmadi. Raqamni tekshirib qayta kiriting.',
            ]);
        }

        $questions = $this->activeQuestions($fanTesti);
        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'student_id_number' => 'Bu test to\'plamida hali savol yo\'q.',
            ]);
        }

        $attempt = FanTestiAttempt::query()
            ->where('fan_testi_id', $fanTesti->id)
            ->where('student_id', $student->id)
            ->first();

        // Bir marta topshiriladi — tugatgan bo'lsa natijasini ko'rsatamiz.
        if ($attempt && $attempt->isFinished()) {
            return redirect()->route('kiosk.fan-testi.result', [$fanTesti, $attempt]);
        }

        // Yarim qolgan urinish (brauzer yopilgan) — o'sha joyidan davom etadi.
        if ($attempt) {
            if ($attempt->secondsLeft() <= 0) {
                $this->finalize($attempt, 'expired');

                return redirect()->route('kiosk.fan-testi.result', [$fanTesti, $attempt]);
            }

            return redirect()->route('kiosk.fan-testi.take', [$fanTesti, $attempt]);
        }

        $snapshot = $questions->values();
        if ($fanTesti->shuffle_questions) {
            $snapshot = $snapshot->shuffle()->values();
        }

        $attempt = FanTestiAttempt::create([
            'fan_testi_id' => $fanTesti->id,
            'student_id' => $student->id,
            'student_hemis_id' => $student->hemis_id,
            'student_name' => $student->full_name,
            'student_id_number' => $student->student_id_number,
            'group_id' => $student->group_id,
            'group_name' => $student->group_name,
            'faculty_name' => $student->department_name,
            'specialty_name' => $student->specialty_name,
            'status' => 'in_progress',
            'started_at' => now(self::TZ),
            'expires_at' => now(self::TZ)->addMinutes(max(1, (int) $fanTesti->duration_minutes)),
            'questions_count' => $snapshot->count(),
            'total_points' => $snapshot->sum(fn ($question) => max(1, (int) ($question['points'] ?? 1))),
            'questions_snapshot' => $snapshot->all(),
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('kiosk.fan-testi.take', [$fanTesti, $attempt]);
    }

    public function take(FanTesti $fanTesti, FanTestiAttempt $attempt)
    {
        $this->assertAttemptBelongs($fanTesti, $attempt);

        if ($attempt->isFinished()) {
            return redirect()->route('kiosk.fan-testi.result', [$fanTesti, $attempt]);
        }

        if ($attempt->secondsLeft() <= 0) {
            $this->finalize($attempt, 'expired');

            return redirect()->route('kiosk.fan-testi.result', [$fanTesti, $attempt]);
        }

        return view('kiosk.fan-testi.take', [
            'test' => $fanTesti->load('subject'),
            'attempt' => $attempt,
            'questions' => collect($attempt->questions_snapshot ?? []),
            'secondsLeft' => $attempt->secondsLeft(),
        ]);
    }

    public function submit(Request $request, FanTesti $fanTesti, FanTestiAttempt $attempt)
    {
        $this->assertAttemptBelongs($fanTesti, $attempt);

        if ($attempt->isFinished()) {
            return redirect()->route('kiosk.fan-testi.result', [$fanTesti, $attempt]);
        }

        $data = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable'],
        ]);

        // Vaqt tugagan bo'lsa ham belgilangan javoblar hisobga olinadi —
        // talabaning ishi yo'qolmasligi kerak.
        $expired = $attempt->secondsLeft() <= 0;
        $this->gradeAnswers($attempt, $data['answers'] ?? []);
        $this->finalize($attempt, $expired ? 'expired' : 'submitted');

        return redirect()->route('kiosk.fan-testi.result', [$fanTesti, $attempt]);
    }

    public function result(FanTesti $fanTesti, FanTestiAttempt $attempt)
    {
        $this->assertAttemptBelongs($fanTesti, $attempt);

        return view('kiosk.fan-testi.result', [
            'test' => $fanTesti->load('subject'),
            'attempt' => $attempt->load('answers'),
        ]);
    }

    private function assertReady(FanTesti $fanTesti): void
    {
        abort_unless(
            Schema::hasTable('fan_testi_attempts'),
            503,
            'Test natijalari jadvali migratsiyasi hali ishga tushirilmagan.'
        );
        abort_unless($fanTesti->is_active, 404, 'Bu test to\'plami faol emas.');
    }

    private function findStudent(string $identifier): ?Student
    {
        $identifier = trim($identifier);

        return Student::query()
            ->where(function ($query) use ($identifier) {
                $query->where('student_id_number', $identifier)
                    ->orWhere('hemis_id', $identifier);
            })
            ->first();
    }

    /** Faqat faol savollar — questionCount() bundan farqli, u hammasini sanaydi. */
    private function activeQuestions(FanTesti $fanTesti)
    {
        return collect($fanTesti->questions ?? [])
            ->filter(fn ($question) => ($question['is_active'] ?? true) !== false);
    }

    private function assertAttemptBelongs(FanTesti $fanTesti, FanTestiAttempt $attempt): void
    {
        abort_unless((int) $attempt->fan_testi_id === (int) $fanTesti->id, 404);
    }

    /**
     * Baholash urinish boshlanganda olingan nusxa bo'yicha bajariladi, shuning
     * uchun o'qituvchi savolni o'chirsa yoki tahrirlasa ham natija buzilmaydi.
     */
    private function gradeAnswers(FanTestiAttempt $attempt, array $answers): void
    {
        $questions = collect($attempt->questions_snapshot ?? []);

        DB::transaction(function () use ($attempt, $questions, $answers) {
            $attempt->answers()->delete();

            foreach ($questions as $index => $question) {
                $given = $answers[$index] ?? null;
                $points = max(1, (int) ($question['points'] ?? 1));
                $type = $question['type'] ?? 'single_choice';

                $row = [
                    'attempt_id' => $attempt->id,
                    'question_index' => (int) $index,
                    'question_type' => $type,
                    'question_prompt' => $question['prompt'] ?? '',
                    'points_possible' => $points,
                    'is_correct' => false,
                    'points_earned' => 0,
                    'answered_at' => null,
                ];

                if ($type === 'fill_in_blank') {
                    $text = trim((string) $given);
                    $correct = trim((string) ($question['correct_answer_text'] ?? ''));
                    $row['answer_text'] = $text;
                    $row['correct_answer_text'] = $correct;

                    if ($text !== '') {
                        $row['answered_at'] = now(self::TZ);
                        $row['is_correct'] = ($question['case_sensitive'] ?? false)
                            ? $text === $correct
                            : mb_strtolower($text) === mb_strtolower($correct);
                    }
                } else {
                    $options = $question['options'] ?? [];
                    $correctIndex = collect($options)->search(fn ($option) => ($option['is_correct'] ?? false) === true);
                    $row['correct_answer_text'] = $correctIndex !== false
                        ? (string) ($options[$correctIndex]['text'] ?? '')
                        : '';

                    if ($given !== null && $given !== '' && isset($options[(int) $given])) {
                        $chosen = (int) $given;
                        $row['answered_at'] = now(self::TZ);
                        $row['selected_option_index'] = $chosen;
                        $row['selected_option_text'] = (string) ($options[$chosen]['text'] ?? '');
                        $row['is_correct'] = ($options[$chosen]['is_correct'] ?? false) === true;
                    }
                }

                if ($row['is_correct']) {
                    $row['points_earned'] = $points;
                }

                FanTestiAttemptAnswer::create($row);
            }
        });
    }

    private function finalize(FanTestiAttempt $attempt, string $status): void
    {
        $answers = $attempt->answers()->get();
        $score = (int) $answers->sum('points_earned');
        $total = (int) $answers->sum('points_possible') ?: (int) $attempt->total_points;
        $percent = $total > 0 ? round($score * 100 / $total, 2) : 0;
        $passPercent = $attempt->test?->pass_percent;

        $attempt->update([
            'status' => $status,
            'submitted_at' => now(self::TZ),
            'duration_seconds' => $attempt->started_at
                ? $attempt->started_at->diffInSeconds(now(self::TZ))
                : null,
            'answers_count' => $answers->whereNotNull('answered_at')->count(),
            'correct_count' => $answers->where('is_correct', true)->count(),
            'total_points' => $total,
            'score' => $score,
            'percent' => $percent,
            'is_passed' => $passPercent ? $percent >= (float) $passPercent : $percent >= 60,
        ]);
    }
}
