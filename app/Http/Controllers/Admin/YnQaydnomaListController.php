<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\YnQaydnomaConfirmation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * "Vedomostlar": amaliy o'qituvchi tasdiqlagan YN qaydnomalar ro'yxati. Dekan — faqat o'z
 * fakulteti guruhlari; admin/superadmin — hammasi. Har qaydnomani sahifada ko'rish,
 * Excel yuklab olish va tasdiqlangan nusxani (QR havolasi) ochish mumkin.
 */
class YnQaydnomaListController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $groupIds = null;   // null — cheklovsiz
        if (is_active_dekan()) {
            $faculties = JournalController::deanFacultyHemisIds();
            $groupIds = $faculties
                ? Group::whereIn('department_hemis_id', $faculties)->pluck('group_hemis_id')->map(fn ($id) => (string) $id)->all()
                : [];
        }

        $confirmations = YnQaydnomaConfirmation::with('verification')
            ->when($groupIds !== null, fn ($q) => $q->whereIn('group_hemis_id', $groupIds ?: ['-']))
            ->orderByDesc('confirmed_at')
            ->get();

        $groups = Group::whereIn('group_hemis_id', $confirmations->pluck('group_hemis_id')->unique()->values())
            ->get(['group_hemis_id', 'name', 'curriculum_hemis_id', 'department_name'])
            ->keyBy(fn ($g) => (string) $g->group_hemis_id);
        $subjects = DB::table('curriculum_subjects')
            ->whereIn('curricula_hemis_id', $groups->pluck('curriculum_hemis_id')->filter()->unique()->values())
            ->whereIn('subject_id', $confirmations->pluck('subject_id')->unique()->values())
            ->get(['curricula_hemis_id', 'subject_id', 'semester_code', 'subject_name', 'semester_name'])
            ->keyBy(fn ($s) => $s->curricula_hemis_id . '|' . $s->subject_id . '|' . $s->semester_code);

        // Bitta qaydnoma (fan + semestr + guruh) — bitta qator, tasdiqlagan o'qituvchilar bilan
        $rows = $confirmations
            ->groupBy(fn ($c) => $c->subject_id . '|' . $c->semester_code . '|' . $c->group_hemis_id)
            ->map(function ($items) use ($groups, $subjects) {
                $c = $items->first();
                $group = $groups->get((string) $c->group_hemis_id);
                $subject = $group ? $subjects->get($group->curriculum_hemis_id . '|' . $c->subject_id . '|' . $c->semester_code) : null;
                $w = $c->weights ?? [];

                return (object) [
                    'group' => $group->name ?? $c->group_hemis_id,
                    'faculty' => $group->department_name ?? '',
                    'subject' => $subject->subject_name ?? ('Fan #' . $c->subject_id),
                    'semester' => $subject->semester_name ?? $c->semester_code,
                    'confirmed_at' => $c->confirmed_at,
                    'teachers' => $items->unique('teacher_id')->values(),
                    'params' => [
                        'subject_id' => $c->subject_id, 'semester_code' => $c->semester_code, 'group_hemis_id' => $c->group_hemis_id,
                        'weight_jn' => $w['jn'] ?? 30, 'weight_mt' => $w['mt'] ?? 10, 'weight_on' => $w['on'] ?? 0,
                        'weight_oski' => $w['oski'] ?? 0, 'weight_test' => $w['test'] ?? 60,
                    ],
                ];
            })
            ->when($search !== '', fn ($rows) => $rows->filter(fn ($r) => str_contains(
                mb_strtolower($r->group . ' ' . $r->subject . ' ' . $r->teachers->pluck('teacher_name')->implode(' ')),
                mb_strtolower($search)
            )))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator($rows->forPage($page, self::PER_PAGE)->values(), $rows->count(), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.yn-qaydnomalar.index', ['rows' => $paginator, 'search' => $search, 'isDean' => is_active_dekan()]);
    }
}
