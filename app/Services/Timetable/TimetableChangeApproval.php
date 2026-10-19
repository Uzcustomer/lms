<?php

namespace App\Services\Timetable;

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentNotification;
use App\Models\Teacher;
use App\Models\TeacherNotification;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableChangeRequest;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Dars jadvali o'zgarishlarini registrator ofisi tasdig'idan o'tkazish.
 *
 * Doska sozlamasida "approval_required" yoqilgan bo'lsa, kafedra mudirining jadvalni
 * o'zgartiradigan so'rovi (joylash, ko'chirish, kun soati, o'qituvchi/xona) darhol
 * bajarilmaydi — `timetable_change_requests` ga yoziladi. Registrator tasdiqlaganda
 * asl so'rov kafedra mudiri nomidan qayta bajariladi (barcha tekshiruvlar bilan), so'ng
 * guruh talabalari, o'qituvchi, o'quv bo'limi, dekanat va kafedra mudiriga xabar boradi.
 */
class TimetableChangeApproval
{
    /** Tasdiq talab qiladigan route'lar: [controller metodi, model parametri, turi]. */
    public const GATED = [
        'admin.timetable.cards.place'         => ['placeCard', 'card', 'move'],
        'admin.timetable.cards.week-override' => ['weekOverride', 'card', 'move'],
        'admin.timetable.boards.unplace'      => ['unplaceAll', 'board', 'unplace'],
        'admin.timetable.cycle-plan'          => ['cyclePlan', 'board', 'unplace'],   // faqat clear=1
        'admin.timetable.cycle-place'         => ['cyclePlace', 'board', 'cycle'],
        'admin.timetable.cycle-assign'        => ['cycleAssignSave', 'board', 'teacher'],
        'admin.timetable.cycle-lecture-slots' => ['cycleLectureSlots', 'board', 'hour'],
        'admin.timetable.assign-teacher'      => ['assignTeacher', 'board', 'teacher'],
        'admin.timetable.assign-room'         => ['assignUnitRoom', 'board', 'room'],
    ];

    /** Tasdiqlay oladigan rollar. */
    public const APPROVER_ROLES = ['superadmin', 'admin', 'registrator_ofisi'];

    /** Tasdiqdan o'tishi kerak bo'lgan rollar. */
    public const GATED_ROLES = ['kafedra_mudiri'];

    public static function tableReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('timetable_change_requests');
    }

    public static function boardRequiresApproval(?TimetableBoard $board): bool
    {
        return $board && self::tableReady() && !empty(($board->settings ?? [])['approval_required']);
    }

    /** Shu so'rov tasdiqqa yuborilishi kerakmi (o'zgartiradigan amal + gated rol + doska rejimi). */
    public static function shouldIntercept(Request $request, string $activeRole, ?TimetableBoard $board): bool
    {
        if (!in_array($activeRole, self::GATED_ROLES, true) || !self::boardRequiresApproval($board)) {
            return false;
        }
        $route = (string) optional($request->route())->getName();
        if (!isset(self::GATED[$route])) {
            return false;
        }
        // Sikl rejasi faqat "Bo'shatish" (clear=1) bo'lganda o'zgartiradi; filtrli ko'rinishda clear yo'q.
        if ($route === 'admin.timetable.cycle-plan') {
            return $request->boolean('clear') && !$request->boolean('mine') && !$request->boolean('kafedra');
        }

        return true;
    }

    /** So'rovni yozib qo'yadi (bir xil joyga oldingi kutilayotgan so'rov bo'lsa — almashtiradi). */
    public function capture(Request $request, $requester, TimetableBoard $board): TimetableChangeRequest
    {
        $route = (string) $request->route()->getName();
        [, $param, $kind] = self::GATED[$route];
        $payload = collect($request->except(['_token', '_method']))->all();
        $hint = trim((string) ($payload['_change_hint'] ?? ''));
        unset($payload['_change_hint']);

        $card = $param === 'card' ? $this->routeCard($request) : null;
        $params = ['board' => $board->id] + ($card ? ['card' => $card->id] : []);

        $cards = $this->affectedCards($board, $route, $payload, $card);
        $details = $this->detailsFromCards($cards);
        [$subject, $core] = $this->describe($board, $route, $payload, $card, $cards);
        if ($route === 'admin.timetable.cycle-assign' || $route === 'admin.timetable.assign-teacher') {
            if (!empty($payload['teacher_id'])) {
                $details['new_teacher_id'] = (int) $payload['teacher_id'];
            }
        }

        $targetKey = $this->targetKey($route, $payload, $card, $board);
        $kafedra = $this->requesterKafedra($requester);

        return DB::transaction(function () use ($board, $requester, $route, $targetKey, $params, $payload, $kind, $subject, $core, $hint, $details, $kafedra) {
            // Shu kafedra mudirining shu joy bo'yicha kutilayotgan so'rovi — yangisi bilan almashtiriladi.
            TimetableChangeRequest::where('board_id', $board->id)
                ->where('requester_id', $requester?->id)
                ->where('target_key', $targetKey)
                ->where('status', TimetableChangeRequest::PENDING)
                ->update(['status' => TimetableChangeRequest::CANCELLED, 'review_note' => 'Yangi so\'rov bilan almashtirildi', 'reviewed_at' => now()]);

            $req = TimetableChangeRequest::create([
                'board_id' => $board->id,
                'requester_id' => $requester?->id,
                'requester_name' => $requester?->full_name ?? $requester?->name,
                'kafedra_name' => $kafedra,
                'route_name' => $route,
                'target_key' => $targetKey,
                'params' => $params,
                'payload' => $payload,
                'kind' => $kind,
                'subject_name' => $subject,
                'summary' => $core . ($hint !== '' ? ' — ' . mb_substr($hint, 0, 300) : ''),
                'details' => $details,
                'status' => TimetableChangeRequest::PENDING,
            ]);

            $this->notifyRegistrars($req);

            return $req;
        });
    }

    /**
     * Tasdiqlash: asl so'rov kafedra mudiri nomidan qayta bajariladi.
     * @return array{ok: bool, error: ?string}
     */
    public function apply(TimetableChangeRequest $req, $approver): array
    {
        [$method, $param] = self::GATED[$req->route_name] ?? [null, null];
        $requester = $req->requester;
        if (!$method || !$requester) {
            return $this->finish($req, $approver, false, 'So\'rov yuboruvchi yoki amal topilmadi.');
        }
        $board = TimetableBoard::find($req->board_id);
        $model = $param === 'card'
            ? TimetableCard::find($req->params['card'] ?? 0)
            : $board;
        if (!$board || !$model) {
            return $this->finish($req, $approver, false, 'Doska yoki kartochka endi mavjud emas.');
        }

        $sub = Request::create('/', 'POST', $req->payload ?? []);
        $sub->headers->set('Accept', 'application/json');
        $sub->setUserResolver(fn () => $requester);
        if (request()->hasSession()) {
            $sub->setLaravelSession(request()->session());
        }

        $prevRole = session('active_role');
        session(['active_role' => 'kafedra_mudiri']);
        $error = null;
        try {
            $response = app(TimetableController::class)->{$method}($sub, $model);
            $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;
            $data = $response instanceof JsonResponse ? $response->getData(true) : [];
            if ($status >= 400 || !empty($data['error'])) {
                $error = (string) ($data['error'] ?? $data['message'] ?? ('HTTP ' . $status));
            }
        } catch (ValidationException $e) {
            $error = collect($e->errors())->flatten()->first() ?: 'Ma\'lumot noto\'g\'ri.';
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $error = $e->getMessage() ?: ('HTTP ' . $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('[TimetableChange] apply failed: ' . $e->getMessage(), ['request_id' => $req->id]);
            $error = 'Bajarishda xatolik: ' . $e->getMessage();
        } finally {
            session(['active_role' => $prevRole]);
        }

        $result = $this->finish($req, $approver, $error === null, $error);
        if ($result['ok']) {
            $id = $req->id;
            dispatch(function () use ($id) {
                $fresh = TimetableChangeRequest::find($id);
                if ($fresh) {
                    app(self::class)->notifyApproved($fresh);
                }
            })->afterResponse();
        }

        return $result;
    }

    public function reject(TimetableChangeRequest $req, $approver, ?string $note): void
    {
        $req->update([
            'status' => TimetableChangeRequest::REJECTED,
            'reviewer_id' => $approver?->id,
            'reviewer_name' => $approver?->full_name ?? $approver?->name,
            'review_note' => $note,
            'reviewed_at' => now(),
        ]);
        if ($req->requester) {
            $this->sendToTeacher($req->requester, 'Dars jadvali o\'zgarishi rad etildi',
                $req->summary . ($note ? "\nIzoh: " . $note : ''), $req);
        }
    }

    private function finish(TimetableChangeRequest $req, $approver, bool $ok, ?string $error): array
    {
        $req->update([
            'status' => $ok ? TimetableChangeRequest::APPROVED : TimetableChangeRequest::FAILED,
            'reviewer_id' => $approver?->id,
            'reviewer_name' => $approver?->full_name ?? $approver?->name,
            'error' => $error,
            'reviewed_at' => now(),
        ]);

        return ['ok' => $ok, 'error' => $error];
    }

    // ─── Tavsif va qamrov ──────────────────────────────────────────

    private function routeCard(Request $request): ?TimetableCard
    {
        // Bazadan qayta o'qiladi: sinov bajarish route'dagi kartani xotirada o'zgartirib
        // qo'yadi (rollback faqat bazani tiklaydi) — tavsifda eski joy ko'rinsin.
        $card = $request->route('card');
        $id = $card instanceof TimetableCard ? $card->getKey() : $card;

        return $id ? TimetableCard::find($id) : null;
    }

    private function affectedCards(TimetableBoard $board, string $route, array $p, ?TimetableCard $card)
    {
        if ($card) {
            return collect([$card]);
        }
        if (in_array($route, ['admin.timetable.boards.unplace', 'admin.timetable.cycle-plan'], true)) {
            return collect();
        }
        $controller = app(TimetableController::class);

        return $controller->changeRequestCards($board, $route, $p);
    }

    private function detailsFromCards($cards): array
    {
        $controller = app(TimetableController::class);
        $groups = [];
        $teachers = [];
        $faculties = [];
        $specialty = null;
        $course = null;
        foreach ($cards as $c) {
            foreach ($controller->changeRequestCardGroups($c) as $g) {
                $groups[$g] = true;
            }
            if ($c->teacher_id) {
                $teachers[(int) $c->teacher_id] = true;
            }
            if ($c->faculty_name) {
                $faculties[$c->faculty_name] = true;
            }
            $specialty ??= $c->specialty_name;
            $course ??= (int) $c->course;
        }

        return [
            'groups' => array_keys($groups),
            'teacher_ids' => array_keys($teachers),
            'faculties' => array_keys($faculties),
            'specialty' => $specialty,
            'course' => $course,
            'card_ids' => $cards->pluck('id')->take(200)->values()->all(),
        ];
    }

    private function describe(TimetableBoard $board, string $route, array $p, ?TimetableCard $card, $cards): array
    {
        $type = fn ($t) => $t === 'lecture' ? 'ma\'ruza' : ($t === 'practice' ? 'amaliy' : '');
        $dayName = function ($d) use ($board) {
            $names = $board->day_names ?: \App\Models\TimetableBoard::DEFAULT_DAY_NAMES;

            return $names[((int) $d) - 1] ?? ($d . '-kun');
        };
        $slot = fn ($d, $pr) => ($d && $pr) ? $dayName($d) . ', ' . $pr . '-para' : 'joylanmagan';

        $subject = $card->subject_name ?? ($p['subject_name'] ?? null);
        $group = $card
            ? ($card->group_name ?: implode(', ', (array) $card->group_names) ?: $card->oqim_label)
            : ($p['group_name'] ?? ($p['oqim_label'] ?? ''));
        $head = trim($subject . ($group ? ' · ' . $group : '') . (($card->training_type ?? $p['training_type'] ?? null) ? ' (' . $type($card->training_type ?? $p['training_type']) . ')' : ''));

        switch ($route) {
            case 'admin.timetable.cards.place':
                $to = (!empty($p['day']) && !empty($p['pair'])) ? $slot($p['day'], $p['pair']) : 'jadvaldan olib tashlash';
                $text = $head . ': ' . $slot($card->day, $card->pair) . ' → ' . $to;
                break;
            case 'admin.timetable.cards.week-override':
                $act = ($p['action'] ?? '') === 'cancel' ? 'dars bekor qilinadi'
                    : (($p['action'] ?? '') === 'reset' ? 'asl joyiga qaytariladi' : 'ko\'chiriladi → ' . $slot($p['day'] ?? null, $p['pair'] ?? null));
                $text = $head . ': ' . ($p['week'] ?? '?') . '-hafta — ' . $act;
                break;
            case 'admin.timetable.cycle-lecture-slots':
                $day = isset($p['day_index']) ? ((int) $p['day_index'] + 1) . '-kun' : 'kunlar';
                $text = $head . ': ' . $day . (isset($p['start_hour']) ? ' — darslar ' . (int) $p['start_hour'] . '-soatdan boshlanadi' : ' — kun soatlari o\'zgartiriladi');
                break;
            case 'admin.timetable.cycle-place':
                $act = $p['action'] ?? 'place';
                $text = $head . ': sikl bloki ' . ($act === 'unplace' ? 'bo\'shatiladi' : (isset($p['start_index']) ? ((int) $p['start_index'] + 1) . '-kundan boshlab joylanadi' : 'ko\'chiriladi'));
                break;
            case 'admin.timetable.cycle-assign':
            case 'admin.timetable.assign-teacher':
                $t = !empty($p['teacher_id']) ? (Teacher::find($p['teacher_id'])?->full_name ?? ('#' . $p['teacher_id'])) : 'olib tashlanadi';
                $text = $head . ': o\'qituvchi → ' . $t;
                if (!empty($p['auditorium_code'])) {
                    $text .= '; xona → ' . $p['auditorium_code'];
                }
                break;
            case 'admin.timetable.assign-room':
                $text = $head . ': xona → ' . (!empty($p['auditorium_code']) ? $p['auditorium_code'] : 'olib tashlanadi');
                break;
            default:
                $scope = trim(implode(', ', array_filter([
                    implode('/', (array) ($p['faculty_names'] ?? [])),
                    implode('/', (array) ($p['specialty_names'] ?? [])) ?: ($p['specialty_name'] ?? ''),
                    ($p['courses'] ?? null) ? implode(',', (array) $p['courses']) . '-kurs' : (!empty($p['course']) ? $p['course'] . '-kurs' : ''),
                ])));
                $text = ($route === 'admin.timetable.cycle-plan' ? 'Sikl bloklarini bo\'shatish' : 'Joylashuvlarni bo\'shatish') . ($scope ? ': ' . $scope : '');
        }

        return [$subject, $text];
    }

    private function targetKey(string $route, array $p, ?TimetableCard $card, TimetableBoard $board): string
    {
        $pick = fn (array $keys) => implode('|', array_map(fn ($k) => mb_strtolower(trim((string) (is_array($p[$k] ?? null) ? implode(',', $p[$k]) : ($p[$k] ?? '')))), $keys));
        $short = match ($route) {
            'admin.timetable.cards.place' => 'card:' . $card?->id,
            'admin.timetable.cards.week-override' => 'card:' . $card?->id . ':w' . ($p['week'] ?? ''),
            'admin.timetable.cycle-lecture-slots' => $pick(['specialty_name', 'course', 'subject_name', 'group_name', 'placement_group', 'sub_group', 'training_type', 'day_index']),
            'admin.timetable.cycle-place', 'admin.timetable.cycle-assign' => $pick(['specialty_name', 'course', 'subject_name', 'group_name', 'training_type']),
            'admin.timetable.assign-teacher', 'admin.timetable.assign-room' => $pick(['faculty_name', 'specialty_name', 'course', 'subject_name', 'training_type', 'oqim_label', 'group_name']),
            default => md5(json_encode([$p['faculty_names'] ?? null, $p['specialty_names'] ?? null, $p['specialty_name'] ?? null, $p['courses'] ?? null, $p['course'] ?? null])),
        };

        return mb_substr($route . '#' . md5($short), 0, 191);
    }

    private function requesterKafedra($requester): ?string
    {
        if (!$requester) {
            return null;
        }
        $deptId = $requester->head_department_hemis_id ?? null ?: ($requester->department_hemis_id ?? null);
        $name = $deptId ? \App\Models\Department::where('department_hemis_id', $deptId)->value('name') : null;

        return $name ?: ($requester->department ?? null);
    }

    // ─── Xabarlar ──────────────────────────────────────────────────

    private function notifyRegistrars(TimetableChangeRequest $req): void
    {
        $title = 'Dars jadvali o\'zgarishi — tasdiq kutilmoqda';
        $body = ($req->kafedra_name ? $req->kafedra_name . "\n" : '') . ($req->requester_name ? $req->requester_name . ":\n" : '') . $req->summary;
        $id = $req->id;
        dispatch(function () use ($title, $body, $id) {
            $svc = app(self::class);
            $req = TimetableChangeRequest::find($id);
            if (!$req || $req->status !== TimetableChangeRequest::PENDING) {
                return;
            }
            $svc->teachersWithRoles([\App\Enums\ProjectRole::REGISTRAR_OFFICE->value])
                ->each(fn (Teacher $t) => $svc->sendToTeacher($t, $title, $body, $req));
        })->afterResponse();
    }

    public function notifyApproved(TimetableChangeRequest $req): void
    {
        $details = $req->details ?? [];
        $title = 'Dars jadvali o\'zgardi';
        $body = $req->summary . "\n(Registrator ofisi tasdiqladi" . ($req->reviewer_name ? ': ' . $req->reviewer_name : '') . ')';
        $notified = [];
        $send = function (Teacher $t, ?string $customTitle = null) use (&$notified, $title, $body, $req) {
            if (isset($notified[$t->id])) {
                return;
            }
            $notified[$t->id] = true;
            $this->sendToTeacher($t, $customTitle ?? $title, $body, $req);
        };

        // Kafedra mudiri — natija
        if ($req->requester) {
            $send($req->requester, 'Dars jadvali o\'zgarishi tasdiqlandi');
        }
        // O'qituvchi(lar)
        $teacherIds = array_unique(array_merge((array) ($details['teacher_ids'] ?? []), array_filter([(int) ($details['new_teacher_id'] ?? 0)])));
        if ($teacherIds) {
            Teacher::whereIn('id', $teacherIds)->get()->each(fn (Teacher $t) => $send($t));
        }
        // O'quv bo'limi
        $this->teachersWithRoles([
            \App\Enums\ProjectRole::ACADEMIC_DEPARTMENT->value,
            \App\Enums\ProjectRole::ACADEMIC_DEPARTMENT_HEAD->value,
        ])->each(fn (Teacher $t) => $send($t));

        // Guruh talabalari
        $students = $this->studentsOfGroups((array) ($details['groups'] ?? []), (array) ($details['faculties'] ?? []));
        $facultyIds = $students->pluck('department_id')->filter()->map(fn ($v) => (int) $v)->unique()->all();
        // Dekanat — talabalar fakultetining dekani
        if ($facultyIds) {
            $this->teachersWithRoles([\App\Enums\ProjectRole::DEAN->value])
                ->filter(fn (Teacher $t) => (bool) array_intersect($facultyIds, array_map('intval', (array) $t->deanFacultyIds)))
                ->each(fn (Teacher $t) => $send($t));
        }

        $link = Route::has('student.schedule') ? route('student.schedule') : null;
        $telegram = new TelegramService();
        foreach ($students as $st) {
            try {
                StudentNotification::create([
                    'student_id' => $st->id,
                    'type' => 'timetable_change',
                    'title' => 'Dars jadvalingiz o\'zgardi',
                    'message' => $req->summary,
                    'link' => $link,
                    'data' => ['timetable_change_request_id' => $req->id],
                ]);
            } catch (\Throwable $e) {
                Log::warning('[TimetableChange] StudentNotification: ' . $e->getMessage());
            }
            if (!empty($st->telegram_chat_id) && !empty($st->telegram_verified_at)) {
                $telegram->sendToUser((string) $st->telegram_chat_id,
                    '<b>📅 Dars jadvalingiz o\'zgardi</b>' . "\n\n" . e($req->summary));
            }
        }
    }

    /** Faol xodimlar shu rollar bo'yicha (bazada yo'q rol xato bermaydi). */
    public function teachersWithRoles(array $roles)
    {
        $existing = \Spatie\Permission\Models\Role::whereIn('name', $roles)->pluck('name')->unique()->values()->all();

        return $existing ? Teacher::role($existing)->where('status', true)->get() : collect();
    }

    /**
     * Guruhlarning faol talabalari. Bir xil nomli guruh boshqa fakultetda ham bo'lishi
     * mumkin — kartaning fakulteti ma'lum bo'lsa, shu fakultet talabalari olinadi.
     */
    private function studentsOfGroups(array $groups, array $faculties = [])
    {
        $bases = [];
        foreach ($groups as $g) {
            $base = trim((string) preg_replace('/\s*\(.*$/u', '', (string) $g));
            if ($base !== '') {
                $bases[$base] = true;
            }
        }
        if (!$bases) {
            return collect();
        }

        $query = Student::where('student_status_code', 11)
            ->where(function ($q) use ($bases) {
                foreach (array_keys($bases) as $b) {
                    $q->orWhere('group_name', $b)->orWhere('group_name', 'like', $b . ' (%');
                }
            });
        $faculties = array_values(array_filter($faculties, fn ($f) => trim((string) $f) !== ''));
        if ($faculties) {
            $query->whereIn('department_name', $faculties);
        }

        return $query->get(['id', 'department_id', 'telegram_chat_id', 'telegram_verified_at', 'group_name']);
    }

    public function sendToTeacher(Teacher $teacher, string $title, string $message, TimetableChangeRequest $req): void
    {
        $link = Route::has('admin.timetable.index') ? route('admin.timetable.index') . '?board=' . $req->board_id : null;
        try {
            TeacherNotification::create([
                'teacher_id' => $teacher->id,
                'type' => 'timetable_change',
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'data' => ['timetable_change_request_id' => $req->id],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TimetableChange] TeacherNotification: ' . $e->getMessage());
        }
        try {
            Notification::create([
                'recipient_id' => $teacher->id,
                'recipient_type' => Teacher::class,
                'subject' => $title,
                'body' => $message,
                'type' => 'timetable_change',
                'url' => $link,
                'data' => ['timetable_change_request_id' => $req->id],
                'is_read' => false,
                'is_draft' => false,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TimetableChange] Notification: ' . $e->getMessage());
        }
        if (!empty($teacher->telegram_chat_id)) {
            (new TelegramService())->sendToUser((string) $teacher->telegram_chat_id,
                '<b>' . e($title) . '</b>' . "\n\n" . e($message));
        }
    }
}
