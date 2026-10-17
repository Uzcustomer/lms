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

        // Route'dagi RoleMiddleware foydalanuvchining ISTALGAN roliga qaraydi, quyidagi
        // cheklovlar esa FAOL rolga. Faol rol ro'yxatda bo'lmasa (mas. faol rol — dekan,
        // lekin oqituvchi roli ham bor) cheklovsiz to'liq kirishga tushib qolmasin.
        // Ro'yxat routes/web.php dagi dars-jadvali-tuzish guruhi bilan bir xil.
        $timetableRoles = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi',
            'oquv_bolimi', 'oquv_bolimi_boshligi', 'kafedra_mudiri', 'oqituvchi'];
        if ($user && !in_array($activeRole, $timetableRoles, true)) {
            abort(403, 'Dars jadvaliga kirish uchun mos rolni tanlang.');
        }

        // Sinov (faqat admin) doskasi — boshqa rollarga yopiq
        if (!in_array($activeRole, ['superadmin', 'admin'], true)) {
            $board = $request->route('board');
            if (!$board && ($card = $request->route('card'))) {
                $board = is_object($card) ? \App\Models\TimetableBoard::find($card->board_id) : null;
            }
            if ($board && !is_object($board)) {
                $board = \App\Models\TimetableBoard::find($board);
            }
            if ($board && !empty($board->admin_only)) {
                abort(403, 'Bu sinov doskasi faqat admin uchun.');
            }
        }

        // O'qituvchi: dars jadvalini faqat ko'radi (tashqi/ichki almashtirgich bilan).
        if ($user && $activeRole === 'oqituvchi') {
            if (!$request->routeIs(['admin.timetable.index', 'admin.timetable.boards.data', 'admin.timetable.cycle-plan'])) {
                abort(403, 'O\'qituvchi dars jadvalini faqat ko\'ra oladi.');
            }

            return $next($request);
        }

        // Kafedra mudiri va o'quv bo'limi ruxsatlari bir xil: joylashtirish
        // (haftalik + sikl) hamda o'qituvchi/auditoriya biriktirish hammasiga.
        $auditoriumAssignmentRoles = ['oquv_bolimi', 'oquv_bolimi_boshligi', 'kafedra_mudiri'];
        $placementRoles = ['oquv_bolimi', 'oquv_bolimi_boshligi', 'kafedra_mudiri'];
        $teacherAssignmentRoles = ['kafedra_mudiri', 'oquv_bolimi', 'oquv_bolimi_boshligi'];
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
                    'admin.timetable.boards.unplace',
                    'admin.timetable.cards.place',
                    'admin.timetable.cards.week-override',
                    'admin.timetable.cycle-plan',
                    'admin.timetable.cycle-place',
                    'admin.timetable.cycle-assign-options',
                    'admin.timetable.cycle-assign',
                    'admin.timetable.cycle-lecture-slots',
                ]);
            }

            if (in_array($activeRole, $teacherAssignmentRoles, true)) {
                $allowed = array_merge($allowed, [
                    'admin.timetable.teacher-units',
                    'admin.timetable.assign-teacher',
                    // O'qituvchi biriktirish oynasidan dars birligiga xona
                    'admin.timetable.assign-room',
                ]);
            }

            if (!$request->routeIs($allowed)) {
                abort(403, 'Bu amal uchun ruxsat berilmagan.');
            }
        }

        return $next($request);
    }
}
