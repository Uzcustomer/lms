<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LessonOpening;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Dars ochish so'rovlarini ko'rib chiqish.
 *
 * O'qituvchi o'tkazib yuborilgan kun uchun so'rov yuboradi. 1-so'rovni
 * o'quv prorektori tasdiqlaydi; 2-so'rovdan boshlab registrator ofisi ham
 * tasdiqlashi kerak (tartib ixtiyoriy). Kerakli hamma tasdiq olingach dars
 * ochiladi va o'qituvchining baho qo'yish muddati AYNAN SHU PAYTDAN
 * hisoblanadi. Istalgan bosqichdagi rad etish so'rovni yopadi.
 */
class LessonOpeningRequestController extends Controller
{
    public const STAGE_PROREKTOR = 'prorektor';
    public const STAGE_REGISTRAR = 'registrar';

    public function index(Request $request): View
    {
        $status = in_array($request->input('status'), ['pending', 'active', 'expired', 'rejected', 'all'], true)
            ? $request->input('status')
            : 'pending';

        if (Schema::hasTable('lesson_openings')) {
            LessonOpening::expireOverdue();
        }

        $stage = $this->stage();

        $query = LessonOpening::query();
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        // Kutilayotganlar ichida avval shu foydalanuvchi qarori kerak bo'lganlari
        if ($stage === self::STAGE_REGISTRAR) {
            $query->orderByRaw("CASE WHEN status = 'pending' AND needs_registrar = 1 AND registrar_status IS NULL THEN 0 ELSE 1 END");
        } elseif ($stage === self::STAGE_PROREKTOR) {
            $query->orderByRaw("CASE WHEN status = 'pending' AND prorektor_status IS NULL THEN 0 ELSE 1 END");
        }
        $query->latest();

        $openings = $query->paginate(30)->withQueryString();

        // Guruh va fan nomlari bitta so'rovda (har qator uchun alohida emas)
        $groups = DB::table('groups')
            ->whereIn('group_hemis_id', $openings->pluck('group_hemis_id')->unique())
            ->get(['id', 'name', 'group_hemis_id'])
            ->keyBy('group_hemis_id');

        $subjectNames = DB::table('schedules')
            ->whereIn('subject_id', $openings->pluck('subject_id')->unique())
            ->whereNull('deleted_at')
            ->select('subject_id', DB::raw('MAX(subject_name) as subject_name'))
            ->groupBy('subject_id')
            ->pluck('subject_name', 'subject_id');

        $teachers = $this->lessonTeachers($openings->getCollection());

        $counts = LessonOpening::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Shu foydalanuvchi qarorini kutayotganlar soni
        $myQueue = match ($stage) {
            self::STAGE_REGISTRAR => LessonOpening::where('status', LessonOpening::STATUS_PENDING)
                ->where('needs_registrar', true)->whereNull('registrar_status')->count(),
            self::STAGE_PROREKTOR => LessonOpening::where('status', LessonOpening::STATUS_PENDING)
                ->whereNull('prorektor_status')->count(),
            default => null,
        };

        return view('admin.lesson-openings.index', [
            'openings' => $openings,
            'status' => $status,
            'counts' => $counts,
            'groups' => $groups,
            'subjectNames' => $subjectNames,
            'teachers' => $teachers,
            'stage' => $stage,
            'myQueue' => $myQueue,
            'canReview' => $stage !== null,
            'openingDays' => max((int) Setting::get('lesson_opening_days', 3), 1),
        ]);
    }

    /**
     * Tasdiqlash — joriy foydalanuvchining bosqichi bo'yicha. Kerakli
     * hamma tasdiq olingan bo'lsa dars ochiladi, muddat hozirdan hisoblanadi.
     */
    public function approve(LessonOpening $opening): RedirectResponse
    {
        $stage = $this->stage();
        abort_unless($stage !== null, 403);

        if (!$this->awaitsStage($opening, $stage)) {
            return back()->with('error', "Bu so'rov bo'yicha sizning qaroringiz kerak emas yoki allaqachon ko'rib chiqilgan.");
        }

        $reviewer = $this->reviewer();

        $opening = DB::transaction(function () use ($opening, $stage, $reviewer) {
            // Registrator va prorektor bir vaqtda bossa ham holat to'g'ri qolsin
            $opening = LessonOpening::whereKey($opening->id)->lockForUpdate()->first();
            if (!$opening || !$this->awaitsStage($opening, $stage)) {
                return null;
            }

            if ($stage === self::STAGE_REGISTRAR) {
                $opening->fill([
                    'registrar_status' => LessonOpening::DECISION_APPROVED,
                    'registrar_id' => $reviewer['id'],
                    'registrar_name' => $reviewer['name'],
                    'registrar_guard' => $reviewer['guard'],
                    'registrar_at' => now(),
                ]);
            } else {
                $opening->fill([
                    'prorektor_status' => LessonOpening::DECISION_APPROVED,
                    'reviewed_by_id' => $reviewer['id'],
                    'reviewed_by_name' => $reviewer['name'],
                    'reviewed_by_guard' => $reviewer['guard'],
                    'reviewed_at' => now(),
                ]);
            }

            if ($opening->allApprovalsGiven()) {
                $days = max((int) Setting::get('lesson_opening_days', 3), 1);
                $opening->fill([
                    'status' => LessonOpening::STATUS_ACTIVE,
                    'deadline' => Carbon::now('Asia/Tashkent')->addDays($days)->endOfDay(),
                    'review_comment' => null,
                ]);
            }

            $opening->save();

            return $opening;
        });

        if (!$opening) {
            return back()->with('error', "Bu so'rov allaqachon ko'rib chiqilgan.");
        }

        if ($opening->status === LessonOpening::STATUS_ACTIVE) {
            // O'qituvchiga xabar faqat haqiqatan ochilganda boradi
            JournalController::notifyTeachersAboutOpening($opening->fresh());
            $days = max((int) Setting::get('lesson_opening_days', 3), 1);

            return back()->with('success', "Dars ochildi. O'qituvchi {$days} kun ichida baho qo'ya oladi.");
        }

        $waiting = $stage === self::STAGE_REGISTRAR ? "o'quv prorektori" : 'registrator ofisi';

        return back()->with('success', "Tasdiqlandi. Dars {$waiting} ham tasdiqlagach ochiladi.");
    }

    /** Rad etish: sababi majburiy, o'qituvchi uni jurnalda ko'radi. */
    public function reject(Request $request, LessonOpening $opening): RedirectResponse
    {
        $stage = $this->stage();
        abort_unless($stage !== null, 403);

        $data = $request->validate([
            'comment' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'comment.required' => 'Rad etish sababini yozing.',
            'comment.min' => 'Sabab juda qisqa.',
        ]);

        if (!$this->awaitsStage($opening, $stage)) {
            return back()->with('error', "Bu so'rov bo'yicha sizning qaroringiz kerak emas yoki allaqachon ko'rib chiqilgan.");
        }

        $reviewer = $this->reviewer();
        $decision = [
            'status' => LessonOpening::STATUS_REJECTED,
            'deadline' => null,
            'review_comment' => trim($data['comment']),
        ];

        if ($stage === self::STAGE_REGISTRAR) {
            $decision += [
                'registrar_status' => LessonOpening::DECISION_REJECTED,
                'registrar_id' => $reviewer['id'],
                'registrar_name' => $reviewer['name'],
                'registrar_guard' => $reviewer['guard'],
                'registrar_at' => now(),
            ];
        } else {
            $decision += [
                'prorektor_status' => LessonOpening::DECISION_REJECTED,
                'reviewed_by_id' => $reviewer['id'],
                'reviewed_by_name' => $reviewer['name'],
                'reviewed_by_guard' => $reviewer['guard'],
                'reviewed_at' => now(),
            ];
        }

        $opening->update($decision);

        return back()->with('success', "So'rov rad etildi.");
    }

    /**
     * Har bir so'rov uchun o'sha kuni darsni o'tishi kerak bo'lgan
     * o'qituvchi(lar): [opening_id => [['name' => ..., 'type' => ...], ...]].
     * O'sha kungi jadval qatori o'chirilgan bo'lsa — fanning shu guruhdagi
     * o'qituvchisi ko'rsatiladi.
     */
    private function lessonTeachers($openings): array
    {
        if ($openings->isEmpty()) {
            return [];
        }

        $groupIds = $openings->pluck('group_hemis_id')->unique()->values();
        $subjectIds = $openings->pluck('subject_id')->unique()->values();
        $dates = $openings->map(fn ($o) => $o->lesson_date?->format('Y-m-d'))->filter()->unique()->values();

        $key = fn ($group, $subject, $semester) => $group . '|' . $subject . '|' . $semester;

        $byDay = [];
        DB::table('schedules')
            ->whereIn('group_id', $groupIds)
            ->whereIn('subject_id', $subjectIds)
            ->whereIn(DB::raw('DATE(lesson_date)'), $dates)
            ->whereNull('deleted_at')
            ->orderBy('lesson_pair_code')
            ->get(['group_id', 'subject_id', 'semester_code', 'employee_name', 'training_type_name', DB::raw('DATE(lesson_date) as day')])
            ->each(function ($row) use (&$byDay, $key) {
                $byDay[$key($row->group_id, $row->subject_id, $row->semester_code) . '|' . $row->day][] = $row;
            });

        $result = [];
        $missing = [];
        foreach ($openings as $opening) {
            $rows = $byDay[$key($opening->group_hemis_id, $opening->subject_id, $opening->semester_code) . '|' . $opening->lesson_date?->format('Y-m-d')] ?? [];
            if ($rows) {
                $result[$opening->id] = $this->uniqueTeachers($rows);
            } else {
                $missing[] = $opening;
            }
        }

        if ($missing) {
            $fallback = [];
            DB::table('schedules')
                ->whereIn('group_id', collect($missing)->pluck('group_hemis_id')->unique()->values())
                ->whereIn('subject_id', collect($missing)->pluck('subject_id')->unique()->values())
                ->whereNull('deleted_at')
                ->select('group_id', 'subject_id', 'semester_code', 'employee_name', 'training_type_name')
                ->distinct()
                ->get()
                ->each(function ($row) use (&$fallback, $key) {
                    $fallback[$key($row->group_id, $row->subject_id, $row->semester_code)][] = $row;
                });

            foreach ($missing as $opening) {
                $rows = $fallback[$key($opening->group_hemis_id, $opening->subject_id, $opening->semester_code)] ?? [];
                $result[$opening->id] = $this->uniqueTeachers($rows);
            }
        }

        return $result;
    }

    /** Bir o'qituvchi bir necha juftlikda bo'lsa — bir marta, turlari bilan. */
    private function uniqueTeachers(array $rows): array
    {
        $teachers = [];
        foreach ($rows as $row) {
            $name = trim((string) $row->employee_name);
            if ($name === '') {
                continue;
            }
            $teachers[$name] ??= ['name' => $name, 'types' => []];
            $type = trim((string) $row->training_type_name);
            if ($type !== '' && !in_array($type, $teachers[$name]['types'], true)) {
                $teachers[$name]['types'][] = $type;
            }
        }

        return array_values($teachers);
    }

    /** Shu so'rov hozir berilgan bosqich qarorini kutyaptimi */
    private function awaitsStage(LessonOpening $opening, string $stage): bool
    {
        return $stage === self::STAGE_REGISTRAR
            ? $opening->isAwaitingRegistrar()
            : $opening->isAwaitingProrektor();
    }

    /**
     * Joriy foydalanuvchi qaysi bosqichni tasdiqlaydi (faol rol bo'yicha):
     * registrator ofisi — 'registrar', o'quv prorektori va superadmin —
     * 'prorektor'. Admin faqat ko'radi (null).
     */
    private function stage(): ?string
    {
        $user = auth()->guard('web')->user() ?? auth()->guard('teacher')->user();
        if (!$user || !method_exists($user, 'getRoleNames')) {
            return null;
        }

        $roles = $user->getRoleNames()->all();
        $active = (string) session('active_role', '');
        if (!in_array($active, $roles, true)) {
            $active = collect(['oquv_prorektori', 'registrator_ofisi', 'superadmin'])
                ->first(fn ($role) => in_array($role, $roles, true)) ?? '';
        }

        return match ($active) {
            'registrator_ofisi' => self::STAGE_REGISTRAR,
            'oquv_prorektori', 'superadmin' => self::STAGE_PROREKTOR,
            default => null,
        };
    }

    private function reviewer(): array
    {
        $teacher = auth()->guard('teacher')->user();
        $web = auth()->guard('web')->user();
        $user = $web ?? $teacher;

        return [
            'id' => $user?->id,
            'name' => $user->name ?? $user->full_name ?? 'Unknown',
            'guard' => $teacher ? 'teacher' : 'web',
        ];
    }
}
