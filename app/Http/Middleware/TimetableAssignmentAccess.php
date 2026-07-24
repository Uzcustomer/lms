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

        if ($user && $user->hasAnyRole(['oquv_bolimi', 'oquv_bolimi_boshligi'])) {
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
