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
            // Excelga yuklash faqat klient yuborgan (o'zi ko'rib turgan) panjarani
            // faylga yozadi — yangi ma'lumot ochmaydi, shuning uchun ruxsat.
            if (!$request->routeIs(['admin.timetable.index', 'admin.timetable.boards.data', 'admin.timetable.cycle-plan', 'admin.timetable.boards.excel-export'])) {
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
                // O'zgarish so'rovlari: o'z so'rovlarini ko'rish / bekor qilish
                'admin.timetable.change-requests.index',
                'admin.timetable.change-requests.cancel',
                // Ekrandagi jadvalni Excelga yuklash (faqat klient panjarasini yozadi)
                'admin.timetable.boards.excel-export',
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

            // Doskada "tasdiq talab qilinadi" yoqilgan bo'lsa, kafedra mudirining jadvalni
            // o'zgartiradigan amali darhol bajarilmaydi — registrator ofisiga so'rov bo'lib boradi.
            $board = $this->routeBoard($request);
            if (\App\Services\Timetable\TimetableChangeApproval::shouldIntercept($request, $activeRole, $board)) {
                // Sinov bajarish: amal hozir o'tadimi (kafedra, to'qnashuv va h.k. tekshiruvlari)?
                // Natija bekor qilinadi (rollback) — xato bo'lsa, so'rov yaratilmaydi, xato darhol ko'rinadi.
                \Illuminate\Support\Facades\DB::beginTransaction();
                try {
                    $trial = $next($request);
                } finally {
                    \Illuminate\Support\Facades\DB::rollBack();
                }
                $trialData = $trial instanceof \Illuminate\Http\JsonResponse ? $trial->getData(true) : [];
                if ($trial->getStatusCode() >= 400 || !empty($trialData['error'])) {
                    return $trial;
                }

                $changeRequest = app(\App\Services\Timetable\TimetableChangeApproval::class)->capture($request, $user, $board);

                return response()->json([
                    'ok' => true,
                    'pending_approval' => true,
                    'change_request_id' => $changeRequest->id,
                    'message' => 'O\'zgarish registrator ofisiga tasdiqlash uchun yuborildi. Tasdiqlangach jadvalga tushadi.',
                    'summary' => $changeRequest->summary,
                ]);
            }
        }

        return $next($request);
    }

    private function routeBoard(Request $request): ?\App\Models\TimetableBoard
    {
        $board = $request->route('board');
        if (!$board && ($card = $request->route('card'))) {
            $card = is_object($card) ? $card : \App\Models\TimetableCard::find($card);
            $board = $card ? \App\Models\TimetableBoard::find($card->board_id) : null;
        }
        if ($board && !is_object($board)) {
            $board = \App\Models\TimetableBoard::find($board);
        }

        return $board ?: null;
    }
}
