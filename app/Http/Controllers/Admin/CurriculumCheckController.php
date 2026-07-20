<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CurriculumComparisonExport;
use App\Exports\ManualCurriculumExport;
use App\Http\Controllers\Controller;
use App\Imports\ManualCurriculumImport;
use App\Models\ContingentProjection;
use App\Models\Curriculum;
use App\Models\ManualCurriculum;
use App\Models\ManualCurriculumComparison;
use App\Models\ManualCurriculumSubject;
use App\Models\Semester;
use App\Models\Student;
use App\Services\CurriculumComparisonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * O'quv reja to'g'riligi (akkreditatsiya monitoringi):
 * namunaviy va ishchi o'quv rejalarni Excel orqali yuklash
 * hamda ularni fanlar kesimida solishtirish.
 */
class CurriculumCheckController extends Controller
{
    public function index()
    {
        $curricula = ManualCurriculum::withCount('subjects')
            ->withSum('subjects as total_hours', 'total_hours')
            ->withSum('subjects as total_credit', 'credit')
            ->orderByDesc('id')
            ->get();

        $educationTypes = Student::query()
            ->whereNotNull('education_type_code')
            ->select('education_type_code', 'education_type_name')
            ->distinct()
            ->orderBy('education_type_code')
            ->get();

        // Bajarilgan (saqlangan) solishtirishlar ro'yxati — eng so'nggisi tepada.
        // Reja o'chirilgan bo'lsa (relation yo'q) — ko'rsatilmaydi.
        $savedComparisons = ManualCurriculumComparison::with(['reference', 'working'])
            ->latest()
            ->get()
            ->filter(fn ($c) => $c->reference && $c->working)
            ->values();

        // Jamlangan solishtirish: har bir namunaviy reja uchun shu HEMIS rejaga
        // tegishli barcha ishchi rejalar (barcha yuklangan semestrlar) yig'iladi.
        // Yangi semestr yuklansa, guruhga avtomatik qo'shiladi.
        $ishchiByHemis = $curricula->where('type', 'ishchi')
            ->filter(fn ($c) => $c->curricula_hemis_id)
            ->groupBy('curricula_hemis_id');
        $groupedComparisons = $curricula->where('type', 'namunaviy')
            ->filter(fn ($c) => $c->curricula_hemis_id && $ishchiByHemis->has($c->curricula_hemis_id))
            // Bir HEMIS reja uchun bir nechta namunaviy yuklangan bo'lsa, faqat
            // kanonigini (eng to'liq) qoldiramiz — aks holda ro'yxatda dublikat
            // qatorlar chiqadi va kam fanli namunaviy solishtirishga tushib qolib,
            // ishchi rejadagi fanlar noto'g'ri "Namunaviy rejada yo'q" bo'ladi.
            ->groupBy('curricula_hemis_id')
            ->map(fn ($group) => $group->sortByDesc(fn ($c) => ManualCurriculum::canonicalRank($c))->first())
            ->map(fn ($c) => [
                'reference' => $c,
                'workings'  => $ishchiByHemis[$c->curricula_hemis_id]
                    ->sortBy(fn ($w) => [CurriculumComparisonService::semesterNumber($w->semester_code) ?? 99, $w->id])
                    ->values(),
            ])
            ->values();

        // O'qituvchilar ehtiyoji tabidagi fakultet filtri uchun
        $faculties = \App\Models\Department::where('structure_type_code', 11)
            ->where('active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.oquv-reja.index', compact('curricula', 'educationTypes', 'savedComparisons', 'groupedComparisons', 'faculties'));
    }

    /**
     * Cascade tanlov ro'yxatlari TALABALAR jadvali asosida quriladi
     * (HEMIS'dagi semestr "current" flagi ishonchsiz bo'lgani uchun).
     * Active talaba = student_status_code 11. Har bir tanlov o'sha yo'nalishga
     * haqiqatan biriktirilgan talabalar bo'yicha aniqlanadi:
     * ta'lim turi -> fakultet -> yo'nalish -> kurs -> semestr -> o'quv reja
     */
    public function options(Request $request)
    {
        return response()->json(match ($request->input('list')) {
            // HEMIS curricula jadvalidan to'g'ridan-to'g'ri qidiruv (kelajak rejalari rejimi uchun).
            // q parametri ham curriculum.name, ham specialty.code/name bo'yicha qidiradi.
            'all_curricula' => Curriculum::query()
                ->leftJoin('specialties', 'specialties.specialty_hemis_id', '=', 'curricula.specialty_hemis_id')
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = '%' . $request->q . '%';
                    $q->where(fn($sub) => $sub
                        ->where('curricula.name', 'like', $term)
                        ->orWhere('specialties.code', 'like', $term)
                        ->orWhere('specialties.name', 'like', $term)
                    );
                })
                ->when($request->filled('education_year_code'), fn($q) => $q->where('curricula.education_year_code', $request->education_year_code))
                ->when($request->filled('education_type_code'), fn($q) => $q->where('curricula.education_type_code', $request->education_type_code))
                ->select('curricula.curricula_hemis_id as id', 'curricula.name', 'curricula.education_year_name',
                    'curricula.education_type_name', 'curricula.education_period', 'curricula.semester_count',
                    'specialties.code as specialty_code', 'specialties.name as specialty_name')
                ->orderByDesc('curricula.education_year_code')
                ->orderBy('curricula.name')
                ->limit(50)
                ->get(),

            'education_years' => Curriculum::query()
                ->select('education_year_code as code', 'education_year_name as name')
                ->distinct()
                ->orderByDesc('education_year_code')
                ->get(),

            // Berilgan reja (curriculum_id) bo'yicha HEMIS semestrlar ro'yxati.
            // Batch modal uchun: foydalanuvchi qaysi semestr uchun yuklamoqchi ekanini tanlaydi.
            'semesters_for_curriculum' => \App\Models\Semester::where('curriculum_hemis_id', $request->curriculum_id)
                ->select('code', 'name', 'level_code', 'level_name', 'current')
                ->orderBy('code')
                ->get(),

            'faculties' => $this->students($request)
                ->whereNotNull('department_id')
                ->select('department_id as id', 'department_name as name')
                ->distinct()
                ->orderBy('department_name')
                ->get(),

            // Yo'nalishlar kod bo'yicha birlashtiriladi: bir xil kodli
            // (bir nechta specialty_id ga bo'lingan) dublikatlar bitta
            // qatorga yig'iladi, talabalar soni jamlanadi.
            'specialties' => $this->students($request)
                ->whereNotNull('specialty_code')
                ->selectRaw('specialty_code as id, specialty_code as code, MAX(specialty_name) as name, count(*) as student_count')
                ->groupBy('specialty_code')
                ->orderBy('specialty_code')
                ->get(),

            'levels' => $this->students($request)
                ->whereNotNull('level_code')
                ->selectRaw('level_code, level_name, count(*) as student_count')
                ->groupBy('level_code', 'level_name')
                ->orderBy('level_code')
                ->get(),

            'semesters' => $this->students($request)
                ->whereNotNull('semester_code')
                ->selectRaw('semester_code as code, semester_name as name, count(*) as student_count')
                ->groupBy('semester_code', 'semester_name')
                ->orderBy('semester_code')
                ->get(),

            // Active talaba o'z joriy semestrida bo'ladi — kursdagi yagona
            // (yoki eng ko'p talabali) semestr avtomatik tanlash uchun
            'current' => $this->students($request)
                ->whereNotNull('level_code')
                ->selectRaw('level_code, level_name, semester_code as code, semester_name as name, count(*) as student_count')
                ->groupBy('level_code', 'level_name', 'semester_code', 'semester_name')
                ->orderByDesc('student_count')
                ->get(),

            // O'quv rejalar — shu kurs/semestrdagi talabalar biriktirilgan
            // rejalar. curricula jadvalida bor-yo'qligi alohida belgilanadi
            'curricula' => $this->curriculaForStudents($request),

            // Diagnostika: tanlangan fakultet bo'yicha xom ma'lumot
            'diagnose' => $this->diagnose($request),

            default => [],
        });
    }

    /**
     * Cascade va diagnostika uchun umumiy talaba so'rovi.
     * current_only yoqilganda faqat o'qiyotgan talabalar (status 11).
     */
    private function students(Request $request)
    {
        $query = Student::query();

        if ($request->boolean('current_only')) {
            $query->where('student_status_code', 11);
        }
        if ($request->filled('education_type_code')) {
            $query->where('education_type_code', $request->education_type_code);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('specialty_id')) {
            $query->where('specialty_id', $request->specialty_id);
        } elseif ($request->filled('specialty_code')) {
            $query->where('specialty_code', $request->specialty_code);
        }
        if ($request->filled('level_code')) {
            $query->where('level_code', $request->level_code);
        }
        if ($request->filled('semester_code')) {
            $query->where('semester_code', $request->semester_code);
        }

        return $query;
    }

    private function curriculaForStudents(Request $request)
    {
        $rows = $this->students($request)
            ->whereNotNull('curriculum_id')
            ->selectRaw('curriculum_id, count(*) as student_count')
            ->groupBy('curriculum_id')
            ->orderByDesc('student_count')
            ->get();

        $names = Curriculum::whereIn('curricula_hemis_id', $rows->pluck('curriculum_id'))
            ->pluck('name', 'curricula_hemis_id');

        return $rows->map(fn($row) => [
            'id' => $row->curriculum_id,
            'name' => $names[$row->curriculum_id] ?? null,
            'exists' => isset($names[$row->curriculum_id]),
            'student_count' => $row->student_count,
        ])->values();
    }

    /**
     * Tanlangan fakultet (va ixtiyoriy yo'nalish) bo'yicha to'liq xom kesim:
     * har bir yo'nalish-kurs-semestr-reja kombinatsiyasida nechta talaba bor,
     * reja curricula jadvalida bormi, semestri "current" deb belgilanganmi.
     * Bu nima uchun biror kurs/reja cascade'da ko'rinmayotganini ko'rsatadi.
     */
    private function diagnose(Request $request)
    {
        $rows = $this->students($request->merge(['current_only' => false]))
            ->where('student_status_code', '!=', 60)
            ->selectRaw('specialty_id, specialty_code, specialty_name, level_code, level_name,
                semester_code, semester_name, curriculum_id, student_status_code, count(*) as student_count')
            ->groupBy('specialty_id', 'specialty_code', 'specialty_name', 'level_code', 'level_name',
                'semester_code', 'semester_name', 'curriculum_id', 'student_status_code')
            ->orderBy('specialty_code')
            ->orderBy('level_code')
            ->orderBy('semester_code')
            ->get();

        $curriculumNames = Curriculum::whereIn('curricula_hemis_id', $rows->pluck('curriculum_id')->filter())
            ->pluck('name', 'curricula_hemis_id');

        // Reja semestri HEMIS'da "current" deb belgilanganmi
        $currentSemesters = Semester::whereIn('curriculum_hemis_id', $rows->pluck('curriculum_id')->filter())
            ->where('current', true)
            ->get(['curriculum_hemis_id', 'code'])
            ->groupBy('curriculum_hemis_id')
            ->map(fn($g) => $g->pluck('code')->all());

        return $rows->map(fn($row) => [
            'specialty' => trim(($row->specialty_code ?? '') . ' — ' . ($row->specialty_name ?? '')),
            'specialty_id' => $row->specialty_id,
            'level_name' => $row->level_name,
            'semester' => $row->semester_name ?: $row->semester_code,
            'student_count' => $row->student_count,
            'status_code' => $row->student_status_code,
            'curriculum_id' => $row->curriculum_id,
            'curriculum_name' => $row->curriculum_id
                ? ($curriculumNames[$row->curriculum_id] ?? "❌ curricula jadvalida YO'Q")
                : "❌ talabaga reja biriktirilmagan",
            'curriculum_exists' => $row->curriculum_id && isset($curriculumNames[$row->curriculum_id]),
            'semester_is_current' => $row->curriculum_id
                && in_array($row->semester_code, $currentSemesters[$row->curriculum_id] ?? []),
        ])->values();
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'            => 'required|in:namunaviy,ishchi',
            'curricula_hemis_id' => 'required|exists:curricula,curricula_hemis_id',
            'level_code'      => 'nullable|string|max:20',
            'semester_code'   => 'nullable|string|max:20',   // cascade tab (bitta select)
            'semester_codes'  => 'nullable|array',            // batch modal (checkboxlar)
            'semester_codes.*' => 'nullable|string|max:20',
            'file'            => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        // Batch modal semester_codes[] yoki cascade tab semester_code ni birlashtirish
        $semCodes = array_values(array_unique(array_filter(
            (array) ($request->input('semester_codes') ?: ($request->filled('semester_code') ? [$request->semester_code] : [])),
            fn($s) => $s !== null && $s !== ''
        )));
        if (empty($semCodes)) {
            $semCodes = [null]; // semestr ko'rsatilmagan holat (namunaviy reja va h.k.)
        }

        $hemisCurriculum = Curriculum::where('curricula_hemis_id', $request->curricula_hemis_id)->first();
        $specialty = Student::where('curriculum_id', $hemisCurriculum->curricula_hemis_id)
            ->whereNotNull('specialty_code')
            ->select('specialty_code as code', 'specialty_name as name')
            ->first();

        $file     = $request->file('file');
        $filePath = $file->store('manual-curricula', 'public');

        // Semestr nomi va level_code larini oldindan bir so'rovda olamiz
        $semesterInfo = Semester::where('curriculum_hemis_id', $hemisCurriculum->curricula_hemis_id)
            ->whereIn('code', array_filter($semCodes))
            ->get(['code', 'name', 'level_code'])
            ->keyBy('code');

        DB::beginTransaction();
        try {
            $firstCurriculum = null;
            $import          = null;
            $createdIds      = [];

            foreach ($semCodes as $i => $semCode) {
                $semInfo   = $semCode ? ($semesterInfo[$semCode] ?? null) : null;
                $levelCode = $semInfo?->level_code ?? $request->level_code;

                $typeLabel = $request->type === 'namunaviy' ? 'namunaviy' : 'ishchi';
                $name = $hemisCurriculum->name . ' — ' . $typeLabel;
                if ($request->type === 'ishchi' && $semCode) {
                    $semName = $semInfo?->name ?? ($semCode . '-semestr');
                    $name .= ' (' . $semName . ')';
                }

                $curriculum = ManualCurriculum::create([
                    'type'               => $request->type,
                    'name'               => $name,
                    'specialty_code'     => $specialty?->code,
                    'specialty_name'     => $specialty?->name,
                    'plan_year'          => $hemisCurriculum->education_year_name,
                    'curricula_hemis_id' => $hemisCurriculum->curricula_hemis_id,
                    'level_code'         => $levelCode,
                    'semester_code'      => $semCode,
                    'education_type_name'=> $hemisCurriculum->education_type_name,
                    'education_period'   => $hemisCurriculum->education_period,
                    'file_original_name' => $file->getClientOriginalName(),
                    'file_path'          => $filePath,
                    'created_by'         => Auth::id(),
                ]);
                $createdIds[] = $curriculum->id;

                if ($i === 0) {
                    // Birinchi semestr: Exceldan import
                    $import = new ManualCurriculumImport($curriculum);
                    Excel::import($import, $file);

                    if (!empty($import->errors)) {
                        DB::rollBack();
                        return back()->with('error', implode(' ', $import->errors));
                    }

                    // Himoya: fayl ichidagi semestrlar tanlangan semestrlarga
                    // mos kelmasa — noto'g'ri (boshqa kursning) fayli
                    $fileSems = $curriculum->subjects()->whereNotNull('semester')
                        ->distinct()->pluck('semester')->map(fn($s) => (int) $s)->all();
                    $targetSems = array_map(
                        fn($c) => (int) $c >= 11 ? (int) $c - 10 : (int) $c,
                        array_filter($semCodes)
                    );
                    if ($fileSems && $targetSems && !array_intersect($fileSems, $targetSems)) {
                        DB::rollBack();
                        return back()->with('error',
                            'Fayl ichidagi semestrlar (' . implode(', ', $fileSems) .
                            ') tanlangan semestrlarga (' . implode(', ', $targetSems) .
                            "-semestr) mos kelmadi — boshqa kursning fayli bo'lishi mumkin. Yuklash bekor qilindi.");
                    }

                    $firstCurriculum = $curriculum;
                } else {
                    // Qo'shimcha semestrlar: birinchi semestrdan fanlarni nusxalaymiz
                    $now  = now();
                    $rows = $firstCurriculum->subjects()->get()->map(fn($s) => [
                        'manual_curriculum_id' => $curriculum->id,
                        'block'        => $s->block,
                        'subject_code' => $s->subject_code,
                        'subject_name' => $s->subject_name,
                        'reference_name' => $s->reference_name,
                        'kurs'         => $s->kurs,
                        'semester'     => $s->semester,
                        'total_hours'  => $s->total_hours,
                        'audit_total'  => $s->audit_total,
                        'lecture'      => $s->lecture,
                        'practice'     => $s->practice,
                        'laboratory'   => $s->laboratory,
                        'seminar'      => $s->seminar,
                        'independent'  => $s->independent,
                        'credit'       => $s->credit,
                        'note'         => $s->note,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ])->toArray();
                    ManualCurriculumSubject::insert($rows);
                }
            }

            // Bir HEMIS o'quv reja uchun namunaviy BITTA bo'lishi shart. Yangi
            // namunaviy muvaffaqiyatli yuklangach, xuddi shu HEMIS rejaga tegishli
            // eski namunaviy(lar) almashtiriladi (o'chiriladi) — fanlari va saqlangan
            // solishtirishlari kaskad orqali tozalanadi. Shunda dublikat va noto'g'ri
            // "Namunaviy rejada yo'q" natijalari qaytmaydi.
            $replaced = 0;
            if ($request->type === 'namunaviy' && $hemisCurriculum->curricula_hemis_id) {
                $replaced = ManualCurriculum::where('type', 'namunaviy')
                    ->where('curricula_hemis_id', $hemisCurriculum->curricula_hemis_id)
                    ->whereNotIn('id', $createdIds)
                    ->get()
                    ->each(fn ($old) => $old->delete())
                    ->count();
            }

            DB::commit();

            $semCount = count($semCodes);
            $msg = $semCount > 1
                ? "{$semCount} ta semestr uchun o'quv reja yuklandi: {$import->imported} ta fan qatori."
                : "O'quv reja yuklandi: {$import->imported} ta fan qatori o'qib olindi.";
            if ($replaced > 0) {
                $msg .= " Shu HEMIS reja uchun eski namunaviy ({$replaced} ta) almashtirildi.";
            }

            return redirect()->route('admin.oquv-reja.show', $firstCurriculum)->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("O'quv reja import xatolik: " . $e->getMessage());
            return back()->with('error', "Faylni o'qishda xatolik: " . $e->getMessage());
        }
    }

    public function export(Request $request, ManualCurriculum $curriculum)
    {
        $format = in_array($request->input('format'), ['jadval', 'setka']) ? $request->input('format') : 'jadval';
        $curriculum->load(['subjects' => fn($q) => $q->orderBy('id')]);
        $slug = \Illuminate\Support\Str::slug($curriculum->name);
        $fileName = "{$slug}-{$format}-" . now()->format('Y-m-d') . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(new ManualCurriculumExport($curriculum, $format), $fileName);
    }

    /**
     * Yo'nalish bo'yicha barcha kurslar (kohortlar) va yuklangan rejalar holati.
     * specialty_id (students.specialty_id) bo'yicha faol talabalar guruhlash asosida quriladi.
     */
    public function batchView(Request $request)
    {
        $request->validate([
            'specialty_code' => 'required_without:specialty_id|string',
            'specialty_id'   => 'required_without:specialty_code',
        ]);

        // Faol talabalar (status 11) bo'yicha har bir kohort (curriculum_id + level) uchun ma'lumot.
        // Yo'nalish kod bo'yicha tanlanadi (bir nechta specialty_id birlashtiriladi),
        // fakultet/ta'lim turi bo'yicha cheklanadi (students() helper orqali).
        $request->merge(['current_only' => true]);
        $rows = $this->students($request)
            ->whereNotNull('curriculum_id')
            ->selectRaw('curriculum_id, level_code, level_name, semester_code, semester_name, count(*) as student_count')
            ->groupBy('curriculum_id', 'level_code', 'level_name', 'semester_code', 'semester_name')
            ->orderBy('level_code')
            ->orderByDesc('student_count')
            ->get();

        $curricIds = $rows->pluck('curriculum_id')->filter()->unique()->values()->all();

        $curriculaInfo = Curriculum::whereIn('curricula_hemis_id', $curricIds)
            ->select('curricula_hemis_id', 'name', 'education_year_name', 'education_type_name', 'semester_count')
            ->get()->keyBy('curricula_hemis_id');

        // Yuklangan manual rejalar (namunaviy + ishchi)
        $uploaded = ManualCurriculum::whereIn('curricula_hemis_id', $curricIds)
            ->select('id', 'curricula_hemis_id', 'type', 'name', 'semester_code')
            ->get()->groupBy('curricula_hemis_id');

        return response()->json($rows->map(fn($r) => [
            'curriculum_id'   => $r->curriculum_id,
            'curriculum_name' => $curriculaInfo[$r->curriculum_id]?->name ?? "❌ ({$r->curriculum_id})",
            'education_year'  => $curriculaInfo[$r->curriculum_id]?->education_year_name,
            'education_type'  => $curriculaInfo[$r->curriculum_id]?->education_type_name,
            'semester_count'  => $curriculaInfo[$r->curriculum_id]?->semester_count,
            'level_code'      => $r->level_code,
            'level_name'      => $r->level_name,
            'semester_code'   => $r->semester_code,
            'semester_name'   => $r->semester_name,
            'student_count'   => $r->student_count,
            'uploaded'        => ($uploaded[$r->curriculum_id] ?? collect())->map(fn($m) => [
                'id'            => $m->id,
                'type'          => $m->type,
                'name'          => $m->name,
                'semester_code' => $m->semester_code,
            ])->values()->all(),
        ])->values());
    }

    /**
     * Bulk yuklash: har bir kurs (kohort) uchun bitta fayl — shu kursdagi barcha
     * curriculum va tanlangan semestrlarga qo'llanadi. Fayl bir marta o'qiladi,
     * qolgan (curriculum, semestr) juftliklariga fanlar nusxalanadi.
     */
    public function storeBulk(Request $request)
    {
        $request->validate([
            'type'                     => 'required|in:namunaviy,ishchi',
            'items'                    => 'required|array|min:1',
            'items.*.file'             => 'nullable|file|mimes:xlsx,xls|max:10240',
            'items.*.curricula'        => 'required|array|min:1',
            'items.*.curricula.*'      => 'integer|exists:curricula,curricula_hemis_id',
            'items.*.semester_codes'   => 'required|array|min:1',
            'items.*.semester_codes.*' => 'string|max:20',
        ]);

        $type    = $request->type;
        $preview = $request->boolean('preview');
        $created = 0;
        $skipped = 0;
        $errors  = [];
        $previewItems = [];

        foreach ($request->input('items', []) as $idx => $item) {
            $file = $request->file("items.$idx.file");
            if (!$file) {
                continue; // fayl tanlanmagan kurs — o'tkazib yuboriladi
            }

            $curricIds = array_values(array_unique(array_map('intval', $item['curricula'])));
            $semCodes  = array_values(array_unique(array_filter($item['semester_codes'])));
            if (!$curricIds || !$semCodes) {
                continue;
            }

            $curricula = Curriculum::whereIn('curricula_hemis_id', $curricIds)
                ->get()->keyBy('curricula_hemis_id');
            $semesterInfo = Semester::whereIn('curriculum_hemis_id', $curricIds)
                ->whereIn('code', $semCodes)
                ->get(['curriculum_hemis_id', 'code', 'name', 'level_code'])
                ->groupBy('curriculum_hemis_id');

            // Oldin yuklangan (curriculum, semestr) juftliklar takror yuklanmaydi
            $existing = ManualCurriculum::whereIn('curricula_hemis_id', $curricIds)
                ->where('type', $type)
                ->whereIn('semester_code', $semCodes)
                ->get(['curricula_hemis_id', 'semester_code'])
                ->map(fn($m) => $m->curricula_hemis_id . '|' . $m->semester_code)
                ->flip();

            $targetSems = array_map(fn($c) => (int) $c >= 11 ? (int) $c - 10 : (int) $c, $semCodes);

            // ── Preview: faylni o'qib ko'ramiz, lekin saqlamaymiz (rollback) ──
            if ($preview) {
                $summary = [
                    'file'        => $file->getClientOriginalName(),
                    'target_sems' => $targetSems,
                    'error'       => null,
                    'imported'    => 0,
                    'file_sems'   => [],
                    'sem_ok'      => true,
                    'credit_sum'  => 0,
                    'hours_sum'   => 0,
                    'sample'      => [],
                    'targets'     => [],
                ];
                DB::beginTransaction();
                try {
                    $firstCurr = $curricula[$curricIds[0]] ?? null;
                    $tmp = ManualCurriculum::create([
                        'type'                => $type,
                        'name'                => '[tekshiruv] ' . $file->getClientOriginalName(),
                        'plan_year'           => $firstCurr?->education_year_name,
                        'curricula_hemis_id'  => $curricIds[0],
                        'semester_code'       => $semCodes[0],
                        'education_type_name' => $firstCurr?->education_type_name,
                        'education_period'    => $firstCurr?->education_period,
                        'file_original_name'  => $file->getClientOriginalName(),
                        'file_path'           => '',
                        'created_by'          => Auth::id(),
                    ]);
                    $import = new ManualCurriculumImport($tmp);
                    Excel::import($import, $file);
                    if (!empty($import->errors)) {
                        throw new \RuntimeException(implode(' ', $import->errors));
                    }
                    $subs = $tmp->subjects()->get();
                    $fileSems = $subs->pluck('semester')->filter()
                        ->map(fn($s) => (int) $s)->unique()->sort()->values()->all();
                    $summary['imported']   = $subs->count();
                    $summary['file_sems']  = $fileSems;
                    $summary['sem_ok']     = empty($fileSems) || count(array_intersect($fileSems, $targetSems)) > 0;
                    $summary['credit_sum'] = round($subs->sum('credit'), 1);
                    $summary['hours_sum']  = round($subs->sum('total_hours'), 1);
                    $summary['sample']     = $subs->take(3)->pluck('subject_name')->all();
                } catch (\Exception $e) {
                    $summary['error'] = $e->getMessage();
                }
                DB::rollBack();

                foreach ($curricIds as $cid) {
                    $new = [];
                    $skip = [];
                    foreach ($semCodes as $semCode) {
                        $n = (int) $semCode >= 11 ? (int) $semCode - 10 : (int) $semCode;
                        if (isset($existing[$cid . '|' . $semCode])) {
                            $skip[] = $n;
                        } else {
                            $new[] = $n;
                        }
                    }
                    $summary['targets'][] = [
                        'name'         => $curricula[$cid]?->name ?? ('#' . $cid),
                        'sems'         => $new,
                        'skipped_sems' => $skip,
                    ];
                }
                $previewItems[] = $summary;
                continue;
            }

            $filePath = $file->store('manual-curricula', 'public');

            DB::beginTransaction();
            try {
                $master          = null;
                $masterRows      = null;
                $itemCreated     = 0;
                $createdIdsByCid = [];

                foreach ($curricIds as $cid) {
                    $hemisCurriculum = $curricula[$cid] ?? null;
                    if (!$hemisCurriculum) {
                        continue;
                    }
                    $specialty = Student::where('curriculum_id', $cid)
                        ->whereNotNull('specialty_code')
                        ->select('specialty_code as code', 'specialty_name as name')
                        ->first();
                    $semInfos = ($semesterInfo[$cid] ?? collect())->keyBy('code');

                    foreach ($semCodes as $semCode) {
                        if (isset($existing[$cid . '|' . $semCode])) {
                            $skipped++;
                            continue;
                        }
                        $semInfo   = $semInfos[$semCode] ?? null;
                        $semName   = $semInfo?->name ?? ($semCode . '-semestr');
                        $typeLabel = $type === 'namunaviy' ? 'namunaviy' : 'ishchi';
                        $name = $hemisCurriculum->name . ' — ' . $typeLabel;
                        if ($type === 'ishchi') {
                            $name .= ' (' . $semName . ')';
                        }

                        $curriculum = ManualCurriculum::create([
                            'type'                => $type,
                            'name'                => $name,
                            'specialty_code'      => $specialty?->code,
                            'specialty_name'      => $specialty?->name,
                            'plan_year'           => $hemisCurriculum->education_year_name,
                            'curricula_hemis_id'  => $cid,
                            'level_code'          => $semInfo?->level_code,
                            'semester_code'       => $semCode,
                            'education_type_name' => $hemisCurriculum->education_type_name,
                            'education_period'    => $hemisCurriculum->education_period,
                            'file_original_name'  => $file->getClientOriginalName(),
                            'file_path'           => $filePath,
                            'created_by'          => Auth::id(),
                        ]);
                        $createdIdsByCid[$cid][] = $curriculum->id;

                        if ($master === null) {
                            // Birinchi juftlik: Exceldan import
                            $import = new ManualCurriculumImport($curriculum);
                            Excel::import($import, $file);
                            if (!empty($import->errors)) {
                                throw new \RuntimeException(implode(' ', $import->errors));
                            }

                            // Himoya: fayl ichidagi semestrlar tanlangan semestrlarga
                            // mos kelmasa — noto'g'ri (boshqa kursning) fayli, bekor qilamiz
                            $fileSems = $curriculum->subjects()->whereNotNull('semester')
                                ->distinct()->pluck('semester')->map(fn($s) => (int) $s)->all();
                            $targetSems = array_map(fn($c) => (int) $c >= 11 ? (int) $c - 10 : (int) $c, $semCodes);
                            if ($fileSems && !array_intersect($fileSems, $targetSems)) {
                                throw new \RuntimeException(
                                    'fayl ichidagi semestrlar (' . implode(', ', $fileSems) .
                                    ') tanlangan semestrlarga (' . implode(', ', $targetSems) .
                                    ") mos emas — boshqa kursning fayli bo'lishi mumkin"
                                );
                            }

                            $master = $curriculum;
                        } else {
                            // Qolganlariga fanlarni nusxalaymiz
                            if ($masterRows === null) {
                                $masterRows = $master->subjects()->get();
                            }
                            $now = now();
                            ManualCurriculumSubject::insert($masterRows->map(fn($s) => [
                                'manual_curriculum_id' => $curriculum->id,
                                'block'          => $s->block,
                                'subject_code'   => $s->subject_code,
                                'subject_name'   => $s->subject_name,
                                'reference_name' => $s->reference_name,
                                'kurs'           => $s->kurs,
                                'semester'       => $s->semester,
                                'total_hours'    => $s->total_hours,
                                'audit_total'    => $s->audit_total,
                                'lecture'        => $s->lecture,
                                'practice'       => $s->practice,
                                'laboratory'     => $s->laboratory,
                                'seminar'        => $s->seminar,
                                'independent'    => $s->independent,
                                'credit'         => $s->credit,
                                'note'           => $s->note,
                                'created_at'     => $now,
                                'updated_at'     => $now,
                            ])->toArray());
                        }
                        $itemCreated++;
                    }
                }

                // Namunaviy uchun: har bir HEMIS reja bo'yicha yangi namunaviy
                // yuklangach, o'sha rejaga tegishli eski namunaviy(lar) almashtiriladi
                // (bir HEMIS reja = bitta namunaviy). Fanlar va solishtirishlar kaskad
                // orqali o'chadi.
                if ($type === 'namunaviy') {
                    foreach ($createdIdsByCid as $cid => $newIds) {
                        ManualCurriculum::where('type', 'namunaviy')
                            ->where('curricula_hemis_id', $cid)
                            ->whereNotIn('id', $newIds)
                            ->get()
                            ->each(fn ($old) => $old->delete());
                    }
                }

                DB::commit();
                $created += $itemCreated;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Bulk o'quv reja import xatolik: " . $e->getMessage());
                $errors[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
            }
        }

        if ($preview) {
            return response()->json(['preview' => true, 'items' => $previewItems]);
        }

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
        ]);
    }

    /**
     * Rejalashtirilgan reja uchun nusxa manbalari: mavjud ishchi rejalar ro'yxati.
     * Ixtiyoriy specialty_code bo'yicha filtrlash mumkin.
     */
    public function plannedSources(Request $request)
    {
        $q = ManualCurriculum::query()
            ->where('type', 'ishchi')
            ->withCount('subjects')
            ->orderByDesc('id');

        if ($request->filled('specialty_code')) {
            $q->where('specialty_code', $request->specialty_code);
        }
        if ($request->filled('level_code')) {
            $q->where('level_code', $request->level_code);
        }

        return response()->json($q->get()->map(fn($m) => [
            'id'             => $m->id,
            'name'           => $m->name,
            'specialty_code' => $m->specialty_code,
            'specialty_name' => $m->specialty_name,
            'plan_year'      => $m->plan_year,
            'level_code'     => $m->level_code,
            'semester_code'  => $m->semester_code,
            'subjects_count' => $m->subjects_count,
        ])->values());
    }

    /**
     * Rejalashtirilgan (HEMIS'siz) ishchi reja yaratish.
     *  - mode=copy: mavjud reja(lar)dan nusxa (o'tgan yildan)
     *  - mode=new : yangi yo'nalish uchun (Excel yuklab yoki bo'sh, keyin qo'lda to'ldiriladi)
     * Har ikkalasida ham status='planned', curricula_hemis_id=NULL.
     */
    public function storePlanned(Request $request)
    {
        $request->validate([
            'mode'      => 'required|in:copy,new',
            'plan_year' => 'required|string|max:50',
        ]);

        // ── Nusxa rejimi ──────────────────────────────────────────────
        if ($request->mode === 'copy') {
            $data = $request->validate([
                'source_ids'   => 'required|array|min:1',
                'source_ids.*' => 'integer|exists:manual_curricula,id',
            ]);

            $sources = ManualCurriculum::with('subjects')
                ->whereIn('id', $data['source_ids'])->get();
            if ($sources->isEmpty()) {
                return response()->json(['error' => 'Manba reja topilmadi.'], 422);
            }

            $created = 0;
            DB::beginTransaction();
            try {
                foreach ($sources as $src) {
                    $planned = ManualCurriculum::create([
                        'type'                => 'ishchi',
                        'status'              => 'planned',
                        'name'                => $this->plannedName($src->specialty_name ?: $src->name, $request->plan_year, $src->semester_code),
                        'specialty_code'      => $src->specialty_code,
                        'specialty_name'      => $src->specialty_name,
                        'plan_year'           => $request->plan_year,
                        'curricula_hemis_id'  => null,
                        'level_code'          => $src->level_code,
                        'semester_code'       => $src->semester_code,
                        'education_type_name' => $src->education_type_name,
                        'education_period'    => $src->education_period,
                        'notes'               => 'Nusxa manbasi: #' . $src->id . ' (' . $src->name . ')',
                        'created_by'          => Auth::id(),
                    ]);
                    $this->copySubjects($planned->id, $src->subjects);
                    $created++;
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Rejalashtirilgan reja (nusxa) xatolik: ' . $e->getMessage());
                return response()->json(['error' => $e->getMessage()], 500);
            }

            return response()->json(['created' => $created]);
        }

        // ── Yangi yo'nalish rejimi ────────────────────────────────────
        $data = $request->validate([
            'specialty_code'      => 'nullable|string|max:50',
            'specialty_name'      => 'required|string|max:255',
            'level_code'          => 'nullable|string|max:20',
            'education_type_name' => 'nullable|string|max:255',
            'semester_codes'      => 'required|array|min:1',
            'semester_codes.*'    => 'string|max:20',
            'file'                => 'nullable|file|mimes:xlsx,xls|max:10240',
        ]);

        $semCodes = array_values(array_unique(array_filter($data['semester_codes'])));
        $file     = $request->file('file');
        $filePath = $file ? $file->store('manual-curricula', 'public') : null;

        $created = 0;
        DB::beginTransaction();
        try {
            $master = null;
            $masterRows = null;
            foreach ($semCodes as $semCode) {
                $planned = ManualCurriculum::create([
                    'type'                => 'ishchi',
                    'status'              => 'planned',
                    'name'                => $this->plannedName($data['specialty_name'], $request->plan_year, $semCode),
                    'specialty_code'      => $data['specialty_code'] ?? null,
                    'specialty_name'      => $data['specialty_name'],
                    'plan_year'           => $request->plan_year,
                    'curricula_hemis_id'  => null,
                    'level_code'          => $data['level_code'] ?? null,
                    'semester_code'       => $semCode,
                    'education_type_name' => $data['education_type_name'] ?? null,
                    'file_original_name'  => $file?->getClientOriginalName(),
                    'file_path'           => $filePath,
                    'created_by'          => Auth::id(),
                ]);

                if ($file) {
                    if ($master === null) {
                        $import = new ManualCurriculumImport($planned);
                        Excel::import($import, $file);
                        if (!empty($import->errors)) {
                            throw new \RuntimeException(implode(' ', $import->errors));
                        }
                        $master = $planned;
                        $masterRows = $master->subjects()->get();
                    } else {
                        $this->copySubjects($planned->id, $masterRows);
                    }
                }
                $created++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Rejalashtirilgan reja (yangi) xatolik: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(['created' => $created]);
    }

    /**
     * Rejalashtirilgan rejani HEMIS o'quv rejasiga bog'lash: HEMIS reja
     * paydo bo'lgach, curricula_hemis_id to'ldiriladi va status='active' bo'ladi.
     * Shundan keyin reja "Yo'nalish bo'yicha" tabida ko'rinadi.
     */
    public function linkToHemis(Request $request, ManualCurriculum $curriculum)
    {
        $request->validate([
            'curricula_hemis_id' => 'required|exists:curricula,curricula_hemis_id',
        ]);

        $hemis = Curriculum::where('curricula_hemis_id', $request->curricula_hemis_id)->first();

        $curriculum->update([
            'curricula_hemis_id'  => $hemis->curricula_hemis_id,
            'status'              => 'active',
            'level_code'          => $curriculum->level_code ?: null,
            'education_type_name' => $curriculum->education_type_name ?: $hemis->education_type_name,
        ]);

        return back()->with('success', "Reja HEMIS o'quv rejasiga bog'landi: {$hemis->name}");
    }

    /** Rejalashtirilgan reja nomini shakllantirish. */
    private function plannedName(string $base, string $planYear, ?string $semCode): string
    {
        $name = trim($base) . ' — ishchi (REJA · ' . $planYear;
        if ($semCode) {
            $n = (int) $semCode >= 11 ? (int) $semCode - 10 : (int) $semCode;
            $name .= ', ' . $n . '-semestr';
        }
        return $name . ')';
    }

    /** Manba fanlarni yangi rejaga ko'chirish. */
    private function copySubjects(int $targetId, $sourceSubjects): void
    {
        if ($sourceSubjects->isEmpty()) {
            return;
        }
        $now = now();
        ManualCurriculumSubject::insert($sourceSubjects->map(fn($s) => [
            'manual_curriculum_id' => $targetId,
            'block'          => $s->block,
            'subject_code'   => $s->subject_code,
            'subject_name'   => $s->subject_name,
            'reference_name' => $s->reference_name,
            'kurs'           => $s->kurs,
            'semester'       => $s->semester,
            'total_hours'    => $s->total_hours,
            'audit_total'    => $s->audit_total,
            'lecture'        => $s->lecture,
            'practice'       => $s->practice,
            'laboratory'     => $s->laboratory,
            'seminar'        => $s->seminar,
            'independent'    => $s->independent,
            'credit'         => $s->credit,
            'note'           => $s->note,
            'created_at'     => $now,
            'updated_at'     => $now,
        ])->toArray());
    }

    /**
     * 2-BOSQICH: Barcha ishchi rejalardagi o'tiladigan fanlarni bir joyga to'plash.
     * Yo'nalish + kurs + semestr kesimida takrorlanmas fanlar, soatlari turlar
     * bo'yicha (ma'ruza/amaliy/lab/seminar/mustaqil) — jadval yuklamasini
     * hisoblash uchun asos.
     */
    private function subjectsSummaryQuery(Request $request)
    {
        return DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->when(!$request->boolean('include_planned', true), fn($q) => $q->where('mc.status', 'active'))
            ->when($request->filled('specialty_code'), fn($q) => $q->where('mc.specialty_code', $request->specialty_code))
            ->when($request->filled('plan_year'), fn($q) => $q->where('mc.plan_year', $request->plan_year))
            // O'qitiladigan o'quv yili = plan_year boshi + (kurs - 1). Bir o'quv yilining
            // barcha kurslari turli plan_year larda saqlanadi (guruh kirgan yili bo'yicha).
            ->when($request->filled('academic_year'), function ($q) use ($request) {
                $start = (int) substr($request->academic_year, 0, 4);
                $q->whereRaw(
                    "(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + GREATEST(CAST(mc.level_code AS UNSIGNED) - 10, 0) - 1) = ?",
                    [$start]
                );
            })
            ->when($request->filled('level_code'), fn($q) => $q->where('mc.level_code', $request->level_code))
            ->when($request->filled('semester'), fn($q) => $q->where('s.semester', $request->semester))
            ->groupBy('mc.specialty_code', 'mc.specialty_name', 'mc.level_code', 's.semester', 's.block', 's.subject_name')
            ->selectRaw("mc.specialty_code, mc.specialty_name, mc.level_code, s.semester,
                s.block, s.subject_name,
                MAX(s.lecture) as lecture, MAX(s.practice) as practice, MAX(s.laboratory) as laboratory,
                MAX(s.seminar) as seminar, MAX(s.independent) as independent,
                MAX(s.total_hours) as total_hours, MAX(s.credit) as credit,
                COUNT(DISTINCT s.manual_curriculum_id) as reja_count,
                GROUP_CONCAT(DISTINCT CONCAT(mc.id, '::', mc.name) SEPARATOR '|||') as reja_pairs")
            ->orderBy('mc.specialty_code')
            ->orderBy('s.semester')
            ->orderBy('s.block')
            ->orderBy('s.subject_name');
    }

    /** Fan nomini kafedra qidirish uchun normallashtirish (raqam/tinish belgilarsiz). */
    private function normSubject(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(["'", '’', 'ʻ', 'ʼ', '`', '´'], '', $s);
        $s = preg_replace('/[.,;:()\-\–\/]/u', ' ', $s);
        $s = preg_replace('/\b\d+([.,]\d+)?\b/u', ' ', $s); // "1.2" kabi raqamlarni olib tashlash
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    /**
     * Fan nomi (normallashtirilgan) → kafedra nomi xaritasi.
     * HEMIS curriculum_subjects jadvalidagi department_name asosida.
     */
    private function kafedraMap(): array
    {
        $rows = DB::table('curriculum_subjects')
            ->whereNotNull('department_name')
            ->where('department_name', '!=', '')
            ->selectRaw('subject_name, department_name, COUNT(*) as c')
            ->groupBy('subject_name', 'department_name')
            ->get();

        $acc = [];
        foreach ($rows as $r) {
            $k = $this->normSubject($r->subject_name);
            if ($k === '') {
                continue;
            }
            $acc[$k][$r->department_name] = ($acc[$k][$r->department_name] ?? 0) + (int) $r->c;
        }
        $map = [];
        foreach ($acc as $k => $deps) {
            arsort($deps);
            $map[$k] = array_key_first($deps);
        }
        return $map;
    }

    /** Qo'lda kiritilgan fan tuzatishlari: norm_name → ['kafedra'=>, 'practice'=>]. */
    private function subjectOverrides(): array
    {
        return \App\Models\SubjectKafedraOverride::get()->keyBy('norm_name')
            ->map(fn($o) => [
                'kafedra'  => $o->kafedra_name ?: null,
                'practice' => $o->practice_group_size ?: null,
            ])->all();
    }

    /** Fanning amaliy guruh o'lchamining sukut qiymati (blok/nom kalit so'zlaridan). */
    private function defaultPracticeSize(?string $name, ?string $block): int
    {
        $t = $this->normSubject(($block ?? '') . ' ' . ($name ?? ''));

        // Klinik fanlar — kichik guruh (~10)
        foreach (['klinik', 'kasallik', 'terapiya', 'xirurgiya', 'jarrohlik', 'pediatriya', 'akusher',
                  'ginekolog', 'nevrolog', 'kardiolog', 'onkolog', 'urolog', 'endokrin', 'dermato',
                  'psixiatr', 'stomatolog', 'ftiziatr', 'reanimatsiya', 'anesteziolog', 'yuqumli'] as $kw) {
            if (str_contains($t, $kw)) {
                return 10;
            }
        }

        // Til fanlari (xorijiy til, rus/o'zbek tili, lotin tili) — kichik til guruhi
        if (preg_match('/(\btil|xorijiy|ingliz|inglis)/u', $t)) {
            return 15;
        }

        // Gumanitar-ijtimoiy fanlar — butun guruh (~30)
        foreach (['ijtimoiy', 'gumanitar', 'tarix', 'falsafa', 'din', 'huquq', 'iqtisod', 'pedagog',
                  'psixolog', 'jismoniy', 'sport', 'madaniyat', 'siyosat'] as $kw) {
            if (str_contains($t, $kw)) {
                return 30;
            }
        }

        return 15;
    }

    public function subjectsSummary(Request $request)
    {
        $rows  = $this->subjectsSummaryQuery($request)->get();
        $kafMap = $this->kafedraMap();
        $overrides = $this->subjectOverrides();

        $num = fn($v) => $v === null ? null : (float) $v;
        $kaf = function ($name) use ($overrides, $kafMap) {
            $k = $this->normSubject($name);
            $ov = $overrides[$k] ?? null;
            if ($ov && $ov['kafedra']) {
                return ['name' => $ov['kafedra'], 'manual' => true];
            }
            return ['name' => $kafMap[$k] ?? null, 'manual' => false];
        };
        $psize = function ($name, $block) use ($overrides) {
            $k = $this->normSubject($name);
            $ov = $overrides[$k] ?? null;
            if ($ov && $ov['practice']) {
                return ['size' => (int) $ov['practice'], 'manual' => true];
            }
            return ['size' => $this->defaultPracticeSize($name, $block), 'manual' => false];
        };
        $data = $rows->map(fn($r) => [
            'specialty_code' => $r->specialty_code,
            'specialty_name' => $r->specialty_name,
            'level_code'     => $r->level_code,
            'kurs'           => $r->level_code ? ((int) $r->level_code >= 11 ? (int) $r->level_code - 10 : (int) $r->level_code) : null,
            'semester'       => $r->semester ? (int) $r->semester : null,
            'block'          => $r->block,
            'subject_name'   => $r->subject_name,
            'kafedra'        => $kaf($r->subject_name)['name'],
            'kafedra_manual' => $kaf($r->subject_name)['manual'],
            'practice_size'  => $psize($r->subject_name, $r->block)['size'],
            'practice_manual'=> $psize($r->subject_name, $r->block)['manual'],
            'reja'           => collect(explode('|||', $r->reja_pairs ?? ''))
                ->filter()
                ->map(function ($p) {
                    [$id, $name] = array_pad(explode('::', $p, 2), 2, '');
                    return ['id' => (int) $id, 'name' => $name];
                })->values()->all(),
            'lecture'        => $num($r->lecture),
            'practice'       => $num($r->practice),
            'laboratory'     => $num($r->laboratory),
            'seminar'        => $num($r->seminar),
            'independent'    => $num($r->independent),
            'total_hours'    => $num($r->total_hours),
            'credit'         => $num($r->credit),
            'reja_count'     => (int) $r->reja_count,
        ])->all();

        $sum = fn($rows, $k) => round(array_sum(array_map(fn($r) => $r[$k] ?? 0, $rows)), 1);
        $mkTotals = fn($rows) => [
            'subjects'    => count($rows),
            'lecture'     => $sum($rows, 'lecture'),
            'practice'    => $sum($rows, 'practice'),
            'laboratory'  => $sum($rows, 'laboratory'),
            'seminar'     => $sum($rows, 'seminar'),
            'independent' => $sum($rows, 'independent'),
            'total_hours' => $sum($rows, 'total_hours'),
            'credit'      => $sum($rows, 'credit'),
        ];

        // Semestr kesimida yuklama — semestrlararo balansni ko'rish uchun
        // (keyingi bosqichda fanni semestrdan semestrga ko'chirib yuklamani tenglashtirish)
        $bySemester = collect($data)
            ->groupBy(fn($r) => $r['semester'] ?? 0)
            ->map(fn($rows, $sem) => array_merge(['semester' => $sem ?: null], $mkTotals($rows->all())))
            ->sortBy('semester')
            ->values();

        return response()->json([
            'rows'        => $data,
            'totals'      => $mkTotals($data),
            'by_semester' => $bySemester,
        ]);
    }

    public function subjectsSummaryExport(Request $request)
    {
        $rows   = $this->subjectsSummaryQuery($request)->get();
        $kafMap = $this->kafedraMap();
        $overrides = $this->subjectOverrides();

        $headers = ['Yo\'nalish kodi', 'Yo\'nalish', 'Kurs', 'Semestr', 'Blok', 'Fan', 'Kafedra', 'Amaliy guruh (kishi)', 'O\'quv reja(lar)',
            'Ma\'ruza', 'Amaliy', 'Laboratoriya', 'Seminar', 'Mustaqil', 'Jami soat', 'Kredit', 'Rejalar soni'];

        $fname = 'otiladigan-fanlar-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows, $headers, $kafMap, $overrides) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM (Excel uchun)
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                $kurs = $r->level_code ? ((int) $r->level_code >= 11 ? (int) $r->level_code - 10 : (int) $r->level_code) : '';
                $nk = $this->normSubject($r->subject_name);
                $ov = $overrides[$nk] ?? null;
                $kafedra = ($ov && $ov['kafedra']) ? $ov['kafedra'] : ($kafMap[$nk] ?? '');
                $psize = ($ov && $ov['practice']) ? $ov['practice'] : $this->defaultPracticeSize($r->subject_name, $r->block);
                $rejaNames = collect(explode('|||', $r->reja_pairs ?? ''))
                    ->filter()->map(fn($p) => trim(explode('::', $p, 2)[1] ?? ''))->implode(' | ');
                fputcsv($out, [
                    $r->specialty_code, $r->specialty_name, $kurs, $r->semester,
                    $r->block, $r->subject_name, $kafedra, $psize, $rejaNames,
                    $r->lecture, $r->practice, $r->laboratory, $r->seminar, $r->independent,
                    $r->total_hours, $r->credit, $r->reja_count,
                ]);
            }
            fclose($out);
        }, $fname, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Kafedra tanlash uchun mavjud kafedralar ro'yxati (HEMIS + qo'lda kiritilganlar). */
    public function kafedraList()
    {
        $fromHemis = DB::table('curriculum_subjects')
            ->whereNotNull('department_name')->where('department_name', '!=', '')
            ->distinct()->pluck('department_name');
        $fromDepts = DB::table('departments')
            ->whereNotNull('name')->where('name', '!=', '')
            ->pluck('name');
        $fromOverrides = \App\Models\SubjectKafedraOverride::pluck('kafedra_name');

        $all = $fromHemis->merge($fromDepts)->merge($fromOverrides)
            ->map(fn($s) => trim($s))->filter()->unique()->sort()->values();

        return response()->json($all);
    }

    /** Fan uchun kafedrani qo'lda belgilash/tozalash (fan nomi bo'yicha, barqaror). */
    public function setKafedra(Request $request)
    {
        $data = $request->validate([
            'subject_name' => 'required|string|max:255',
            'kafedra_name' => 'nullable|string|max:255',
        ]);

        $norm = $this->normSubject($data['subject_name']);
        if ($norm === '') {
            return response()->json(['error' => "Fan nomi bo'sh."], 422);
        }

        $kafedra = trim((string) ($data['kafedra_name'] ?? ''));
        $ov = \App\Models\SubjectKafedraOverride::firstOrNew(['norm_name' => $norm]);
        $ov->sample_name  = $data['subject_name'];
        $ov->kafedra_name = $kafedra;
        $ov->department_id = $kafedra !== ''
            ? (DB::table('curriculum_subjects')->where('department_name', $kafedra)->value('department_id')
                ?? DB::table('departments')->where('name', $kafedra)->value('department_hemis_id'))
            : null;
        $ov->updated_by = Auth::id();

        // Ikkala sozlama ham bo'sh bo'lsa — yozuvni o'chiramiz (avtomatik qaytadi)
        if ($kafedra === '' && !$ov->practice_group_size) {
            if ($ov->exists) {
                $ov->delete();
            }
            return response()->json(['ok' => true, 'cleared' => true]);
        }
        $ov->save();

        return response()->json(['ok' => true, 'kafedra' => $kafedra]);
    }

    /** Fan uchun amaliy guruh o'lchamini (nechta kishilik) qo'lda belgilash/tozalash. */
    public function setPracticeSize(Request $request)
    {
        $data = $request->validate([
            'subject_name'        => 'required|string|max:255',
            'practice_group_size' => 'nullable|integer|min:1|max:500',
        ]);

        $norm = $this->normSubject($data['subject_name']);
        if ($norm === '') {
            return response()->json(['error' => "Fan nomi bo'sh."], 422);
        }

        $size = $data['practice_group_size'] ?? null;
        $ov = \App\Models\SubjectKafedraOverride::firstOrNew(['norm_name' => $norm]);
        $ov->sample_name = $data['subject_name'];
        $ov->practice_group_size = $size ?: null;
        if ($ov->kafedra_name === null) {
            $ov->kafedra_name = '';
        }
        $ov->updated_by = Auth::id();

        if (!$size && ($ov->kafedra_name === '' || $ov->kafedra_name === null)) {
            if ($ov->exists) {
                $ov->delete();
            }
            return response()->json(['ok' => true, 'cleared' => true]);
        }
        $ov->save();

        return response()->json(['ok' => true, 'practice_group_size' => $size]);
    }

    /**
     * 3-BOSQICH: Bo'lajak kontingent — kelasi o'quv yili uchun har yo'nalish+kursda
     * kutilayotgan talaba soni va undan hisoblanadigan oqim/guruh soni.
     * Proyeksiya: joriy (k-1)-kurs talabalari kelasi yili k-kurs bo'ladi.
     * 1-kurs = yangi qabul (joriy 1-kurs soni taxminiy default sifatida).
     */
    public function contingentData(Request $request)
    {
        $request->merge(['current_only' => true]);
        $rows = $this->students($request)
            ->whereNotNull('specialty_code')
            ->selectRaw('specialty_code, MAX(specialty_name) as specialty_name, level_code, COUNT(*) as cnt')
            ->groupBy('specialty_code', 'level_code')
            ->get();

        $cur = [];   // [specialty][course] = joriy talaba soni
        $names = [];
        foreach ($rows as $r) {
            $course = (int) $r->level_code >= 11 ? (int) $r->level_code - 10 : (int) $r->level_code;
            if ($course < 1) {
                continue;
            }
            $cur[$r->specialty_code][$course] = (int) $r->cnt;
            $names[$r->specialty_code] = $r->specialty_name;
        }

        $saved = ContingentProjection::where('academic_year', $request->input('academic_year', ''))
            ->get();
        // Til bilan: [specialty|level|lang] => yozuv
        $savedByLang = $saved->keyBy(fn($p) => $p->specialty_code . '|' . $p->level_code . '|' . ($p->lang ?: 'uz'));

        // Joriy 1-kurs talabalarining til bo'yicha taqsimoti (yangi qabul uchun default)
        $curLangSplit = $this->contingentCurrentFirstYearLangSplit($request); // [specialty] => [uz,rus,ing]

        $out = [];
        foreach ($cur as $spec => $courses) {
            $maxCourse = max(array_keys($courses));
            for ($k = 1; $k <= min($maxCourse + 1, 6); $k++) {
                $prev = $k >= 2 ? ($courses[$k - 1] ?? 0) : null;   // shu kursni to'ldiradigan joriy kohort
                $lvl  = (string) (10 + $k);

                if ($k === 1) {
                    // 1-kurs (yangi qabul) — TIL bo'yicha alohida
                    $split = $curLangSplit[(string) $spec] ?? ['uz' => ($courses[1] ?? 0), 'rus' => 0, 'ing' => 0];
                    $langs = [];
                    $hasOv = false;
                    foreach (['uz', 'rus', 'ing'] as $lg) {
                        $ov = $savedByLang[$spec . '|' . $lvl . '|' . $lg] ?? null;
                        $langs[$lg] = $ov ? (int) $ov->expected_count : (int) ($split[$lg] ?? 0);
                        if ($ov) { $hasOv = true; }
                    }
                    $out[] = [
                        'specialty_code' => (string) $spec,
                        'specialty_name' => $names[$spec] ?? (string) $spec,
                        'course'         => 1,
                        'level_code'     => $lvl,
                        'current_prev'   => null,
                        'current_first'  => $courses[1] ?? 0,
                        'langs'          => $langs,     // {uz, rus, ing} — saqlangan yoki joriy split
                        'cur_langs'      => ['uz' => (int) ($split['uz'] ?? 0), 'rus' => (int) ($split['rus'] ?? 0), 'ing' => (int) ($split['ing'] ?? 0)],
                        'projected'      => array_sum($langs),
                        'has_override'   => $hasOv,
                    ];
                    continue;
                }

                // 2+ kurs — joriy talabalardan avtomatik (til bu yerda kiritilmaydi).
                // Bo'sh (oldingi kurs yo'q) kurslarni chiqarmaymiz.
                if ((int) ($prev ?? 0) <= 0) {
                    continue;
                }
                $out[] = [
                    'specialty_code' => (string) $spec,
                    'specialty_name' => $names[$spec] ?? (string) $spec,
                    'course'         => $k,
                    'level_code'     => $lvl,
                    'current_prev'   => $prev,
                    'current_first'  => $courses[1] ?? 0,
                    'projected'      => $prev ?? 0,
                    'has_override'   => false,
                ];
            }
        }

        // Joriy talabasi yo'q, lekin bashorat saqlangan yangi yo'nalishlar (1-kurs) ham ko'rinsin
        $present = collect($out)->filter(fn($r) => $r['course'] === 1)
            ->keyBy('specialty_code');
        // Yangi yo'nalishlarni til bo'yicha yig'amiz
        $newSpecs = [];
        foreach ($saved as $ov) {
            if ((string) $ov->level_code !== '11' || $present->has($ov->specialty_code)) {
                continue;
            }
            $sc = $ov->specialty_code;
            if (!isset($newSpecs[$sc])) {
                $newSpecs[$sc] = [
                    'specialty_code'  => $sc,
                    'specialty_name'  => $ov->specialty_name ?: $sc,
                    'course'          => 1,
                    'level_code'      => '11',
                    'current_prev'    => null,
                    'current_first'   => 0,
                    'department_id'   => $ov->department_id,
                    'department_name' => $ov->department_name,
                    'langs'           => ['uz' => 0, 'rus' => 0, 'ing' => 0],
                    'has_override'    => true,
                ];
            }
            $newSpecs[$sc]['langs'][$ov->lang ?: 'uz'] = (int) $ov->expected_count;
        }
        foreach ($newSpecs as $row) {
            $row['projected'] = array_sum($row['langs']);
            $out[] = $row;
        }

        usort($out, fn($a, $b) => [$a['specialty_name'], $a['course']] <=> [$b['specialty_name'], $b['course']]);

        return response()->json(['rows' => $out]);
    }

    public function contingentSave(Request $request)
    {
        $data = $request->validate([
            'academic_year'            => 'required|string|max:50',
            'items'                    => 'required|array|min:1',
            'items.*.specialty_code'   => 'required|string|max:50',
            'items.*.specialty_name'   => 'nullable|string|max:255',
            'items.*.level_code'       => 'required|string|max:20',
            'items.*.lang'             => 'nullable|string|in:uz,rus,ing',
            'items.*.department_id'    => 'nullable',
            'items.*.department_name'  => 'nullable|string|max:255',
            'items.*.expected_count'   => 'nullable|integer|min:0',
        ]);

        $n = 0;
        foreach ($data['items'] as $it) {
            // Fakultet mahalliy id sifatida kelsa — oqim proyeksiyasi uchun HEMIS id ga o'giramiz
            $deptId = $it['department_id'] ?? null;
            if ($deptId) {
                $hemis = DB::table('departments')->where('id', $deptId)->value('department_hemis_id');
                if ($hemis) {
                    $deptId = $hemis;
                }
            }
            $lang = $it['lang'] ?? 'uz';
            ContingentProjection::updateOrCreate(
                [
                    'academic_year'  => $data['academic_year'],
                    'specialty_code' => $it['specialty_code'],
                    'level_code'     => $it['level_code'],
                    'lang'           => $lang,
                ],
                [
                    'specialty_name'  => $it['specialty_name'] ?? null,
                    'department_id'   => $deptId,
                    'department_name' => $it['department_name'] ?? null,
                    'expected_count'  => (int) ($it['expected_count'] ?? 0),
                    'updated_by'      => Auth::id(),
                ]
            );
            $n++;
        }

        return response()->json(['ok' => true, 'saved' => $n]);
    }

    /**
     * Joriy 1-kurs talabalarining yo'nalish + til bo'yicha taqsimoti (yangi qabul
     * bashorati uchun default). Qaytaradi: [specialty_code => ['uz'=>n,'rus'=>n,'ing'=>n]].
     */
    private function contingentCurrentFirstYearLangSplit(Request $request): array
    {
        $q = DB::table('students as s')
            ->leftJoin('groups as g', 'g.group_hemis_id', '=', 's.group_id')
            ->where('s.student_status_code', 11)
            ->where('s.level_code', '11')
            ->whereNotNull('s.specialty_code');
        if ($request->filled('education_type_code')) {
            $q->where('s.education_type_code', $request->education_type_code);
        }
        if ($request->filled('department_id')) {
            $q->where('s.department_id', $request->department_id);
        }
        $rows = $q->selectRaw('s.specialty_code, g.education_lang_name as lang_name, s.group_name, COUNT(*) as c')
            ->groupBy('s.specialty_code', 'g.education_lang_name', 's.group_name')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $sc = (string) $r->specialty_code;
            if (!isset($out[$sc])) { $out[$sc] = ['uz' => 0, 'rus' => 0, 'ing' => 0]; }
            $s = mb_strtolower(trim(($r->lang_name ?? '') . ' ' . ($r->group_name ?? '')));
            if (str_contains($s, 'рус') || str_contains($s, 'rus') || str_contains($s, '(rus')) {
                $out[$sc]['rus'] += (int) $r->c;
            } elseif (str_contains($s, 'ingl') || str_contains($s, 'engl') || str_contains($s, 'англ')
                || str_contains($s, '(ing') || str_contains($s, '(ang')) {
                $out[$sc]['ing'] += (int) $r->c;
            } else {
                $out[$sc]['uz'] += (int) $r->c;
            }
        }
        return $out;
    }

    // ===== 4-BOSQICH: O'qituvchilar ehtiyoji =====

    /** O'qituvchi lavozim normalari ro'yxati. */
    public function teacherNorms()
    {
        return response()->json(
            \App\Models\TeacherNorm::orderBy('sort')->orderBy('id')->get(['id', 'position', 'annual_hours'])
        );
    }

    /** Normalarni saqlash (qo'shish/tahrirlash/o'chirish). */
    public function teacherNormsSave(Request $request)
    {
        $data = $request->validate([
            'items'               => 'required|array',
            'items.*.position'    => 'required|string|max:100',
            'items.*.annual_hours'=> 'required|integer|min:1|max:5000',
        ]);
        \App\Models\TeacherNorm::truncate();
        foreach (array_values($data['items']) as $i => $it) {
            \App\Models\TeacherNorm::create([
                'position'     => $it['position'],
                'annual_hours' => $it['annual_hours'],
                'sort'         => $i + 1,
            ]);
        }
        return response()->json(['ok' => true]);
    }

    /** Yo'nalish nomini solishtirish uchun kalit (harf/raqamdan boshqasi olib tashlanadi). */
    private function specKey(?string $name): string
    {
        return preg_replace('/[^a-z0-9]/u', '', mb_strtolower(trim((string) $name)));
    }

    /**
     * Tasdiqlangan oqim snapshotlaridan yo'nalish+kurs kesimida oqim/talaba
     * ma'lumotini yig'ish: [specKey][course] => ['oqim'=>N, 'students'=>N,
     * 'pract_by_lang'=>[lang=>students]].
     */
    private function oqimCountsForDemand(string $academicYear, string $kind, $facultyId = null): array
    {
        $q = \App\Models\OqimSnapshot::where('status', 'approved');
        if ($kind === 'plan') {
            $q->where('context->projection', 1)
              ->where('context->academic_year', $academicYear);
        } else {
            // Real (joriy) — projection kaliti yo'q yoki 0
            $q->where(function ($w) {
                $w->whereNull('context->projection')->orWhere('context->projection', 0);
            });
        }
        if ($facultyId) {
            $q->where('context->faculty', (string) $facultyId);
        }
        $snaps = $q->get();

        $map = [];
        foreach ($snaps as $snap) {
            foreach ($snap->data ?? [] as $bl) {
                // merge_key = dept|specialty|track → yo'nalish nomi
                $spec = $this->specKey(explode('|', $bl['merge_key'] ?? '')[1] ?? '');
                if ($spec === '') {
                    $spec = $this->specKey($bl['title'] ?? '');
                }
                foreach ($bl['courses'] ?? [] as $co) {
                    $lvl = (int) ($co['level_code'] ?? 0);
                    $course = $lvl >= 11 ? $lvl - 10 : $lvl;
                    if ($course < 1) {
                        continue;
                    }
                    $ref = &$map[$spec][$course];
                    if (!isset($ref)) {
                        $ref = ['oqim' => 0, 'students' => 0, 'pract_by_lang' => []];
                    }
                    foreach ($co['oqims'] ?? [] as $oq) {
                        $lang = $oq['lang'] ?? 'uz';
                        $ref['oqim'] += 1;
                        $ref['students'] += (int) ($oq['total'] ?? 0);
                        $ref['pract_by_lang'][$lang] = ($ref['pract_by_lang'][$lang] ?? 0) + (int) ($oq['total'] ?? 0);
                    }
                    unset($ref);
                }
            }
        }
        return $map;
    }

    public function teacherDemand(Request $request)
    {
        $request->validate([
            'academic_year' => 'required|string|max:50',
            'kind'          => 'nullable|in:plan,real',
            'norm_hours'    => 'nullable|integer|min:1',
        ]);
        $academicYear = $request->academic_year;
        $kind = $request->input('kind', 'plan');
        $norm = max(1, (int) $request->input('norm_hours', 900));
        $facultyId = $request->input('faculty') ?: null;

        // Fanlar (o'quv yili bo'yicha)
        $subReq = new Request(['academic_year' => $academicYear, 'include_planned' => 1]);
        $rows = $this->subjectsSummaryQuery($subReq)->get();
        $kafMap = $this->kafedraMap();
        $overrides = $this->subjectOverrides();

        // Oqim/talaba ma'lumoti (tasdiqlangan snapshotlardan)
        $oqim = $this->oqimCountsForDemand($academicYear, $kind, $facultyId);

        $kafedras = [];   // kafedra => ['lecture'=>, 'practice'=>, 'subjects'=>[]]
        $unmatched = [];

        foreach ($rows as $r) {
            $course = $r->level_code ? ((int) $r->level_code >= 11 ? (int) $r->level_code - 10 : (int) $r->level_code) : 0;
            $sk = $this->specKey($r->specialty_name ?: $r->specialty_code);
            $info = $oqim[$sk][$course] ?? null;
            if (!$info || ($info['oqim'] <= 0)) {
                $unmatched[$r->specialty_name . ' ' . $course . '-kurs'] = true;
                continue;
            }

            $nk = $this->normSubject($r->subject_name);
            $ov = $overrides[$nk] ?? null;
            $kafedra = ($ov && $ov['kafedra']) ? $ov['kafedra'] : ($kafMap[$nk] ?? '— (kafedra belgilanmagan)');
            $psize = ($ov && $ov['practice']) ? (int) $ov['practice'] : $this->defaultPracticeSize($r->subject_name, $r->block);

            $lecture = (float) ($r->lecture ?? 0);
            $practHours = (float) ($r->practice ?? 0) + (float) ($r->laboratory ?? 0) + (float) ($r->seminar ?? 0);

            // Ma'ruza: har til oqimiga alohida o'qiladi → oqim soni
            $lectureLoad = $lecture * $info['oqim'];
            // Amaliy: har til bo'yicha ⌈talaba / amaliy_o'lcham⌉ guruh
            $practGroups = 0;
            foreach ($info['pract_by_lang'] as $st) {
                $practGroups += (int) ceil($st / max(1, $psize));
            }
            $practLoad = $practHours * $practGroups;

            if (!isset($kafedras[$kafedra])) {
                $kafedras[$kafedra] = ['lecture' => 0, 'practice' => 0, 'subjects' => []];
            }
            $kafedras[$kafedra]['lecture']  += $lectureLoad;
            $kafedras[$kafedra]['practice'] += $practLoad;
            $kafedras[$kafedra]['subjects'][] = [
                'subject'   => $r->subject_name,
                'specialty' => $r->specialty_name,
                'course'    => $course,
                'oqim'      => $info['oqim'],
                'pract_grp' => $practGroups,
                'lecture'   => round($lectureLoad, 1),
                'practice'  => round($practLoad, 1),
            ];
        }

        $result = [];
        $grand = ['lecture' => 0, 'practice' => 0, 'total' => 0, 'stavka' => 0];
        foreach ($kafedras as $name => $k) {
            $total = $k['lecture'] + $k['practice'];
            $stavka = round($total / $norm, 2);
            $grand['lecture']  += $k['lecture'];
            $grand['practice'] += $k['practice'];
            $grand['total']    += $total;
            $grand['stavka']   += $stavka;
            $result[] = [
                'kafedra'  => $name,
                'lecture'  => round($k['lecture'], 1),
                'practice' => round($k['practice'], 1),
                'total'    => round($total, 1),
                'stavka'   => $stavka,
                'subjects' => $k['subjects'],
            ];
        }
        usort($result, fn($a, $b) => $b['total'] <=> $a['total']);
        $grand['lecture'] = round($grand['lecture'], 1);
        $grand['practice'] = round($grand['practice'], 1);
        $grand['total'] = round($grand['total'], 1);
        $grand['stavka'] = round($grand['stavka'], 2);

        return response()->json([
            'kafedras'  => $result,
            'grand'     => $grand,
            'norm'      => $norm,
            'unmatched' => array_keys($unmatched),
            'has_oqim'  => !empty($oqim),
        ]);
    }

    public function show(ManualCurriculum $curriculum)
    {
        $curriculum->load(['subjects' => fn($q) => $q->orderBy('id')]);

        return view('admin.oquv-reja.show', compact('curriculum'));
    }

    public function destroy(ManualCurriculum $curriculum)
    {
        $curriculum->delete();
        return redirect()->route('admin.oquv-reja.index')->with('success', "O'quv reja o'chirildi.");
    }

    /**
     * Yuklangan rejaga yangi fan qatori qo'shish (qo'lda tahrirlash).
     */
    public function storeSubject(Request $request, ManualCurriculum $curriculum)
    {
        $data = $this->validateSubject($request);
        $data['audit_total'] = $this->auditTotal($data);
        $curriculum->subjects()->create($data);

        return redirect()->route('admin.oquv-reja.show', $curriculum)
            ->with('success', "Yangi fan qatori qo'shildi.");
    }

    /**
     * Mavjud fan qatorini tahrirlash (nom, soat, kredit va h.k.).
     */
    public function updateSubject(Request $request, ManualCurriculum $curriculum, ManualCurriculumSubject $subject)
    {
        abort_unless($subject->manual_curriculum_id === $curriculum->id, 404);

        $data = $this->validateSubject($request);
        $data['audit_total'] = $this->auditTotal($data);
        $subject->update($data);

        return redirect()->route('admin.oquv-reja.show', $curriculum)
            ->with('success', "Fan qatori yangilandi.");
    }

    /**
     * Fan qatorini o'chirish (masalan, ortiqcha qator).
     */
    public function destroySubject(ManualCurriculum $curriculum, ManualCurriculumSubject $subject)
    {
        abort_unless($subject->manual_curriculum_id === $curriculum->id, 404);

        $subject->delete();

        return redirect()->route('admin.oquv-reja.show', $curriculum)
            ->with('success', "Fan qatori o'chirildi.");
    }

    private function validateSubject(Request $request): array
    {
        return $request->validate([
            'block' => 'nullable|string|max:255',
            'subject_code' => 'nullable|string|max:255',
            'subject_name' => 'required|string|max:1000',
            'reference_name' => 'nullable|string|max:1000',
            'kurs' => 'nullable|string|max:50',
            'semester' => 'nullable|string|max:50',
            'total_hours' => 'nullable|numeric|min:0',
            'lecture' => 'nullable|numeric|min:0',
            'practice' => 'nullable|numeric|min:0',
            'laboratory' => 'nullable|numeric|min:0',
            'seminar' => 'nullable|numeric|min:0',
            'independent' => 'nullable|numeric|min:0',
            'credit' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:1000',
        ]);
    }

    /**
     * Auditoriya jami = ma'ruza + amaliy + laboratoriya + seminar
     * (mustaqil ta'lim alohida hisoblanadi).
     */
    private function auditTotal(array $data): float
    {
        return (float) ($data['lecture'] ?? 0)
            + (float) ($data['practice'] ?? 0)
            + (float) ($data['laboratory'] ?? 0)
            + (float) ($data['seminar'] ?? 0);
    }

    public function compare(Request $request, CurriculumComparisonService $service)
    {
        [$reference, $working] = $this->resolvePair($request);
        $comparison = $service->compare($reference, $working, $this->hemisSubjectNames($reference, $working));

        // "Solishtirish" bosilganda juftlik tarixга saqlanadi (takror saqlanmaydi).
        ManualCurriculumComparison::firstOrCreate(
            ['reference_id' => $reference->id, 'working_id' => $working->id],
            ['created_by' => Auth::id()],
        );

        return view('admin.oquv-reja.compare', compact('reference', 'working', 'comparison'));
    }

    /**
     * Jamlangan solishtirish: bitta namunaviy reja bilan shu HEMIS rejaga
     * tegishli barcha ishchi rejalar (barcha semestrlar) birgalikda.
     */
    public function compareGroup(Request $request, CurriculumComparisonService $service)
    {
        [$reference, $workings] = $this->resolveGroup($request);
        if ($workings->isEmpty()) {
            return redirect()->route('admin.oquv-reja.index')
                ->with('error', "Ushbu namunaviy rejaga mos ishchi reja topilmadi. Avval ishchi rejani yuklang.");
        }

        $comparison = $service->compareGroup($reference, $workings,
            $this->hemisSubjectNamesForIds($this->groupHemisIds($reference, $workings)));

        // Rejaning barcha semestrlaridan hali yuklanmaganlari
        $missingSemesters = $this->missingSemesters($reference, $comparison['covered_semesters'] ?? []);

        return view('admin.oquv-reja.compare-group',
            compact('reference', 'workings', 'comparison', 'missingSemesters'));
    }

    public function compareGroupExport(Request $request, CurriculumComparisonService $service)
    {
        [$reference, $workings] = $this->resolveGroup($request);
        abort_if($workings->isEmpty(), 404);

        $comparison = $service->compareGroup($reference, $workings,
            $this->hemisSubjectNamesForIds($this->groupHemisIds($reference, $workings)));

        $title = "{$reference->name} <-> barcha ishchi rejalar (jamlangan) solishtirma";
        $fileName = 'oquv-reja-jamlangan-solishtirma-' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new CurriculumComparisonExport($title, $comparison), $fileName);
    }

    /** Namunaviy reja va unga tegishli barcha ishchi rejalarni aniqlash. */
    private function resolveGroup(Request $request): array
    {
        $request->validate([
            'reference_id' => 'required|exists:manual_curricula,id',
        ]);

        $reference = ManualCurriculum::findOrFail($request->reference_id);
        abort_unless($reference->type === 'namunaviy', 404);

        // Bir HEMIS reja uchun bir nechta namunaviy bo'lsa, solishtirish har doim
        // kanonik (eng to'liq) namunaviyga tayanadi — havolada eski/kam fanli
        // dublikat ko'rsatilgan bo'lsa ham to'g'ri natija chiqadi.
        if ($reference->curricula_hemis_id) {
            $canonical = ManualCurriculum::where('type', 'namunaviy')
                ->where('curricula_hemis_id', $reference->curricula_hemis_id)
                ->withCount('subjects')
                ->get()
                ->sortByDesc(fn ($c) => ManualCurriculum::canonicalRank($c))
                ->first();
            if ($canonical) {
                $reference = $canonical;
            }
        }

        $workings = ManualCurriculum::where('type', 'ishchi')
            ->when($reference->curricula_hemis_id,
                fn ($q) => $q->where('curricula_hemis_id', $reference->curricula_hemis_id),
                fn ($q) => $q->where('specialty_code', $reference->specialty_code)
                    ->where('plan_year', $reference->plan_year))
            ->orderBy('semester_code')
            ->orderBy('id')
            ->get();

        return [$reference, $workings];
    }

    private function groupHemisIds(ManualCurriculum $reference, $workings): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [$reference->curricula_hemis_id],
            $workings->pluck('curricula_hemis_id')->all(),
        ))));
    }

    /** HEMIS'dagi reja semestrlaridan hali ishchi reja yuklanmaganlari. */
    private function missingSemesters(ManualCurriculum $reference, array $covered): array
    {
        if (!$reference->curricula_hemis_id) {
            return [];
        }

        $all = Semester::where('curriculum_hemis_id', $reference->curricula_hemis_id)
            ->pluck('code')
            ->map(fn ($c) => CurriculumComparisonService::semesterNumber($c))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return array_values(array_diff($all, $covered));
    }

    public function destroyComparison(ManualCurriculumComparison $comparison)
    {
        $comparison->delete();

        return redirect()->route('admin.oquv-reja.index')
            ->with('success', "Solishtirish ro'yxatdan o'chirildi.");
    }

    public function compareExport(Request $request, CurriculumComparisonService $service)
    {
        [$reference, $working] = $this->resolvePair($request);
        $comparison = $service->compare($reference, $working, $this->hemisSubjectNames($reference, $working));

        $title = "{$reference->name} <-> {$working->name} solishtirma";
        $fileName = 'oquv-reja-solishtirma-' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new CurriculumComparisonExport($title, $comparison), $fileName);
    }

    /**
     * Solishtirilayotgan rejalarning HEMIS o'quv reja(lar)idagi fanlar nomlari.
     * Namunaviy/ishchi nomlarini shu HEMIS nomlari bilan solishtirish uchun.
     */
    private function hemisSubjectNames(ManualCurriculum $reference, ManualCurriculum $working): array
    {
        return $this->hemisSubjectNamesForIds(array_values(array_unique(array_filter([
            $reference->curricula_hemis_id,
            $working->curricula_hemis_id,
        ]))));
    }

    private function hemisSubjectNamesForIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return DB::table('curriculum_subjects')
            ->whereIn('curricula_hemis_id', $ids)
            ->where('is_active', 1)
            ->whereNotNull('subject_name')
            ->distinct()
            ->pluck('subject_name')
            ->all();
    }

    private function resolvePair(Request $request): array
    {
        $request->validate([
            'reference_id' => 'required|exists:manual_curricula,id',
            'working_id' => 'required|exists:manual_curricula,id',
        ]);

        return [
            ManualCurriculum::findOrFail($request->reference_id),
            ManualCurriculum::findOrFail($request->working_id),
        ];
    }
}
