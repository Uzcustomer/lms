<?php

namespace App\Http\Middleware;

use App\Services\Timetable\TimetableChangeApproval;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            'oquv_bolimi', 'oquv_bolimi_boshligi', 'kafedra_mudiri', 'oqituvchi', 'fan_masuli'];
        if ($user && !in_array($activeRole, $timetableRoles, true)) {
            abort(403, 'Dars jadvaliga kirish uchun mos rolni tanlang.');
        }

        // Sinov (faqat admin) doskasi — boshqa rollarga yopiq
        if (!in_array($activeRole, ['superadmin', 'admin'], true)) {
            $board = $request->route('board');
            if (!$board && ($card = $request->route('card'))) {
                $board = is_object($card) ? \App\Models\TimetableBoard::find($card->board_id) : null;
            }
            if (!$board && ($change = $request->route('changeRequest'))) {
                $board = \App\Models\TimetableBoard::find($change->board_id);
            }
            if ($board && !is_object($board)) {
                $board = \App\Models\TimetableBoard::find($board);
            }
            if ($board && !empty($board->admin_only)) {
                abort(403, 'Bu sinov doskasi faqat admin uchun.');
            }
        }

        // O'qituvchi va fan mas'uli: dars jadvalini faqat ko'radi (tashqi/ichki almashtirgich bilan).
        if ($user && in_array($activeRole, ['oqituvchi', 'fan_masuli'], true)) {
            // Excelga yuklash faqat klient yuborgan (o'zi ko'rib turgan) panjarani
            // faylga yozadi — yangi ma'lumot ochmaydi, shuning uchun ruxsat.
            if (!$request->routeIs(['admin.timetable.index', 'admin.timetable.boards.data', 'admin.timetable.boards.conflicts', 'admin.timetable.cycle-plan', 'admin.timetable.boards.excel-export', 'admin.timetable.change-requests.index', 'admin.timetable.change-requests.cancel', 'admin.timetable.cards.time-proposal', 'admin.timetable.cards.time-proposal.check'])) {
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
                // Ichki jadvaldagi to'qnashuvlar (hisoblagich va panel uchun, faqat o'qiydi)
                'admin.timetable.boards.conflicts',
                // O'zgarish so'rovlari: o'z so'rovlarini ko'rish / bekor qilish
                'admin.timetable.change-requests.index',
                'admin.timetable.change-requests.cancel',
                'admin.timetable.change-requests.approve',
                'admin.timetable.change-requests.reject',
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
                    // Sikl to'qnashuvlarini guruhcha smenasi bilan avtomatik hal qilish
                    'admin.timetable.cycle-auto-shift',
                ]);
            }

            // Butun doska bo'yicha joylanmagan kartalar hisoboti (Excel) — o'quv bo'limiga ham.
            if (in_array($activeRole, ['oquv_bolimi', 'oquv_bolimi_boshligi'], true)) {
                $allowed = array_merge($allowed, [
                    'admin.timetable.boards.unplaced-report',
                    'admin.timetable.boards.unplaced-report.status',
                    'admin.timetable.boards.unplaced-report.download',
                ]);
            }

            if (in_array($activeRole, $teacherAssignmentRoles, true)) {
                $allowed = array_merge($allowed, [
                    'admin.timetable.teacher-units',
                    'admin.timetable.assign-teacher',
                    // Tanlangan dars birligi vaqtida band o'qituvchilar/xonalar (ro'yxatda kulrang)
                    'admin.timetable.unit-busy',
                    // O'qituvchi biriktirish oynasidan dars birligiga xona
                    'admin.timetable.assign-room',
                ]);
            }

            if (!$request->routeIs($allowed)) {
                abort(403, 'Bu amal uchun ruxsat berilmagan.');
            }
        }

        return $this->withHistory($request, $next, $user, $activeRole);
    }

    /**
     * Jadvalni o'zgartiradigan amal tarixga yoziladi. Doskada "tasdiq talab qilinadi" yoqilgan
     * bo'lsa, kafedra mudirining amali darhol bajarilmaydi — registrator ofisiga so'rov bo'lib boradi.
     */
    private function withHistory(Request $request, Closure $next, $user, string $activeRole)
    {
        if (!TimetableChangeApproval::isChange($request)) {
            return $next($request);
        }
        $board = $this->routeBoard($request);
        $approval = app(TimetableChangeApproval::class);

        if (TimetableChangeApproval::shouldIntercept($request, $activeRole, $board)) {
            // Bo'sh darsga birinchi biriktirish — tasdiqsiz. Tekshiruv va yozish bitta doska qulfi ostida:
            // parallel so'rov darsni endi bo'sh ko'rmaydi va tasdiqqa boradi.
            $direct = isset(TimetableChangeApproval::ASSIGN_FIELDS[(string) $request->route()->getName()])
                ? DB::transaction(function () use ($request, $next, $user, $activeRole, $board, $approval) {
                    \App\Models\TimetableBoard::whereKey($board->id)->lockForUpdate()->first();

                    return TimetableChangeApproval::isFirstAssignment($request, $board)
                        ? $this->runRecorded($request, $next, $user, $activeRole, $board, $approval, true)
                        : null;
                })
                : null;
            if ($direct) {
                return $direct;
            }

            $draft = $approval->draft($request, $user, $board, $activeRole);
            // Sinov bajarish: amal hozir o'tadimi (kafedra, to'qnashuv va h.k. tekshiruvlari)?
            // Natija bekor qilinadi (rollback) — xato bo'lsa, so'rov yaratilmaydi, xato darhol ko'rinadi.
            DB::beginTransaction();
            try {
                $trial = $next($request);
            } finally {
                DB::rollBack();
            }
            if (!TimetableChangeApproval::succeeded($trial)) {
                return $trial;
            }

            $changeRequest = $approval->submit($draft);

            return response()->json([
                'ok' => true,
                'pending_approval' => true,
                'change_request_id' => $changeRequest->id,
                'message' => 'O\'zgarish registrator ofisiga tasdiqlash uchun yuborildi. Tasdiqlangach jadvalga tushadi.',
                'summary' => $changeRequest->summary,
            ]);
        }

        return $this->runRecorded($request, $next, $user, $activeRole, $board, $approval, false);
    }

    /** Amalni bajaradi va tarixga "qo'llangan" deb yozadi; birinchi biriktirishda mudirning eskirgan so'rovlari bekor qilinadi. */
    private function runRecorded(Request $request, Closure $next, $user, string $activeRole, ?\App\Models\TimetableBoard $board,
        TimetableChangeApproval $approval, bool $firstAssignment)
    {
        // Tarix yordamchi: yozib bo'lmasa ham amalning o'zi to'xtamaydi.
        $draft = null;
        if ($board) {
            try {
                $draft = $approval->draft($request, $user, $board, $activeRole);
            } catch (\Throwable $e) {
                Log::warning('[TimetableChange] history draft: ' . $e->getMessage());
            }
        }
        $response = $next($request);
        if ($draft && TimetableChangeApproval::succeeded($response)) {
            try {
                $approval->record($draft);
                if ($firstAssignment) {
                    $approval->supersedeAssignments($draft);
                }
            } catch (\Throwable $e) {
                Log::warning('[TimetableChange] history record: ' . $e->getMessage());
            }
        }

        return $response;
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
