<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HemisQuizResult;
use App\Models\QuizGradeAppeal;
use App\Models\RetakeApplication;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Services\ActivityLogService;
use App\Services\Retake\RetakeJournalService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Sistemaga adashib yuklangan test natijalarini o'quv prorektori (va superadmin)
 * asoslovchi hujjat yuklab tuzatishi (almashtirish / o'chirish) uchun.
 * Faqat KO'RSATISH emas — bevosita amal qiladi va har bir amal audit qilinadi.
 */
class QuizGradeAppealController extends Controller
{
    // Baho tuzatish (almashtirish/o'chirish) — faqat o'quv prorektori/superadmin.
    private const ALLOWED_ROLES = ['superadmin', 'oquv_prorektori'];
    // Apelyatsiyalar TARIXINI ko'rish — yuqoridagilar + admin (faqat o'qish).
    private const VIEW_ROLES = ['superadmin', 'oquv_prorektori', 'admin'];

    /**
     * Amal qilish (qidiruv/tuzatish) huquqi — faqat o'quv prorektori/superadmin.
     */
    private function checkAccess(): void
    {
        $user = auth()->user() ?? auth()->guard('teacher')->user();
        if (!$user) {
            abort(403);
        }
        if (!in_array(session('active_role', ''), self::ALLOWED_ROLES, true)) {
            abort(403, "Bu amalni faqat o'quv prorektori bajara oladi.");
        }
    }

    /**
     * Tarix/hujjatni ko'rish huquqi — prorektor/superadmin va admin (o'qish).
     */
    private function checkViewAccess(): void
    {
        $user = auth()->user() ?? auth()->guard('teacher')->user();
        if (!$user) {
            abort(403);
        }
        if (!in_array(session('active_role', ''), self::VIEW_ROLES, true)) {
            abort(403, "Bu bo'limni ko'rish huquqingiz yo'q.");
        }
    }

    /**
     * Joriy foydalanuvchi apelyatsiya qila oladimi (tuzatish tugmasi uchun).
     */
    public static function canAppeal(): bool
    {
        return in_array(session('active_role', ''), self::ALLOWED_ROLES, true);
    }

    /**
     * Joriy foydalanuvchi apelyatsiyalar tarixini ko'ra oladimi (menyu uchun).
     */
    public static function canView(): bool
    {
        return in_array(session('active_role', ''), self::VIEW_ROLES, true);
    }

    private function routePrefix(): string
    {
        return auth()->guard('teacher')->check() ? 'teacher' : 'admin';
    }

    /**
     * Moodle orqali yuklangan natija ikki xil ko'rinishda saqlanadi:
     *  - 'quiz'  : OSKI/Test — reason='quiz_result', qiymat `grade` ustunida
     *              (uploadToGrades orqali yaratilgan yangi qator).
     *  - 'mavzu' : "N-mavzu" qayta topshirish — reason o'zgarmaydi
     *              ('absent'/'low_grade'), qiymat `retake_grade` ustunida
     *              (uploadMavzuRetake orqali mavjud qatorga yozilgan).
     * quiz_result_id ikkalasida ham to'ldiriladi — shu orqali ajratamiz.
     */
    private function gradeKind(StudentGrade $g): string
    {
        // 'mavzu' — mavjud jurnal (dars) qatoriga yozilgan qayta topshirish:
        //   quiz_result_id bor, lekin reason o'zgarmagan ('absent'/'low_grade'),
        //   qiymat retake_grade ustunida (uploadMavzuRetake).
        // 'quiz'  — OSKI/Test natijasi: reason='quiz_result' (Moodle yuklamasi)
        //   YOKI quiz_result_id BO'LMAGAN, ammo training_type 101/102 bo'lgan
        //   test yozuvi (masalan sinov YN test). Qiymat grade ustunida.
        return ($g->quiz_result_id && $g->reason !== 'quiz_result') ? 'mavzu' : 'quiz';
    }

    private function currentValue(StudentGrade $g): ?float
    {
        return $this->gradeKind($g) === 'quiz' ? $g->grade : $g->retake_grade;
    }

    /**
     * training_type_code -> ko'rinadigan nom (Moodle quiz natijasi bilan
     * bog'lanmagan 101/102 test yozuvlari uchun).
     */
    private function trainingTypeLabel($code): string
    {
        return match ((int) $code) {
            101 => 'OSKE',
            102 => 'Yakuniy test',
            default => 'Test',
        };
    }

    /**
     * Semestr nomi/raqamidan kurs (o'quv yili) ni chiqarish:
     *   1-2 semestr -> 1-kurs, 3-4 -> 2-kurs, 5-6 -> 3-kurs ...
     */
    private function kursFromSemester($semester): ?string
    {
        if (!$semester) {
            return null;
        }
        if (preg_match('/(\d+)/', (string) $semester, $m)) {
            $sem = (int) $m[1];
            if ($sem > 0) {
                return (string) ((int) ceil($sem / 2)) . '-kurs';
            }
        }
        return null;
    }

    /**
     * Apelyatsiyalar tarixi.
     */
    public function index(Request $request)
    {
        $this->checkViewAccess();
        $routePrefix = $this->routePrefix();
        $canPerform = self::canAppeal(); // admin uchun false — faqat tarix ko'rinadi

        $appeals = Schema::hasTable('quiz_grade_appeals')
            ? QuizGradeAppeal::orderByDesc('created_at')->paginate(50)
            : null;

        return view('admin.quiz-grade-appeals.index', compact('appeals', 'routePrefix', 'canPerform'));
    }

    /**
     * FISH / HEMIS ID / Talaba ID bo'yicha talabani qidirib, uning sistemaga
     * Moodle orqali yuklangan BARCHA natijalarini qaytaradi — OSKI/Test,
     * "N-mavzu" qayta topshirish va "Qayta o'qish" (kurs qayta o'qish)
     * turlarining barchasi.
     *
     * Diagnostika (tartibgaSol) bilan bir xil strategiya: qidiruv AVVAL
     * hemis_quiz_results.student_name/student_id ga to'g'ridan-to'g'ri
     * qaraladi (Moodle manbasi), students jadvaliga esa faqat ism/fakultet
     * kabi ko'rinish ma'lumotlarini to'ldirish uchun ishlatiladi. Aks holda
     * (avvalgi versiyada bo'lgani kabi) talabaning students jadvalidagi
     * yozuvi topilmasa yoki quiz natijasi boshqa student_hemis_id bilan
     * bog'langan bo'lsa (masalan alohida "qayta o'qish" yozuvi), natija
     * butunlay ko'rinmay qolardi.
     */
    public function searchGrades(Request $request)
    {
        $this->checkAccess();

        $request->validate(['q' => 'required|string|min:2']);
        $q = trim($request->q);

        // 1) students jadvalidan mos hemis_id/student_id_number lar — Moodle
        //    tomonidagi student_id ko'pincha shularga teng bo'ladi.
        $students = Student::where('full_name', 'LIKE', '%' . $q . '%')
            ->orWhere('student_id_number', 'LIKE', '%' . $q . '%')
            ->orWhere('hemis_id', 'LIKE', '%' . $q . '%')
            ->limit(50)
            ->get(['id', 'hemis_id', 'student_id_number', 'full_name', 'department_name', 'specialty_name', 'group_name']);

        $studentLookup = [];
        $nameIds = [];
        $hemisIds = [];
        foreach ($students as $s) {
            if ($s->hemis_id) {
                $studentLookup[$s->hemis_id] = $s;
                $nameIds[] = $s->hemis_id;
                $hemisIds[] = $s->hemis_id;
            }
            if ($s->student_id_number) {
                $studentLookup[$s->student_id_number] = $s;
                $nameIds[] = $s->student_id_number;
            }
        }

        // 2) hemis_quiz_results ni TO'G'RIDAN-TO'G'RI qidiramiz (Moodle
        //    manbasi) — bular quiz_result_id orqali student_grades ga bog'langan.
        $quizResultIds = HemisQuizResult::where(function ($qq) use ($q, $nameIds) {
                $qq->where('student_name', 'LIKE', '%' . $q . '%')
                   ->orWhere('student_id', 'LIKE', '%' . $q . '%');
                if (!empty($nameIds)) {
                    $qq->orWhereIn('student_id', $nameIds);
                }
            })
            ->limit(1000)
            ->pluck('id');

        // Na Moodle natijasi, na students yozuvi topilmasa — bo'sh qaytaramiz
        // (aks holda quyidagi shartsiz so'rov barcha yozuvlarni tortib yuborardi).
        if ($quizResultIds->isEmpty() && empty($hemisIds)) {
            return response()->json(['success' => true, 'rows' => [], 'students_found' => $students->count()]);
        }

        // 3) student_grades ni ikki yo'l bilan qamraymiz:
        //    (a) quiz_result_id orqali bog'langan HAR QANDAY Moodle yuklamasi
        //        (OSKI/Test + "N-mavzu" qayta topshirish), va
        //    (b) quiz_result_id BO'LMAGAN, ammo training_type_code 101/102
        //        (OSKE/Test) bo'lgan yozuvlar — masalan "sinov YN test".
        //    "Adashib yuklangan test natijasi" shu ikkalasini o'z ichiga oladi;
        //    JN/mavzu (haftalik) baholariga tegmaymiz (faqat 101/102 va quiz).
        $grades = StudentGrade::where(function ($qb) use ($quizResultIds, $hemisIds) {
                if ($quizResultIds->isNotEmpty()) {
                    $qb->whereIn('quiz_result_id', $quizResultIds);
                }
                if (!empty($hemisIds)) {
                    $qb->orWhere(function ($q2) use ($hemisIds) {
                        $q2->whereIn('student_hemis_id', $hemisIds)
                           ->whereIn('training_type_code', [101, 102]);
                    });
                }
            })
            ->orderByDesc('lesson_date')
            ->limit(1000)
            ->get();

        if ($grades->isEmpty()) {
            return response()->json(['success' => true, 'rows' => [], 'students_found' => $students->count()]);
        }

        $quizResults = HemisQuizResult::whereIn('id', $grades->pluck('quiz_result_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        // 4) Dastlabki ism-qidiruvida topilmagan student_hemis_id lar uchun
        //    qo'shimcha (aniq) qidiruv — grade qatorining o'z hemis_id/
        //    student_id_number qiymati bo'yicha.
        $missingIds = $grades->pluck('student_hemis_id')
            ->unique()
            ->filter()
            ->reject(fn ($id) => isset($studentLookup[$id]))
            ->values();

        if ($missingIds->isNotEmpty()) {
            Student::where(function ($qb) use ($missingIds) {
                    $qb->whereIn('hemis_id', $missingIds)
                       ->orWhereIn('student_id_number', $missingIds);
                })
                ->get(['hemis_id', 'student_id_number', 'full_name', 'department_name', 'specialty_name', 'group_name'])
                ->each(function ($s) use (&$studentLookup) {
                    if ($s->hemis_id) {
                        $studentLookup[$s->hemis_id] = $s;
                    }
                    if ($s->student_id_number) {
                        $studentLookup[$s->student_id_number] = $s;
                    }
                });
        }

        $rows = $grades->map(function ($g) use ($quizResults, $studentLookup) {
            $student = $studentLookup[$g->student_hemis_id] ?? null;
            $quiz = $g->quiz_result_id ? ($quizResults[$g->quiz_result_id] ?? null) : null;
            $kind = $this->gradeKind($g);

            return [
                'source'       => 'grade',
                'id'           => $g->id, // student_grade_id
                'student_hemis_id' => $g->student_hemis_id,
                // Students jadvalida yozuv topilmasa — Moodle'dagi xom ismga tushamiz
                // (diagnostika sahifasi ham xuddi shunday qiladi).
                'student_name' => $student?->full_name ?? $quiz?->student_name ?? '-',
                'faculty'      => $student?->department_name ?? '-',
                'direction'    => $student?->specialty_name ?? '-',
                'group'        => $student?->group_name ?? '-',
                'fan_name'     => $g->subject_name,
                'kurs'         => $this->kursFromSemester($g->semester_name) ?? '-',
                'semester'     => $g->semester_name ?: '-',
                // Moodle natijasi bo'lsa o'shandan; aks holda grade qatorining
                // o'zidan (quiz_result_id siz 101/102 test yozuvi).
                'quiz_type'    => $quiz?->quiz_type ?? $this->trainingTypeLabel($g->training_type_code),
                'shakl'        => $quiz?->shakl ?? (((int) $g->attempt) > 1 ? $g->attempt . '-urinish' : '1-urinish'),
                'attempt_name' => $quiz?->attempt_name ?? '-',
                'kind'         => $kind, // 'quiz' (OSKI/Test) | 'mavzu' (qayta topshirish)
                'kind_label'   => $kind === 'mavzu' ? 'Qayta topshirish' : 'OSKI/Test',
                'grade'        => $this->currentValue($g),
                'date'         => $quiz && $quiz->date_finish
                    ? $quiz->date_finish->format('d.m.Y')
                    : ($g->lesson_date ? \Illuminate\Support\Carbon::parse($g->lesson_date)->format('d.m.Y') : '-'),
            ];
        })->values()->all();

        // 5) QAYTA O'QISH natijalari — retake_applications.oske_score/test_score.
        //    Bular student_grades da EMAS (test markazi to'g'ridan-to'g'ri
        //    retake_applications ga yozadi), shuning uchun alohida qatorlar
        //    sifatida qo'shamiz. Har bir mavjud komponent (OSKE/Test) bitta qator.
        if (!empty($hemisIds)) {
            $retakeApps = RetakeApplication::with(['group.student', 'retakeGroup'])
                ->whereIn('student_hemis_id', $hemisIds)
                ->where('final_status', RetakeApplication::STATUS_APPROVED)
                ->where(function ($qb) {
                    $qb->whereNotNull('oske_score')->orWhereNotNull('test_score');
                })
                ->limit(500)
                ->get();

            foreach ($retakeApps as $app) {
                $student = $studentLookup[$app->student_hemis_id] ?? ($app->group->student ?? null);
                $subjectName = $app->retakeGroup->subject_name ?? $app->subject_name ?? '-';
                $semester = $app->semester_name ?: ($app->retakeGroup->semester_name ?? null);
                $date = $app->final_grade_set_at ? $app->final_grade_set_at->format('d.m.Y') : '-';

                foreach (['oske' => $app->oske_score, 'test' => $app->test_score] as $component => $score) {
                    if ($score === null) {
                        continue;
                    }
                    $componentLabel = $component === 'oske' ? 'OSKE' : 'Test';
                    $rows[] = [
                        'source'       => 'retake',
                        'id'           => 'ra-' . $app->id . '-' . $component, // faqat UI kaliti
                        'retake_application_id' => $app->id,
                        'component'    => $component,
                        'student_hemis_id' => $app->student_hemis_id,
                        'student_name' => $student?->full_name ?? '-',
                        'faculty'      => $student?->department_name ?? '-',
                        'direction'    => $student?->specialty_name ?? '-',
                        'group'        => $student?->group_name ?? '-',
                        'fan_name'     => $subjectName,
                        'kurs'         => $this->kursFromSemester($semester) ?? '-',
                        'semester'     => $semester ?: '-',
                        'quiz_type'    => "Qayta o'qish",
                        'shakl'        => $componentLabel,
                        'attempt_name' => '-',
                        'kind'         => 'retake',
                        'kind_label'   => "Qayta o'qish ({$componentLabel})",
                        'grade'        => (float) $score,
                        'date'         => $date,
                    ];
                }
            }
        }

        return response()->json([
            'success'        => true,
            'rows'           => $rows,
            'students_found' => $students->count(),
        ]);
    }

    /**
     * Bahoni almashtirish yoki o'chirish — asoslovchi hujjat majburiy.
     */
    public function store(Request $request)
    {
        $this->checkAccess();

        $data = $request->validate([
            'source'                => 'nullable|in:grade,retake',
            'student_grade_id'      => 'required_without:retake_application_id|nullable|integer|exists:student_grades,id',
            'retake_application_id' => 'nullable|integer|exists:retake_applications,id',
            'component'             => 'nullable|in:oske,test',
            'action'                => 'required|in:replace,delete',
            'new_grade'             => 'required_if:action,replace|nullable|numeric|min:0|max:100',
            'reason'                => 'required|string|min:5|max:2000',
            'document'              => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png',
        ], [
            'new_grade.required_if' => 'Almashtirish uchun yangi baho kiritilishi shart.',
            'reason.required'       => 'Sabab (asoslash) kiritilishi shart.',
            'reason.min'            => 'Sabab kamida 5 belgidan iborat bo\'lsin.',
            'document.required'     => 'Asoslovchi hujjatni yuklash shart.',
            'document.mimes'        => 'Hujjat PDF, JPG yoki PNG bo\'lishi kerak.',
            'document.max'          => 'Hujjat hajmi 5MB dan oshmasin.',
        ]);

        // Qayta o'qish (retake_applications) OSKE/Test natijasi bo'lsa — alohida oqim.
        if (($data['source'] ?? null) === 'retake' || !empty($data['retake_application_id'])) {
            return $this->storeRetake($request, $data);
        }

        $grade = StudentGrade::findOrFail($data['student_grade_id']);
        // Apelyatsiya faqat test natijalari uchun: (a) Moodle yuklamasi
        // (quiz_result_id bor) YOKI (b) OSKE/Test yozuvi (training_type 101/102).
        // Haftalik JN/mavzu (dars) baholariga tegishga yo'l qo'ymaymiz.
        $isTestRow = in_array((int) $grade->training_type_code, [101, 102], true);
        if (!$grade->quiz_result_id && !$isTestRow) {
            return response()->json([
                'success' => false,
                'message' => "Bu yozuv test natijasi emas (OSKE/Test yoki Moodle yuklamasi emas) — apelyatsiya doirasida emas.",
            ], 422);
        }

        $user = auth()->user() ?? auth()->guard('teacher')->user();
        $kind = $this->gradeKind($grade); // 'quiz' | 'mavzu'
        $oldGrade = $this->currentValue($grade);
        $isReplace = $data['action'] === QuizGradeAppeal::ACTION_REPLACE;

        $file = $request->file('document');
        $path = $file->store('quiz-grade-appeals/' . $grade->id, 'public');

        $appeal = QuizGradeAppeal::create([
            'student_grade_id'       => $grade->id,
            'quiz_result_id'         => $grade->quiz_result_id,
            'student_hemis_id'       => $grade->student_hemis_id,
            'student_name'           => optional($grade->student)->full_name,
            'subject_id'             => $grade->subject_id,
            'subject_name'           => $grade->subject_name,
            'action'                 => $data['action'],
            'old_grade'              => $oldGrade,
            'new_grade'              => $isReplace ? $data['new_grade'] : null,
            'reason'                 => $data['reason'],
            'document_path'          => $path,
            'document_original_name' => $file->getClientOriginalName(),
            'performed_by_guard'     => auth()->guard('teacher')->check() ? 'teacher' : 'web',
            'performed_by_id'        => $user?->id,
            'performed_by_name'      => $user->full_name ?? $user->name ?? null,
            'performed_by_role'      => session('active_role'),
        ]);

        // Bahoni qo'llash — Eloquent orqali (LogsActivity trait avtomatik audit qiladi).
        // OSKI/Test ('quiz') natijasi `grade` ustunida, mavzu qayta topshirish
        // ('mavzu') natijasi `retake_grade` ustunida saqlanadi — noto'g'ri
        // ustunga yozish klassik jurnal bahosini buzib qo'yishi mumkin.
        if ($isReplace) {
            if ($kind === 'quiz') {
                $grade->grade = $data['new_grade'];
            } else {
                $grade->retake_grade = $data['new_grade'];
            }
            $grade->save();
        } else {
            if ($kind === 'quiz') {
                // Butun qator faqat shu quiz yuklamasi tufayli mavjud — to'liq o'chiramiz.
                $grade->delete(); // soft delete
            } else {
                // Qator asl (darsdagi) baho yozuvi — uni o'chirmaymiz, faqat
                // Moodle orqali qo'yilgan retake bog'lanishini bekor qilamiz.
                $grade->retake_grade = null;
                $grade->retake_comment = null;
                $grade->retake_was_sababli = null;
                $grade->quiz_result_id = null;
                $grade->save();
            }
        }

        ActivityLogService::log(
            'update',
            'student_grade',
            $isReplace
                ? "Apelyatsiya (prorektor, {$kind}): test bahosi {$oldGrade} -> {$data['new_grade']} almashtirildi"
                : "Apelyatsiya (prorektor, {$kind}): test bahosi ({$oldGrade}) o'chirildi",
            $appeal
        );

        $this->sendAppealTelegram($appeal, $kind === 'mavzu' ? 'Qayta topshirish (mavzu)' : 'OSKI/Test');

        return response()->json([
            'success' => true,
            'message' => $isReplace ? 'Baho almashtirildi.' : 'Baho o\'chirildi.',
        ]);
    }

    /**
     * Qayta o'qish (retake_applications) OSKE/Test natijasini tuzatish.
     * Bu baho student_grades da emas — test markazi to'g'ridan-to'g'ri
     * retake_applications.oske_score/test_score ga yozadi. Shu qiymatni
     * almashtiramiz yoki o'chiramiz (null) va yakuniy bahoni (final_grade_value)
     * mavjud RetakeJournalService logikasi orqali qayta hisoblaymiz.
     */
    private function storeRetake(Request $request, array $data)
    {
        if (empty($data['retake_application_id']) || empty($data['component'])) {
            return response()->json([
                'success' => false,
                'message' => "Qayta o'qish yozuvi to'liq ko'rsatilmagan (ariza yoki komponent yo'q).",
            ], 422);
        }

        $component = $data['component']; // 'oske' | 'test'
        $col = $component === 'oske' ? 'oske_score' : 'test_score';
        $componentLabel = $component === 'oske' ? 'OSKE' : 'Test';

        $app = RetakeApplication::with(['group.student', 'retakeGroup'])
            ->findOrFail($data['retake_application_id']);

        $oldGrade = $app->$col !== null ? (float) $app->$col : null;
        $isReplace = $data['action'] === QuizGradeAppeal::ACTION_REPLACE;

        $user = auth()->user() ?? auth()->guard('teacher')->user();
        $file = $request->file('document');
        $path = $file->store('quiz-grade-appeals/retake-' . $app->id, 'public');

        $subjectName = $app->retakeGroup->subject_name ?? $app->subject_name;

        $appeal = QuizGradeAppeal::create([
            'student_grade_id'       => null,
            'quiz_result_id'         => null,
            'retake_application_id'  => $app->id,
            'retake_component'       => $component,
            'student_hemis_id'       => $app->student_hemis_id,
            'student_name'           => optional($app->group->student ?? null)->full_name,
            'subject_id'             => $app->subject_id,
            'subject_name'           => trim(($subjectName ?: '-') . " (qayta o'qish, {$componentLabel})"),
            'action'                 => $data['action'],
            'old_grade'              => $oldGrade,
            'new_grade'              => $isReplace ? $data['new_grade'] : null,
            'reason'                 => $data['reason'],
            'document_path'          => $path,
            'document_original_name' => $file->getClientOriginalName(),
            'performed_by_guard'     => auth()->guard('teacher')->check() ? 'teacher' : 'web',
            'performed_by_id'        => $user?->id,
            'performed_by_name'      => $user->full_name ?? $user->name ?? null,
            'performed_by_role'      => session('active_role'),
        ]);

        // Yangi qiymatlar: tuzatilayotgan komponent o'zgaradi, ikkinchisi
        // o'zgarmaydi. saveOskeTestScore ikkalasini qabul qilib, final_grade_value
        // ni qayta hisoblaydi.
        $newVal = $isReplace ? (float) $data['new_grade'] : null;
        $newOske = $component === 'oske' ? $newVal : ($app->oske_score !== null ? (float) $app->oske_score : null);
        $newTest = $component === 'test' ? $newVal : ($app->test_score !== null ? (float) $app->test_score : null);

        app(RetakeJournalService::class)->saveOskeTestScore($app, $newOske, $newTest);

        ActivityLogService::log(
            'update',
            'student_grade',
            $isReplace
                ? "Apelyatsiya (prorektor, qayta o'qish {$componentLabel}): {$oldGrade} -> {$data['new_grade']} almashtirildi"
                : "Apelyatsiya (prorektor, qayta o'qish {$componentLabel}): ({$oldGrade}) o'chirildi",
            $appeal
        );

        $this->sendAppealTelegram($appeal, "Qayta o'qish ({$componentLabel})");

        return response()->json([
            'success' => true,
            'message' => $isReplace ? 'Qayta o\'qish bahosi almashtirildi.' : 'Qayta o\'qish bahosi o\'chirildi.',
        ]);
    }

    /**
     * Apelyatsiya (baho almashtirildi/o'chirildi) haqida Telegram xabari.
     * Xato bo'lsa amalni buzmaydi (TelegramService jim log qiladi).
     */
    private function sendAppealTelegram(QuizGradeAppeal $appeal, string $typeLabel): void
    {
        $isDelete = $appeal->action === QuizGradeAppeal::ACTION_DELETE;
        $old = $appeal->old_grade !== null ? rtrim(rtrim(number_format($appeal->old_grade, 2, '.', ''), '0'), '.') : '—';
        $new = $appeal->new_grade !== null ? rtrim(rtrim(number_format($appeal->new_grade, 2, '.', ''), '0'), '.') : '—';

        $lines = [
            "🔔 Test bahosi apelyatsiyasi",
            "👤 Talaba: " . ($appeal->student_name ?: '—') . " (" . ($appeal->student_hemis_id ?: '—') . ")",
            "📚 Fan: " . ($appeal->subject_name ?: '—'),
            "🏷 Turi: " . $typeLabel,
            "⚙️ Amal: " . ($isDelete ? "Baho o'chirildi" : "Baho almashtirildi"),
            "📊 Baho: " . ($isDelete ? "{$old} → o'chirildi" : "{$old} → {$new}"),
            "📝 Sabab: " . ($appeal->reason ?: '—'),
            "👮 Kim: " . ($appeal->performed_by_name ?: '—') . " (" . ($appeal->performed_by_role ?: '—') . ")",
            "🕒 " . ($appeal->created_at?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i')),
        ];

        app(TelegramService::class)->notifyChat(
            config('services.telegram.appeal_chat_id'),
            implode("\n", $lines)
        );
    }

    /**
     * Asoslovchi hujjatni yuklab olish.
     */
    public function download($id)
    {
        $this->checkViewAccess();

        $appeal = QuizGradeAppeal::findOrFail($id);
        if (!$appeal->document_path || !Storage::disk('public')->exists($appeal->document_path)) {
            abort(404, 'Fayl topilmadi.');
        }

        return Storage::disk('public')->download($appeal->document_path, $appeal->document_original_name);
    }
}
