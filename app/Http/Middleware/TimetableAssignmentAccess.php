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
        $placementRoles = ['oquv_bolimi', 'oquv_bolimi_boshligi'];
        $teacherAssignmentRoles = ['kafedra_mudiri'];
        $assignmentRoles = $auditoriumAssignmentRoles;

        if ($user && in_array($activeRole, $assignmentRoles, true)) {
            $allowed = [
                'admin.timetable.index',
                'admin.timetable.boards.data',
            ];

            if (in_array($activeRole, $auditoriumAssignmentRoles, true)) {
                $allowed = array_merge($allowed, [
                    'admin.timetable.subjects',
                    'admin.timetable.subject-setting.save',
                    'admin.timetable.teachers',
                    'admin.timetable.teachers.departments',
                    'admin.timetable.auditorium-teachers',
                    'admin.timetable.auditorium-teachers.destroy',
                    'admin.timetable.assign-auditorium-teacher',
                    'admin.timetable.auditoriums',
                ]);
            }

            if (in_array($activeRole, $placementRoles, true)) {
                $allowed = array_merge($allowed, [
                    'admin.timetable.boards.auto-place',
                    'admin.timetable.boards.unplace',
                    'admin.timetable.cards.place',
                    'admin.timetable.cards.week-override',
                    'admin.timetable.boards.compact-week',
                    'admin.timetable.cycle-plan',
                    'admin.timetable.cycle-place',
                    'admin.timetable.cycle-assign-options',
                    'admin.timetable.cycle-assign',
                ]);
            }

            if (in_array($activeRole, $teacherAssignmentRoles, true)) {
                $allowed = array_merge($allowed, [
                    'admin.timetable.teacher-units',
                    'admin.timetable.assign-teacher',
                ]);
            }

            if (!$request->routeIs($allowed)) {
                abort(403, 'Bu amal uchun ruxsat berilmagan.');
            }
        }

        return $next($request);
    }
}
