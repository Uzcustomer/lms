<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\YnQaydnomaConfirmation;
use App\Models\YnQaydnomaRejection;
use App\Services\YnqFamily;
use App\Services\YnqForm;
use App\Services\YnqReadiness;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Vedomostlar": YN (OSKI / test) imtihonlari tugagan fanlar bo'yicha qaydnoma kimlar tomonidan
 * topshirilgani (amaliy o'qituvchi), kafedra mudiri va dekan tasdiqlagani yoki rad etilgani.
 * Kafedra mudiri — faqat o'z kafedrasi fanlari; dekan — o'z fakulteti guruhlari;
 * admin/superadmin/registrator — hammasi (fakultet filtri bilan), dekan tasdiqlaganlari PDF da.
 * "Vedomostlarim": o'qituvchining o'z guruh × fanlari. Kichik guruhlar (a/b/c) — bitta qator
 * (bitta umumiy qaydnoma, YnqFamily): hammasining o'qituvchilari topshirgach kafedra mudiriga o'tadi.
 * Har shakl (12, 12-qo'shimcha, 12a, 12b va qo'shimchalari — YnqForm) — alohida qator, o'z tasdiq zanjiri bilan.
 */
class YnQaydnomaListController extends Controller
{
    private const PER_PAGE = 30;

    public const STATUS_NONE = 'none';           // o'qituvchi hali tasdiqlamagan
    public const STATUS_PARTIAL = 'partial';     // kichik guruhlarning bir qismi o'qituvchilari tasdiqlagan
    public const STATUS_TEACHER = 'teacher';     // o'qituvchi tasdiqlagan, kafedra mudiri kutilmoqda
    public const STATUS_HEAD = 'head';           // kafedra mudiri tasdiqlagan, dekan kutilmoqda
    public const STATUS_REJECTED = 'rejected';   // dekan rad etgan, o'qituvchi qayta topshirishi kerak
    public const STATUS_DEAN = 'dean';           // dekan ham tasdiqlagan

    private const STATUSES = [self::STATUS_NONE, self::STATUS_PARTIAL, self::STATUS_TEACHER, self::STATUS_HEAD, self::STATUS_REJECTED, self::STATUS_DEAN];

    public function index(Request $request)
    {
        $isDean = is_active_dekan();
        $isHead = !$isDean && JournalController::isActiveHead();
        // Registrator / admin — Vedomost tekshirishdagi filtrlar bilan: guruh × fanlar va qaydnoma holati
        // (ma'lumot sahifadan AJAX bilan — VedomostTekshirishController::holat)
        if (!$isDean && !$isHead) {
            return view('admin.yn-qaydnomalar.holat', app(VedomostTekshirishController::class)->filterViewData() + [
                'canDelete' => (auth()->user()?->hasRole('superadmin') ?? false) && !session('impersonating'),
            ]);
        }
        $current = $request->input('current', '1') === '1';
        $rows = $this->finishedVedomosts($isDean ? JournalController::deanFacultyHemisIds() : null, $current,
            $isHead ? JournalController::headKafedraHemisIds() : null);

        return $this->listView($request, $rows, $current, [
            'isDean' => $isDean,
            'isHead' => $isHead,
            'isTeacher' => false,
            'canPdf' => false,
            'canDelete' => false,
        ]);
    }

    /** O'qituvchi: o'zi amaliy mashg'ulot o'tadigan guruh × fanlar va ularning qaydnoma holati */
    public function mine(Request $request)
    {
        $hemisId = get_teacher_hemis_id();
        $teacher = $hemisId ? Teacher::where('hemis_id', $hemisId)->first() : null;
        abort_unless($teacher, 403, "Sahifa o'qituvchilar uchun.");
        $current = $request->input('current', '1') === '1';

        return $this->listView($request, $this->teacherVedomosts($teacher, $current), $current, [
            'isDean' => false,
            'isHead' => false,
            'isTeacher' => true,
            'canPdf' => false,
            'canDelete' => false,
        ]);
    }

    /** Dekan, kafedra mudiri, o'qituvchi: holat kartochkalari (status filtri) va ro'yxat */
    private function listView(Request $request, Collection $rows, bool $current, array $context)
    {
        $status = (string) $request->input('status', '');
        $counts = ['all' => $rows->count()];
        foreach (self::STATUSES as $s) {
            $counts[$s] = $rows->where('status', $s)->count();
        }
        // "Topshirilmagan" — qisman topshirilganlar bilan (kafedra mudiriga hali o'tmagan)
        $counts[self::STATUS_NONE] += $counts[self::STATUS_PARTIAL];
        $filtered = in_array($status, self::STATUSES, true)
            ? $rows->whereIn('status', $status === self::STATUS_NONE ? [self::STATUS_NONE, self::STATUS_PARTIAL] : [$status])->values()
            : $rows->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $filtered->forPage($page, self::PER_PAGE)->values();
        if (!$context['isTeacher']) {
            $this->attachPendingTeachers($items);
        }
        $paginator = new LengthAwarePaginator($items, $filtered->count(), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.yn-qaydnomalar.index', $context + [
            'rows' => $paginator, 'counts' => $counts, 'filters' => ['status' => $status, 'current' => $current ? '1' : '0'],
            // O'qituvchi: imtihoni tugagan, YN yuborilgan va hali tasdiqlamagan vedomostlari — sahifa tepasida
            'needsAction' => $context['isTeacher'] ? $rows->where('needs_action', true)->values() : collect(),
        ]);
    }

    /**
     * YN imtihonlari tugagan guruh × fan lar (yopilish shakli talab qilgan OSKI / test sanasi
     * bugungacha o'tgan) va o'qituvchi topshirgan yoki dekan rad etgan barcha qaydnomalar
     * (imtihoni tugamagan bo'lsa ham), holati bilan; eng so'nggisi birinchi.
     */
    public function finishedVedomosts(?array $facultyHemisIds, bool $currentSemester = true, ?array $kafedraHemisIds = null): Collection
    {
        $today = Carbon::today()->toDateString();
        $key = self::keyFn();

        $exams = DB::table('exam_schedules as es')
            ->join('groups as g', 'g.group_hemis_id', '=', 'es.group_hemis_id')
            ->leftJoin('semesters as s', function ($j) {
                $j->on('s.curriculum_hemis_id', '=', 'g.curriculum_hemis_id')->on('s.code', '=', 'es.semester_code');
            })
            ->leftJoin('curriculum_subjects as cs', function ($j) {
                $j->on('cs.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
                  ->on('cs.subject_id', '=', 'es.subject_id')
                  ->on('cs.semester_code', '=', 'es.semester_code');
            })
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
            ->leftJoin('departments as dep', 'dep.department_hemis_id', '=', 'g.department_hemis_id')
            ->whereNull('es.student_hemis_id')
            ->when($facultyHemisIds !== null, fn ($q) => $q->whereIn('g.department_hemis_id', $facultyHemisIds ?: [-1]))
            ->when($currentSemester, fn ($q) => $q->where('s.current', true))
            ->when($kafedraHemisIds !== null, fn ($q) => $q->whereIn('cs.department_id', $kafedraHemisIds ?: ['-']))
            ->where(fn ($q) => $q->where('es.oski_date', '<=', $today)->orWhere('es.test_date', '<=', $today))
            ->select('es.group_hemis_id', 'es.subject_id', 'es.semester_code', 'es.oski_date', 'es.test_date', 'es.oski_na', 'es.test_na',
                'es.subject_name as exam_subject_name', 'cs.subject_name', 'cs.semester_name', 'cs.closing_form', 'cs.department_id as kafedra_id', 'g.id as group_pk',
                'g.name as group_name', 'g.department_hemis_id', 'g.specialty_hemis_id', 'dep.name as faculty_name',
                'sp.name as specialty_name', 's.level_code', 's.level_name', 'g.curriculum_hemis_id')
            ->get()
            ->unique($key)
            ->filter(fn ($r) => self::examsFinished($r, $today))
            ->keyBy($key);

        // Tasdiq va rad etishlar — fakultet guruhlari bo'yicha (imtihon jadvali bilan cheklanmaydi;
        // kichik guruhlar bir fakultetda)
        $facultyGroups = $facultyHemisIds === null ? null
            : DB::table('groups')->whereIn('department_hemis_id', $facultyHemisIds ?: [-1])->pluck('group_hemis_id')->map(fn ($v) => (string) $v)->all();
        [$confirmations, $rejections] = $this->statusData($facultyGroups);

        // Topshirilgan yoki rad etilgan qaydnoma — imtihon tugamagan, jadvalda yo'q yoki o'tgan
        // semestrniki bo'lsa ham doim ko'rinadi ("Joriy semestr" faqat topshirilmaganlarni toraytiradi)
        $refs = $confirmations->map->first()->union($rejections)->keyBy($key)->diffKeys($exams);
        if ($refs->isNotEmpty()) {
            $exams = $exams->union($this->entries($refs));
        }

        // Qo'shimcha / 12a / 12b shakllari — o'qituvchi topshirgan (yoki rad etilgan) bo'lsa alohida qator
        return $this->withFamilies($exams)
            ->flatMap(fn ($r) => array_map(fn ($form) => $this->makeRow($this->withForm($r, $form), $confirmations, $rejections),
                array_merge([YnqForm::MAIN], $this->submittedForms($r, $confirmations, $rejections))))
            // Kafedra mudiri — faqat o'z kafedrasi fanlari
            ->when($kafedraHemisIds !== null, fn ($c) => $c->whereIn('kafedra_id', $kafedraHemisIds))
            ->sortByDesc('finished_at')->values();
    }

    /**
     * O'qituvchining joriy dars jadvalidagi amaliy (ma'ruza va nazoratlardan tashqari) guruh × fanlari
     * va o'zi tasdiqlagan qaydnomalar; YN yuborilgani va imtihon tugagani bilan.
     */
    public function teacherVedomosts(Teacher $teacher, bool $currentSemester = true): Collection
    {
        $key = self::keyFn();
        $refs = DB::table('schedules')
            ->whereNull('deleted_at')
            ->where('employee_id', $teacher->hemis_id)
            ->whereNotIn('training_type_code', [11, 99, 100, 101, 102, 103])
            ->when($currentSemester, fn ($q) => $q->where('education_year_current', true))
            ->select('group_id as group_hemis_id', 'subject_id', 'semester_code', 'subject_name')
            ->distinct()
            ->get()
            ->unique($key)
            ->keyBy($key);
        // O'zi topshirgan va o'zi topshirib rad etilganlari — semestridan qat'i nazar
        $own = YnQaydnomaConfirmation::where('teacher_id', $teacher->id)->where('role', YnQaydnomaConfirmation::ROLE_TEACHER)
            ->get(['group_hemis_id', 'subject_id', 'semester_code'])->keyBy($key);
        if (Schema::hasTable('yn_qaydnoma_rejections') && Schema::hasColumn('yn_qaydnoma_rejections', 'teacher_ids')) {
            $own = $own->union(YnQaydnomaRejection::whereNull('resolved_at')->get()
                ->filter(fn ($r) => in_array($teacher->id, $r->teacher_ids ?? []))->keyBy($key));
        }
        $refs = $refs->union($own->diffKeys($refs));

        $groups = $refs->pluck('group_hemis_id')->map(fn ($v) => (string) $v)->unique()->values()->all();
        $submitted = Schema::hasTable('yn_submissions')
            ? DB::table('yn_submissions')->whereIn('group_hemis_id', $groups)->get(['group_hemis_id', 'subject_id', 'semester_code'])->keyBy($key)
            : collect();
        // Holat — kichik guruhlar oilasining barcha tasdiqlari bo'yicha (boshqa o'qituvchilarniki ham)
        $entries = $this->withFamilies($this->entries($refs));
        [$confirmations, $rejections] = $this->statusData($entries->flatMap(fn ($r) => $r->members)->unique()->values()->all());

        $journal = app(JournalController::class);
        // Fan tugaganmi: imtihonlar (OSKI / test) o'tgan, imtihonsiz fanda — oxirgi dars o'tgan
        $ready = YnqReadiness::forMany($entries->map(fn ($r) => [$r->group_hemis_id, $r->subject_id, $r->semester_code]));

        return $entries
            ->flatMap(function ($r) use ($confirmations, $rejections, $submitted, $ready, $key, $journal, $teacher) {
                $flags = function ($row) use ($r, $submitted, $ready, $key, $teacher) {
                    $row->yn_submitted = $submitted->has($key($r));
                    $row->exams_finished = $ready[$key($r)]['finished'] ?? false;
                    $row->finish_reason = $ready[$key($r)]['reason'] ?? null;
                    $row->current = $r->current;
                    // Tasdiqlashingiz kerak: fan tugagan, siz hali topshirmagansiz (YN yuborilmagan bo'lsa — avval yuborasiz)
                    $row->needs_action = $row->exams_finished
                        && in_array($row->status, [self::STATUS_NONE, self::STATUS_PARTIAL, self::STATUS_REJECTED], true)
                        && !$row->teachers->contains('teacher_id', $teacher->id);

                    return $row;
                };
                $main = $flags($this->makeRow($r, $confirmations, $rejections));
                // Asosiy qaydnoma topshirilgach — talabasi bor qo'shimcha / 12a / 12b shakllari (dars jadvali
                // va baholar bo'yicha, keshlangan) ham alohida qator
                $forms = $this->submittedForms($r, $confirmations, $rejections);
                if ($main->exams_finished && $main->yn_submitted && $main->status !== self::STATUS_NONE) {
                    try {
                        $forms = array_merge($forms, $journal->ynqActiveForms((string) $r->subject_id, (string) $r->semester_code, (string) $r->group_hemis_id));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
                $forms = array_values(array_diff(array_intersect(YnqForm::codes(), $forms), [YnqForm::MAIN]));

                return array_merge([$main], array_map(fn ($form) => $flags($this->makeRow($this->withForm($r, $form), $confirmations, $rejections)), $forms));
            })
            // "Joriy semestr" — faqat hali topshirilmaganlarga
            ->filter(fn ($row) => !$currentSemester || $row->current || $row->status !== self::STATUS_NONE)
            ->sortBy(fn ($r) => [array_search($r->status, [self::STATUS_REJECTED, self::STATUS_NONE, self::STATUS_PARTIAL, self::STATUS_TEACHER, self::STATUS_HEAD, self::STATUS_DEAN]), $r->group, $r->subject, $r->form])
            ->values();
    }

    /** Qatorning shakl bo'yicha nusxasi */
    private function withForm(object $r, string $form): object
    {
        $copy = clone $r;
        $copy->form = $form;

        return $copy;
    }

    /** Asosiydan tashqari shakllar — oila guruhlari bo'yicha tasdiq yoki ochiq rad etishi borlari */
    private function submittedForms(object $r, Collection $confirmations, Collection $rejections): array
    {
        $members = $r->members ?? [(string) $r->group_hemis_id];

        return array_values(array_filter(YnqForm::codes(), fn ($form) => $form !== YnqForm::MAIN
            && collect($members)->contains(fn ($g) => $confirmations->has($k = $g . '|' . $r->subject_id . '|' . $r->semester_code . '|' . $form)
                || $rejections->has($k))));
    }

    /**
     * Berilgan guruh × fanlar (group_hemis_id, subject_id, semester_code) ning qaydnoma holati —
     * kichik guruhlar bitta qatorda; topshirmaganlarga — hali topshirmagan o'qituvchilar
     * (Vedomost tekshirish → Vedomostlar holati).
     */
    public function statusFor(Collection $refs): Collection
    {
        if ($refs->isEmpty()) {
            return collect();
        }
        $key = self::keyFn();
        $entries = $this->withFamilies($this->entries($refs->keyBy($key)));
        [$confirmations, $rejections] = $this->statusData($entries->flatMap(fn ($r) => $r->members)->unique()->values()->all());
        $today = Carbon::today()->toDateString();
        $rows = $entries->map(function ($r) use ($confirmations, $rejections, $today) {
            $row = $this->makeRow($r, $confirmations, $rejections);
            $row->exams_finished = self::examsFinished($r, $today);

            return $row;
        })->sortBy(fn ($r) => [$r->group, $r->subject])->values();
        $this->attachPendingTeachers($rows);

        return $rows;
    }

    private static function keyFn(): \Closure
    {
        return fn ($r) => $r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
    }

    /** Tasdiqlar (kalit|shakl → ro'yxat) va yopilmagan rad etishlar (kalit|shakl → eng so'nggisi); null — hamma guruh */
    private function statusData(?array $groupHemisIds): array
    {
        $key = fn ($r) => $r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code . '|' . ($r->form ?? YnqForm::MAIN);
        $confirmations = YnQaydnomaConfirmation::with('verification')
            ->when($groupHemisIds !== null, fn ($q) => $q->whereIn('group_hemis_id', $groupHemisIds ?: ['-']))
            ->orderByDesc('confirmed_at')
            ->get()
            ->groupBy($key);
        $rejections = Schema::hasTable('yn_qaydnoma_rejections')
            ? YnQaydnomaRejection::whereNull('resolved_at')
                ->when($groupHemisIds !== null, fn ($q) => $q->whereIn('group_hemis_id', $groupHemisIds ?: ['-']))
                ->orderByDesc('rejected_at')
                ->get()
                ->unique($key)
                ->keyBy($key)
            : collect();

        return [$confirmations, $rejections];
    }

    /**
     * Kichik guruhlar (a/b/c) — bitta qaydnoma: har qatorga oila guruhlari (members), bir oilaning
     * qatorlari bittaga (asosiy guruhniki, bo'lmasa birinchisi) birlashadi, nomi — harfsiz umumiy.
     */
    private function withFamilies(Collection $rows): Collection
    {
        foreach ($rows as $r) {
            $r->members = YnqFamily::membersFor((string) $r->subject_id, (string) $r->semester_code, (string) $r->group_hemis_id,
                $r->group_name, $r->curriculum_hemis_id ?? null);
        }
        $names = DB::table('groups')
            ->whereIn('group_hemis_id', $rows->filter(fn ($r) => count($r->members) > 1)->flatMap(fn ($r) => $r->members)->unique()->values())
            ->pluck('name', 'group_hemis_id');

        return $rows->groupBy(fn ($r) => $r->members[0] . '|' . $r->subject_id . '|' . $r->semester_code)
            ->map(function (Collection $family) use ($names) {
                $r = $family->first(fn ($x) => (string) $x->group_hemis_id === $x->members[0]) ?? $family->first();
                $r->member_names = [];
                if (count($r->members) > 1) {
                    $r->member_names = array_map(fn ($g) => (string) ($names[$g] ?? $g), $r->members);
                    $r->group_name = YnqFamily::label($r->group_name, count($r->members));
                }

                return $r;
            });
    }

    private function makeRow(object $r, Collection $confirmations, Collection $rejections): object
    {
        $members = $r->members ?? [(string) $r->group_hemis_id];
        $form = YnqForm::normalize($r->form ?? null);
        $keys = array_map(fn ($g) => $g . '|' . $r->subject_id . '|' . $r->semester_code . '|' . $form, $members);
        $all = collect($keys)->flatMap(fn ($k) => $confirmations->get($k, collect()))->sortByDesc('confirmed_at')->values();
        $teacherRows = $all->where('role', YnQaydnomaConfirmation::ROLE_TEACHER);
        $teachers = $teacherRows->unique('teacher_id')->values();
        // Kichik guruhlardan hali hech bir o'qituvchisi topshirmaganlari
        $uncovered = array_values(array_diff($members, $teacherRows->pluck('group_hemis_id')->map(fn ($v) => (string) $v)->all()));
        $complete = $teachers->isNotEmpty() && $uncovered === [];
        $head = $complete ? $all->firstWhere('role', YnQaydnomaConfirmation::ROLE_HEAD) : null;
        $dean = $head ? $all->firstWhere('role', YnQaydnomaConfirmation::ROLE_DEAN) : null;
        // Rad etish — o'qituvchi(lar) hali qayta topshirmagan bo'lsa amalda
        $rejection = $complete ? null : collect($keys)->map(fn ($k) => $rejections->get($k))->filter()->sortByDesc('rejected_at')->first();
        $w = $teachers->first()->weights ?? ($rejection->weights ?? null);
        $status = match (true) {
            (bool) $dean => self::STATUS_DEAN,
            (bool) $head => self::STATUS_HEAD,
            $complete => self::STATUS_TEACHER,
            (bool) $rejection => self::STATUS_REJECTED,
            $teachers->isNotEmpty() => self::STATUS_PARTIAL,
            default => self::STATUS_NONE,
        };

        return (object) [
            'group_hemis_id' => (string) $r->group_hemis_id,
            'group_pk' => $r->group_pk ?? null,
            'group' => $r->group_name,
            'faculty_id' => (string) $r->department_hemis_id,
            'kafedra_id' => (string) ($r->kafedra_id ?? ''),
            'faculty' => $r->faculty_name ?? '',
            'specialty_id' => (string) $r->specialty_hemis_id,
            'specialty' => $r->specialty_name ?? '',
            'level_code' => (string) $r->level_code,
            'level_name' => $r->level_name ?? '',
            'subject_id' => (string) $r->subject_id,
            'subject' => $r->subject_name ?? $r->exam_subject_name,
            'form' => $form,
            'form_label' => YnqForm::label($form),
            'semester_code' => (string) $r->semester_code,
            'semester' => $r->semester_name ?? $r->semester_code,
            'oski_date' => $r->oski_na ? null : $r->oski_date,
            'test_date' => $r->test_na ? null : $r->test_date,
            'finished_at' => max((string) $r->oski_date, (string) $r->test_date, (string) ($all->first()?->confirmed_at), (string) ($rejection?->rejected_at)),
            'members' => $members,
            'member_names' => $r->member_names ?? [],
            'uncovered' => $uncovered,
            'teachers' => $teachers,
            'head' => $head,
            'dean' => $dean,
            'rejection' => $rejection,
            'pending_teachers' => collect(),
            'yn_submitted' => null,
            'exams_finished' => null,
            'finish_reason' => null,
            'needs_action' => false,
            'status' => $status,
            // Qaydnoma sahifasi — topshirilgan yoki rad etilgan (qayta ko'rib chiqish) vaznlari bilan
            'params' => $w === null ? null : [
                'subject_id' => (string) $r->subject_id, 'semester_code' => (string) $r->semester_code, 'group_hemis_id' => (string) $r->group_hemis_id,
                'weight_jn' => $w['jn'] ?? 30, 'weight_mt' => $w['mt'] ?? 10, 'weight_on' => $w['on'] ?? 0,
                'weight_oski' => $w['oski'] ?? 0, 'weight_test' => $w['test'] ?? 60, 'form' => $form,
            ],
        ];
    }

    /**
     * Kalit → {group_hemis_id, subject_id, semester_code, [subject_name], [verification]} bo'yicha
     * qator ma'lumoti (guruh, semestr, fan nomlari — jadvallardan, bo'lmasa tasdiq / dars jadvalidan,
     * imtihon sanalari); "current" — semestr joriymi (yozuvi bo'lmasa — ha).
     */
    private function entries(Collection $refs): Collection
    {
        $groups = DB::table('groups as g')
            ->leftJoin('departments as dep', 'dep.department_hemis_id', '=', 'g.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
            ->whereIn('g.group_hemis_id', $refs->pluck('group_hemis_id')->map(fn ($v) => (string) $v)->unique()->values())
            ->get(['g.id', 'g.group_hemis_id', 'g.name', 'g.curriculum_hemis_id', 'g.department_hemis_id', 'g.specialty_hemis_id', 'dep.name as faculty_name', 'sp.name as specialty_name'])
            ->keyBy(fn ($g) => (string) $g->group_hemis_id);
        $curricula = $groups->pluck('curriculum_hemis_id')->filter()->unique()->values();
        $subjectIds = $refs->pluck('subject_id')->map(fn ($v) => (string) $v)->unique()->values();
        $semesters = DB::table('semesters')->whereIn('curriculum_hemis_id', $curricula)
            ->get(['curriculum_hemis_id', 'code', 'current', 'level_code', 'level_name'])
            ->keyBy(fn ($s) => $s->curriculum_hemis_id . '|' . $s->code);
        $subjects = DB::table('curriculum_subjects')->whereIn('curricula_hemis_id', $curricula)
            ->whereIn('subject_id', $subjectIds)
            ->get(['curricula_hemis_id', 'subject_id', 'semester_code', 'subject_name', 'semester_name', 'closing_form', 'department_id'])
            ->keyBy(fn ($s) => $s->curricula_hemis_id . '|' . $s->subject_id . '|' . $s->semester_code);
        $exams = DB::table('exam_schedules')->whereNull('student_hemis_id')
            ->whereIn('group_hemis_id', $groups->keys())
            ->whereIn('subject_id', $subjectIds)
            ->get(['group_hemis_id', 'subject_id', 'semester_code', 'subject_name', 'oski_date', 'test_date', 'oski_na', 'test_na'])
            ->keyBy(self::keyFn());

        return $refs->map(function ($c, $k) use ($groups, $semesters, $subjects, $exams) {
            $g = $groups->get((string) $c->group_hemis_id);
            if (!$g) {
                return null;
            }
            $sem = $semesters->get($g->curriculum_hemis_id . '|' . $c->semester_code);
            $subject = $subjects->get($g->curriculum_hemis_id . '|' . $c->subject_id . '|' . $c->semester_code);
            $exam = $exams->get($k);
            $verification = $c instanceof YnQaydnomaConfirmation ? $c->verification : null;

            return (object) [
                'group_hemis_id' => (string) $c->group_hemis_id, 'subject_id' => (string) $c->subject_id, 'semester_code' => (string) $c->semester_code,
                'oski_date' => $exam->oski_date ?? null, 'test_date' => $exam->test_date ?? null,
                'oski_na' => $exam->oski_na ?? false, 'test_na' => $exam->test_na ?? false,
                'closing_form' => $subject->closing_form ?? null,
                'kafedra_id' => $subject->department_id ?? null,
                'exam_subject_name' => $exam->subject_name ?? ($c->subject_name ?? ($verification->subject_name ?? ('Fan #' . $c->subject_id))),
                'subject_name' => $subject->subject_name ?? null,
                'semester_name' => $subject->semester_name ?? ($verification->semester_name ?? null),
                'group_pk' => $g->id, 'group_name' => $g->name, 'department_hemis_id' => $g->department_hemis_id, 'specialty_hemis_id' => $g->specialty_hemis_id,
                'faculty_name' => $g->faculty_name, 'specialty_name' => $g->specialty_name, 'curriculum_hemis_id' => $g->curriculum_hemis_id,
                'level_code' => $sem->level_code ?? '', 'level_name' => $sem->level_name ?? '',
                'current' => $sem ? (bool) $sem->current : true,
            ];
        })->filter();
    }

    /**
     * Yopilish shakli talab qilgan imtihonlar bugungacha o'tganmi: OSKI → OSKI sanasi, test → test,
     * OSKI+test → ikkalasi; shakl belgilanmagan bo'lsa — jadvaldagi sanalarning barchasi.
     */
    public static function examsFinished(object $r, string $today): bool
    {
        $oski = $r->oski_na ? null : $r->oski_date;
        $test = $r->test_na ? null : $r->test_date;
        $required = match ($r->closing_form) {
            'oski' => ['oski' => $oski],
            'test' => ['test' => $test],
            'oski_test' => ['oski' => $oski, 'test' => $test],
            default => array_filter(['oski' => $oski, 'test' => $test]),
        };

        return $required !== [] && collect($required)->every(fn ($date) => $date && substr((string) $date, 0, 10) <= $today);
    }

    /** Topshirmaganlar uchun — hali topshirilmagan (kichik) guruhlarda fandan amaliy mashg'ulot o'tgan o'qituvchilar (dars jadvali) */
    private function attachPendingTeachers(Collection $items): void
    {
        $pending = $items->whereIn('status', [self::STATUS_NONE, self::STATUS_PARTIAL, self::STATUS_REJECTED]);
        if ($pending->isEmpty()) {
            return;
        }
        $names = DB::table('schedules')
            ->whereNull('deleted_at')
            ->whereIn('group_id', $pending->flatMap(fn ($r) => $r->uncovered)->unique()->values())
            ->whereIn('subject_id', $pending->pluck('subject_id')->unique()->values())
            ->whereNotIn('training_type_code', [11, 99, 100, 101, 102, 103])
            ->whereNotNull('employee_name')
            ->select('group_id', 'subject_id', 'employee_name')
            ->distinct()
            ->get()
            ->groupBy(fn ($s) => $s->group_id . '|' . $s->subject_id);
        foreach ($pending as $row) {
            $row->pending_teachers = collect($row->uncovered)->flatMap(fn ($g) => $names->get($g . '|' . $row->subject_id, collect())->pluck('employee_name'))
                ->unique()->values();
        }
    }
}
