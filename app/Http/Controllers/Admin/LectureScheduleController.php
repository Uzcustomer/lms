<?php

namespace App\Http\Controllers\Admin;

use App\Exports\LectureScheduleTemplate;
use App\Http\Controllers\Controller;
use App\Imports\LectureScheduleImport;
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

        // Batch yaratish
        $user = auth()->user();
        $guard = auth()->guard('teacher')->check() ? 'teacher' : 'web';

        $batch = LectureScheduleBatch::create([
            'uploaded_by' => $user->id,
            'uploaded_by_guard' => $guard,
            'file_name' => $file->getClientOriginalName(),
            'semester_code' => $request->semester_code,
            'education_year' => $request->education_year,
        ]);

        // Import
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

        // Ichki konfliktlarni aniqlash
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

        // Unique juftliklar
        $pairs = $items->unique('lesson_pair_code')
            ->sortBy('lesson_pair_code')
            ->map(fn($i) => [
                'code' => $i->lesson_pair_code,
                'name' => $i->lesson_pair_name,
                'start' => $i->lesson_pair_start_time,
                'end' => $i->lesson_pair_end_time,
            ])
            ->values();

        // Grid formatiga o'tkazish
        $grid = [];
        foreach ($items as $item) {
            $key = $item->week_day . '_' . $item->lesson_pair_code;
            if (!isset($grid[$key])) {
                $grid[$key] = [];
            }
            $grid[$key][] = [
                'id' => $item->id,
                'group_name' => $item->group_name,
                'subject_name' => $item->subject_name,
                'employee_name' => $item->employee_name,
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
        $batch->delete(); // cascade orqali items ham o'chadi

        return back()->with('success', 'Jadval o\'chirildi.');
    }

    /**
     * Namuna Excel shablon
     */
    public function downloadTemplate()
    {
        return Excel::download(new LectureScheduleTemplate(), 'dars_jadvali_shablon.xlsx');
    }
}
