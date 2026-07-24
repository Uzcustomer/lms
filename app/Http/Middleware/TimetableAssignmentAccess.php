<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TimetableAssignmentAccess
{
    /**
     * O'quv bo'limi faqat doskani ko'rib, o'qituvchi biriktirishi mumkin.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        $userRoles = $user ? $user->getRoleNames()->toArray() : [];
        $activeRole = session('active_role', $userRoles[0] ?? '');
        if (!in_array($activeRole, $userRoles, true) && $userRoles) {
            $activeRole = $userRoles[0];
        }

        if ($user && in_array($activeRole, ['oquv_bolimi', 'oquv_bolimi_boshligi'], true)) {
            $allowed = [
                'admin.timetable.index',
                'admin.timetable.boards.data',
                'admin.timetable.teachers',
                'admin.timetable.teacher-units',
                'admin.timetable.assign-teacher',
            ];

            if (!$request->routeIs($allowed)) {
                abort(403, 'Bu amal uchun ruxsat berilmagan.');
            }
        }

        return $next($request);
    }
}
