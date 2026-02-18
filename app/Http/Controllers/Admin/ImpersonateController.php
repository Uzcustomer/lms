<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ImpersonateController extends Controller
{
    /**
     * Guard session kalitlari — Laravel ichki formati:
     * login_{guardName}_{sha1(Illuminate\Auth\SessionGuard)}
     */
    private const GUARD_SESSION_KEYS = [
        'login_teacher_59ba36addc2b2f9401580f014c7f58ea4e30989d',
        'login_student_59ba36addc2b2f9401580f014c7f58ea4e30989d',
    ];

    private const IMPERSONATION_SESSION_KEYS = [
        'impersonating',
        'impersonator_id',
        'impersonator_guard',
        'impersonated_name',
        'impersonator_active_role',
    ];

    /**
     * DEBUG: Barcha guard va session holatini logga yozish
     */
    private function debugState(string $label): void
    {
        Log::channel('daily')->info("🔍 IMPERSONATE DEBUG [{$label}]", [
            'web_check' => Auth::guard('web')->check(),
            'web_id' => Auth::guard('web')->id(),
            'teacher_check' => Auth::guard('teacher')->check(),
            'teacher_id' => Auth::guard('teacher')->id(),
            'student_check' => Auth::guard('student')->check(),
            'student_id' => Auth::guard('student')->id(),
            'session.impersonating' => session('impersonating'),
            'session.impersonator_id' => session('impersonator_id'),
            'session.impersonator_guard' => session('impersonator_guard'),
            'session.impersonated_name' => session('impersonated_name'),
            'session.active_role' => session('active_role'),
            'session_id' => session()->getId(),
            'all_session_keys' => array_keys(session()->all()),
        ]);
    }

    /**
     * Hozir qaysi guard aktiv ekanligini aniqlash.
     * Admin (web) birinchi tekshiriladi, keyin teacher.
     */
    private function detectCurrentGuard(): string
    {
        if (Auth::guard('web')->check()) {
            return 'web';
        }
        if (Auth::guard('teacher')->check()) {
            return 'teacher';
        }
        return 'web'; // default fallback
    }

    /**
     * Teacher/student guard va impersonation session ma'lumotlarini to'liq tozalash.
     */
    private function purgeNonWebGuards(): void
    {
        foreach (['teacher', 'student'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Log::channel('daily')->info("🧹 PURGE: {$guard} guard logout qilinmoqda, id=" . Auth::guard($guard)->id());
                Auth::guard($guard)->logout();
            }
        }
        foreach (self::GUARD_SESSION_KEYS as $key) {
            session()->forget($key);
        }
        session()->forget(self::IMPERSONATION_SESSION_KEYS);
    }

    /**
     * Superadmin talaba sifatida tizimga kiradi.
     */
    public function impersonateStudent(Student $student): RedirectResponse
    {
        $currentGuard = $this->detectCurrentGuard();
        $admin = Auth::guard($currentGuard)->user();

        if (!$admin || !$admin->hasRole('superadmin')) {
            abort(403);
        }

        Log::channel('daily')->info("🟢 IMPERSONATE STUDENT: Boshlanmoqda", [
            'admin_id' => $admin->id,
            'admin_guard' => $currentGuard,
            'admin_class' => get_class($admin),
            'student_id' => $student->id,
            'student_name' => $student->full_name,
        ]);
        $this->debugState('impersonateStudent:BEFORE');

        ActivityLogService::log(
            'impersonate',
            'auth',
            "Talaba sifatida kirdi: {$student->full_name} (ID: {$student->student_id_number})",
            $student
        );

        session([
            'impersonating' => true,
            'impersonator_id' => $admin->id,
            'impersonator_guard' => $currentGuard, // 'web' yoki 'teacher' — haqiqiy guard
            'impersonated_name' => $student->full_name,
            'impersonator_active_role' => session('active_role'),
        ]);
        session()->forget('active_role');

        Auth::guard($currentGuard)->logout();
        Auth::guard('student')->login($student);

        $this->debugState('impersonateStudent:AFTER');

        return redirect()->route('student.dashboard');
    }

    /**
     * Superadmin o'qituvchi sifatida tizimga kiradi.
     */
    public function impersonateTeacher(Teacher $teacher): RedirectResponse
    {
        $currentGuard = $this->detectCurrentGuard();
        $admin = Auth::guard($currentGuard)->user();

        if (!$admin || !$admin->hasRole('superadmin')) {
            abort(403);
        }

        Log::channel('daily')->info("🟢 IMPERSONATE TEACHER: Boshlanmoqda", [
            'admin_id' => $admin->id,
            'admin_guard' => $currentGuard,
            'admin_class' => get_class($admin),
            'teacher_id' => $teacher->id,
            'teacher_name' => $teacher->full_name,
        ]);
        $this->debugState('impersonateTeacher:BEFORE');

        ActivityLogService::log(
            'impersonate',
            'auth',
            "O'qituvchi sifatida kirdi: {$teacher->full_name} (ID: {$teacher->id})",
            $teacher
        );

        session([
            'impersonating' => true,
            'impersonator_id' => $admin->id,
            'impersonator_guard' => $currentGuard, // 'web' yoki 'teacher' — haqiqiy guard
            'impersonated_name' => $teacher->full_name,
            'impersonator_active_role' => session('active_role'),
        ]);

        // O'qituvchi uchun active_role o'rnatish (oqituvchi rolini afzal ko'rish)
        $teacherRoles = $teacher->getRoleNames()->toArray();
        $defaultRole = in_array('oqituvchi', $teacherRoles) ? 'oqituvchi' : ($teacherRoles[0] ?? null);
        if ($defaultRole) {
            session(['active_role' => $defaultRole]);
        } else {
            session()->forget('active_role');
        }

        Auth::guard($currentGuard)->logout();
        Auth::guard('teacher')->login($teacher);

        $this->debugState('impersonateTeacher:AFTER');

        return redirect()->route('teacher.dashboard');
    }

    /**
     * Impersonatsiya paytida teacher'dan student'ga o'tish.
     */
    public function switchToStudent(Student $student): RedirectResponse
    {
        Log::channel('daily')->info("🔄 SWITCH TO STUDENT: Boshlanmoqda", [
            'student_id' => $student->id,
            'student_name' => $student->full_name,
        ]);
        $this->debugState('switchToStudent:BEFORE');

        if (!session('impersonating') || !session('impersonator_id')) {
            Log::channel('daily')->warning("🔄 SWITCH TO STUDENT: Impersonatsiya topilmadi, 403");
            abort(403);
        }

        $impersonatorId = session('impersonator_id');
        $impersonatorGuard = session('impersonator_guard', 'web');

        foreach (['student', 'teacher'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }
        foreach (self::GUARD_SESSION_KEYS as $key) {
            session()->forget($key);
        }

        ActivityLogService::log(
            'impersonate',
            'auth',
            "Talaba sifatida kirdi (o'tish): {$student->full_name} (ID: {$student->student_id_number})",
            $student
        );

        session([
            'impersonating' => true,
            'impersonator_id' => $impersonatorId,
            'impersonator_guard' => $impersonatorGuard,
            'impersonated_name' => $student->full_name,
            'impersonator_active_role' => session('impersonator_active_role'),
        ]);

        Auth::guard('student')->login($student);

        $this->debugState('switchToStudent:AFTER');

        return redirect()->route('student.dashboard');
    }

    /**
     * Impersonatsiyani to'xtatish — asl admin/teacher hisobiga qaytish.
     */
    public function stopImpersonation(): RedirectResponse
    {
        Log::channel('daily')->info("🔴 STOP IMPERSONATION: ========== BOSHLANMOQDA ==========");
        $this->debugState('stopImpersonation:ENTRY');

        $impersonatorId = session('impersonator_id');
        $impersonatorGuard = session('impersonator_guard', 'web');
        $previousActiveRole = session('impersonator_active_role');

        Log::channel('daily')->info("🔴 STOP IMPERSONATION: Session dan olingan qiymatlar", [
            'impersonator_id' => $impersonatorId,
            'impersonator_guard' => $impersonatorGuard,
            'previousActiveRole' => $previousActiveRole,
        ]);

        if (!$impersonatorId) {
            Log::channel('daily')->warning("🔴 STOP IMPERSONATION: ❌ impersonator_id NULL!");
            $this->purgeNonWebGuards();
            session()->forget('active_role');
            session()->save();
            return redirect()->route('admin.login');
        }

        // ============================================================
        // Impersonator'ni uning HAQIQIY guard'iga qarab topish:
        // 'web' guard → User modeldan (users jadvali)
        // 'teacher' guard → Teacher modeldan (teachers jadvali)
        // ============================================================
        $originalUser = null;
        if ($impersonatorGuard === 'teacher') {
            $originalUser = Teacher::find($impersonatorId);
            Log::channel('daily')->info("🔴 STOP IMPERSONATION: Teacher::find({$impersonatorId})", [
                'found' => !is_null($originalUser),
                'name' => $originalUser?->full_name,
            ]);
        } else {
            $originalUser = User::find($impersonatorId);
            Log::channel('daily')->info("🔴 STOP IMPERSONATION: User::find({$impersonatorId})", [
                'found' => !is_null($originalUser),
                'name' => $originalUser?->name,
            ]);
        }

        if (!$originalUser) {
            Log::channel('daily')->warning("🔴 STOP IMPERSONATION: ❌ Original user topilmadi!", [
                'impersonator_id' => $impersonatorId,
                'impersonator_guard' => $impersonatorGuard,
            ]);
            $this->purgeNonWebGuards();
            session()->forget('active_role');
            session()->save();
            return redirect()->route('admin.login');
        }

        Log::channel('daily')->info("🔴 STOP IMPERSONATION: ✅ Original user topildi", [
            'id' => $originalUser->id,
            'guard' => $impersonatorGuard,
            'class' => get_class($originalUser),
        ]);

        // 1. BARCHA guardlarni logout + session tozalash
        foreach (['teacher', 'student', 'web'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Log::channel('daily')->info("🔴 STOP IMPERSONATION: {$guard} guard logout, id=" . Auth::guard($guard)->id());
                Auth::guard($guard)->logout();
            }
        }

        // 2. Session'ni to'liq invalidate qilish (yangi toza session)
        session()->invalidate();
        session()->regenerateToken();

        Log::channel('daily')->info("🔴 STOP IMPERSONATION: Session invalidated, yangi session yaratildi");

        // 3. Original user'ni uning guard'iga login qilish
        Auth::guard($impersonatorGuard)->login($originalUser);

        // 4. Active rolni tiklash
        $roleToSet = $previousActiveRole ?? 'superadmin';
        session(['active_role' => $roleToSet]);

        Log::channel('daily')->info("🔴 STOP IMPERSONATION: Login qilindi", [
            'guard' => $impersonatorGuard,
            'user_id' => $originalUser->id,
            'active_role' => $roleToSet,
            'web_check' => Auth::guard('web')->check(),
            'teacher_check' => Auth::guard('teacher')->check(),
        ]);

        // 5. Session'ni saqlash
        session()->save();

        $this->debugState('stopImpersonation:FINAL_STATE');

        ActivityLogService::log(
            'stop_impersonate',
            'auth',
            'Impersonatsiyadan qaytdi'
        );

        Log::channel('daily')->info("🔴 STOP IMPERSONATION: ========== TUGADI ==========");

        return redirect()->route('admin.dashboard');
    }
}
