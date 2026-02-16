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
            'session.impersonator_active_role' => session('impersonator_active_role'),
            'session.active_role' => session('active_role'),
            'session_id' => session()->getId(),
            'all_session_keys' => array_keys(session()->all()),
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

        Log::channel('daily')->info("🟢 IMPERSONATE STUDENT: Session o'rnatildi, web guard logout qilinmoqda");
        Auth::guard('web')->logout();
        Log::channel('daily')->info("🟢 IMPERSONATE STUDENT: web logout tugadi, student login qilinmoqda");
        Auth::guard('student')->login($student);

        $this->debugState('impersonateStudent:AFTER');
        Log::channel('daily')->info("🟢 IMPERSONATE STUDENT: Tugadi, student.dashboard ga redirect");

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

        Log::channel('daily')->info("🟢 IMPERSONATE TEACHER: Session o'rnatildi, web guard logout qilinmoqda");
        Auth::guard('web')->logout();
        Log::channel('daily')->info("🟢 IMPERSONATE TEACHER: web logout tugadi, teacher login qilinmoqda");
        Auth::guard('teacher')->login($teacher);

        $this->debugState('impersonateTeacher:AFTER');
        Log::channel('daily')->info("🟢 IMPERSONATE TEACHER: Tugadi, teacher.dashboard ga redirect");

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
        Log::channel('daily')->info("🔄 SWITCH TO STUDENT: Tugadi, student.dashboard ga redirect");

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
            Log::channel('daily')->warning("🔴 STOP IMPERSONATION: ❌ impersonator_id NULL! Early return qilinmoqda");

            // Impersonator topilmasa ham, teacher/student guardlarni tozalash kerak
            foreach (['teacher', 'student'] as $guard) {
                if (Auth::guard($guard)->check()) {
                    Log::channel('daily')->info("🔴 STOP IMPERSONATION: (early) {$guard} guard logout qilinmoqda, user_id=" . Auth::guard($guard)->id());
                    Auth::guard($guard)->logout();
                }
            }
            session()->forget([
                'impersonating',
                'impersonator_id',
                'impersonator_guard',
                'impersonated_name',
                'impersonator_active_role',
            ]);

            $this->debugState('stopImpersonation:EARLY_RETURN_AFTER_CLEANUP');
            Log::channel('daily')->info("🔴 STOP IMPERSONATION: admin.login ga redirect (early)");
            return redirect()->route('admin.login');
        }

        $admin = \App\Models\User::find($impersonatorId);
        if (!$admin) {
            Log::channel('daily')->warning("🔴 STOP IMPERSONATION: ❌ User topilmadi DB dan!", ['impersonator_id' => $impersonatorId]);
            return redirect()->route('admin.login');
        }

        Log::channel('daily')->info("🔴 STOP IMPERSONATION: ✅ Admin topildi", [
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ]);

        // Teacher va student guardlarni to'liq logout qilish
        foreach (['teacher', 'student'] as $guard) {
            $isChecked = Auth::guard($guard)->check();
            $guardId = Auth::guard($guard)->id();
            Log::channel('daily')->info("🔴 STOP IMPERSONATION: {$guard} guard check={$isChecked}, id={$guardId}");
            if ($isChecked) {
                Auth::guard($guard)->logout();
                Log::channel('daily')->info("🔴 STOP IMPERSONATION: {$guard} guard LOGOUT qilindi");
            }
        }

        $this->debugState('stopImpersonation:AFTER_GUARD_LOGOUT');

        // Impersonatsiya session kalitlarini tozalash
        $keysToForget = [
            'impersonating',
            'impersonator_id',
            'impersonator_guard',
            'impersonated_name',
            'impersonator_active_role',
            'active_role',
        ];
        Log::channel('daily')->info("🔴 STOP IMPERSONATION: Session kalitlar o'chirilmoqda", ['keys' => $keysToForget]);
        session()->forget($keysToForget);

        $this->debugState('stopImpersonation:AFTER_SESSION_FORGET');

        // Asl adminni web guard orqali login qilish
        Log::channel('daily')->info("🔴 STOP IMPERSONATION: Admin web guard orqali login qilinmoqda, admin_id={$admin->id}");
        Auth::guard('web')->login($admin);

        // Active rolni tiklash
        $roleToSet = $previousActiveRole ?? 'superadmin';
        session(['active_role' => $roleToSet]);
        Log::channel('daily')->info("🔴 STOP IMPERSONATION: active_role o'rnatildi: {$roleToSet}");

        $this->debugState('stopImpersonation:FINAL_STATE');

        // Verify: session('impersonating') haqiqatdan null mi?
        Log::channel('daily')->info("🔴 STOP IMPERSONATION: VERIFY", [
            'impersonating_is_null' => is_null(session('impersonating')),
            'impersonating_value' => session('impersonating'),
            'impersonated_name_value' => session('impersonated_name'),
            'web_check_final' => Auth::guard('web')->check(),
            'web_id_final' => Auth::guard('web')->id(),
            'teacher_check_final' => Auth::guard('teacher')->check(),
            'student_check_final' => Auth::guard('student')->check(),
        ]);

        ActivityLogService::log(
            'stop_impersonate',
            'auth',
            'Impersonatsiyadan qaytdi'
        );

        Log::channel('daily')->info("🔴 STOP IMPERSONATION: ========== TUGADI, admin.dashboard ga redirect ==========");

        return redirect()->route('admin.dashboard');
    }
}
