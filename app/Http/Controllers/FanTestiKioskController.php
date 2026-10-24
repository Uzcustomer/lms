<?php

namespace App\Http\Controllers;

use App\Models\FanTesti;
use App\Models\FanTestiAttempt;
use App\Models\FanTestiAttemptAnswer;
use App\Models\FanTestiEntryRequest;
use App\Models\Setting;
use App\Models\Student;
use App\Services\FaceIdService;
use App\Services\FanTestiAttempts;
use App\Services\FanTestiGroups;
use App\Services\TestDeviceToken;
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
    public const SESSION_KEY = 'fan_testi_attempt_id';

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
            ] + $this->follow('closed'));
        }

        return view('kiosk.fan-testi.start', [
            'test' => $fanTesti->load('subject'),
            'liveness' => FaceIdService::getLivenessConfig(),
            'retake' => $this->isRetakeLaunch($fanTesti),
        ] + $this->follow('start'));
    }

    /**
     * 1-bosqich: ID bo'yicha talabani va uning tasdiqlangan rasmi borligini
     * tekshiradi. Ism ham, rasm ham qaytarilmaydi — ID terib talabalarning
     * shaxsi va rasmlarini yig'ib bo'lmasin. Solishtirish serverda jim bo'ladi.
     */
    public function check(Request $request, FanTesti $fanTesti)
    {
        $this->assertTableReady();
        $this->throttle($request, 'fan_testi_check', 20, 600);

        $data = $request->validate([
            'student_id_number' => ['required', 'string', 'max:64'],
        ], [], ['student_id_number' => 'Talaba ID']);

        // O'qituvchi shu kompyuterga yuz tekshiruvisiz kirish ruxsatini bergan bo'lsa
        $bypass = $this->faceBypassDevice($fanTesti) !== null;
        $student = $this->eligibleStudent($fanTesti, $data['student_id_number'], $bypass);

        // Umumiy ochilgan test (ro'yxatdagi kompyuter emas): so'rov o'qituvchiga
        // tushadi, talaba "ruxsat kutilmoqda" ekranida kutadi.
        if ($this->needsTeacherApproval($fanTesti)) {
            $entry = FanTestiEntryRequest::openFor($fanTesti, $student, $request->ip());

            return response()->json([
                'photo_found' => true,
                'face_bypass' => false,
                'entry' => $entry->isUsable() ? 'approved' : 'pending',
                'entry_token' => $entry->token,
            ]);
        }

        return response()->json(['photo_found' => true, 'face_bypass' => $bypass]);
    }

    /** Kutish ekrani so'raydi: o'qituvchi ruxsat berdimi. */
    public function entryStatus(FanTesti $fanTesti, string $token)
    {
        $entry = FanTestiEntryRequest::query()
            ->where('fan_testi_id', $fanTesti->id)
            ->where('token', $token)
            ->first();

        if (!$entry) {
            return response()->json(['status' => 'gone', 'message' => "So'rov topilmadi. ID raqamingizni qayta kiriting."], 404);
        }
        if ($entry->used_at) {
            return response()->json(['status' => 'used', 'message' => "Bu ruxsat ishlatib bo'lingan. ID raqamingizni qayta kiriting."]);
        }
        if ($entry->status === 'rejected') {
            return response()->json(['status' => 'rejected', 'message' => "O'qituvchi kirishni rad etdi" . ($entry->decided_by ? " ({$entry->decided_by})" : '') . '.']);
        }
        if ($entry->status === 'approved' && !$entry->isUsable()) {
            return response()->json(['status' => 'expired', 'message' => "Ruxsat muddati o'tdi. ID raqamingizni qayta kiriting."]);
        }

        return response()->json(['status' => $entry->status]);
    }

    /**
     * Umumiy rejimdagi test sahifasi har bir necha soniyada yuboradi: nechta
     * savolga javob berilgan. O'qituvchi "To'xtatish" bosgan bo'lsa sahifaga
     * aytiladi — u belgilangan javoblarni topshiradi (javoblar brauzerda turadi,
     * serverda to'xtatib bo'lmaydi).
     */
    public function progress(Request $request, FanTesti $fanTesti, FanTestiAttempt $attempt)
    {
        $this->assertAttemptBelongs($fanTesti, $attempt);
        if (!$this->ownsAttempt($attempt)) {
            return response()->json(['message' => 'Sessiya topilmadi.'], 403);
        }
        if (!Schema::hasColumn('fan_testi_entry_requests', 'progress_at')) {
            return response()->json(['ok' => true]);
        }

        $entry = FanTestiEntryRequest::where('attempt_id', $attempt->id)->latest('id')->first();
        if (!$entry) {
            return response()->json(['ok' => true]);
        }

        $entry->forceFill([
            'progress_answered' => min(65535, max(0, (int) $request->input('answered', 0))),
            'progress_at' => now(),
        ])->save();

        return response()->json([
            'ok' => true,
            'stop' => $entry->stop_requested_at !== null && !$attempt->isFinished(),
        ]);
    }

    /** Shu kompyuterga test "Qayta topshirish testlari" tabidan (qayta topshirish rejimida) berilgan. */
    private function isRetakeLaunch(FanTesti $fanTesti): bool
    {
        if (!Schema::hasColumn('test_kiosk_devices', 'assigned_mode')) {
            return false;
        }
        $device = TestDeviceToken::resolve(request());

        return $device
            && (int) $device->assigned_fan_testi_id === (int) $fanTesti->id
            && $device->isRetakeMode();
    }

    /**
     * Umumiy ochilgan testda, ro'yxatdagi (shu testga berilgan) kompyuterdan
     * bo'lmasa — o'qituvchi ruxsati kerak.
     */
    private function needsTeacherApproval(FanTesti $fanTesti): bool
    {
        if (!$fanTesti->isPublicOpen() || !Schema::hasTable('fan_testi_entry_requests')) {
            return false;
        }

        $device = Schema::hasColumn('test_kiosk_devices', 'assigned_fan_testi_id')
            ? TestDeviceToken::resolve(request())
            : null;

        return !($device && (int) $device->assigned_fan_testi_id === (int) $fanTesti->id);
    }

    /** Ruxsat berilgan so'rov (shu brauzer belgisi va shu talaba uchun), bo'lmasa 422. */
    private function approvedEntry(Request $request, FanTesti $fanTesti, Student $student): FanTestiEntryRequest
    {
        $token = (string) $request->input('entry_token', '');
        $entry = $token !== ''
            ? FanTestiEntryRequest::query()
                ->where('fan_testi_id', $fanTesti->id)
                ->where('student_id', $student->id)
                ->where('token', $token)
                ->first()
            : null;

        if (!$entry || !$entry->isUsable()) {
            throw ValidationException::withMessages(['student_id_number' => "O'qituvchi ruxsati yo'q yoki muddati o'tgan. ID raqamingizni qayta kiriting va ruxsatni kuting."]);
        }

        return $entry;
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
        $this->throttle($request, 'fan_testi_start', 10, 300);

        if ($request->boolean('face_bypass')) {
            return $this->startWithFaceBypass($request, $fanTesti);
        }

        $data = $request->validate([
            'student_id_number' => ['required', 'string', 'max:64'],
            'snapshot' => ['required', 'string', 'max:500000'],
            'liveness_passed' => ['required', 'boolean'],
        ], [], ['student_id_number' => 'Talaba ID', 'snapshot' => 'Yuz surati']);

        $student = $this->eligibleStudent($fanTesti, $data['student_id_number']);
        $entry = $this->needsTeacherApproval($fanTesti) ? $this->approvedEntry($request, $fanTesti, $student) : null;

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

        $redirect = $this->enterAttempt($request, $fanTesti, $student);

        // Ruxsat bir marta ishlaydi — o'qituvchi oynasida "Testga kirdi" bo'ladi
        if ($entry) {
            $entry->forceFill([
                'used_at' => now(),
                'attempt_id' => (int) $request->session()->get(self::SESSION_KEY) ?: null,
            ])->save();
        }

        return response()->json(['redirect' => $redirect]);
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

        // Ikkinchi marta chalg'ib yakunlangan, lekin javoblar yetib kelmagan
        // (sahifa yangilangan) — saqlangan holicha topshiriladi.
        if ($this->hasLockColumns() && (int) $attempt->lock_count >= 2) {
            $this->finalize($attempt, 'submitted');

            return redirect()->route('kiosk.fan-testi.result', [$fanTesti, $attempt]);
        }

        // Qulflangan test uchun vaqt o'qituvchi ochib bergach uzaytiriladi —
        // qulf paytida muddat o'tib ketgani testni yakunlamaydi.
        if (!$attempt->isLocked() && $attempt->secondsLeft() <= 0) {
            $this->finalize($attempt, 'expired');

            return redirect()->route('kiosk.fan-testi.result', [$fanTesti, $attempt]);
        }

        return view('kiosk.fan-testi.take', [
            'test' => $fanTesti->load('subject'),
            'attempt' => $attempt,
            'questions' => collect($attempt->questions_snapshot ?? []),
            'secondsLeft' => $attempt->secondsLeft(),
            'locked' => $this->hasLockColumns() && $attempt->isLocked(),
            'awayLimit' => FanTestiAttempt::AWAY_LIMIT,
            // Yuz tekshiruvisiz (o'qituvchi ruxsati) — test davomida jim yuz solishtirilmaydi
            'faceBypass' => !empty($attempt->face_bypass_by),
            // Umumiy rejim: jarayon o'qituvchining "Kirish so'rovlari" oynasiga yuboriladi
            'publicEntry' => $this->needsTeacherApproval($fanTesti),
            // Sahifa qayta ochilganda belgilangan javoblar tiklanadi
            'draft' => Schema::hasColumn('fan_testi_attempts', 'draft_answers') ? ($attempt->draft_answers ?? []) : [],
            'monitor' => $this->monitorConfig(),
        ] + $this->follow('take'));
    }

    /**
     * Test davomidagi kuzatuv chegaralari. Sozlamalar jadvalidan o'zgartirish
     * mumkin (kod o'zgarmaydi): fan_test_away_seconds, fan_test_noface_seconds,
     * fan_test_yaw_limit, fan_test_other_face_ratio, fan_test_mismatches_to_pause,
     * fan_test_detector_score, fan_test_tick_seconds.
     *
     * 2026-10 da yumshatildi: sinfda parda tez-tez tushib turardi (o'qish uchun
     * bosh egish, yon tomonga qarash, yorug'lik).
     */
    private function monitorConfig(): array
    {
        $num = fn (string $key, float $default) => (float) (Setting::get($key, $default) ?: $default);

        return [
            'tickMs' => (int) ($num('fan_test_tick_seconds', 5) * 1000),
            // Boshqa tomonga qarash / yonida odam — shuncha soniya uzluksiz bo'lsa to'xtaydi
            'awayMs' => (int) ($num('fan_test_away_seconds', 10) * 1000),
            // Yuz umuman ko'rinmasa (bosh egib o'qish, yorug'lik) — bundan ham uzoqroq kutiladi
            'noFaceMs' => (int) ($num('fan_test_noface_seconds', 10) * 1000),
            'backMs' => 500,
            'yawLimit' => $num('fan_test_yaw_limit', 0.5),
            'otherFaceRatio' => $num('fan_test_other_face_ratio', 0.5),
            'mismatchesToPause' => (int) $num('fan_test_mismatches_to_pause', 3),
            'recheckMs' => 8000,
            'detectorScore' => $num('fan_test_detector_score', 0.35),
        ];
    }

    /**
     * Javoblar qoralamasi: brauzer belgilangan maydonlarni [nom, qiymat]
     * juftliklari bilan yuboradi. Sahifa yopilsa qayta kirganda tiklanadi;
     * javoblar kelmay test yakunlansa shu qoralama baholanadi.
     */
    public function saveDraft(Request $request, FanTesti $fanTesti, FanTestiAttempt $attempt)
    {
        $this->assertAttemptBelongs($fanTesti, $attempt);
        if (!$this->ownsAttempt($attempt)) {
            return response()->json(['message' => 'Sessiya topilmadi.'], 403);
        }
        if ($attempt->isFinished()) {
            return response()->json(['finished' => true]);
        }
        if (!Schema::hasColumn('fan_testi_attempts', 'draft_answers')) {
            return response()->json(['ok' => false]);
        }

        $data = $request->validate([
            'fields' => ['present', 'array', 'max:3000'],
            'fields.*' => ['array', 'size:2'],
            'fields.*.0' => ['required', 'string', 'max:80', 'regex:/^(answers\[\d+\](\[\d*\])?|order:\d+:\d+)$/'],
            'fields.*.1' => ['nullable', 'string', 'max:2000'],
        ]);

        $attempt->forceFill([
            'draft_answers' => array_values($data['fields']),
            'draft_saved_at' => now(),
        ])->save();

        return response()->json(['ok' => true]);
    }

    /** Qoralamani submit() dagi answers[] ko'rinishiga keltiradi. */
    private function draftAnswers(FanTestiAttempt $attempt): array
    {
        $pairs = [];
        $orders = [];
        foreach ((array) ($attempt->draft_answers ?? []) as $field) {
            if (!is_array($field) || count($field) < 2 || !is_string($field[0])) {
                continue;
            }
            [$name, $value] = $field;
            if (str_starts_with($name, 'order:')) {
                // order:{savol}:{bosqich} = pozitsiya (1..n)
                $parts = explode(':', $name);
                $pos = (int) $value;
                if (count($parts) === 3 && $pos > 0) {
                    $orders[(int) $parts[1]][$pos - 1] = (string) $parts[2];
                }
                continue;
            }
            if (str_starts_with($name, 'answers[')) {
                $pairs[] = $name . '=' . rawurlencode((string) $value);
            }
        }
        // Ketma-ketlik: formadagi kabi pozitsiya tartibida ro'yxat, bo'sh joylar ''
        foreach ($orders as $q => $steps) {
            for ($i = 0, $n = max(array_keys($steps)) + 1; $i < $n; $i++) {
                $pairs[] = "answers[{$q}][]=" . rawurlencode($steps[$i] ?? '');
            }
        }

        parse_str(implode('&', $pairs), $parsed);

        return is_array($parsed['answers'] ?? null) ? $parsed['answers'] : [];
    }

    /** O'qituvchi oynasi uchun: sahifa bilan aloqa yo'q urinishni qoralama bilan yakunlash. */
    public function finalizeFromDraft(FanTestiAttempt $attempt, string $status = 'submitted'): void
    {
        if (!$attempt->isFinished()) {
            $this->finalize($attempt, $status);
        }
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

        // O'qituvchi testni shu kompyuterda qayta boshlagan: eski sahifadan
        // kelgan javoblar yangi urinishga yozilmasin.
        if ($request->filled('attempt_started')
            && $request->input('attempt_started') !== (string) $attempt->started_at?->timestamp) {
            return redirect()->route('kiosk.fan-testi.take', [$fanTesti, $attempt]);
        }

        $data = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable'],
            // 'tab' — talaba test oynasidan boshqa oynaga o'tgani uchun sahifa testni o'zi yakunladi
            'ended_reason' => ['nullable', 'in:tab'],
        ]);

        if (!empty($data['ended_reason']) && $this->hasLockColumns() && empty($attempt->ended_reason)) {
            $attempt->forceFill(['ended_reason' => $data['ended_reason']])->save();
        }

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
        ] + $this->follow('result'));
    }

    /**
     * Ro'yxatdagi sinf kompyuterida sahifa o'qituvchining "Boshlash/To'xtatish"
     * buyrug'ini kuzatadi (layout dagi skript): test to'xtatilsa yoki boshqa
     * test berilsa — boshlash/yopiq sahifasi darhol, natija sahifasi biroz
     * kutib kutish ekraniga o'tadi; test ishlanayotgan bo'lsa javoblar avval
     * avtomatik topshiriladi.
     */
    /**
     * Shu kompyuterda o'qituvchi bergan bir martalik "yuz tekshiruvisiz kirish"
     * ruxsati (yuzi tanilmayotgan talaba uchun). Kompyuter shu testga berilgan
     * va ruxsat muddati o'tmagan bo'lishi kerak.
     */
    private function faceBypassDevice(FanTesti $fanTesti): ?\App\Models\TestKioskDevice
    {
        if (!Schema::hasColumn('test_kiosk_devices', 'face_bypass_at')) {
            return null;
        }
        $device = TestDeviceToken::resolve(request());

        return $device
            && (int) $device->assigned_fan_testi_id === (int) $fanTesti->id
            && $device->hasFaceBypass()
            ? $device
            : null;
    }

    /**
     * O'qituvchi ruxsati bilan faqat ID orqali kirish. Ruxsat bir marta
     * ishlatiladi: shu so'rovda atomar o'chiriladi — keyingi talaba yana yuz
     * tekshiruvidan o'tadi. Kim ruxsat bergani urinishga va Face ID logiga yoziladi.
     */
    private function startWithFaceBypass(Request $request, FanTesti $fanTesti)
    {
        $data = $request->validate([
            'student_id_number' => ['required', 'string', 'max:64'],
        ], [], ['student_id_number' => 'Talaba ID']);

        $device = $this->faceBypassDevice($fanTesti);
        if (!$device) {
            return response()->json(['message' => "Yuz tekshiruvisiz kirish ruxsati yo'q yoki muddati tugagan. O'qituvchiga murojaat qiling."], 422);
        }
        $grantedBy = (string) $device->face_bypass_by;

        $student = $this->eligibleStudent($fanTesti, $data['student_id_number'], true);

        // Bir marta: ikki kompyuter/so'rov bir vaqtda ishlata olmasin
        $consumed = \App\Models\TestKioskDevice::whereKey($device->id)
            ->whereNotNull('face_bypass_at')
            ->update(['face_bypass_at' => null, 'face_bypass_by' => null]);
        if (!$consumed) {
            return response()->json(['message' => "Bu ruxsat allaqachon ishlatilgan. O'qituvchiga murojaat qiling."], 422);
        }

        FaceIdService::logAttempt([
            'attempt_type' => 'fan_test',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'student_id' => $student->id,
            'student_id_number' => $student->student_id_number,
            'target_student_id' => $student->id,
            'target_student_id_number' => $student->student_id_number,
            'result' => 'success',
            'failure_reason' => "Fan testi: o'qituvchi ruxsati bilan yuz tekshiruvisiz kirildi ({$grantedBy}, test #{$fanTesti->id}, kompyuter: {$device->name})",
        ]);

        $redirect = $this->enterAttempt($request, $fanTesti, $student);
        $attemptId = (int) $request->session()->get(self::SESSION_KEY);
        if ($attemptId && Schema::hasColumn('fan_testi_attempts', 'face_bypass_by')) {
            FanTestiAttempt::whereKey($attemptId)->update(['face_bypass_by' => mb_substr($grantedBy ?: "O'qituvchi", 0, 120)]);
        }

        return response()->json(['redirect' => $redirect]);
    }

    private function follow(string $mode): array
    {
        $device = Schema::hasColumn('test_kiosk_devices', 'assigned_fan_testi_id')
            ? TestDeviceToken::resolve(request())
            : null;

        return ['followDevice' => $device !== null, 'followMode' => $mode];
    }

    /**
     * Test davomidagi jim kuzatuv: brauzer vaqti-vaqti bilan surat yuboradi
     * (talaba monitorga to'g'ri qarab turganda) va monitordan chalg'ish
     * holatlari sonini xabar qiladi. Surat talabaning tasdiqlangan rasmi bilan
     * solishtiriladi; mos kelmasa brauzer testni to'xtatib turadi.
     *
     * Xizmat javob bermasa match = null qaytadi va test to'xtatilmaydi —
     * ArcFace nosozligi butun sinfning testini bloklamasin.
     */
    public function monitorFace(Request $request, FanTesti $fanTesti, FanTestiAttempt $attempt)
    {
        $this->assertAttemptBelongs($fanTesti, $attempt);
        if (!$this->ownsAttempt($attempt)) {
            return response()->json(['message' => 'Sessiya topilmadi.'], 403);
        }
        if ($attempt->isFinished()) {
            return response()->json(['finished' => true]);
        }

        $data = $request->validate([
            'snapshot' => ['nullable', 'string', 'max:500000'],
            'away' => ['nullable', 'integer', 'min:0', 'max:50'],
            'ping' => ['nullable', 'boolean'],
        ]);

        // Cheklov faqat surat solishtirishga (og'ir so'rov); chalg'ish xabari va
        // qulf holatini so'rash yengil — qulf paytida sahifa har 4 soniyada so'raydi.
        if (!empty($data['snapshot'])) {
            $key = 'fan_testi_face:' . $attempt->id;
            if (RateLimiter::tooManyAttempts($key, 12)) {
                return response()->json(['match' => null] + $this->lockState($attempt));
            }
            RateLimiter::hit($key, 60);
        }

        $updates = [];
        if (!empty($data['away']) && $this->hasMonitorColumns()) {
            $updates['away_count'] = (int) $attempt->away_count + (int) $data['away'];

            // Qulflangan paytda chalg'ish qayta sanalmaydi
            if ($this->hasLockColumns() && !$attempt->isLocked()) {
                $streak = (int) $attempt->away_streak + (int) $data['away'];
                $updates['away_streak'] = $streak;
                if ($streak >= FanTestiAttempt::AWAY_LIMIT) {
                    $lockCount = (int) $attempt->lock_count + 1;
                    $updates['lock_count'] = min(2, $lockCount);
                    $updates['locked_at'] = now(self::TZ);
                    if ($lockCount >= 2) {
                        $updates['ended_reason'] = 'away';
                    }
                }
            }
        }

        $match = null;
        $similarity = null;
        if (!empty($data['snapshot']) && empty($attempt->face_bypass_by)) {
            $student = Student::find($attempt->student_id);
            $similarity = $student ? $this->compareFace($student, $data['snapshot']) : null;

            if ($similarity !== null) {
                $threshold = FaceIdService::getArcFaceThreshold();
                $match = $similarity >= $threshold;

                if ($this->hasMonitorColumns()) {
                    $updates['face_checks'] = (int) $attempt->face_checks + 1;
                    if (!$match) {
                        $updates['face_mismatches'] = (int) $attempt->face_mismatches + 1;
                    }
                }

                // Mos kelmaganlar logga tushadi — kim o'tirganini keyin ko'rish uchun.
                if (!$match) {
                    FaceIdService::logAttempt([
                        'attempt_type' => 'fan_test_monitor',
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'student_id' => $attempt->student_id,
                        'student_id_number' => $attempt->student_id_number,
                        'target_student_id' => $attempt->student_id,
                        'target_student_id_number' => $attempt->student_id_number,
                        'result' => 'failed',
                        'confidence' => round($similarity / 100, 4),
                        'failure_reason' => "Fan testi davomida yuz mos kelmadi ({$similarity}% < {$threshold}%, test #{$fanTesti->id}, urinish #{$attempt->id})",
                        'snapshot' => $data['snapshot'],
                    ]);
                }
            }
        }

        if ($updates) {
            $attempt->update($updates);
        }

        return response()->json([
            'match' => $match,
            'similarity' => $similarity !== null ? round($similarity, 1) : null,
        ] + $this->lockState($attempt));
    }

    /**
     * Sahifa uchun qulf holati: locked — o'qituvchi ochib berishini kutadi;
     * final — ikkinchi marta chalg'idi, javoblar topshirilishi kerak.
     */
    private function lockState(FanTestiAttempt $attempt): array
    {
        if (!$this->hasLockColumns()) {
            return [];
        }

        return [
            'locked' => $attempt->locked_at !== null,
            'final' => (int) $attempt->lock_count >= 2,
            'away_streak' => (int) $attempt->away_streak,
            'away_limit' => FanTestiAttempt::AWAY_LIMIT,
            'seconds_left' => $attempt->secondsLeft(),
        ];
    }

    private function hasLockColumns(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('fan_testi_attempts', 'lock_count');
    }

    private function hasMonitorColumns(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('fan_testi_attempts', 'face_checks');
    }

    /**
     * Testga kirish shartlari: test ochiq, talaba topildi, guruhi fanga
     * biriktirilgan, savol bor va yuz solishtirish uchun tasdiqlangan rasmi bor.
     */
    private function eligibleStudent(FanTesti $fanTesti, string $identifier, bool $skipFace = false): Student
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

        // Har bir talaba testni bir marta topshiradi. Yarim qolgan urinish
        // (kompyuter o'chib qolgan) esa yuz tekshiruvidan keyin davom etadi.
        // O'qituvchi jurnalda "Qayta topshirish" bergan bo'lsa — yangi urinish ochiladi.
        $latest = FanTestiAttempt::latestFor($fanTesti->id, $student->id);
        if ($latest && $latest->isFinished() && !$latest->retakeAllowed()) {
            if (!$this->isRetakeLaunch($fanTesti)) {
                $fail("Bu talaba testni allaqachon topshirgan. Qayta topshirish uchun o'qituvchi jurnalda ruxsat berishi kerak.");
            }
            // Qayta topshirish rejimi: o'tolmagan (yoki qatnashmagan) talaba bir marta qayta ishlaydi.
            // 60% dan past baho olgan ham kiradi — to'plamning o'tish foizi pastroq qo'yilgan bo'lsa ham.
            if ($latest->is_passed && (float) $latest->percent >= 60) {
                $shown = rtrim(rtrim(number_format((float) $latest->percent, 1, '.', ''), '0'), '.');
                $fail("Bu talaba testdan {$shown}% bilan o'tgan — qayta topshirish shart emas.");
            }
            if ($latest->is_retake) {
                $fail("Bu talaba qayta topshirishni allaqachon ishlagan. Yana topshirishi uchun o'qituvchi jurnalda ruxsat berishi kerak.");
            }
        }

        if ($this->activeQuestions($fanTesti)->isEmpty()) {
            $fail('Bu test to\'plamida hali savol yo\'q.');
        }

        if (!$skipFace && !FaceIdService::isArcFaceEnabled()) {
            $fail("Yuz tekshiruvi tizimda o'chirilgan. O'qituvchiga murojaat qiling.");
        }

        if (!$skipFace && !FaceIdService::hasApprovedPhoto($student)) {
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
        $attempt = FanTestiAttempt::latestFor($fanTesti->id, $student->id);
        $retakeLaunch = $this->isRetakeLaunch($fanTesti);

        // Yo'q bo'lsa — 1-urinish; tugagan va qayta topshirishga ruxsat berilgan
        // (yoki test qayta topshirish rejimida ochilgan) bo'lsa — keyingi raqamli
        // yangi urinish (oldingisi jurnalda qoladi).
        if (!$attempt || $attempt->retakeAllowed() || ($retakeLaunch && $attempt->isFinished())) {
            $attempt = FanTestiAttempt::create(FanTestiAttempts::freshFields($fanTesti, FanTestiAttempts::questionSnapshot($fanTesti)) + [
                'fan_testi_id' => $fanTesti->id,
                'student_id' => $student->id,
                'attempt_no' => $attempt ? (int) $attempt->attempt_no + 1 : 1,
            ] + (Schema::hasColumn('fan_testi_attempts', 'is_retake') ? [
                // Faqat "Qayta topshirish testlari"dan ochilgan test orqali — jurnalda alohida tab.
                // Jurnaldagi "Qayta topshirish" tugmasi bilan ochilgan 2-urinish asosiy tabda qoladi.
                'is_retake' => $retakeLaunch,
            ] : []) + [
                'student_hemis_id' => $student->hemis_id,
                'student_name' => $student->full_name,
                'student_id_number' => $student->student_id_number,
                'group_id' => $student->group_id,
                'group_name' => $student->group_name,
                'faculty_name' => $student->department_name,
                'specialty_name' => $student->specialty_name,
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

    /**
     * Urinishlar cheklovi asosan talaba ID si bo'yicha: sinf kompyuterlari
     * serverga bitta IP orqali chiqadi, IP bo'yicha qattiq cheklov butun
     * sinfni bloklab qo'yardi. IP uchun faqat katta umumiy chegara qoladi.
     */
    private function throttle(Request $request, string $prefix, int $perStudent, int $perIp): void
    {
        $keys = [
            [$prefix . ':ip:' . $request->ip(), $perIp],
            [$prefix . ':id:' . mb_strtolower(trim((string) $request->input('student_id_number'))), $perStudent],
        ];

        foreach ($keys as [$key, $limit]) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                throw ValidationException::withMessages(['student_id_number' => "Juda ko'p urinish. Bir daqiqadan keyin qayta urinib ko'ring."]);
            }
        }
        foreach ($keys as [$key]) {
            RateLimiter::hit($key, 60);
        }
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
        // Javoblar kelmagan (sahifa yopilgan, vaqt tugagan, o'qituvchi to'xtatgan) —
        // oxirgi saqlangan qoralama baholanadi, talabaning ishi yo'qolmaydi
        if ($attempt->answers()->doesntExist() && !empty($attempt->draft_answers)) {
            $this->gradeAnswers($attempt, $this->draftAnswers($attempt));
        }

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

        // Qayta topshirish testi — natija jurnaldagi mavzuga mos NB katagiga otrabotka bo'lib tushadi
        if ($attempt->is_retake) {
            \App\Services\FanTestiJournalSync::sync($attempt->fresh());
        }
    }
}
