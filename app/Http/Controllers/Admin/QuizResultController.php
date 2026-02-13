<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HemisQuizResult;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\CurriculumSubject;
use App\Models\Group;
use App\Models\Semester;
use App\Imports\QuizResultImport;
use App\Exports\QuizResultExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class QuizResultController extends Controller
{
    /**
     * Joriy guard bo'yicha route prefiksini aniqlash (admin yoki teacher).
     */
    private function routePrefix(): string
    {
        return auth()->guard('teacher')->check() ? 'teacher' : 'admin';
    }

    /**
     * Diagnostika sahifasi — yangi dizayn bilan.
     */
    public function diagnostikaPage(Request $request)
    {
        $routePrefix = $this->routePrefix();

        return view('admin.diagnostika.index', compact('routePrefix'));
    }

    /**
     * Diagnostika sahifasi uchun AJAX data endpoint.
     */
    public function diagnostikaData(Request $request)
    {
        $query = HemisQuizResult::where('is_active', 1);

        if ($request->filled('faculty')) {
            $query->where('faculty', $request->faculty);
        }
        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }
        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }
        if ($request->filled('fan_name')) {
            $query->where('fan_name', 'LIKE', '%' . $request->fan_name . '%');
        }
        if ($request->filled('student_name')) {
            $query->where('student_name', 'LIKE', '%' . $request->student_name . '%');
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('quiz_type')) {
            $query->where('quiz_type', $request->quiz_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date_finish', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date_finish', '<=', $request->date_to);
        }

        // Excel export
        if ($request->input('export') === 'excel') {
            return (new QuizResultExport($request))->export();
        }

        $perPage = $request->input('per_page', 50);
        $paginated = $query->orderByDesc('date_finish')->paginate($perPage);

        $data = collect($paginated->items())->map(function ($item, $i) use ($paginated) {
            return [
                'id' => $item->id,
                'row_num' => $paginated->firstItem() + $i,
                'student_id' => $item->student_id,
                'student_name' => $item->student_name,
                'faculty' => $item->faculty,
                'direction' => $item->direction,
                'semester' => $item->semester,
                'fan_name' => $item->fan_name,
                'quiz_type' => $item->quiz_type,
                'shakl' => $item->shakl,
                'grade' => $item->grade,
                'date' => $item->date_finish ? $item->date_finish->format('d.m.Y') : '',
            ];
        });

        return response()->json([
            'data' => $data,
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
        ]);
    }

    /**
     * Tartibga solish — natijalarni jurnal shaklida ko'rsatish.
     * FISH, fakultet, yo'nalish, kurs, semestr, guruh students jadvalidan olinadi.
     * Quiz turi bo'yicha YN turi ustunida Test yoki OSKI deb ko'rsatiladi.
     * Barcha urinishlar ko'rsatiladi.
     */
    public function tartibgaSol(Request $request)
    {
        $query = HemisQuizResult::where('is_active', 1);

        if ($request->filled('date_from')) {
            $query->whereDate('date_finish', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date_finish', '<=', $request->date_to);
        }

        $results = $query->orderBy('student_id')->orderBy('fan_id')->orderBy('date_finish')->get();

        // Barcha student_id larni yig'ish va bulk query
        $studentIds = $results->pluck('student_id')->unique()->values()->toArray();

        $students = Student::where(function ($q) use ($studentIds) {
            $q->whereIn('hemis_id', $studentIds)
              ->orWhereIn('student_id_number', $studentIds);
        })->get();

        // Lookup yaratish (hemis_id va student_id_number bo'yicha)
        $studentLookup = [];
        foreach ($students as $student) {
            $studentLookup[$student->hemis_id] = $student;
            if ($student->student_id_number) {
                $studentLookup[$student->student_id_number] = $student;
            }
        }

        $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
        $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];

        $data = [];
        $rowNum = 0;

        foreach ($results as $result) {
            $student = $studentLookup[$result->student_id] ?? null;
            if (!$student) continue;

            // Semestrni Moodle formatidan aniqlash: "3-SEM" -> 3, "5-sem" -> 5
            $semNum = null;
            $semLabel = $result->semester ?: $student->semester_name;
            if ($semLabel && preg_match('/(\d+)/', $semLabel, $m)) {
                $semNum = (int) $m[1];
            }

            // Kursni semestr raqamidan hisoblash: 1,2->1-kurs, 3,4->2-kurs, ...
            $kurs = $semNum ? (int) ceil($semNum / 2) : null;

            // YN turi aniqlash
            $ynTuri = '-';
            if (in_array($result->quiz_type, $testTypes)) {
                $ynTuri = 'Test';
            } elseif (in_array($result->quiz_type, $oskiTypes)) {
                $ynTuri = 'OSKI';
            }

            $rowNum++;
            $data[] = [
                'id' => $result->id,
                'row_num' => $rowNum,
                'student_id' => $result->student_id,
                'full_name' => $student->full_name,
                'faculty' => $student->department_name,
                'direction' => $student->specialty_name,
                'kurs' => $kurs ? $kurs . '-kurs' : '-',
                'semester' => $semNum ? $semNum . '-sem' : ($semLabel ?: '-'),
                'group' => $student->group_name,
                'fan_name' => $result->fan_name,
                'yn_turi' => $ynTuri,
                'shakl' => $result->shakl,
                'grade' => $result->grade,
                'date' => $result->date_finish ? $result->date_finish->format('d.m.Y') : '',
            ];
        }

        return response()->json([
            'data' => $data,
            'total' => count($data),
        ]);
    }

    public function index(Request $request)
    {
        $query = HemisQuizResult::where('is_active', 1);

        if ($request->filled('faculty')) {
            $query->where('faculty', $request->faculty);
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        if ($request->filled('fan_name')) {
            $query->where('fan_name', 'LIKE', '%' . $request->fan_name . '%');
        }

        if ($request->filled('student_name')) {
            $query->where('student_name', 'LIKE', '%' . $request->student_name . '%');
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('quiz_type')) {
            $query->where('quiz_type', $request->quiz_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date_finish', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date_finish', '<=', $request->date_to);
        }

        $perPage = $request->input('per_page', 50);
        $results = $query->orderByDesc('date_finish')->paginate($perPage)->withQueryString();

        // Filtrlar uchun unique qiymatlarni olish
        $faculties = HemisQuizResult::where('is_active', 1)
            ->whereNotNull('faculty')
            ->distinct()
            ->pluck('faculty')
            ->sort();

        $semesters = HemisQuizResult::where('is_active', 1)
            ->whereNotNull('semester')
            ->distinct()
            ->pluck('semester')
            ->sort();

        $quizTypes = HemisQuizResult::where('is_active', 1)
            ->whereNotNull('quiz_type')
            ->distinct()
            ->pluck('quiz_type')
            ->sort();

        $routePrefix = $this->routePrefix();

        return view('admin.quiz_results.index', compact(
            'results', 'faculties', 'semesters', 'quizTypes', 'routePrefix'
        ));
    }

    public function exportExcel(Request $request)
    {
        return (new QuizResultExport($request))->export();
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        $import = new QuizResultImport();
        Excel::import($import, $request->file('file'));

        $successCount = $import->successCount;
        $errors = $import->errors;

        $indexRoute = $this->routePrefix() . '.quiz-results.index';

        if (count($errors) > 0) {
            return redirect()->route($indexRoute)
                ->with('success', "$successCount ta natija student_grades ga muvaffaqiyatli yuklandi.")
                ->with('import_errors', $errors)
                ->with('error_count', count($errors));
        }

        return redirect()->route($indexRoute)
            ->with('success', "$successCount ta natija student_grades ga muvaffaqiyatli yuklandi.");
    }

    /**
     * Diagnostika — dry-run: qaysi natijalar o'tadi, qaysilari xato beradi tekshirish.
     * Faqat 1-urinish natijalar hisobga olinadi.
     * student_grades ga hech narsa yozilmaydi.
     */
    public function diagnostika(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:hemis_quiz_results,id',
        ]);

        $results = HemisQuizResult::whereIn('id', $request->ids)->get();

        $ok = [];
        $errors = [];
        $skipped = 0;
        $duplicateTracker = [];

        foreach ($results as $result) {
            $row = [
                'id' => $result->id,
                'attempt_id' => $result->attempt_id,
                'student_id' => $result->student_id,
                'student_name' => $result->student_name,
                'fan_id' => $result->fan_id,
                'fan_name' => $result->fan_name,
                'grade' => $result->grade,
            ];

            // Faqat 1-urinish hisobga olinadi
            if ($result->shakl !== '1-urinish') {
                $row['error'] = "1-urinish emas ({$result->shakl}) — o'tkazib yuborildi";
                $errors[] = $row;
                $skipped++;
                continue;
            }

            // Baho validatsiyasi
            if ($result->grade === null || $result->grade < 0 || $result->grade > 100) {
                $row['error'] = "Baho noto'g'ri: {$result->grade}";
                $errors[] = $row;
                continue;
            }

            // Talabani topish (avval hemis_id, keyin student_id_number bo'yicha)
            $student = Student::where('hemis_id', $result->student_id)
                ->orWhere('student_id_number', $result->student_id)
                ->first();
            if (!$student) {
                $row['error'] = "Talaba topilmadi (student_id: {$result->student_id})";
                $errors[] = $row;
                continue;
            }

            // Guruhni topish
            $group = Group::where('group_hemis_id', $student->group_id)->first();
            if (!$group) {
                $row['error'] = "Talaba guruhida guruh topilmadi";
                $errors[] = $row;
                continue;
            }

            // Fanni topish
            $subject = null;
            if ($result->fan_id) {
                $subject = CurriculumSubject::where('subject_id', $result->fan_id)->first();
            }
            if (!$subject) {
                $row['error'] = "Fan topilmadi (fan_id: {$result->fan_id})";
                $errors[] = $row;
                continue;
            }

            // Avval yuklangan-mi tekshirish
            $existing = StudentGrade::where('quiz_result_id', $result->id)->first();
            if ($existing) {
                $row['error'] = "Bu natija avval yuklangan (grade_id: {$existing->id})";
                $errors[] = $row;
                continue;
            }

            // Bir xil talaba+fan dublikatlarni aniqlash (bir necha marta test yechganlar)
            $key = $result->student_id . '_' . $result->fan_id;
            if (isset($duplicateTracker[$key])) {
                $row['error'] = "Dublikat: bu talaba uchun shu fandan yana bir natija mavjud (#{$duplicateTracker[$key]})";
                $errors[] = $row;
                continue;
            }
            $duplicateTracker[$key] = $result->id;

            $row['student_db_name'] = $student->full_name ?? $student->fio ?? $result->student_name;
            $row['subject_name'] = $subject->subject_name;
            $ok[] = $row;
        }

        return response()->json([
            'ok_count' => count($ok),
            'error_count' => count($errors),
            'skipped_count' => $skipped,
            'ok' => $ok,
            'errors' => $errors,
        ]);
    }

    /**
     * Sistemaga yuklash — filtrlangan natijalarni student_grades ga yozish.
     */
    public function uploadToGrades(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:hemis_quiz_results,id',
        ]);

        $results = HemisQuizResult::whereIn('id', $request->ids)->get();

        $successCount = 0;
        $errors = [];
        $duplicateTracker = [];

        foreach ($results as $result) {
            $rowInfo = [
                'id' => $result->id,
                'attempt_id' => $result->attempt_id,
                'student_id' => $result->student_id,
                'student_name' => $result->student_name,
                'fan_name' => $result->fan_name,
                'grade' => $result->grade,
            ];

            // Faqat 1-urinish yuklanadi
            if ($result->shakl !== '1-urinish') {
                $rowInfo['error'] = "Faqat 1-urinish yuklanadi (hozirgi: {$result->shakl})";
                $errors[] = $rowInfo;
                continue;
            }

            if ($result->grade === null || $result->grade < 0 || $result->grade > 100) {
                $rowInfo['error'] = "Baho noto'g'ri: {$result->grade}";
                $errors[] = $rowInfo;
                continue;
            }

            $student = Student::where('hemis_id', $result->student_id)
                ->orWhere('student_id_number', $result->student_id)
                ->first();
            if (!$student) {
                $rowInfo['error'] = "Talaba topilmadi (student_id: {$result->student_id})";
                $errors[] = $rowInfo;
                continue;
            }

            $group = Group::where('group_hemis_id', $student->group_id)->first();
            if (!$group) {
                $rowInfo['error'] = "Talaba guruhida guruh topilmadi";
                $errors[] = $rowInfo;
                continue;
            }

            $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
                ->where('code', $student->semester_code)
                ->first();

            $subject = null;
            if ($result->fan_id) {
                $subject = CurriculumSubject::where('subject_id', $result->fan_id)->first();
            }
            if (!$subject) {
                $rowInfo['error'] = "Fan topilmadi (fan_id: {$result->fan_id})";
                $errors[] = $rowInfo;
                continue;
            }

            $existing = StudentGrade::where('quiz_result_id', $result->id)->first();
            if ($existing) {
                $rowInfo['error'] = "Bu natija avval yuklangan";
                $errors[] = $rowInfo;
                continue;
            }

            // Dublikat tekshirish
            $key = $result->student_id . '_' . $result->fan_id;
            if (isset($duplicateTracker[$key])) {
                $rowInfo['error'] = "Dublikat: bu talaba+fan juftligi takrorlangan";
                $errors[] = $rowInfo;
                continue;
            }
            $duplicateTracker[$key] = $result->id;

            StudentGrade::create([
                'student_id' => $student->id,
                'student_hemis_id' => $student->hemis_id,
                'hemis_id' => 999999999,
                'semester_code' => $semester->code ?? $student->semester_code,
                'semester_name' => $semester->name ?? $student->semester_name,
                'subject_schedule_id' => 0,
                'subject_id' => $subject->subject_id,
                'subject_name' => $subject->subject_name,
                'subject_code' => $subject->subject_code ?? '',
                'training_type_code' => 103,
                'training_type_name' => 'Quiz test',
                'employee_id' => 0,
                'employee_name' => auth()->user()->name ?? 'Test markazi',
                'lesson_pair_name' => '',
                'lesson_pair_code' => '',
                'lesson_pair_start_time' => '',
                'lesson_pair_end_time' => '',
                'lesson_date' => $result->date_finish ?? now(),
                'created_at_api' => $result->created_at ?? now(),
                'reason' => 'quiz_result',
                'status' => 'recorded',
                'grade' => round($result->grade),
                'deadline' => now(),
                'quiz_result_id' => $result->id,
            ]);

            $successCount++;
        }

        return response()->json([
            'success_count' => $successCount,
            'error_count' => count($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * Bitta quiz natijani o'chirish (is_active = 0).
     */
    public function destroy($id)
    {
        $result = HemisQuizResult::findOrFail($id);
        $result->update(['is_active' => 0]);

        return response()->json(['success' => true]);
    }
}
