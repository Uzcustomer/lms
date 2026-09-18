<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LessonOpening;
use App\Models\Setting;
use App\Services\LessonOpeningNotifier;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Dars ochish so'rovlarini ko'rib chiqish.
 *
 * Tasdiqlovchilar so'rov raqamiga bog'liq (LessonOpening::stagesFor):
 * 1-so'rov — registrator ofisi; 2-so'rov — u va o'quv bo'limi boshlig'i;
 * 3-dan boshlab — ular va o'quv prorektori. Har bir tasdiqlovchi faqat o'zi
 * qatnashadigan so'rovlarni ko'radi. Tartib ixtiyoriy; bittasi rad etsa
 * so'rov rad etiladi, lekin rad etgan tomon keyin fikrini o'zgartirib
 * tasdiqlashi mumkin. Hamma tasdiqlagach dars ochiladi va o'qituvchining
 * baho qo'yish muddati AYNAN SHU PAYTDAN hisoblanadi.
 */
class LessonOpeningRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->input('status'), ['pending', 'active', 'expired', 'rejected', 'all'], true)
            ? $request->input('status')
            : 'pending';

        if (Schema::hasTable('lesson_openings')) {
            LessonOpening::expireOverdue();
        }

        $stage = $this->stage();

        $query = LessonOpening::query()->visibleToStage($stage);
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        // Avval shu foydalanuvchi qarori kerak bo'lganlari
        if ($stage) {
            $column = LessonOpening::statusColumn($stage);
            $query->orderByRaw("CASE WHEN status = 'pending' AND {$column} IS NULL THEN 0 ELSE 1 END");
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
            ->visibleToStage($stage)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.lesson-openings.index', [
            'openings' => $openings,
            'status' => $status,
            'counts' => $counts,
            'groups' => $groups,
            'subjectNames' => $subjectNames,
            'teachers' => $teachers,
            'stage' => $stage,
            // Shu foydalanuvchi qarorini kutayotganlar soni
            'myQueue' => $stage ? LessonOpening::awaitingStage($stage)->count() : null,
            'canReview' => $stage !== null,
            'canDelete' => $this->canDelete(),
            'openingDays' => max((int) Setting::get('lesson_opening_days', 3), 1),
        ]);
    }

    /**
     * Tasdiqlash — joriy foydalanuvchining bosqichi bo'yicha. Rad etgan
     * bosqich ham keyin tasdiqlashi mumkin: boshqa rad etgan bo'lmasa so'rov
     * yana kutilayotgan holatga qaytadi. Hamma tasdiqlagach dars ochiladi.
     */
    public function approve(LessonOpening $opening, LessonOpeningNotifier $notifier): RedirectResponse
    {
        $stage = $this->stage();
        abort_unless($stage !== null, 403);

        $reviewer = $this->reviewer();

        $opening = DB::transaction(function () use ($opening, $stage, $reviewer) {
            // Bir necha tasdiqlovchi bir vaqtda bossa ham holat to'g'ri qolsin
            $opening = LessonOpening::whereKey($opening->id)->lockForUpdate()->first();
            if (!$opening || !($opening->awaits($stage) || $opening->canReapprove($stage))) {
                return null;
            }

            $opening->setStageDecision($stage, LessonOpening::DECISION_APPROVED, $reviewer);

            if ($opening->status === LessonOpening::STATUS_REJECTED && !$opening->anyStageRejected()) {
                $opening->fill(['status' => LessonOpening::STATUS_PENDING, 'review_comment' => null]);
            }

            if ($opening->isPending() && $opening->allApprovalsGiven()) {
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
            return back()->with('error', "Bu so'rov bo'yicha sizning qaroringiz kerak emas yoki allaqachon ko'rib chiqilgan.");
        }

        if ($opening->status === LessonOpening::STATUS_ACTIVE) {
            // So'rov egasi va jadvaldagi o'qituvchiga: dars ochildi, muddat bilan
            $notifier->opened($opening->fresh());
            $days = max((int) Setting::get('lesson_opening_days', 3), 1);

            return back()->with('success', "Dars ochildi. O'qituvchi {$days} kun ichida baho qo'ya oladi.");
        }

        if ($opening->status === LessonOpening::STATUS_REJECTED) {
            return back()->with('success', "Tasdiqlandi, lekin so'rovni boshqa tasdiqlovchi rad etgan.");
        }

        // Oraliq tasdiq: o'qituvchi so'rovi qayerda turganini bilib tursin
        $notifier->approvedStep($opening, $stage);

        $waiting = implode(', ', array_map(
            fn ($s) => LessonOpening::STAGE_LABELS[$s],
            $opening->remainingStagesAfter($stage)
        ));

        return back()->with('success', "Tasdiqlandi. Dars {$waiting} ham tasdiqlagach ochiladi.");
    }

    /** Rad etish: sababi majburiy, o'qituvchi uni jurnalda ko'radi. */
    public function reject(Request $request, LessonOpening $opening, LessonOpeningNotifier $notifier): RedirectResponse
    {
        $stage = $this->stage();
        abort_unless($stage !== null, 403);

        $data = $request->validate([
            'comment' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'comment.required' => 'Rad etish sababini yozing.',
            'comment.min' => 'Sabab juda qisqa.',
        ]);

        $reviewer = $this->reviewer();
        $wasOpen = false;

        $opening = DB::transaction(function () use ($opening, $stage, $reviewer, $data, &$wasOpen) {
            $opening = LessonOpening::whereKey($opening->id)->lockForUpdate()->first();
            // Kutilayotgan so'rov yoki shu bosqich tasdiqlab ochilgan dars
            // (adashib tasdiqlangan bo'lsa qaytarib olinadi)
            if (!$opening || !($opening->awaits($stage) || $opening->canRevoke($stage))) {
                return null;
            }
            $wasOpen = $opening->status === LessonOpening::STATUS_ACTIVE;

            // Bitta tasdiqlovchining rad etishi yetarli
            $opening->setStageDecision($stage, LessonOpening::DECISION_REJECTED, $reviewer);
            $opening->fill([
                'status' => LessonOpening::STATUS_REJECTED,
                'deadline' => null,
                'review_comment' => trim($data['comment']),
            ]);
            $opening->save();

            return $opening;
        });

        if (!$opening) {
            return back()->with('error', "Bu so'rov bo'yicha sizning qaroringiz kerak emas yoki allaqachon ko'rib chiqilgan.");
        }

        $notifier->rejected($opening, $stage, $wasOpen);

        return back()->with('success', $wasOpen
            ? "Ochilgan dars yopildi va so'rov rad etildi."
            : "So'rov rad etildi.");
    }

    /**
     * So'rovni butunlay o'chirish — faqat admin va superadmin. Yuklangan
     * asos hujjat va tushuntirish xati ham diskdan o'chiriladi. Ochilgan dars
     * bo'lsa yopiladi; shu vaqtgacha qo'yilgan baholarga tegilmaydi.
     */
    public function destroy(LessonOpening $opening): RedirectResponse
    {
        abort_unless($this->canDelete(), 403);

        foreach ([$opening->file_path, $opening->explanation_file_path] as $path) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }

        $opening->delete();

        return back()->with('success', "So'rov va uning fayllari o'chirildi.");
    }

    /** O'chirish huquqi — faqat admin va superadmin (faol rol bo'yicha). */
    private function canDelete(): bool
    {
        $user = auth()->guard('web')->user() ?? auth()->guard('teacher')->user();
        if (!$user || !method_exists($user, 'hasAnyRole')) {
            return false;
        }

        $active = (string) session('active_role', '');
        if ($active !== '' && $user->hasRole($active)) {
            return in_array($active, ['superadmin', 'admin'], true);
        }

        return $user->hasAnyRole(['superadmin', 'admin']);
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

    /**
     * Joriy foydalanuvchi qaysi bosqichni tasdiqlaydi (faol rol bo'yicha):
     * registrator ofisi, o'quv bo'limi boshlig'i yoki o'quv prorektori.
     * Admin va superadmin faqat ko'radi (null).
     */
    private function stage(): ?string
    {
        $user = auth()->guard('web')->user() ?? auth()->guard('teacher')->user();
        if (!$user || !method_exists($user, 'getRoleNames')) {
            return null;
        }

        $roleStages = [
            'registrator_ofisi' => LessonOpening::STAGE_REGISTRAR,
            'oquv_bolimi_boshligi' => LessonOpening::STAGE_DEPARTMENT,
            'oquv_prorektori' => LessonOpening::STAGE_PROREKTOR,
        ];

        $roles = $user->getRoleNames()->all();
        $active = (string) session('active_role', '');
        if (!in_array($active, $roles, true)) {
            $active = collect(array_keys($roleStages))->first(fn ($role) => in_array($role, $roles, true)) ?? '';
        }

        return $roleStages[$active] ?? null;
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
