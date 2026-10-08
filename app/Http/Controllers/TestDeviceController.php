<?php

namespace App\Http\Controllers;

use App\Models\TestKioskDevice;
use App\Services\TestDeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Sinf kompyuterlarini fan testi uchun ro'yxatdan o'tkazish.
 *
 * Admin har bir sinf kompyuterida LMS ga kirib /test-kompyuter sahifasini
 * ochadi va tugmani bosadi — brauzerga maxfiy belgi yoziladi. LMS dan
 * chiqilgandan keyin ham belgi qoladi.
 */
class TestDeviceController extends Controller
{
    public function index(Request $request)
    {
        if (!$this->isAdmin()) {
            return view('kiosk.fan-testi.device-guest');
        }

        $tableReady = Schema::hasTable('test_kiosk_devices');
        $current = $tableReady ? TestDeviceToken::deviceFor(TestDeviceToken::fromRequest($request)) : null;

        return view('admin.test-devices.index', [
            'tableReady' => $tableReady,
            'current' => $current && $current->isActive() ? $current : null,
            'devices' => $tableReady ? TestKioskDevice::orderByRaw('revoked_at IS NOT NULL')->orderBy('name')->get() : collect(),
            'enforced' => (bool) config('services.fan_test.require_device'),
        ]);
    }

    /** Shu brauzerni test kompyuteri sifatida belgilaydi (yoki nomini yangilaydi). */
    public function store(Request $request)
    {
        abort_unless($this->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ], [], ['name' => 'Kompyuter nomi']);

        $user = $this->currentUser();
        $token = TestDeviceToken::fromRequest($request);
        $device = TestDeviceToken::deviceFor($token);

        if ($device && $device->isActive()) {
            $device->update(['name' => trim($data['name'])]);
            $message = "Kompyuter nomi yangilandi: {$device->name}";
        } else {
            $device = new TestKioskDevice([
                'name' => trim($data['name']),
                'registered_by_user_id' => $user?->id,
                'registered_by_name' => $user?->name ?? $user?->full_name ?? $user?->short_name ?? $user?->email,
            ]);
            $message = "Kompyuter ro'yxatdan o'tdi: " . trim($data['name']);
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
            ->with('success', "{$device->name} bekor qilindi.");
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
