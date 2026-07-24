<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TimetableAssignmentAccess
{
    /**
     * O'quv bo'limi rollari uchun jadval sahifasini faqat
     * o'qituvchi biriktirish oqimiga cheklaydi.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        $activeRole = session('active_role', $user?->getRoleNames()->first());

        if (in_array($activeRole, ['oquv_bolimi', 'oquv_bolimi_boshligi'], true)) {
            $allowedRoutes = [
                'admin.timetable.index',
                'admin.timetable.teacher-units',
                'admin.timetable.teachers',
                'admin.timetable.assign-teacher',
            ];

            abort_unless(
                in_array($request->route()?->getName(), $allowedRoutes, true),
                403
            );
        }

        return $next($request);
    }
}
