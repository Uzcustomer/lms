<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TimetableAssignmentAccess
{
    /**
     * Cheklangan rollar doskani ko'rib, faqat o'ziga berilgan biriktirish amallarini bajaradi.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        $userRoles = $user ? $user->getRoleNames()->toArray() : [];
        $activeRole = session('active_role', $userRoles[0] ?? '');
        if (!in_array($activeRole, $userRoles, true) && $userRoles) {
            $activeRole = $userRoles[0];
        }

        $auditoriumAssignmentRoles = ['oquv_bolimi', 'oquv_bolimi_boshligi', 'kafedra_mudiri'];
        $departmentHeadRoles = ['kafedra_mudiri'];
        $assignmentRoles = $auditoriumAssignmentRoles;

        if ($user && in_array($activeRole, $assignmentRoles, true)) {
            $allowed = [
                'admin.timetable.index',
                'admin.timetable.boards.data',
            ];

            if (in_array($activeRole, $auditoriumAssignmentRoles, true)) {
                $allowed = array_merge($allowed, [
                    'admin.timetable.teachers',
                    'admin.timetable.teachers.departments',
                    'admin.timetable.auditorium-teachers',
                    'admin.timetable.auditorium-teachers.destroy',
                    'admin.timetable.assign-auditorium-teacher',
                    'admin.timetable.auditoriums',
                ]);
            }

            if (in_array($activeRole, $departmentHeadRoles, true)) {
                $allowed = array_merge($allowed, [
                    'admin.timetable.auditoriums.store',
                    'admin.timetable.auditoriums.destroy',
                ]);
            }

            if (!$request->routeIs($allowed)) {
                abort(403, 'Bu amal uchun ruxsat berilmagan.');
            }
        }

        return $next($request);
    }
}
