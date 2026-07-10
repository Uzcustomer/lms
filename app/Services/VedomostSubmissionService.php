<?php

namespace App\Services;

use App\Models\CurriculumSubject;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\Teacher;
use App\Models\VedomostSubmission;
use App\Support\WorkdayCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VedomostSubmissionService
{
    /** Yopilish shakllari — vedomost topshirilishi kerak bo'lganlar. */
    public const CLOSING_FORMS_WITH_VEDOMOST = ['oski', 'test', 'oski_test', 'sinov', 'normativ'];

    /** 12a/12b (qayta topshirish) ochiladigan yopilish shakllari — OSKI/Test imtihonli fanlar. */
    public const RESIT_CLOSING_FORMS = ['oski', 'test', 'oski_test'];

    /** Yiqilgan deb hisoblanadigan chegara (V < 60). */
    public const PASS_GRADE = 60;

    /** Deadline: base sanadan necha ish kuni keyin. */
    public const DEADLINE_WORKDAYS = 3;

    /** Sync davomida takroriy so'rovlarni kamaytirish uchun kesh. */
    private array $teacherCache = [];
    private array $mudiriCache = [];
    private ?\Illuminate\Support\Collection $fanMasuliMap = null;
    private ?bool $studentGradeAttemptCol = null;

    /** Dry-run rejimida o'chiriladigan eskirgan qatorlar (sinov uchun). */
    public array $lastPruneCandidates = [];

    private ?bool $studentGradeSababliCol = null;
    private ?bool $studentGradeQoshimchaCol = null;

    public function __construct(private VedomostMergeService $merge)
    {
    }

    /**
     * student_grades.retake_was_sababli ustuni mavjudmi (keshlanadi).
     */
    private function studentGradeSababliColumn(): bool
    {
        if ($this->studentGradeSababliCol === null) {
            try {
                $this->studentGradeSababliCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'retake_was_sababli');
            } catch (\Throwable $e) {
                $this->studentGradeSababliCol = false;
            }
        }

        return $this->studentGradeSababliCol;
    }

    /**
     * student_grades.is_qoshimcha ustuni mavjudmi (keshlanadi).
     */
    private function studentGradeQoshimchaColumn(): bool
    {
        if ($this->studentGradeQoshimchaCol === null) {
            try {
                $this->studentGradeQoshimchaCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'is_qoshimcha');
            } catch (\Throwable $e) {
                $this->studentGradeQoshimchaCol = false;
            }
        }

        return $this->studentGradeQoshimchaCol;
    }

    /**
     * Reja → fakultet (curricula.department_hemis_id) xaritasi. 12a/12b birliklarini
     * fakultet bo'yicha bo'lish uchun — bitta yo'nalish bir necha fakultetda
     * (masalan "Davolash ishi" 1-son va 2-son davolashda) bo'lsa, har fakultet
     * o'z umumiy varag'iga ega bo'ladi.
     *
     * @return \Illuminate\Support\Collection<string,string>  curriculum_hemis_id => faculty department_hemis_id
     */
    private function facultyByCurriculum(array $curriculumIds): Collection
    {
        if (empty($curriculumIds)) {
            return collect();
        }

        return DB::table('curricula')
            ->whereIn('curricula_hemis_id', $curriculumIds)
            ->whereNotNull('department_hemis_id')
            ->pluck('department_hemis_id', 'curricula_hemis_id');
    }

    /**
     * Joriy o'quv yili kodi (HEMIS "current" bayrog'idan).
     */
    public function currentEducationYear(): ?string
    {
        return DB::table('semesters')->where('current', true)->max('education_year');
    }

    /**
     * Har bir GURUH uchun joriy semestrni qaytaradi.
     *
     * Joriy semestr TALABALAR jadvalidan aniqlanadi: faol (status 11) talaba
     * HEMIS'da doim o'z joriy semestrida turadi. curriculum_weeks sanalariga
     * tayanilmaydi — xalqaro ta'lim fakulteti kabi bahorda o'qish boshlagan
     * kohortalar (toq semestr bahorda) ham to'g'ri chiqishi uchun.
     *
     * Reja bo'yicha emas, guruh bo'yicha: bitta o'quv rejada ikki kohorta
     * (masalan 48 talaba 7-semestrda, 24 talaba 8-semestrda) bo'lishi mumkin —
     * har bir guruh o'z talabalari turgan semestrni oladi. Guruh ichida
     * bir nechta semestr uchrasa (akademik qarzdor va h.k.), eng ko'p
     * talabalisi olinadi.
     *
     * @return Collection<int, object{code:string, student_count:int}> group_hemis_id => semestr
     */
    public function currentSemestersByGroup(): Collection
    {
        return DB::table('students')
            ->where('student_status_code', 11)
            ->whereNotNull('group_id')
            ->whereNotNull('semester_code')
            ->selectRaw('group_id, semester_code as code, count(*) as student_count')
            ->groupBy('group_id', 'semester_code')
            ->get()
            ->groupBy('group_id')
            ->map(fn($g) => $g->sortByDesc('student_count')->first());
    }

    /**
     * Joriy semestr bo'yicha guruh×fan vedomost yozuvlarini yaratadi/yangilaydi.
     * Mavjud yozuvlarning status/fayl/tekshiruv ma'lumotlari saqlanadi.
     *
     * @return int yaratilgan/yangilangan yozuvlar soni
     */
    public function sync(bool $dryRun = false): int
    {
        $this->lastPruneCandidates = [];

        $currentYear = $this->currentEducationYear();
        if (!$currentYear) {
            return 0;
        }

        $semByGroup = $this->currentSemestersByGroup();
        if ($semByGroup->isEmpty()) {
            return 0;
        }

        $groups = Group::where('active', true)
            ->whereIn('group_hemis_id', $semByGroup->keys()->all())
            ->whereNotNull('curriculum_hemis_id')
            ->get();

        $curriculumIds = $groups->pluck('curriculum_hemis_id')->unique()->all();

        // Har reja+semestr uchun o'quv yili — semesters jadvalidan (global
        // max emas: xalqaro fakultet kohortalarining yili farq qilishi mumkin)
        $semesterYears = DB::table('semesters')
            ->whereIn('curriculum_hemis_id', $curriculumIds)
            ->get(['curriculum_hemis_id', 'code', 'education_year'])
            ->keyBy(fn($s) => $s->curriculum_hemis_id . '|' . $s->code)
            ->map(fn($s) => $s->education_year);

        // 12a/12b birliklarini fakultet bo'yicha bo'lish uchun reja→fakultet xaritasi.
        $facultyByCurriculum = $this->facultyByCurriculum($curriculumIds);

        // Fan mas'ullarini oldindan yuklab olamiz (har qator uchun alohida so'rov bermaslik uchun)
        $this->fanMasuliMap = DB::table('teacher_responsible_subjects as trs')
            ->join('teachers as t', 't.id', '=', 'trs.teacher_id')
            ->select('trs.curriculum_subject_id', 't.hemis_id', 't.full_name', 't.phone')
            ->get()
            ->keyBy('curriculum_subject_id');

        // Dry-run: barcha o'zgarishlar tranzaksiyada bajariladi va oxirida
        // rollback qilinadi — bazaga hech narsa yozilmaydi/o'chirilmaydi.
        if ($dryRun) {
            DB::beginTransaction();
        }

        try {
        $count = 0;
        $keptIds = []; // joriy (haqiqiy) qatorlar — qolganlari eskirgan hisoblanadi
        $units = []; // 12a/12b uchun yo'nalish × fan × semestr birliklari
        foreach ($groups as $group) {
            $sem = $semByGroup->get($group->group_hemis_id);
            if (!$sem) {
                continue;
            }
            $semCode = (string) $sem->code;

            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semCode)
                ->where('is_active', true)
                ->whereIn('closing_form', self::CLOSING_FORMS_WITH_VEDOMOST)
                ->get();

            foreach ($subjects as $subject) {
                $base = $this->computeBaseDate($group, $subject, $semCode);
                $deadline = $base['date']
                    ? WorkdayCalculator::addWorkdays(Carbon::parse($base['date']), self::DEADLINE_WORKDAYS)->toDateString()
                    : null;
                $teacher = $this->primaryTeacher((int) $group->group_hemis_id, $subject);
                $deptHemisId = $subject->department_id ?: $group->department_hemis_id;

                $teacherInfo = $teacher ? $this->teacherInfo($teacher->employee_id) : null;
                $fanMasuli = $this->fanMasuli($subject->id);
                $kafedraMudiri = $this->kafedraMudiri($deptHemisId);
                $educationYear = $semesterYears["{$group->curriculum_hemis_id}|{$semCode}"] ?? $currentYear;

                $row = VedomostSubmission::updateOrCreate(
                    [
                        'group_hemis_id' => $group->group_hemis_id,
                        'subject_id' => $subject->subject_id,
                        'semester_code' => $semCode,
                        'form_type' => VedomostSubmission::FORM_12,
                    ],
                    [
                        'education_year' => $educationYear,
                        'group_name' => $group->name,
                        'curriculum_hemis_id' => $group->curriculum_hemis_id,
                        'curriculum_subject_id' => $subject->id,
                        'subject_name' => $subject->subject_name,
                        'department_hemis_id' => $deptHemisId,
                        'department_name' => $subject->department_name ?: $group->department_name,
                        'specialty_name' => $group->specialty_name,
                        'closing_form' => $subject->closing_form,
                        'teacher_hemis_id' => $teacher?->employee_id,
                        'teacher_name' => $teacher?->employee_name,
                        'teacher_phone' => $teacherInfo?->phone,
                        'fan_masuli_hemis_id' => $fanMasuli?->hemis_id,
                        'fan_masuli_name' => $fanMasuli?->full_name,
                        'fan_masuli_phone' => $fanMasuli?->phone,
                        'kafedra_mudiri_hemis_id' => $kafedraMudiri?->hemis_id,
                        'kafedra_mudiri_name' => $kafedraMudiri?->full_name,
                        'kafedra_mudiri_phone' => $kafedraMudiri?->phone,
                        'base_type' => $base['type'],
                        'base_date' => $base['date'],
                        'deadline' => $deadline,
                        // status / fayllar / tekshiruv — atayin yangilanmaydi (oqim saqlanadi)
                    ]
                );
                $keptIds[] = $row->id;
                $count++;

                // 12a/12b — faqat OSKI/Test imtihonli fanlar uchun birlik to'playmiz.
                // Birlik FAKULTET bo'yicha ham bo'linadi — har fakultet o'z varag'iga ega.
                if (in_array($subject->closing_form, self::RESIT_CLOSING_FORMS, true)) {
                    $rootSubject = $this->merge->rootSubjectName($subject->subject_name);
                    $facultyId = (string) ($facultyByCurriculum[$group->curriculum_hemis_id] ?? $group->curriculum_hemis_id);
                    $unitKey = implode('|', [
                        $educationYear, $semCode, (string) $group->specialty_name,
                        $subject->closing_form, $rootSubject, $facultyId,
                    ]);
                    if (!isset($units[$unitKey])) {
                        $units[$unitKey] = [
                            'education_year' => $educationYear,
                            'semester_code' => $semCode,
                            'specialty_name' => $group->specialty_name,
                            'closing_form' => $subject->closing_form,
                            'subject_id' => $subject->subject_id,
                            'subject_name' => $rootSubject,
                            'curriculum_hemis_id' => $group->curriculum_hemis_id,
                            'department_hemis_id' => $deptHemisId,
                            'department_name' => $subject->department_name ?: $group->department_name,
                            'fan_masuli_hemis_id' => $fanMasuli?->hemis_id,
                            'fan_masuli_name' => $fanMasuli?->full_name,
                            'fan_masuli_phone' => $fanMasuli?->phone,
                            'kafedra_mudiri_hemis_id' => $kafedraMudiri?->hemis_id,
                            'kafedra_mudiri_name' => $kafedraMudiri?->full_name,
                            'kafedra_mudiri_phone' => $kafedraMudiri?->phone,
                            'group_ids' => [],
                            'subject_keys' => [],
                        ];
                    }
                    $units[$unitKey]['group_ids'][] = (int) $group->group_hemis_id;
                    foreach ([$subject->subject_id, $subject->curriculum_subject_hemis_id] as $sk) {
                        if ($sk && !in_array($sk, $units[$unitKey]['subject_keys'], true)) {
                            $units[$unitKey]['subject_keys'][] = $sk;
                        }
                    }
                }
            }
        }

        $keptIds = array_merge($keptIds, $this->syncResitForms($units));

        // Qo'shimcha (sababli ma'lumotnoma) shakllari — 12q/12aq/12bq.
        // YN bosqich mantig'i (YnStageService) bilan aniqlanadi.
        $keptIds = array_merge(
            $keptIds,
            $this->syncQoshimchaForms($units, $groups->pluck('group_hemis_id')->map(fn($g) => (string) $g)->all(), $semByGroup)
        );

        // Eskirgan qatorlarni tozalash — endi joriy bo'lmagan (masalan, faol
        // talabasi qolmagan guruh) tegilmagan vedomostlar. Faqat pending VA fayl
        // yuklanmagan qatorlar o'chiriladi; yuklangan/tasdiqlanganlarga tegilmaydi.
        // Nima o'chishini aniqlaymiz (sinov + haqiqiy run hisoboti uchun).
        $this->lastPruneCandidates = $this->staleCandidates($keptIds);
        if (!$dryRun) {
            $this->pruneStale($keptIds);
        }

        return $count;
        } finally {
            if ($dryRun) {
                DB::rollBack();
            }
        }
    }

    /**
     * Joriy syncda yaratilmagan (eskirgan) va hali ishlatilmagan (pending, fayl
     * yo'q) qatorlar bo'yicha so'rov.
     */
    private function staleQuery(array $keptIds)
    {
        return VedomostSubmission::whereNotIn('id', $keptIds)
            ->where('status', VedomostSubmission::STATUS_PENDING)
            ->whereNull('pdf_path');
    }

    /**
     * O'chiriladigan eskirgan qatorlar ro'yxati (dry-run uchun).
     *
     * @return array<int, object>
     */
    private function staleCandidates(array $keptIds): array
    {
        $keptIds = array_values(array_unique(array_filter($keptIds)));
        if (empty($keptIds)) {
            return [];
        }

        return $this->staleQuery($keptIds)
            ->orderBy('form_type')
            ->orderBy('group_name')
            ->orderBy('subject_name')
            ->get(['id', 'group_name', 'subject_name', 'form_type', 'semester_code', 'specialty_name'])
            ->all();
    }

    /**
     * Joriy syncda yaratilmagan (eskirgan) va hali ishlatilmagan (pending, fayl
     * yo'q) vedomost qatorlarini o'chiradi. keptIds bo'sh bo'lsa hech narsa
     * o'chirilmaydi (xavfsizlik — sync hech narsa ishlab chiqarmagan holat).
     */
    private function pruneStale(array $keptIds): void
    {
        $keptIds = array_values(array_unique(array_filter($keptIds)));
        if (empty($keptIds)) {
            return;
        }

        $this->staleQuery($keptIds)->delete();
    }

    /**
     * 12a/12b nima uchun ochilmayotganini diagnostika qiladi — sync'ning AYNAN
     * o'sha mantiqi bilan (yiqilganlar + imtihon sanasi). Fan nomi bo'laklari
     * berilsa, faqat shu fanlar bo'yicha.
     *
     * @param  array<string>  $subjectNeedles  fan nomidan qidiriladigan bo'laklar
     * @return array<int, object>
     */
    public function diagnoseResit(array $subjectNeedles = []): array
    {
        $currentYear = $this->currentEducationYear();
        if (!$currentYear) {
            return [];
        }
        $semByGroup = $this->currentSemestersByGroup();
        if ($semByGroup->isEmpty()) {
            return [];
        }

        $groups = Group::where('active', true)
            ->whereIn('group_hemis_id', $semByGroup->keys()->all())
            ->whereNotNull('curriculum_hemis_id')
            ->get();

        $curriculumIds = $groups->pluck('curriculum_hemis_id')->unique()->all();
        $semesterYears = DB::table('semesters')
            ->whereIn('curriculum_hemis_id', $curriculumIds)
            ->get(['curriculum_hemis_id', 'code', 'education_year'])
            ->keyBy(fn($s) => $s->curriculum_hemis_id . '|' . $s->code)
            ->map(fn($s) => $s->education_year);
        $facultyByCurriculum = $this->facultyByCurriculum($curriculumIds);

        $units = [];
        foreach ($groups as $group) {
            $sem = $semByGroup->get($group->group_hemis_id);
            if (!$sem) {
                continue;
            }
            $semCode = (string) $sem->code;

            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semCode)
                ->where('is_active', true)
                ->whereIn('closing_form', self::RESIT_CLOSING_FORMS)
                ->get();

            foreach ($subjects as $subject) {
                if (!empty($subjectNeedles) && !$this->subjectMatches($subject->subject_name, $subjectNeedles)) {
                    continue;
                }
                $educationYear = $semesterYears["{$group->curriculum_hemis_id}|{$semCode}"] ?? $currentYear;
                $rootSubject = $this->merge->rootSubjectName($subject->subject_name);
                $facultyId = (string) ($facultyByCurriculum[$group->curriculum_hemis_id] ?? $group->curriculum_hemis_id);
                $unitKey = implode('|', [
                    $educationYear, $semCode, (string) $group->specialty_name,
                    $subject->closing_form, $rootSubject, $facultyId,
                ]);
                if (!isset($units[$unitKey])) {
                    $units[$unitKey] = [
                        'education_year' => $educationYear,
                        'semester_code' => $semCode,
                        'specialty_name' => $group->specialty_name,
                        'closing_form' => $subject->closing_form,
                        'subject_id' => $subject->subject_id,
                        'subject_name' => $rootSubject,
                        'group_ids' => [],
                        'group_names' => [],
                        'subject_keys' => [],
                    ];
                }
                $units[$unitKey]['group_ids'][] = (int) $group->group_hemis_id;
                $units[$unitKey]['group_names'][] = $group->name;
                foreach ([$subject->subject_id, $subject->curriculum_subject_hemis_id] as $sk) {
                    if ($sk && !in_array($sk, $units[$unitKey]['subject_keys'], true)) {
                        $units[$unitKey]['subject_keys'][] = $sk;
                    }
                }
            }
        }

        if (empty($units)) {
            return [];
        }

        $allGroupIds = [];
        $allSubjectKeys = [];
        $allSemCodes = [];
        $unitByKey = [];
        foreach ($units as $unitKey => $unit) {
            $semCode = (string) $unit['semester_code'];
            $allSemCodes[$semCode] = true;
            foreach ($unit['group_ids'] as $gid) {
                $allGroupIds[(int) $gid] = true;
                foreach ($unit['subject_keys'] as $sk) {
                    $allSubjectKeys[$sk] = true;
                    $unitByKey[$gid . '|' . $sk . '|' . $semCode] = $unitKey;
                }
            }
        }

        $failures = $this->detectFailuresBatch(
            array_keys($allGroupIds), array_keys($allSubjectKeys), array_keys($allSemCodes), $unitByKey
        );
        $dates = $this->examDatesBatch(
            array_keys($allGroupIds), array_keys($allSubjectKeys), array_keys($allSemCodes), $unitByKey, $units
        );

        $today = Carbon::today()->toDateString();
        $out = [];
        foreach ($units as $unitKey => $unit) {
            $f = $failures[$unitKey] ?? ['failed1' => 0, 'failed2' => 0, 'graded' => 0, 'has2' => false, 'has3' => false];
            $d = $dates[$unitKey] ?? ['attempt1' => null, 'resit' => null, 'resit2' => null];
            $open12a = !empty($f['has2'])
                || ($f['failed1'] > 0 && $d['attempt1'] !== null && $d['attempt1'] < $today);
            $open12b = !empty($f['has3'])
                || ($f['failed2'] > 0 && $d['resit'] !== null && $d['resit'] < $today);

            $out[] = (object) [
                'specialty' => $unit['specialty_name'],
                'subject' => $unit['subject_name'],
                'closing_form' => $unit['closing_form'],
                'semester_code' => $unit['semester_code'],
                'groups' => count(array_unique($unit['group_ids'])),
                'group_names' => implode(', ', array_values(array_unique($unit['group_names']))),
                'subject_keys' => implode(', ', $unit['subject_keys']),
                'graded' => $f['graded'] ?? 0,
                'failed1' => $f['failed1'],
                'failed2' => $f['failed2'],
                'attempt1_date' => $d['attempt1'],
                'resit_date' => $d['resit'],
                'resit2_date' => $d['resit2'],
                'today' => $today,
                'open12a' => $open12a,
                'reason12a' => !empty($f['has2'])
                    ? '2-urinish imtihoni topshirilgan (attempt=2 baho bor)'
                    : $this->resitReason($f['failed1'], $d['attempt1'], $today),
                'open12b' => $open12b,
                'reason12b' => !empty($f['has3'])
                    ? '3-urinish imtihoni topshirilgan (attempt=3 baho bor)'
                    : $this->resitReason($f['failed2'], $d['resit'], $today),
            ];
        }

        return $out;
    }

    private function subjectMatches(?string $name, array $needles): bool
    {
        $name = mb_strtolower((string) $name);
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($name, mb_strtolower($needle)) !== false) {
                return true;
            }
        }

        return false;
    }

    private function resitReason(int $failed, ?string $date, string $today): string
    {
        if ($failed <= 0) {
            return "yiqilgan yo'q (failed=0)";
        }
        if ($date === null) {
            return 'imtihon sanasi yo\'q (exam_schedules)';
        }
        if (!($date < $today)) {
            return "sana hali o'tmagan ({$date})";
        }

        return 'OCHILISHI KERAK';
    }

    /**
     * 12a/12b (qayta topshirish) umumiy varaqlarini natijaga qarab ochadi/yopadi.
     *
     * Har bir yo'nalish × fan × semestr birligi uchun (BARCHA guruhlar bitta varaq):
     *  - 12a : 1-urinish (oski/test) sanasi o'tgan VA 1-urinishda yiqilganlar bo'lsa.
     *  - 12b : 2-urinish (resit) sanasi o'tgan VA 2-urinishda yiqilganlar bo'lsa.
     * Yiqilgan/o'tgan aniqlash individual-exam-schedule logikasiga mos
     * (student_grades: training_type 101/102, attempt, retake_grade ?? grade < 60).
     *
     * @return array<int> ochilgan (saqlanadigan) 12a/12b qator id lari
     */
    private function syncResitForms(array $units): array
    {
        if (empty($units)) {
            return [];
        }

        $today = Carbon::today()->toDateString();

        // Barcha birliklar bo'yicha guruh/fan kalitlarini yig'amiz, so'ngra
        // yiqilganlar va sanalarni BITTA-ikkita to'plam (batch) so'rovda olamiz —
        // har birlik uchun alohida so'rov yubormaymiz (sync 504 bermasligi uchun).
        [$allGroupIds, $allSubjectKeys, $allSemCodes, $unitByKey] = $this->unitKeyMaps($units);

        $failures = $this->detectFailuresBatch($allGroupIds, $allSubjectKeys, $allSemCodes, $unitByKey);
        $dates = $this->examDatesBatch($allGroupIds, $allSubjectKeys, $allSemCodes, $unitByKey, $units);

        $keptIds = [];
        foreach ($units as $unitKey => $unit) {
            $groupIds = array_values(array_unique($unit['group_ids']));
            $f = $failures[$unitKey] ?? ['failed1' => 0, 'failed2' => 0];
            $d = $dates[$unitKey] ?? ['attempt1' => null, 'resit' => null, 'resit2' => null];

            // 12a — 1-urinishda yiqilganlar bor (sana o'tgan) YOKI 2-urinish imtihoni
            // topshirilgan (attempt=2 bahosi bor — talaba resitda qatnashgan).
            $open12a = !empty($f['has2'])
                || ($f['failed1'] > 0 && $d['attempt1'] !== null && $d['attempt1'] < $today);
            $id12a = $this->upsertOrCleanResitRow($unit, $groupIds, VedomostSubmission::FORM_12A, $open12a, $d['resit']);
            if ($id12a) {
                $keptIds[] = $id12a;
            }

            // 12b — 2-urinishda yiqilganlar bor (resit sanasi o'tgan) YOKI 3-urinish
            // imtihoni topshirilgan (attempt=3 bahosi bor).
            $open12b = !empty($f['has3'])
                || ($f['failed2'] > 0 && $d['resit'] !== null && $d['resit'] < $today);
            $id12b = $this->upsertOrCleanResitRow($unit, $groupIds, VedomostSubmission::FORM_12B, $open12b, $d['resit2']);
            if ($id12b) {
                $keptIds[] = $id12b;
            }
        }

        return $keptIds;
    }

    /**
     * Birliklar bo'yicha batch so'rovlar uchun kalit xaritalarini quradi.
     *
     * @return array{0:array<int>,1:array<int|string>,2:array<string>,3:array<string,string>}
     *         [groupIds, subjectKeys, semCodes, "group|subjectKey|sem" => unitKey]
     */
    private function unitKeyMaps(array $units): array
    {
        $allGroupIds = [];
        $allSubjectKeys = [];
        $allSemCodes = [];
        $unitByKey = [];
        foreach ($units as $unitKey => $unit) {
            $semCode = (string) $unit['semester_code'];
            $allSemCodes[$semCode] = true;
            foreach ($unit['group_ids'] as $gid) {
                $allGroupIds[(int) $gid] = true;
                foreach ($unit['subject_keys'] as $sk) {
                    $allSubjectKeys[$sk] = true;
                    $unitByKey[$gid . '|' . $sk . '|' . $semCode] = $unitKey;
                }
            }
        }

        return [array_keys($allGroupIds), array_keys($allSubjectKeys), array_keys($allSemCodes), $unitByKey];
    }

    /**
     * Qo'shimcha shakllarini — 12q (har guruh alohida), 12aq/12bq (umumiy)
     * ochadi/yopadi.
     *
     * Trigger manbalari:
     *  - is_qoshimcha=1: farmoyish / qo'shimcha imtihon yozuvi
     *  - retake_was_sababli=1: sababli ariza asosida boshqa muddatda topshirilgan
     *    OSKI/Test yozuvi
     *
     * Shuning uchun 12q/12aq/12bq ochilishida har bir attempt uchun ikkala holat
     * ham hisobga olinadi:
     *   attempt=1 → 12-qo'shimcha
     *   attempt=2 → 12a-qo'shimcha
     *   attempt=3 → 12b-qo'shimcha
     *
     * @param  array<int,string>  $activeGroupHemisIds  joriy faol guruhlar
     * @return array<int>  ochilgan (saqlanadigan) qator id lari
     */
    private function syncQoshimchaForms(array $units, array $activeGroupHemisIds, Collection $semByGroup): array
    {
        if (
            (!$this->studentGradeQoshimchaColumn() && !$this->studentGradeSababliColumn())
            || empty($activeGroupHemisIds)
        ) {
            return [];
        }

        $hasAttempt = $this->studentGradeAttemptColumn();
        $hasQoshimcha = $this->studentGradeQoshimchaColumn();
        $hasSababli = $this->studentGradeSababliColumn();

        // Qo'shimcha triggerlar beradigan OSKI/Test yozuvlari —
        // (guruh, fan, sem, urinish, is_qoshimcha, retake_was_sababli).
        $rows = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereIn('st.group_id', $activeGroupHemisIds)
            ->whereIn('sg.training_type_code', [101, 102])
            ->whereNull('sg.deleted_at')
            ->select(
                'st.group_id as group_hemis_id',
                'sg.subject_id',
                'sg.semester_code',
                $hasAttempt ? 'sg.attempt' : DB::raw('1 as attempt'),
                $hasQoshimcha ? 'sg.is_qoshimcha' : DB::raw('0 as is_qoshimcha'),
                $hasSababli ? 'sg.retake_was_sababli' : DB::raw('0 as retake_was_sababli')
            )
            ->get();

        // "group|subject|sem" => [urinish => true]  (faqat joriy semestr).
        $attemptsByKey = [];
        // Faqat sababli ariza triggerlari uchun alohida xarita —
        // 12a/12b qo'shimchaning guruh bo'yicha satrlarini ochish uchun.
        $sababliAttemptsByKey = [];
        foreach ($rows as $r) {
            $gid = (string) $r->group_hemis_id;
            $sem = (string) $r->semester_code;
            $curSem = $semByGroup->get($gid)?->code;
            if ($curSem === null || (string) $curSem !== $sem) {
                continue;
            }
            $att = (int) ($r->attempt ?? 1);
            if ($att < 1) {
                $att = 1;
            }
            $isQoshimcha = !empty($r->is_qoshimcha);
            $isSababli = !empty($r->retake_was_sababli);
            if (!$isQoshimcha && !$isSababli) {
                continue;
            }

            $key = $gid . '|' . $r->subject_id . '|' . $sem;
            $attemptsByKey[$key][$att] = true;
            if ($isSababli) {
                $sababliAttemptsByKey[$key][$att] = true;
            }
        }

        $keptIds = [];

        // 12q — har guruh alohida: farmoyish imtihoni (attempt=1) bo'lsa, 12-shakl qatorini klonlaymiz.
        foreach ($attemptsByKey as $key => $atts) {
            [$gid, $subjectId, $sem] = explode('|', $key);
            $id = $this->upsertQoshimcha12Row($gid, $subjectId, $sem, !empty($atts[1]));
            if ($id) {
                $keptIds[] = $id;
            }
        }

        // Sababli attempt=2/3 bo'lsa, o'sha talabaning guruhi uchun alohida
        // 12a/12b qo'shimcha satri ham ochiladi.
        $groupDateMap = $this->examDatesForGroupSubjectBatch(array_keys($sababliAttemptsByKey));

        foreach ($sababliAttemptsByKey as $key => $atts) {
            [$gid, $subjectId, $sem] = explode('|', $key);
            $baseDates = $groupDateMap[$key] ?? ['resit' => null, 'resit2' => null];

            $id12ag = $this->upsertGroupSababliResitRow(
                $gid,
                $subjectId,
                $sem,
                VedomostSubmission::FORM_12AG,
                !empty($atts[2]),
                $baseDates['resit']
            );
            if ($id12ag) {
                $keptIds[] = $id12ag;
            }

            $id12bg = $this->upsertGroupSababliResitRow(
                $gid,
                $subjectId,
                $sem,
                VedomostSubmission::FORM_12BG,
                !empty($atts[3]),
                $baseDates['resit2']
            );
            if ($id12bg) {
                $keptIds[] = $id12bg;
            }
        }

        // 12aq/12bq — umumiy (birlik bo'yicha): birlikning bironta guruhida attempt=2/3 farmoyish bo'lsa.
        [$ag, $ask, $asc, $unitByKey] = $this->unitKeyMaps($units);
        $dates = $this->examDatesBatch($ag, $ask, $asc, $unitByKey, $units);

        foreach ($units as $unitKey => $unit) {
            $groupIds = array_values(array_unique($unit['group_ids']));
            $sem = (string) $unit['semester_code'];
            $subjectId = (string) $unit['subject_id'];

            $any12aq = false;
            $any12bq = false;
            foreach ($unit['group_ids'] as $gid) {
                $atts = $attemptsByKey[$gid . '|' . $subjectId . '|' . $sem] ?? [];
                if (!empty($atts[2])) {
                    $any12aq = true;
                }
                if (!empty($atts[3])) {
                    $any12bq = true;
                }
            }

            $d = $dates[$unitKey] ?? ['attempt1' => null, 'resit' => null, 'resit2' => null];

            $id12aq = $this->upsertOrCleanResitRow($unit, $groupIds, VedomostSubmission::FORM_12AQ, $any12aq, $d['resit']);
            if ($id12aq) {
                $keptIds[] = $id12aq;
            }
            $id12bq = $this->upsertOrCleanResitRow($unit, $groupIds, VedomostSubmission::FORM_12BQ, $any12bq, $d['resit2']);
            if ($id12bq) {
                $keptIds[] = $id12bq;
            }
        }

        return $keptIds;
    }

    /**
     * Sababli 12a/12b qo'shimcha uchun guruh bo'yicha alohida qator yaratadi.
     *
     * @return int|null
     */
    private function upsertGroupSababliResitRow(
        string $groupHemisId,
        string $subjectId,
        string $semesterCode,
        string $formType,
        bool $shouldOpen,
        ?string $baseDate
    ): ?int {
        $keys = [
            'group_hemis_id' => $groupHemisId,
            'subject_id' => $subjectId,
            'semester_code' => $semesterCode,
            'form_type' => $formType,
        ];

        if (!$shouldOpen) {
            VedomostSubmission::where($keys)
                ->where('status', VedomostSubmission::STATUS_PENDING)
                ->whereNull('pdf_path')
                ->delete();
            return null;
        }

        $base = VedomostSubmission::where([
            'group_hemis_id' => $groupHemisId,
            'subject_id' => $subjectId,
            'semester_code' => $semesterCode,
            'form_type' => VedomostSubmission::FORM_12,
        ])->first();
        if (!$base) {
            return null;
        }

        $deadline = $baseDate
            ? WorkdayCalculator::addWorkdays(Carbon::parse($baseDate), self::DEADLINE_WORKDAYS)->toDateString()
            : null;

        $row = VedomostSubmission::updateOrCreate($keys, [
            'education_year' => $base->education_year,
            'group_name' => $base->group_name,
            'curriculum_hemis_id' => $base->curriculum_hemis_id,
            'curriculum_subject_id' => $base->curriculum_subject_id,
            'subject_name' => $base->subject_name,
            'department_hemis_id' => $base->department_hemis_id,
            'department_name' => $base->department_name,
            'specialty_name' => $base->specialty_name,
            'closing_form' => $base->closing_form,
            'teacher_hemis_id' => $base->teacher_hemis_id,
            'teacher_name' => $base->teacher_name,
            'teacher_phone' => $base->teacher_phone,
            'fan_masuli_hemis_id' => $base->fan_masuli_hemis_id,
            'fan_masuli_name' => $base->fan_masuli_name,
            'fan_masuli_phone' => $base->fan_masuli_phone,
            'kafedra_mudiri_hemis_id' => $base->kafedra_mudiri_hemis_id,
            'kafedra_mudiri_name' => $base->kafedra_mudiri_name,
            'kafedra_mudiri_phone' => $base->kafedra_mudiri_phone,
            'base_type' => 'exam',
            'base_date' => $baseDate,
            'deadline' => $deadline,
        ]);

        return $row->id;
    }

    /**
     * 12q (12-qo'shimcha) — har guruh alohida varaq. Mavjud 12-shakl qatorining
     * ma'lumotlarini (o'qituvchi, mas'ul, sana) klonlab ochadi; aktiv bo'lmasa
     * va hali ishlatilmagan (pending, fayl yo'q) bo'lsa olib tashlaydi.
     *
     * @return int|null  ochilgan qator id, aks holda null
     */
    private function upsertQoshimcha12Row(string $groupHemisId, string $subjectId, string $semesterCode, bool $shouldOpen): ?int
    {
        $keys = [
            'group_hemis_id' => $groupHemisId,
            'subject_id' => $subjectId,
            'semester_code' => $semesterCode,
            'form_type' => VedomostSubmission::FORM_12Q,
        ];

        if (!$shouldOpen) {
            VedomostSubmission::where($keys)
                ->where('status', VedomostSubmission::STATUS_PENDING)
                ->whereNull('pdf_path')
                ->delete();
            return null;
        }

        // Asos — shu guruh×fan×semestr ning 12-shakl qatori (mavjud bo'lishi shart).
        $base = VedomostSubmission::where([
            'group_hemis_id' => $groupHemisId,
            'subject_id' => $subjectId,
            'semester_code' => $semesterCode,
            'form_type' => VedomostSubmission::FORM_12,
        ])->first();
        if (!$base) {
            return null;
        }

        $row = VedomostSubmission::updateOrCreate($keys, [
            'education_year' => $base->education_year,
            'group_name' => $base->group_name,
            'curriculum_hemis_id' => $base->curriculum_hemis_id,
            'curriculum_subject_id' => $base->curriculum_subject_id,
            'subject_name' => $base->subject_name,
            'department_hemis_id' => $base->department_hemis_id,
            'department_name' => $base->department_name,
            'specialty_name' => $base->specialty_name,
            'closing_form' => $base->closing_form,
            'teacher_hemis_id' => $base->teacher_hemis_id,
            'teacher_name' => $base->teacher_name,
            'teacher_phone' => $base->teacher_phone,
            'fan_masuli_hemis_id' => $base->fan_masuli_hemis_id,
            'fan_masuli_name' => $base->fan_masuli_name,
            'fan_masuli_phone' => $base->fan_masuli_phone,
            'kafedra_mudiri_hemis_id' => $base->kafedra_mudiri_hemis_id,
            'kafedra_mudiri_name' => $base->kafedra_mudiri_name,
            'kafedra_mudiri_phone' => $base->kafedra_mudiri_phone,
            'base_type' => $base->base_type,
            'base_date' => $base->base_date,
            'deadline' => $base->deadline,
            // status / fayllar / tekshiruv — atayin yangilanmaydi (oqim saqlanadi)
        ]);

        return $row->id;
    }

    /**
     * Bir nechta guruh|fan|semestr kalitlari uchun resit sanalarini batch qaytaradi.
     *
     * @param  array<int,string>  $keys
     * @return array<string,array{resit:?string,resit2:?string}>
     */
    private function examDatesForGroupSubjectBatch(array $keys): array
    {
        $result = [];
        if (empty($keys)) {
            return $result;
        }

        $groupIds = [];
        $subjectIds = [];
        $semCodes = [];

        foreach ($keys as $key) {
            [$gid, $subjectId, $sem] = explode('|', $key);
            $groupIds[(string) $gid] = true;
            $subjectIds[(string) $subjectId] = true;
            $semCodes[(string) $sem] = true;
            $result[$key] = ['resit' => null, 'resit2' => null];
        }

        $scheduleRows = ExamSchedule::whereNull('student_hemis_id')
            ->whereIn('group_hemis_id', array_keys($groupIds))
            ->whereIn('subject_id', array_keys($subjectIds))
            ->whereIn('semester_code', array_keys($semCodes))
            ->cursor();

        foreach ($scheduleRows as $row) {
            $key = $row->group_hemis_id . '|' . $row->subject_id . '|' . $row->semester_code;
            if (!isset($result[$key])) {
                continue;
            }

            $resitDates = [];
            $resit2Dates = [];

            if ($row->oski_resit_date) {
                $resitDates[] = $row->oski_resit_date->toDateString();
            }
            if ($row->test_resit_date) {
                $resitDates[] = $row->test_resit_date->toDateString();
            }
            if ($row->oski_resit2_date) {
                $resit2Dates[] = $row->oski_resit2_date->toDateString();
            }
            if ($row->test_resit2_date) {
                $resit2Dates[] = $row->test_resit2_date->toDateString();
            }

            if (!empty($resitDates)) {
                $result[$key]['resit'] = max($resitDates);
            }
            if (!empty($resit2Dates)) {
                $result[$key]['resit2'] = max($resit2Dates);
            }
        }

        if ($this->studentGradeAttemptColumn()) {
            $gradeDates = DB::table('student_grades as sg')
                ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->whereIn('st.group_id', array_keys($groupIds))
                ->whereIn('sg.subject_id', array_keys($subjectIds))
                ->whereIn('sg.semester_code', array_keys($semCodes))
                ->whereIn('sg.training_type_code', [101, 102])
                ->whereIn('sg.attempt', [2, 3])
                ->whereNotNull('sg.lesson_date')
                ->whereNull('sg.deleted_at')
                ->selectRaw('st.group_id, sg.subject_id, sg.semester_code, sg.attempt, MAX(sg.lesson_date) as max_date')
                ->groupBy('st.group_id', 'sg.subject_id', 'sg.semester_code', 'sg.attempt')
                ->get();

            foreach ($gradeDates as $gradeDate) {
                $key = $gradeDate->group_id . '|' . $gradeDate->subject_id . '|' . $gradeDate->semester_code;
                if (!isset($result[$key]) || empty($gradeDate->max_date)) {
                    continue;
                }

                $date = substr((string) $gradeDate->max_date, 0, 10);
                if ((int) $gradeDate->attempt === 2 && $result[$key]['resit'] === null) {
                    $result[$key]['resit'] = $date;
                }
                if ((int) $gradeDate->attempt === 3 && $result[$key]['resit2'] === null) {
                    $result[$key]['resit2'] = $date;
                }
            }
        }

        return $result;
    }

    /**
     * Shart bajarilsa 12a/12b umumiy qatorini yaratadi/yangilaydi; aks holda
     * (hali yuklanmagan, status pending) bo'lsa olib tashlaydi.
     *
     * @return int|null  ochilgan (saqlanadigan) qator id, aks holda null
     */
    private function upsertOrCleanResitRow(array $unit, array $groupIds, string $formType, bool $shouldOpen, ?string $baseDate): ?int
    {
        $repGroupId = !empty($groupIds) ? min($groupIds) : null;
        if (!$repGroupId) {
            return null;
        }

        $keys = [
            'group_hemis_id' => $repGroupId,
            'subject_id' => $unit['subject_id'],
            'semester_code' => $unit['semester_code'],
            'form_type' => $formType,
        ];

        if (!$shouldOpen) {
            // Faqat hali ishlatilmagan (pending, fayl yo'q) qatorni tozalaymiz —
            // yuklangan/tasdiqlangan vedomostga tegmaymiz.
            VedomostSubmission::where($keys)
                ->where('status', VedomostSubmission::STATUS_PENDING)
                ->whereNull('pdf_path')
                ->delete();
            return null;
        }

        $deadline = $baseDate
            ? WorkdayCalculator::addWorkdays(Carbon::parse($baseDate), self::DEADLINE_WORKDAYS)->toDateString()
            : null;

        $row = VedomostSubmission::updateOrCreate($keys, [
            'education_year' => $unit['education_year'],
            'group_name' => 'Barcha guruhlar (' . count($groupIds) . ' ta)',
            'curriculum_hemis_id' => $unit['curriculum_hemis_id'],
            'curriculum_subject_id' => null,
            'subject_name' => $unit['subject_name'],
            'department_hemis_id' => $unit['department_hemis_id'],
            'department_name' => $unit['department_name'],
            'specialty_name' => $unit['specialty_name'],
            'closing_form' => $unit['closing_form'],
            // 12a/12b — umumiy varaq, alohida o'qituvchi yo'q (fan mas'uli javobgar).
            'teacher_hemis_id' => null,
            'teacher_name' => null,
            'teacher_phone' => null,
            'fan_masuli_hemis_id' => $unit['fan_masuli_hemis_id'],
            'fan_masuli_name' => $unit['fan_masuli_name'],
            'fan_masuli_phone' => $unit['fan_masuli_phone'],
            'kafedra_mudiri_hemis_id' => $unit['kafedra_mudiri_hemis_id'],
            'kafedra_mudiri_name' => $unit['kafedra_mudiri_name'],
            'kafedra_mudiri_phone' => $unit['kafedra_mudiri_phone'],
            'base_type' => 'exam',
            'base_date' => $baseDate,
            'deadline' => $deadline,
            // status / fayllar / tekshiruv — atayin yangilanmaydi (oqim saqlanadi)
        ]);

        return $row->id;
    }

    /**
     * Barcha birliklar bo'yicha 1- va 2-urinishda yiqilganlar sonini BITTA
     * so'rovda aniqlaydi (individual-exam-schedule eligibility logikasi bilan mos).
     *
     * @param  array<string,string>  $unitByKey  "group|subjectKey|sem" => unitKey
     * @return array<string, array{failed1:int, failed2:int}>  unitKey bo'yicha
     */
    private function detectFailuresBatch(array $groupIds, array $subjectKeys, array $semCodes, array $unitByKey): array
    {
        $result = [];
        if (empty($groupIds) || empty($subjectKeys)) {
            return $result;
        }

        $hasAttempt = $this->studentGradeAttemptColumn();

        // unitKey => [student_hemis_id => ['f1' => bool, 'f2' => bool]]
        $perUnit = [];
        // unitKey => ['h2' => bool, 'h3' => bool] — attempt=2/3 bahosi MAVJUDMI
        // (talaba resitni TOPSHIRGAN — o'tdimi-yiqildimi ahamiyatsiz, varaqda bo'ladi).
        $unitHas = [];

        $cursor = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereIn('st.group_id', $groupIds)
            ->whereIn('sg.subject_id', $subjectKeys)
            ->whereIn('sg.semester_code', $semCodes)
            ->whereIn('sg.training_type_code', [101, 102])
            ->whereNull('sg.deleted_at')
            ->select(
                'st.group_id',
                'sg.student_hemis_id',
                'sg.subject_id',
                'sg.semester_code',
                'sg.grade',
                'sg.retake_grade',
                $hasAttempt ? 'sg.attempt' : DB::raw('1 as attempt')
            )
            ->cursor();

        foreach ($cursor as $r) {
            $unitKey = $unitByKey[$r->group_id . '|' . $r->subject_id . '|' . $r->semester_code] ?? null;
            if ($unitKey === null) {
                continue;
            }
            $val = $r->retake_grade ?? $r->grade;
            if ($val === null) {
                continue;
            }
            $sid = $r->student_hemis_id;
            if (!isset($perUnit[$unitKey][$sid])) {
                $perUnit[$unitKey][$sid] = ['f1' => false, 'f2' => false];
            }
            $att = (int) ($r->attempt ?? 1);
            if ($att <= 1 && (float) $val < self::PASS_GRADE) {
                $perUnit[$unitKey][$sid]['f1'] = true;
            } elseif ($att === 2) {
                $unitHas[$unitKey]['h2'] = true;
                if ((float) $val < self::PASS_GRADE) {
                    $perUnit[$unitKey][$sid]['f2'] = true;
                }
            } elseif ($att === 3) {
                $unitHas[$unitKey]['h3'] = true;
            }
        }

        foreach ($perUnit as $unitKey => $students) {
            $f1 = 0;
            $f2 = 0;
            foreach ($students as $s) {
                if ($s['f1']) {
                    $f1++;
                }
                if ($s['f2']) {
                    $f2++;
                }
            }
            // graded — shu birlik bo'yicha kamida bitta bahosi topilgan talabalar
            // soni. graded=0 bo'lsa: baho import qilinmagan yoki subject_id mos emas.
            $result[$unitKey] = [
                'failed1' => $f1,
                'failed2' => $f2,
                'graded' => count($students),
                'has2' => !empty($unitHas[$unitKey]['h2']),
                'has3' => !empty($unitHas[$unitKey]['h3']),
            ];
        }

        return $result;
    }

    /**
     * Barcha birliklar bo'yicha urinish sanalarini (eng kech sana) BITTA so'rovda
     * oladi — yopilish shakliga qarab OSKI va/yoki Test ustunlaridan.
     *
     * @param  array<string,string>  $unitByKey  "group|subjectKey|sem" => unitKey
     * @return array<string, array{attempt1:?string, resit:?string, resit2:?string}>
     */
    private function examDatesBatch(array $groupIds, array $subjectKeys, array $semCodes, array $unitByKey, array $units): array
    {
        $result = [];
        if (empty($groupIds) || empty($subjectKeys)) {
            return $result;
        }

        // unitKey => ['attempt1'=>[], 'resit'=>[], 'resit2'=>[], 'resit_g'=>[], 'resit2_g'=>[]]
        // *_g — student_grades attempt sanalaridan fallback (exam_schedules sana yo'q bo'lsa).
        $byUnit = [];
        $ensureUnit = function (string $unitKey) use (&$byUnit) {
            if (!isset($byUnit[$unitKey])) {
                $byUnit[$unitKey] = ['attempt1' => [], 'resit' => [], 'resit2' => [], 'resit_g' => [], 'resit2_g' => []];
            }
        };

        $cursor = ExamSchedule::whereNull('student_hemis_id')
            ->whereIn('group_hemis_id', $groupIds)
            ->whereIn('subject_id', $subjectKeys)
            ->whereIn('semester_code', $semCodes)
            ->cursor();

        foreach ($cursor as $r) {
            $unitKey = $unitByKey[$r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code] ?? null;
            if ($unitKey === null) {
                continue;
            }
            $cf = $units[$unitKey]['closing_form'] ?? '';
            $wantOski = in_array($cf, ['oski', 'oski_test'], true);
            $wantTest = in_array($cf, ['test', 'oski_test'], true);

            $ensureUnit($unitKey);
            if ($wantOski) {
                if (!$r->oski_na && $r->oski_date) {
                    $byUnit[$unitKey]['attempt1'][] = $r->oski_date->toDateString();
                }
                if ($r->oski_resit_date) {
                    $byUnit[$unitKey]['resit'][] = $r->oski_resit_date->toDateString();
                }
                if ($r->oski_resit2_date) {
                    $byUnit[$unitKey]['resit2'][] = $r->oski_resit2_date->toDateString();
                }
            }
            if ($wantTest) {
                if (!$r->test_na && $r->test_date) {
                    $byUnit[$unitKey]['attempt1'][] = $r->test_date->toDateString();
                }
                if ($r->test_resit_date) {
                    $byUnit[$unitKey]['resit'][] = $r->test_resit_date->toDateString();
                }
                if ($r->test_resit2_date) {
                    $byUnit[$unitKey]['resit2'][] = $r->test_resit2_date->toDateString();
                }
            }
        }

        // Fallback: exam_schedules da resit/resit2 sanasi belgilanmagan bo'lsa,
        // student_grades dagi attempt=2/3 OSKI/Test baholarining eng kech sanasini
        // ishlatamiz (showJournal bilan bir xil — imtihon bo'lib o'tgani baholar bor).
        if ($this->studentGradeAttemptColumn()) {
            $gradeDates = DB::table('student_grades as sg')
                ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->whereIn('st.group_id', $groupIds)
                ->whereIn('sg.subject_id', $subjectKeys)
                ->whereIn('sg.semester_code', $semCodes)
                ->whereIn('sg.training_type_code', [101, 102])
                ->whereIn('sg.attempt', [2, 3])
                ->whereNotNull('sg.lesson_date')
                ->whereNull('sg.deleted_at')
                ->selectRaw('st.group_id, sg.subject_id, sg.semester_code, sg.attempt, MAX(sg.lesson_date) as max_date')
                ->groupBy('st.group_id', 'sg.subject_id', 'sg.semester_code', 'sg.attempt')
                ->get();

            foreach ($gradeDates as $g) {
                $unitKey = $unitByKey[$g->group_id . '|' . $g->subject_id . '|' . $g->semester_code] ?? null;
                if ($unitKey === null || empty($g->max_date)) {
                    continue;
                }
                $ensureUnit($unitKey);
                $date = substr((string) $g->max_date, 0, 10);
                if ((int) $g->attempt === 2) {
                    $byUnit[$unitKey]['resit_g'][] = $date;
                } elseif ((int) $g->attempt === 3) {
                    $byUnit[$unitKey]['resit2_g'][] = $date;
                }
            }
        }

        foreach ($byUnit as $unitKey => $d) {
            $result[$unitKey] = [
                'attempt1' => !empty($d['attempt1']) ? max($d['attempt1']) : null,
                // resit/resit2 — avval exam_schedules, bo'lmasa baho sanalari (fallback).
                'resit' => !empty($d['resit']) ? max($d['resit'])
                    : (!empty($d['resit_g']) ? max($d['resit_g']) : null),
                'resit2' => !empty($d['resit2']) ? max($d['resit2'])
                    : (!empty($d['resit2_g']) ? max($d['resit2_g']) : null),
            ];
        }

        return $result;
    }

    /**
     * student_grades.attempt ustuni mavjudmi (bir marta tekshirilib keshlanadi).
     */
    private function studentGradeAttemptColumn(): bool
    {
        if ($this->studentGradeAttemptCol === null) {
            try {
                $this->studentGradeAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');
            } catch (\Throwable $e) {
                $this->studentGradeAttemptCol = false;
            }
        }

        return $this->studentGradeAttemptCol;
    }

    /**
     * Deadline uchun asos sana:
     *  - sinov / normativ → oxirgi dars sanasi (schedules.MAX(lesson_date))
     *  - test / oski / oski_test → oxirgi YN sanasi (exam_schedules)
     *
     * @return array{type:?string, date:?string}
     */
    public function computeBaseDate(Group $group, CurriculumSubject $subject, string $semCode): array
    {
        $cf = $subject->closing_form;
        // schedules/exam_schedules da subject_id HEMIS subject_id YOKI
        // curriculum_subject_hemis_id bo'lishi mumkin — ikkalasini ham qabul qilamiz.
        $subjectKeys = array_values(array_filter([
            $subject->subject_id,
            $subject->curriculum_subject_hemis_id,
        ]));

        if (in_array($cf, ['sinov', 'normativ'], true)) {
            $end = DB::table('schedules')
                ->whereNull('deleted_at')
                ->where('group_id', $group->group_hemis_id)
                ->whereIn('subject_id', $subjectKeys)
                ->max('lesson_date');

            return ['type' => 'lesson', 'date' => $end ? substr($end, 0, 10) : null];
        }

        if (in_array($cf, ['test', 'oski', 'oski_test'], true)) {
            $exam = ExamSchedule::whereNull('student_hemis_id')
                ->where('group_hemis_id', $group->group_hemis_id)
                ->whereIn('subject_id', $subjectKeys)
                ->where('semester_code', $semCode)
                ->first();

            $dates = [];
            if ($exam) {
                if (in_array($cf, ['oski', 'oski_test'], true) && !$exam->oski_na && $exam->oski_date) {
                    $dates[] = $exam->oski_date->toDateString();
                }
                if (in_array($cf, ['test', 'oski_test'], true) && !$exam->test_na && $exam->test_date) {
                    $dates[] = $exam->test_date->toDateString();
                }
            }

            return ['type' => 'exam', 'date' => !empty($dates) ? max($dates) : null];
        }

        return ['type' => null, 'date' => null];
    }

    /**
     * O'qituvchi ma'lumoti (telefon) — hemis_id bo'yicha, keshlanadi.
     */
    private function teacherInfo($hemisId): ?object
    {
        if (!$hemisId) {
            return null;
        }
        if (!array_key_exists($hemisId, $this->teacherCache)) {
            $this->teacherCache[$hemisId] = DB::table('teachers')
                ->where('hemis_id', $hemisId)
                ->select('hemis_id', 'full_name', 'phone')
                ->first();
        }

        return $this->teacherCache[$hemisId];
    }

    /**
     * Fan mas'uli — oldindan yuklangan map'dan (teacher_responsible_subjects).
     */
    private function fanMasuli($curriculumSubjectId): ?object
    {
        if (!$curriculumSubjectId || !$this->fanMasuliMap) {
            return null;
        }

        return $this->fanMasuliMap->get($curriculumSubjectId);
    }

    /**
     * Kafedra mudiri — kafedra (department_hemis_id) bo'yicha, keshlanadi.
     * JournalController bilan bir xil mantiq (rol → role ustuni → staff_position).
     */
    private function kafedraMudiri($departmentHemisId): ?object
    {
        if (!$departmentHemisId) {
            return null;
        }
        if (array_key_exists($departmentHemisId, $this->mudiriCache)) {
            return $this->mudiriCache[$departmentHemisId];
        }

        $mudiri = Teacher::whereHas('roles', fn($q) => $q->where('name', 'kafedra_mudiri'))
            ->where('department_hemis_id', $departmentHemisId)
            ->where('is_active', true)
            ->first();
        if (!$mudiri) {
            $mudiri = Teacher::where('role', 'kafedra_mudiri')
                ->where('department_hemis_id', $departmentHemisId)
                ->where('is_active', true)
                ->first();
        }
        if (!$mudiri) {
            $mudiri = Teacher::where('staff_position', 'LIKE', '%mudiri%')
                ->where('department_hemis_id', $departmentHemisId)
                ->where('is_active', true)
                ->first();
        }

        return $this->mudiriCache[$departmentHemisId] = $mudiri;
    }

    /**
     * Dars jadvalidan guruh+fan uchun asosiy o'qituvchi (eng ko'p dars o'tgan).
     */
    private function primaryTeacher(int $groupHemisId, CurriculumSubject $subject): ?object
    {
        $subjectKeys = array_values(array_filter([
            $subject->subject_id,
            $subject->curriculum_subject_hemis_id,
        ]));
        if (empty($subjectKeys)) {
            return null;
        }

        return DB::table('schedules')
            ->whereNull('deleted_at')
            ->where('group_id', $groupHemisId)
            ->whereIn('subject_id', $subjectKeys)
            ->whereNotNull('employee_id')
            ->select('employee_id', 'employee_name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('employee_id', 'employee_name')
            ->orderByDesc('cnt')
            ->first();
    }
}
