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
 * Dars ochish so'rovlari — o'quv prorektori ko'rib chiqadi.
 *
 * Registrator o'tkazib yuborilgan kun uchun asos hujjat bilan so'rov
 * yuboradi. Prorektor tasdiqlasa dars ochiladi va o'qituvchining baho
 * qo'yish muddati AYNAN SHU PAYTDAN hisoblanadi — so'rov necha kun
 * kutib qolgan bo'lsa ham o'qituvchi to'liq muddat oladi.
 */
class LessonOpeningRequestController extends Controller
{
    /** Ko'rib chiqish huquqi bor rollar */
    private const REVIEWER_ROLES = ['oquv_prorektori', 'superadmin'];

    public function index(Request $request): View
    {
        $status = in_array($request->input('status'), ['pending', 'active', 'expired', 'rejected', 'all'], true)
            ? $request->input('status')
            : 'pending';

        if (Schema::hasTable('lesson_openings')) {
            LessonOpening::expireOverdue();
        }

        $query = LessonOpening::query()->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }

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

        return view('admin.lesson-openings.index', [
            'openings' => $openings,
            'status' => $status,
            'counts' => $counts,
            'groups' => $groups,
            'subjectNames' => $subjectNames,
            'teachers' => $teachers,
            'canReview' => $this->canReview(),
            'openingDays' => max((int) Setting::get('lesson_opening_days', 3), 1),
        ]);
    }

    /** Tasdiqlash: dars ochiladi, muddat hozirdan hisoblanadi. */
    public function approve(LessonOpening $opening): RedirectResponse
    {
        abort_unless($this->canReview(), 403);

        if (!$opening->isPending()) {
            return back()->with('error', "Bu so'rov allaqachon ko'rib chiqilgan.");
        }

        $days = max((int) Setting::get('lesson_opening_days', 3), 1);
        $reviewer = $this->reviewer();

        $opening->update([
            'status' => LessonOpening::STATUS_ACTIVE,
            'deadline' => Carbon::now('Asia/Tashkent')->addDays($days)->endOfDay(),
            'reviewed_by_id' => $reviewer['id'],
            'reviewed_by_name' => $reviewer['name'],
            'reviewed_by_guard' => $reviewer['guard'],
            'reviewed_at' => now(),
            'review_comment' => null,
        ]);

        // O'qituvchiga xabar faqat haqiqatan ochilganda boradi
        JournalController::notifyTeachersAboutOpening($opening->fresh());

        return back()->with('success', "Dars ochildi. O'qituvchi {$days} kun ichida baho qo'ya oladi.");
    }

    /** Rad etish: sababi majburiy, registrator uni jurnalda ko'radi. */
    public function reject(Request $request, LessonOpening $opening): RedirectResponse
    {
        abort_unless($this->canReview(), 403);

        $data = $request->validate([
            'comment' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'comment.required' => 'Rad etish sababini yozing.',
            'comment.min' => 'Sabab juda qisqa.',
        ]);

        if (!$opening->isPending()) {
            return back()->with('error', "Bu so'rov allaqachon ko'rib chiqilgan.");
        }

        $reviewer = $this->reviewer();

        $opening->update([
            'status' => LessonOpening::STATUS_REJECTED,
            'deadline' => null,
            'reviewed_by_id' => $reviewer['id'],
            'reviewed_by_name' => $reviewer['name'],
            'reviewed_by_guard' => $reviewer['guard'],
            'reviewed_at' => now(),
            'review_comment' => trim($data['comment']),
        ]);

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

    private function canReview(): bool
    {
        $user = auth()->guard('web')->user() ?? auth()->guard('teacher')->user();
        if (!$user || !method_exists($user, 'getRoleNames')) {
            return false;
        }

        $roles = $user->getRoleNames()->all();
        $sessionRole = (string) session('active_role', '');

        // Faol rol tanlangan bo'lsa — faqat o'sha rol hisoblanadi
        if ($sessionRole !== '' && in_array($sessionRole, $roles, true)) {
            return in_array($sessionRole, self::REVIEWER_ROLES, true);
        }

        return count(array_intersect($roles, self::REVIEWER_ROLES)) > 0;
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
