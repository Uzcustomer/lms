<?php

namespace App\Http\Controllers\Admin;

use App\Exports\LectureScheduleExport;
use App\Exports\LectureScheduleTemplate;
use App\Http\Controllers\Controller;
use App\Imports\LectureScheduleImport;
use App\Models\Auditorium;
use App\Models\CurriculumSubjectTeacher;
use App\Models\Group;
use App\Models\LectureSchedule;
use App\Models\LectureScheduleBatch;
use App\Services\LectureScheduleConflictService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LectureScheduleController extends Controller
{
    public function index()
    {
        $batches = LectureScheduleBatch::orderByDesc('created_at')->limit(10)->get();
        $activeBatch = $batches->first();

        return view('admin.lecture-schedule.index', compact('batches', 'activeBatch'));
    }

    /**
     * Excel faylni import qilish
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'semester_code' => 'nullable|string',
            'education_year' => 'nullable|string',
        ]);

        $file = $request->file('file');

        $user = auth()->user();
        $guard = auth()->guard('teacher')->check() ? 'teacher' : 'web';

        $batch = LectureScheduleBatch::create([
            'uploaded_by' => $user->id,
            'uploaded_by_guard' => $guard,
            'file_name' => $file->getClientOriginalName(),
            'semester_code' => $request->semester_code,
            'education_year' => $request->education_year,
        ]);

        $import = new LectureScheduleImport($batch);

        try {
            Excel::import($import, $file);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $batch->update(['status' => 'error']);
            $failures = collect($e->failures())->map(fn($f) => "Qator {$f->row()}: {$f->errors()[0]}")->toArray();
            return back()->with('error', 'Excel validatsiya xatosi')->with('import_errors', $failures);
        } catch (\Throwable $e) {
            $batch->update(['status' => 'error']);
            return back()->with('error', 'Import xatosi: ' . $e->getMessage());
        }

        $conflictService = new LectureScheduleConflictService();
        $conflictService->detectInternalConflicts($batch);

        $message = "{$import->importedCount} ta qator muvaffaqiyatli yuklandi.";
        if (count($import->errors) > 0) {
            $message .= ' ' . count($import->errors) . ' ta qatorda xatolik.';
        }

        return redirect()
            ->route(
                auth()->guard('teacher')->check() ? 'teacher.lecture-schedule.index' : 'admin.lecture-schedule.index',
                ['batch' => $batch->id]
            )
            ->with('success', $message)
            ->with('import_errors', collect($import->errors)->map(fn($e) => "Qator {$e['row']}: {$e['error']}")->toArray());
    }

    /**
     * AJAX: yuklangan jadval ma'lumotlari (grid uchun)
     */
    public function data(Request $request)
    {
        $batchId = $request->input('batch_id');
        if (!$batchId) {
            return response()->json(['items' => [], 'pairs' => []]);
        }

        $items = LectureSchedule::where('batch_id', $batchId)
            ->orderBy('week_day')
            ->orderBy('lesson_pair_code')
            ->get();

        $pairs = $items->unique('lesson_pair_code')
            ->sortBy('lesson_pair_code')
            ->map(fn($i) => [
                'code' => $i->lesson_pair_code,
                'name' => $i->lesson_pair_name,
                'start' => $i->lesson_pair_start_time,
                'end' => $i->lesson_pair_end_time,
            ])
            ->values();

        $grid = [];
        foreach ($items as $item) {
            $key = $item->week_day . '_' . $item->lesson_pair_code;
            if (!isset($grid[$key])) {
                $grid[$key] = [];
            }
            $grid[$key][] = [
                'id' => $item->id,
                'week_day' => $item->week_day,
                'lesson_pair_code' => $item->lesson_pair_code,
                'group_name' => $item->group_name,
                'group_id' => $item->group_id,
                'subject_name' => $item->subject_name,
                'subject_id' => $item->subject_id,
                'employee_name' => $item->employee_name,
                'employee_id' => $item->employee_id,
                'auditorium_name' => $item->auditorium_name,
                'training_type_name' => $item->training_type_name,
                'hemis_status' => $item->hemis_status,
                'hemis_diff' => $item->hemis_diff,
                'has_conflict' => $item->has_conflict,
                'conflict_details' => $item->conflict_details,
            ];
        }

        return response()->json([
            'items' => $grid,
            'pairs' => $pairs,
            'days' => LectureSchedule::WEEK_DAYS,
        ]);
    }

    /**
     * AJAX: Dars kartasini boshqa yacheykaga ko'chirish (drag-and-drop)
     */
    public function move(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'week_day' => 'required|integer|between:1,6',
            'lesson_pair_code' => 'required|string',
        ]);

        $item = LectureSchedule::findOrFail($request->id);
        $item->update([
            'week_day' => $request->week_day,
            'lesson_pair_code' => $request->lesson_pair_code,
            'hemis_status' => 'not_checked',
            'hemis_diff' => null,
        ]);

        // Konfliktlarni qayta tekshirish
        $service = new LectureScheduleConflictService();
        $service->resetAndRedetect($item->batch);

        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }

    /**
     * AJAX: Dars kartasini yangilash (inline edit)
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'subject_name' => 'nullable|string|max:255',
            'employee_name' => 'nullable|string|max:255',
            'auditorium_name' => 'nullable|string|max:255',
            'training_type_name' => 'nullable|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'group_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer',
        ]);

        $item = LectureSchedule::findOrFail($id);
        $item->update(array_merge(
            $request->only(['subject_name', 'employee_name', 'auditorium_name', 'training_type_name', 'group_name', 'group_id', 'subject_id', 'employee_id']),
            ['hemis_status' => 'not_checked', 'hemis_diff' => null]
        ));

        $service = new LectureScheduleConflictService();
        $service->resetAndRedetect($item->batch);

        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }

    /**
     * AJAX: Yangi dars kartasini qo'shish
     */
    public function store(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|integer|exists:lecture_schedule_batches,id',
            'week_day' => 'required|integer|between:1,6',
            'lesson_pair_code' => 'required|string',
            'subject_name' => 'required|string|max:255',
            'group_name' => 'required|string|max:255',
            'employee_name' => 'nullable|string|max:255',
            'auditorium_name' => 'nullable|string|max:255',
            'training_type_name' => 'nullable|string|max:255',
            'group_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer',
        ]);

        $item = LectureSchedule::create($request->only([
            'batch_id', 'week_day', 'lesson_pair_code',
            'subject_name', 'group_name', 'employee_name',
            'auditorium_name', 'training_type_name',
            'group_id', 'subject_id', 'employee_id',
        ]));

        $batch = LectureScheduleBatch::find($request->batch_id);
        $batch->increment('total_rows');

        $service = new LectureScheduleConflictService();
        $service->resetAndRedetect($batch);

        return response()->json(['success' => true, 'item' => $item]);
    }

    /**
     * AJAX: Dars kartasini o'chirish
     */
    public function destroyItem($id)
    {
        $item = LectureSchedule::findOrFail($id);
        $batch = $item->batch;
        $item->delete();

        $batch->decrement('total_rows');

        $service = new LectureScheduleConflictService();
        $service->resetAndRedetect($batch);

        return response()->json(['success' => true]);
    }

    /**
     * AJAX: Barcha active guruhlar ro'yxati
     */
    public function groups()
    {
        $groups = Group::where('active', true)
            ->orderBy('name')
            ->select('group_hemis_id', 'name')
            ->get()
            ->map(fn($g) => [
                'id' => $g->group_hemis_id,
                'name' => $g->name,
            ]);

        return response()->json(['groups' => $groups]);
    }

    /**
     * AJAX: Guruhga biriktirilgan fanlar
     */
    public function subjects(Request $request)
    {
        $request->validate(['group_id' => 'required|integer']);

        $subjects = CurriculumSubjectTeacher::where('group_id', $request->group_id)
            ->where('active', true)
            ->select('subject_id', 'subject_name')
            ->distinct()
            ->orderBy('subject_name')
            ->get()
            ->map(fn($s) => [
                'id' => $s->subject_id,
                'name' => $s->subject_name,
            ]);

        return response()->json(['subjects' => $subjects]);
    }

    /**
     * AJAX: Fan o'qituvchilari (guruh + fan + dars turi bo'yicha)
     */
    public function teachers(Request $request)
    {
        $request->validate([
            'group_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'training_type_name' => 'nullable|string',
        ]);

        $query = CurriculumSubjectTeacher::where('group_id', $request->group_id)
            ->where('subject_id', $request->subject_id)
            ->where('active', true);

        if ($request->filled('training_type_name')) {
            $query->where('training_type_name', $request->training_type_name);
        }

        $teachers = $query->select('employee_id', 'employee_name')
            ->distinct()
            ->orderBy('employee_name')
            ->get()
            ->map(fn($t) => [
                'id' => $t->employee_id,
                'name' => $t->employee_name,
            ]);

        return response()->json(['teachers' => $teachers]);
    }

    /**
     * AJAX: Dars turiga mos auditoriyalar
     */
    public function auditoriums(Request $request)
    {
        $request->validate(['training_type_name' => 'nullable|string']);

        $query = Auditorium::where('active', true);

        if ($request->filled('training_type_name')) {
            $typeMap = [
                "Ma'ruza"      => ['Лекцион', 'Lektsion', "Ma'ruza", 'Maruza'],
                'Amaliy'       => ['Амалий', 'Amaliy'],
                'Seminar'      => ['Семинар', 'Seminar'],
                'Laboratoriya' => ['Лаборатория', 'Laboratoriya'],
            ];

            $trainingType = $request->training_type_name;
            if (isset($typeMap[$trainingType])) {
                $query->where(function ($q) use ($typeMap, $trainingType) {
                    foreach ($typeMap[$trainingType] as $pattern) {
                        $q->orWhere('auditorium_type_name', 'LIKE', "%{$pattern}%");
                    }
                });
            }
        }

        $auditoriums = $query->orderBy('name')
            ->select('id', 'name', 'auditorium_type_name', 'volume')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->auditorium_type_name,
                'volume' => $a->volume,
            ]);

        return response()->json(['auditoriums' => $auditoriums]);
    }

    /**
     * AJAX: Hemis bilan solishtirish
     */
    public function compare(Request $request)
    {
        $batchId = $request->input('batch_id');
        $batch = LectureScheduleBatch::findOrFail($batchId);

        $service = new LectureScheduleConflictService();
        $result = $service->compareWithHemis($batch);

        return response()->json($result);
    }

    /**
     * AJAX: ichki konfliktlar
     */
    public function conflicts(Request $request)
    {
        $batchId = $request->input('batch_id');
        $batch = LectureScheduleBatch::findOrFail($batchId);

        $service = new LectureScheduleConflictService();
        $conflicts = $service->detectInternalConflicts($batch);

        return response()->json([
            'conflicts' => $conflicts,
            'count' => count($conflicts),
        ]);
    }

    /**
     * Batch ni o'chirish
     */
    public function destroy($id)
    {
        $batch = LectureScheduleBatch::findOrFail($id);
        $batch->delete();

        return back()->with('success', 'Jadval o\'chirildi.');
    }

    /**
     * Namuna Excel shablon
     */
    public function downloadTemplate()
    {
        return Excel::download(new LectureScheduleTemplate(), 'dars_jadvali_shablon.xlsx');
    }

    /**
     * Tahrirlangan jadvalni Excel ga eksport
     */
    public function export($id)
    {
        $batch = LectureScheduleBatch::findOrFail($id);
        $fileName = 'jadval_' . str_replace([' ', '.'], '_', $batch->file_name) . '_' . date('Y_m_d') . '.xlsx';

        return Excel::download(new LectureScheduleExport($batch), $fileName);
    }
}
