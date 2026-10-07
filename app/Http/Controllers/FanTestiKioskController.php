<?php

namespace App\Http\Controllers;

use App\Models\FanTesti;
use App\Models\FanTestiAttempt;
use App\Models\FanTestiAttemptAnswer;
use App\Models\Student;
use App\Services\FaceIdService;
use App\Services\FanTestiGroups;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Talaba testni ishlaydigan public sahifa (kiosk).
 *
 * Login talab qilinmaydi: o'qituvchi havolani sinf kompyuterlarida ochib
 * qo'yadi. Talaba ID raqamini kiritadi, boshini o'ngga va chapga burib
 * jonlilikni ko'rsatadi, so'ng yuzi tasdiqlangan rasmi bilan solishtiriladi.
 * Test va natija sahifalari faqat shu tekshiruvdan o'tgan sessiyaga ochiq.
 */
class FanTestiKioskController extends Controller
{
    private const TZ = 'Asia/Tashkent';

    /** Yuz tekshiruvidan o'tgan urinish id si — test va natija faqat shu sessiyaga ochiq. */
    private const SESSION_KEY = 'fan_testi_attempt_id';

    public function show(FanTesti $fanTesti)
    {
        $this->assertTableReady();

        // Sinf kompyuterida keyingi talaba oldingisining test yoki natija
        // sahifasiga orqaga qaytib kira olmasin.
        session()->forget(self::SESSION_KEY);

        // Yopilgan test 404 bermaydi: sinf kompyuterlarida havola ochiq
        // turgan bo'lishi mumkin, shuning uchun tushunarli xabar chiqadi.
        // Fani biriktirilmagan qoralama ham shu yerda to'xtaydi: qaysi guruh
        // ishlashi aniq bo'lmagani uchun uni hech kimga ochib bo'lmaydi.
        if (!$fanTesti->is_active || !$fanTesti->curriculum_subject_id) {
            return view('kiosk.fan-testi.closed', [
                'test' => $fanTesti->load('subject'),
            ]);
        }

        return view('kiosk.fan-testi.start', [
            'test' => $fanTesti->load('subject'),
            'liveness' => FaceIdService::getLivenessConfig(),
        ]);
    }

    /**
     * 1-bosqich: ID bo'yicha talabani tekshiradi va uning tasdiqlangan rasmini
     * qaytaradi — keyingi yuz tekshiruvi aynan shu rasm bilan solishtiriladi.
     */
    public function check(Request $request, FanTesti $fanTesti)
    {
        $this->assertTableReady();
        $this->throttle($request, 'fan_testi_check', 60);

        $data = $request->validate([
            'student_id_number' => ['required', 'string', 'max:64'],
        ], [], ['student_id_number' => 'Talaba ID']);

        $student = $this->eligibleStudent($fanTesti, $data['student_id_number']);

        return response()->json([
            'full_name' => $student->full_name,
            'group_name' => $student->group_name,
            'photo_url' => FaceIdService::referenceImageFor($student),
        ]);
    }

    /**
     * 2-bosqich: liveness (boshni o'ngga va chapga burish) brauzerda o'tgach,
     * to'g'ridan olingan surat talabaning tasdiqlangan rasmi bilan serverda
     * (ArcFace) solishtiriladi. O'xshashlik Face ID sozlamalaridagi chegaradan
     * past bo'lsa testga kiritilmaydi.
     */
    public function start(Request $request, FanTesti $fanTesti)
    {
        $this->assertTableReady();
        $this->throttle($request, 'fan_testi_start', 30);

        $data = $request->validate([
            'student_id_number' => ['required', 'string', 'max:64'],
            'snapshot' => ['required', 'string', 'max:500000'],
            'liveness_passed' => ['required', 'boolean'],
        ], [], ['student_id_number' => 'Talaba ID', 'snapshot' => 'Yuz surati']);

        $student = $this->eligibleStudent($fanTesti, $data['student_id_number']);

        $log = [
            'attempt_type' => 'fan_test',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'student_id' => $student->id,
            'student_id_number' => $student->student_id_number,
            'target_student_id' => $student->id,
            'target_student_id_number' => $student->student_id_number,
        ];

        if (!$request->boolean('liveness_passed')) {
            FaceIdService::logAttempt($log + [
                'result' => 'liveness_failed',
                'failure_reason' => "Fan testi: jonlilik tekshiruvi o'tmadi (test #{$fanTesti->id})",
                'snapshot' => $data['snapshot'],
            ]);

            return response()->json(['message' => "Jonlilik tekshiruvi o'tmadi. Boshingizni o'ngga va chapga burib, qayta urinib ko'ring."], 422);
        }

        $similarity = $this->compareFace($student, $data['snapshot']);
        if ($similarity === null) {
            FaceIdService::logAttempt($log + [
                'result' => 'failed',
                'failure_reason' => "Fan testi: ArcFace xizmati javob bermadi (test #{$fanTesti->id})",
                'snapshot' => $data['snapshot'],
            ]);

            return response()->json(['message' => "Yuz tekshirish xizmati javob bermadi. Birozdan keyin qayta urinib ko'ring yoki o'qituvchiga murojaat qiling."], 503);
        }

        $threshold = FaceIdService::getArcFaceThreshold();
        if ($similarity < $threshold) {
            FaceIdService::logAttempt($log + [
                'result' => 'failed',
                'confidence' => round($similarity / 100, 4),
                'failure_reason' => "Fan testi: yuz mos kelmadi ({$similarity}% < {$threshold}%, test #{$fanTesti->id})",
                'snapshot' => $data['snapshot'],
            ]);

            return response()->json([
                'message' => "Yuz rasmingizga mos kelmadi. Yorug' joyda kameraga to'g'ri qarab qayta urinib ko'ring.",
                'confidence' => round($similarity, 1),
            ], 422);
        }

        FaceIdService::logAttempt($log + [
            'result' => 'success',
            'confidence' => round($similarity / 100, 4),
            'snapshot' => $data['snapshot'],
        ]);

        return response()->json(['redirect' => $this->enterAttempt($request, $fanTesti, $student)]);
    }

    public function take(FanTesti $fanTesti, FanTestiAttempt $attempt)
    {
        $this->assertAttemptBelongs($fanTesti, $attempt);
        if (!$this->ownsAttempt($attempt)) {
            return $this->denyAttempt($fanTesti);
        }

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
        if (!$this->ownsAttempt($attempt)) {
            return $this->denyAttempt($fanTesti);
        }

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

    /** Natijani faqat yuz tekshiruvidan o'tib testni topshirgan talaba ko'radi. */
    public function result(FanTesti $fanTesti, FanTestiAttempt $attempt)
    {
        $this->assertAttemptBelongs($fanTesti, $attempt);
        if (!$this->ownsAttempt($attempt)) {
            return $this->denyAttempt($fanTesti);
        }

        return view('kiosk.fan-testi.result', [
            'test' => $fanTesti->load('subject'),
            'attempt' => $attempt->load('answers'),
        ]);
    }

    /**
     * Testga kirish shartlari: test ochiq, talaba topildi, guruhi fanga
     * biriktirilgan, savol bor va yuz solishtirish uchun tasdiqlangan rasmi bor.
     */
    private function eligibleStudent(FanTesti $fanTesti, string $identifier): Student
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['student_id_number' => $message]);

        if (!$fanTesti->is_active || !$fanTesti->curriculum_subject_id) {
            $fail('Bu test hozir yopiq.');
        }

        $student = $this->findStudent($identifier);
        if (!$student) {
            $fail('Bunday ID raqamli talaba topilmadi. Raqamni tekshirib qayta kiriting.');
        }

        if (!$this->studentMayTake($fanTesti, $student)) {
            $fail('Bu test sizning guruhingiz uchun mo\'ljallanmagan.');
        }

        if ($this->activeQuestions($fanTesti)->isEmpty()) {
            $fail('Bu test to\'plamida hali savol yo\'q.');
        }

        if (!FaceIdService::isArcFaceEnabled()) {
            $fail("Yuz tekshiruvi tizimda o'chirilgan. O'qituvchiga murojaat qiling.");
        }

        if (!FaceIdService::hasApprovedPhoto($student)) {
            $fail("Rasmingiz hali tasdiqlanmagan, shuning uchun yuzingizni tekshirib bo'lmaydi. Tutoringizga murojaat qiling.");
        }

        return $student;
    }

    /** Surat talabaning tasdiqlangan rasmi bilan o'xshashligi (%), xizmat javob bermasa null. */
    private function compareFace(Student $student, string $snapshot): ?float
    {
        $reference = FaceIdService::referenceImageFor($student);
        $live = $reference ? FaceIdService::saveTemporarySnapshot($snapshot) : null;
        if (!$live) {
            return null;
        }

        try {
            $result = FaceIdService::compareViaArcFace($live['url'], $reference);
        } finally {
            FaceIdService::deleteTemporarySnapshot($live['rel']);
        }

        return $result ? (float) $result['similarity_percent'] : null;
    }

    /**
     * Talabani urinishiga kiritadi (yangi, yarim qolgan yoki tugagan) va
     * urinishni shu brauzer sessiyasiga bog'laydi.
     */
    private function enterAttempt(Request $request, FanTesti $fanTesti, Student $student): string
    {
        $attempt = FanTestiAttempt::query()
            ->where('fan_testi_id', $fanTesti->id)
            ->where('student_id', $student->id)
            ->first();

        if (!$attempt) {
            $snapshot = $this->activeQuestions($fanTesti)->values();
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
        }

        $request->session()->put(self::SESSION_KEY, (int) $attempt->id);

        // Bir marta topshiriladi — tugatgan bo'lsa natijasini ko'rsatamiz.
        // Yarim qolgan urinish (brauzer yopilgan) o'sha joyidan davom etadi.
        if (!$attempt->isFinished() && $attempt->secondsLeft() <= 0) {
            $this->finalize($attempt, 'expired');
        }

        return $attempt->isFinished()
            ? route('kiosk.fan-testi.result', [$fanTesti, $attempt])
            : route('kiosk.fan-testi.take', [$fanTesti, $attempt]);
    }

    private function ownsAttempt(FanTestiAttempt $attempt): bool
    {
        return (int) session(self::SESSION_KEY) === (int) $attempt->id;
    }

    private function denyAttempt(FanTesti $fanTesti)
    {
        return redirect()
            ->route('kiosk.fan-testi.show', $fanTesti)
            ->withErrors(['student_id_number' => "Bu sahifani faqat testni topshirgan talabaning o'zi ko'radi. ID raqamingizni kiriting va yuz tekshiruvidan o'ting."]);
    }

    private function throttle(Request $request, string $prefix, int $perMinute): void
    {
        $key = $prefix . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            throw ValidationException::withMessages(['student_id_number' => "Juda ko'p urinish. Bir daqiqadan keyin qayta urinib ko'ring."]);
        }
        RateLimiter::hit($key, 60);
    }

    private function assertTableReady(): void
    {
        abort_unless(
            Schema::hasTable('fan_testi_attempts'),
            503,
            'Test natijalari jadvali migratsiyasi hali ishga tushirilmagan.'
        );
    }

    /**
     * Testni faqat to'plam fani biriktirilgan guruhlar talabalari ishlaydi.
     * Guruhlar aniqlanmasa (biriktirma ham, dars jadvali ham bo'sh) test
     * hech kimga ochilmaydi — begona guruhlar kirib qolmasligi uchun.
     */
    private function studentMayTake(FanTesti $fanTesti, Student $student): bool
    {
        $groupIds = FanTestiGroups::forSubject($fanTesti->subject);

        return $groupIds->isNotEmpty()
            && $groupIds->contains((int) $student->group_id);
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
        $rows = $this->buildAnswerRows($attempt->questions_snapshot ?? [], $answers);

        DB::transaction(function () use ($attempt, $rows) {
            $attempt->answers()->delete();

            foreach ($rows as $row) {
                FanTestiAttemptAnswer::create($row + ['attempt_id' => $attempt->id]);
            }
        });
    }

    /**
     * Javoblarni baholaydi va yozuvlar massivini qaytaradi (saqlamaydi).
     *
     * Saqlash ham, o'qituvchining ko'rib chiqishi ham shu yerdan o'tadi —
     * qoida ikki joyda ajralib ketmasligi uchun.
     */
    /** O'qituvchi sinovida ham shu baholash ishlatiladi. */
    public function gradePreview(array $snapshot, array $answers): array
    {
        return $this->buildAnswerRows($snapshot, $answers);
    }

    private function buildAnswerRows(array $snapshot, array $answers): array
    {
        $questions = collect($snapshot);
        $rows = [];

        {
            foreach ($questions as $index => $question) {
                $given = $answers[$index] ?? null;
                $points = max(1, (int) ($question['points'] ?? 1));
                $type = $question['type'] ?? 'single_choice';

                $row = [
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
                } elseif ($type === 'multiple_choice') {
                    // Ball faqat to'liq to'g'ri to'plamga beriladi: bitta ortiqcha
                    // yoki yetishmagan belgi javobni noto'g'ri qiladi.
                    $options = $question['options'] ?? [];
                    $correctIndexes = collect($options)
                        ->filter(fn ($option) => ($option['is_correct'] ?? false) === true)
                        ->keys()
                        ->sort()
                        ->values();

                    $row['correct_answer_text'] = $correctIndexes
                        ->map(fn ($index) => (string) ($options[$index]['text'] ?? ''))
                        ->implode(', ');

                    $chosen = collect(is_array($given) ? $given : [])
                        ->map(fn ($index) => (int) $index)
                        ->filter(fn ($index) => isset($options[$index]))
                        ->unique()
                        ->sort()
                        ->values();

                    if ($chosen->isNotEmpty()) {
                        $row['answered_at'] = now(self::TZ);
                        $row['selected_option_text'] = $chosen
                            ->map(fn ($index) => (string) ($options[$index]['text'] ?? ''))
                            ->implode(', ');
                        $row['answer_text'] = $chosen->implode(',');
                        $row['is_correct'] = $chosen->all() === $correctIndexes->all();
                    }
                } elseif ($type === 'matching') {
                    // Javob: chap ustun indeksi => tanlangan o'ng ustun indeksi.
                    $pairs = $question['pairs'] ?? [];
                    $row['correct_answer_text'] = collect($pairs)
                        ->map(fn ($pair) => ($pair['left'] ?? '') . ' → ' . ($pair['right'] ?? ''))
                        ->implode('; ');

                    $given = is_array($given) ? $given : [];
                    $matched = 0;
                    $shown = [];
                    foreach ($pairs as $pairIndex => $pair) {
                        $picked = $given[$pairIndex] ?? null;
                        if ($picked === null || $picked === '') {
                            continue;
                        }
                        $picked = (int) $picked;
                        $shown[] = ($pair['left'] ?? '') . ' → ' . (string) ($pairs[$picked]['right'] ?? '?');
                        if ($picked === (int) $pairIndex) {
                            $matched++;
                        }
                    }

                    if ($shown) {
                        $row['answered_at'] = now(self::TZ);
                        $row['selected_option_text'] = implode('; ', $shown);
                        $row['answer_text'] = json_encode($given, JSON_UNESCAPED_UNICODE);
                        $row['is_correct'] = $matched === count($pairs);
                    }
                } elseif ($type === 'ordering') {
                    // Javob: talaba tuzgan tartib — bosqich indekslari ketma-ketligi.
                    $steps = $question['steps'] ?? [];
                    $row['correct_answer_text'] = collect($steps)
                        ->map(fn ($step, $index) => ($index + 1) . '. ' . ($step['text'] ?? ''))
                        ->implode('; ');

                    $order = collect(is_array($given) ? $given : [])
                        ->map(fn ($index) => (int) $index)
                        ->filter(fn ($index) => isset($steps[$index]))
                        ->unique()
                        ->values();

                    if ($order->isNotEmpty()) {
                        $row['answered_at'] = now(self::TZ);
                        $row['selected_option_text'] = $order
                            ->map(fn ($index, $position) => ($position + 1) . '. ' . (string) ($steps[$index]['text'] ?? ''))
                            ->implode('; ');
                        $row['answer_text'] = $order->implode(',');
                        $row['is_correct'] = $order->count() === count($steps)
                            && $order->all() === range(0, count($steps) - 1);
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

                $rows[] = $row;
            }
        }

        return $rows;
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
