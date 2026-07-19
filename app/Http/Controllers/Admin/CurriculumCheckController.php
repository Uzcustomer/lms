<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CurriculumComparisonExport;
use App\Exports\ManualCurriculumExport;
use App\Http\Controllers\Controller;
use App\Imports\ManualCurriculumImport;
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

        return view('admin.oquv-reja.index', compact('curricula', 'educationTypes', 'savedComparisons'));
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

            // Yo'nalishlar alohida yozuv (specialty_id) bo'yicha ko'rsatiladi:
            // bir xil kodli dublikatlar ham alohida turadi, qaysi biriga
            // talaba biriktirilgani talaba soni orqali bilinadi
            'specialties' => $this->students($request)
                ->whereNotNull('specialty_id')
                ->selectRaw('specialty_id as id, specialty_code as code, specialty_name as name, count(*) as student_count')
                ->groupBy('specialty_id', 'specialty_code', 'specialty_name')
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

            DB::commit();

            $semCount = count($semCodes);
            $msg = $semCount > 1
                ? "{$semCount} ta semestr uchun o'quv reja yuklandi: {$import->imported} ta fan qatori."
                : "O'quv reja yuklandi: {$import->imported} ta fan qatori o'qib olindi.";

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
        $request->validate(['specialty_id' => 'required|integer']);

        // Faol talabalar (status 11) bo'yicha har bir kohort (curriculum_id + level) uchun ma'lumot
        $rows = Student::query()
            ->where('specialty_id', $request->specialty_id)
            ->where('student_status_code', 11)
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
        $created = 0;
        $skipped = 0;
        $errors  = [];

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

            $filePath = $file->store('manual-curricula', 'public');

            DB::beginTransaction();
            try {
                $master      = null;
                $masterRows  = null;
                $itemCreated = 0;

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

                DB::commit();
                $created += $itemCreated;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Bulk o'quv reja import xatolik: " . $e->getMessage());
                $errors[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
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
        $ids = array_values(array_unique(array_filter([
            $reference->curricula_hemis_id,
            $working->curricula_hemis_id,
        ])));
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
