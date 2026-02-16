<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
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
            'session.impersonated_name' => session('impersonated_name'),
            'session.active_role' => session('active_role'),
            'session_id' => session()->getId(),
            'session_has_teacher_key' => session()->has('login_teacher_59ba36addc2b2f9401580f014c7f58ea4e30989d'),
            'session_has_student_key' => session()->has('login_student_59ba36addc2b2f9401580f014c7f58ea4e30989d'),
            'session_has_web_key' => session()->has('login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d'),
            'all_session_keys' => array_keys(session()->all()),
        ]);
    }

    /**
     * Teacher/student guard va impersonation session ma'lumotlarini to'liq tozalash.
     */
    private function purgeNonWebGuards(): void
    {
        // 1. Auth guard objectlarini logout qilish (ichki cached user'ni tozalaydi)
        foreach (['teacher', 'student'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Log::channel('daily')->info("🧹 PURGE: {$guard} guard logout qilinmoqda, id=" . Auth::guard($guard)->id());
                Auth::guard($guard)->logout();
            }
        }

        // 2. Session dan guard kalitlarini QOËLDA o'chirish
        //    (Auth::logout() ba'zan to'liq tozalamaydi, ayniqsa session migrate bo'lganda)
        foreach (self::GUARD_SESSION_KEYS as $key) {
            session()->forget($key);
        }

        // 3. Impersonation session kalitlarini tozalash
        session()->forget(self::IMPERSONATION_SESSION_KEYS);

        Log::channel('daily')->info("🧹 PURGE: Tozalash tugadi", [
            'session_has_teacher_key' => session()->has('login_teacher_59ba36addc2b2f9401580f014c7f58ea4e30989d'),
            'session_has_student_key' => session()->has('login_student_59ba36addc2b2f9401580f014c7f58ea4e30989d'),
        ]);
    }

    /**
     * Superadmin talaba sifatida tizimga kiradi.
     */
    public function impersonateStudent(Student $student): RedirectResponse
    {
        $admin = Auth::user();

        if (!$admin->hasRole('superadmin')) {
            abort(403);
        }

        Log::channel('daily')->info("🟢 IMPERSONATE STUDENT: Boshlanmoqda", [
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
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
            'impersonator_guard' => 'web',
            'impersonated_name' => $student->full_name,
            'impersonator_active_role' => session('active_role'),
        ]);
        session()->forget('active_role');

        Auth::guard('web')->logout();
        Auth::guard('student')->login($student);

        $this->debugState('impersonateStudent:AFTER');

        return redirect()->route('student.dashboard');
    }

    /**
     * Superadmin o'qituvchi sifatida tizimga kiradi.
     */
    public function impersonateTeacher(Teacher $teacher): RedirectResponse
    {
        $admin = Auth::user();

        if (!$admin->hasRole('superadmin')) {
            abort(403);
        }

        Log::channel('daily')->info("🟢 IMPERSONATE TEACHER: Boshlanmoqda", [
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
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
            'impersonator_guard' => 'web',
            'impersonated_name' => $teacher->full_name,
            'impersonator_active_role' => session('active_role'),
        ]);
        session()->forget('active_role');

        Auth::guard('web')->logout();
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

        // Joriy guard'dan chiqish (teacher)
        foreach (['student', 'teacher'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Log::channel('daily')->info("🔄 SWITCH TO STUDENT: {$guard} guard logout qilinmoqda");
                Auth::guard($guard)->logout();
            }
        }
        // Guard session kalitlarini ham qo'lda o'chirish
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
            'impersonator_guard' => 'web',
            'impersonated_name' => $student->full_name,
            'impersonator_active_role' => session('impersonator_active_role'),
        ]);

        Auth::guard('student')->login($student);

        $this->debugState('switchToStudent:AFTER');

        return redirect()->route('student.dashboard');
    }

    /**
     * Impersonatsiyani to'xtatish — asl superadmin hisobiga qaytish.
     */
    public function stopImpersonation(): RedirectResponse
    {
        Log::channel('daily')->info("🔴 STOP IMPERSONATION: ========== BOSHLANMOQDA ==========");
        $this->debugState('stopImpersonation:ENTRY');

        $impersonatorId = session('impersonator_id');
        $previousActiveRole = session('impersonator_active_role');

        Log::channel('daily')->info("🔴 STOP IMPERSONATION: Session dan olingan qiymatlar", [
            'impersonator_id' => $impersonatorId,
            'previousActiveRole' => $previousActiveRole,
        ]);

        if (!$impersonatorId) {
            Log::channel('daily')->warning("🔴 STOP IMPERSONATION: ❌ impersonator_id NULL!");
            $this->purgeNonWebGuards();
            session()->forget('active_role');
            session()->save();
            return redirect()->route('admin.login');
        }

        $admin = \App\Models\User::find($impersonatorId);
        if (!$admin) {
            Log::channel('daily')->warning("🔴 STOP IMPERSONATION: ❌ User topilmadi DB dan!", ['impersonator_id' => $impersonatorId]);
            $this->purgeNonWebGuards();
            session()->forget('active_role');
            session()->save();
            return redirect()->route('admin.login');
        }

        Log::channel('daily')->info("🔴 STOP IMPERSONATION: ✅ Admin topildi", [
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
        ]);

        // ============================================================
        // MUHIM: Avval BARCHA eski guard va session ma'lumotlarini
        // to'liq tozalash, KEYIN web guard'ni login qilish.
        // ============================================================

        // 1. Teacher/student guard + impersonation session'ni to'liq tozalash
        $this->purgeNonWebGuards();

        // 2. active_role'ni ham o'chirish (keyinroq qayta o'rnatamiz)
        session()->forget('active_role');

        $this->debugState('stopImpersonation:AFTER_PURGE');

        // 3. Admin'ni web guard orqali login qilish
        Log::channel('daily')->info("🔴 STOP IMPERSONATION: Admin login qilinmoqda, admin_id={$admin->id}");
        Auth::guard('web')->login($admin);

        // 4. Active rolni tiklash
        $roleToSet = $previousActiveRole ?? 'superadmin';
        session(['active_role' => $roleToSet]);
        Log::channel('daily')->info("🔴 STOP IMPERSONATION: active_role={$roleToSet}");

        // 5. Session'ni DARHOL saqlash (redirect oldidan)
        session()->save();

        $this->debugState('stopImpersonation:FINAL_STATE');

        // 6. Tekshirish: teacher/student guard haqiqatan o'chirilganmi?
        Log::channel('daily')->info("🔴 STOP IMPERSONATION: VERIFY", [
            'impersonating_is_null' => is_null(session('impersonating')),
            'web_check_final' => Auth::guard('web')->check(),
            'web_id_final' => Auth::guard('web')->id(),
            'teacher_check_final' => Auth::guard('teacher')->check(),
            'student_check_final' => Auth::guard('student')->check(),
            'teacher_session_key_exists' => session()->has('login_teacher_59ba36addc2b2f9401580f014c7f58ea4e30989d'),
            'student_session_key_exists' => session()->has('login_student_59ba36addc2b2f9401580f014c7f58ea4e30989d'),
        ]);

        ActivityLogService::log(
            'stop_impersonate',
            'auth',
            'Impersonatsiyadan qaytdi'
        );

        Log::channel('daily')->info("🔴 STOP IMPERSONATION: ========== TUGADI ==========");

        return redirect()->route('admin.dashboard');
    }
}
