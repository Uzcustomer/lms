<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{
    /**
     * Superadmin talaba sifatida tizimga kiradi.
     */
    public function impersonateStudent(Student $student): RedirectResponse
    {
        $admin = Auth::user();

        if (!$admin->hasRole('superadmin')) {
            abort(403);
        }

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

        return redirect()->route('teacher.dashboard');
    }

    /**
     * Impersonatsiya paytida teacher'dan student'ga o'tish.
     */
    public function switchToStudent(Student $student): RedirectResponse
    {
        if (!session('impersonating') || !session('impersonator_id')) {
            abort(403);
        }

        $impersonatorId = session('impersonator_id');

        // Joriy guard'dan chiqish (teacher)
        foreach (['student', 'teacher'] as $guard) {
            if (Auth::guard($guard)->check()) {
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

        return redirect()->route('student.dashboard');
    }

    /**
     * Impersonatsiyani to'xtatish — asl superadmin hisobiga qaytish.
     */
    public function stopImpersonation(): RedirectResponse
    {
        $impersonatorId = session('impersonator_id');
        $impersonatorGuard = session('impersonator_guard', 'web');

        if (!$impersonatorId) {
            return redirect()->route('admin.login');
        }

        // Joriy guard'dan chiqish
        foreach (['student', 'teacher'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }

        // Asl adminning active_role ni tiklash
        $previousActiveRole = session('impersonator_active_role');

        // Sessiyadan impersonatsiya ma'lumotlarini tozalash
        session()->forget(['impersonating', 'impersonator_id', 'impersonator_guard', 'impersonated_name', 'impersonator_active_role', 'active_role']);

        if ($previousActiveRole) {
            session(['active_role' => $previousActiveRole]);
        }

        // Asl adminni qayta tiklash
        $admin = \App\Models\User::find($impersonatorId);
        if ($admin) {
            Auth::guard($impersonatorGuard)->login($admin);

            ActivityLogService::log(
                'stop_impersonate',
                'auth',
                'Impersonatsiyadan qaytdi'
            );
        }

        return redirect()->route('admin.students.index');
    }
}
