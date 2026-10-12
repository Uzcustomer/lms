<?php

namespace App\Http\Controllers;

use App\Models\FanTesti;
use App\Models\FanTestiAttempt;
use App\Models\TestKioskDevice;
use App\Services\TestDeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Sinf kompyuterlarini fan testi uchun ro'yxatdan o'tkazish va kutish ekrani.
 *
 * Admin har bir sinf kompyuterida LMS ga kirib /test-kompyuter sahifasini
 * ochadi, xona va kompyuter nomini yozib tugmani bosadi — brauzerga maxfiy
 * belgi yoziladi. So'ng kompyuterda /test-kompyuter/kutish ochib qo'yiladi:
 * o'qituvchi shu kompyuterga test berganda u testni o'zi ochadi.
 */
class TestDeviceController extends Controller
{
    /** Kompyuter sahifasi bosqichlari: kutish, test ID, yuz tekshiruvi, test, natija, yopiq. */
    private const STAGES = ['wait', 'start', 'face', 'take', 'result', 'closed'];

    public function index(Request $request)
    {
        if (!$this->isAdmin()) {
            return view('kiosk.fan-testi.device-guest');
        }

        $tableReady = Schema::hasTable('test_kiosk_devices');
        $hasRooms = $tableReady && Schema::hasColumn('test_kiosk_devices', 'room');
        $current = $tableReady ? TestDeviceToken::deviceFor(TestDeviceToken::fromRequest($request)) : null;

        $devices = $tableReady
            ? TestKioskDevice::query()
                // Bekor qilinganlar ro'yxatda ko'rsatilmaydi
                ->whereNull('revoked_at')
                ->when($hasRooms, fn ($q) => $q->with('assignedTest:id,name'))
                ->when($hasRooms, fn ($q) => $q->orderByRaw("COALESCE(room, '') = ''")->orderBy('room'))
                ->orderBy('name')
                ->get()
            : collect();

        return view('admin.test-devices.index', [
            'tableReady' => $tableReady,
            'hasRooms' => $hasRooms,
            'current' => $current && $current->isActive() ? $current : null,
            'devices' => $devices,
            'rooms' => $devices->groupBy(fn (TestKioskDevice $d) => $d->roomLabel()),
            'enforced' => (bool) config('services.fan_test.require_device'),
        ]);
    }

    /** Shu brauzerni test kompyuteri sifatida belgilaydi (yoki xona/nomini yangilaydi). */
    public function store(Request $request)
    {
        abort_unless($this->isAdmin(), 403);

        $hasRooms = Schema::hasColumn('test_kiosk_devices', 'room');
        $data = $request->validate([
            'room' => $hasRooms ? ['required', 'string', 'max:60'] : ['nullable'],
            'name' => ['required', 'string', 'max:120'],
        ], [], ['room' => 'Xona', 'name' => 'Kompyuter nomi']);

        $user = $this->currentUser();
        $token = TestDeviceToken::fromRequest($request);
        $device = TestDeviceToken::deviceFor($token);
        // Xona raqami saqlanadi: "206-xona" yozilsa ham "206" ("-xona" ko'rsatishda qo'shiladi)
        $room = trim((string) preg_replace('/[\s\-]*xona$/iu', '', trim((string) ($data['room'] ?? ''))));
        $fields = ['name' => trim($data['name'])] + ($hasRooms ? ['room' => $room !== '' ? $room : trim((string) $data['room'])] : []);

        if ($device && $device->isActive()) {
            $device->update($fields);
            $message = "Kompyuter ma'lumotlari yangilandi: " . $this->label($device);
        } else {
            $device = new TestKioskDevice($fields + [
                'registered_by_user_id' => $user?->id,
                'registered_by_name' => $user?->name ?? $user?->full_name ?? $user?->short_name ?? $user?->email,
            ]);
            $message = "Kompyuter ro'yxatdan o'tdi: " . $this->label($device);
        }

        $newToken = TestDeviceToken::rotate($device);

        return redirect()
            ->route('test-devices.index')
            ->with('success', $message)
            ->withCookie(TestDeviceToken::cookie($request, $newToken));
    }

    /** Shu brauzerning belgisini o'chiradi. */
    public function forget(Request $request)
    {
        abort_unless($this->isAdmin(), 403);

        $device = TestDeviceToken::deviceFor(TestDeviceToken::fromRequest($request));
        if ($device && $device->isActive()) {
            $device->update(['revoked_at' => now(), 'revoked_reason' => 'Shu kompyuterdan bekor qilindi']);
        }

        return redirect()
            ->route('test-devices.index')
            ->with('success', "Bu kompyuter ro'yxatdan chiqarildi.")
            ->withCookie(TestDeviceToken::forget());
    }

    /** Ro'yxatdagi istalgan kompyuterni bekor qiladi. */
    public function revoke(TestKioskDevice $device)
    {
        abort_unless($this->isAdmin(), 403);

        if ($device->isActive()) {
            $device->update(['revoked_at' => now(), 'revoked_reason' => 'Admin bekor qildi']);
        }

        return redirect()
            ->route('test-devices.index')
            ->with('success', $this->label($device) . ' bekor qilindi.');
    }

    /** Kompyuterni berilgan testdan bo'shatadi (o'qituvchi to'xtatmay qoldirgan bo'lsa). */
    public function release(TestKioskDevice $device)
    {
        abort_unless($this->isAdmin(), 403);

        $device->update(['assigned_fan_testi_id' => null, 'assigned_at' => null, 'assigned_by_name' => null]);

        return redirect()
            ->route('test-devices.index')
            ->with('success', $this->label($device) . ' testdan bo\'shatildi.');
    }

    /** Kutish ekrani: o'qituvchi test berganda kompyuter uni o'zi ochadi. */
    public function wait(Request $request)
    {
        $device = Schema::hasTable('test_kiosk_devices') ? TestDeviceToken::resolve($request) : null;

        return view('kiosk.fan-testi.wait', ['device' => $device]);
    }

    /** Kutish ekrani va test sahifasi so'raydi: shu kompyuterga qaysi test berilgan. */
    public function status(Request $request)
    {
        $device = Schema::hasTable('test_kiosk_devices') ? TestDeviceToken::resolve($request) : null;
        if (!$device) {
            return response()->json(['registered' => false]);
        }

        $goUrl = null;
        if (Schema::hasColumn('test_kiosk_devices', 'kiosk_stage')) {
            $goUrl = $this->recordStage($request, $device);
        } elseif (!$device->last_seen_at || $device->last_seen_at->lt(now()->subSeconds(10))) {
            $device->forceFill(['last_seen_at' => now()])->save();
        }

        $testUrl = null;
        if ($device->assigned_fan_testi_id) {
            $test = FanTesti::find($device->assigned_fan_testi_id);
            if ($test && $test->is_active && $test->curriculum_subject_id) {
                $testUrl = route('kiosk.fan-testi.show', $test);
            }
        }

        return response()->json([
            'registered' => true,
            'test_url' => $testUrl,
            'wait_url' => route('test-devices.wait'),
            'go_url' => $goUrl,
        ]);
    }

    /**
     * Kompyuter qaysi bosqichda ekanini yozadi (o'qituvchining "Kompyuterlar"
     * oynasi uchun) va o'qituvchi buyrug'ini bajaradi. "Qayta boshlash"
     * buyrug'ida urinish shu brauzer sessiyasiga bog'lanadi va test sahifasi
     * manzili qaytariladi — talaba ID va yuz tekshiruvini qayta o'tmaydi.
     */
    private function recordStage(Request $request, TestKioskDevice $device): ?string
    {
        $stage = in_array($request->query('mode'), self::STAGES, true) ? $request->query('mode') : 'wait';
        $attemptId = in_array($stage, ['take', 'result'], true)
            ? ((int) $request->session()->get(FanTestiKioskController::SESSION_KEY) ?: null)
            : null;
        $answered = $stage === 'take' && is_numeric($request->query('answered'))
            ? min(65535, max(0, (int) $request->query('answered')))
            : null;

        $changes = [];
        if ($device->kiosk_stage !== $stage || (int) $device->current_attempt_id !== (int) $attemptId) {
            $changes += ['kiosk_stage' => $stage, 'kiosk_stage_at' => now(), 'current_attempt_id' => $attemptId];
        }
        if ($device->kiosk_answered !== $answered) {
            $changes['kiosk_answered'] = $answered;
        }

        $goUrl = null;
        if ($device->pending_command === 'restart') {
            $attempt = FanTestiAttempt::find($device->pending_attempt_id);
            if ($attempt && (int) $attempt->fan_testi_id === (int) $device->assigned_fan_testi_id && !$attempt->isFinished()) {
                $request->session()->put(FanTestiKioskController::SESSION_KEY, (int) $attempt->id);
                $goUrl = route('kiosk.fan-testi.take', [$attempt->fan_testi_id, $attempt->id]);
            }
            $changes += ['pending_command' => null, 'pending_attempt_id' => null];
        }

        if ($changes || !$device->last_seen_at || $device->last_seen_at->lt(now()->subSeconds(10))) {
            $device->forceFill($changes + ['last_seen_at' => now()])->save();
        }

        return $goUrl;
    }

    private function label(TestKioskDevice $device): string
    {
        return trim(($device->room ? $device->room . '-xona, ' : '') . $device->name);
    }

    /**
     * Admin LMS ga oddiy admin akkaunti (web) yoki o'qituvchi profili
     * (teacher guard, admin roli bilan) orqali kirgan bo'lishi mumkin —
     * menyu ham ikkalasini hisobga oladi.
     */
    private function currentUser()
    {
        return Auth::guard('web')->user() ?? Auth::guard('teacher')->user();
    }

    private function isAdmin(): bool
    {
        $user = $this->currentUser();

        return $user && $user->getRoleNames()->intersect(['admin', 'superadmin'])->isNotEmpty();
    }
}
