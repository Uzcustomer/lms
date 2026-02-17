<?php

namespace App\Http\Controllers\Admin;

use App\Exports\LectureScheduleExport;
use App\Exports\LectureScheduleGridExport;
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

        $query = LectureSchedule::where('batch_id', $batchId)
            ->orderBy('week_day')
            ->orderBy('lesson_pair_code');

        $items = $query->get();
        $totalBefore = $items->count();

        // Hafta filtri: faqat tanlangan haftaga tegishli darslarni ko'rsatish
        $week = $request->input('week');
        if ($week) {
            $week = (int) $week;
            $items = $items->filter(function ($item) use ($week) {
                // Juft/toq hafta tekshiruvi
                $parity = mb_strtolower(trim($item->week_parity ?? ''));
                if ($parity === 'juft' && $week % 2 !== 0) {
                    return false;
                }
                if ($parity === 'toq' && $week % 2 !== 1) {
                    return false;
                }

                // weeks bo'sh bo'lsa — barcha haftalarda ko'rinadi
                if (empty($item->weeks)) {
                    return true;
                }

                return $this->weekInRange($week, $item->weeks, $parity);
            })->values();
        }

        $pairTimes = LectureSchedule::PAIR_TIMES;

        $pairs = $items->unique('lesson_pair_code')
            ->sortBy('lesson_pair_code')
            ->map(function ($i) use ($pairTimes) {
                $code = (int) $i->lesson_pair_code;
                $fallback = $pairTimes[$code] ?? null;
                $start = $i->lesson_pair_start_time;
                $end = $i->lesson_pair_end_time;

                if (empty($start) && $fallback) {
                    $start = $fallback['start'] . ':00';
                }
                if (empty($end) && $fallback) {
                    $end = $fallback['end'] . ':00';
                }

                return [
                    'code' => $i->lesson_pair_code,
                    'name' => $i->lesson_pair_name,
                    'start' => $start,
                    'end' => $end,
                ];
            })
            ->values();

        $grid = [];
        $groupSourceSeen = [];
        $dedupSkipped = 0;
        foreach ($items as $item) {
            $key = $item->week_day . '_' . $item->lesson_pair_code;
            if (!isset($grid[$key])) {
                $grid[$key] = [];
            }

            // Bir xil group_source + auditoriya bo'lsa, faqat birinchisini ko'rsatamiz
            // "Barchasi" rejimida (week filtrsiz) juft/toq alohida ko'rsatilishi kerak
            if ($item->group_source) {
                $gsKey = $key . '|' . $item->group_source . '|' . ($item->auditorium_name ?? '');
                if (!$week && $item->week_parity) {
                    $gsKey .= '|' . $item->week_parity;
                }
                if (isset($groupSourceSeen[$gsKey])) {
                    $dedupSkipped++;
                    continue;
                }
                $groupSourceSeen[$gsKey] = true;
            }

            $grid[$key][] = [
                'id' => $item->id,
                'week_day' => $item->week_day,
                'lesson_pair_code' => $item->lesson_pair_code,
                'group_name' => $item->group_name,
                'group_source' => $item->group_source,
                'subject_name' => $item->subject_name,
                'employee_name' => $item->employee_name,
                'auditorium_name' => $item->auditorium_name,
                'floor' => $item->floor,
                'building_name' => $item->building_name,
                'training_type_name' => $item->training_type_name,
                'weeks' => $item->weeks,
                'week_parity' => $item->week_parity,
                'hemis_status' => $item->hemis_status,
                'hemis_diff' => $item->hemis_diff,
                'has_conflict' => $item->has_conflict,
                'conflict_details' => $item->conflict_details,
            ];
        }

        // Xonalar kesimida tabi uchun: barcha batch dagi xonalarni qaytarish (haftaga bog'liq emas)
        $allRooms = LectureSchedule::where('batch_id', $batchId)
            ->whereNotNull('auditorium_name')
            ->where('auditorium_name', '!=', '')
            ->distinct()
            ->orderBy('auditorium_name')
            ->pluck('auditorium_name')
            ->values();

        // DEBUG: grid ichidagi card'larni sanash va log'ga yozish
        $totalCards = 0;
        foreach ($grid as $cellKey => $cards) {
            $totalCards += count($cards);
        }
        \Log::info("=== DATA DEBUG: batch={$batchId}, week={$week}, DB_items={$totalBefore}, after_filter={$items->count()}, grid_cards={$totalCards}, dedup_skipped={$dedupSkipped} ===");

        if (isset($grid['1_1'])) {
            \Log::info("Cell 1_1 (Dushanba, 1-juftlik): " . count($grid['1_1']) . " ta card");
            foreach ($grid['1_1'] as $card) {
                \Log::info("  - id={$card['id']} fan={$card['subject_name']} potok={$card['group_source']} guruh={$card['group_name']} xona={$card['auditorium_name']} parity={$card['week_parity']}");
            }
        }

        return response()->json([
            'items' => $grid,
            'pairs' => $pairs,
            'days' => LectureSchedule::WEEK_DAYS,
            'all_rooms' => $allRooms,
            '_debug' => [
                'total_db' => $totalBefore,
                'after_filter' => $items->count(),
                'grid_cards' => $totalCards,
                'dedup_skipped' => $dedupSkipped,
                'week' => $week,
            ],
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
            'group_source' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:50',
            'building_name' => 'nullable|string|max:255',
            'weeks' => 'nullable|string|max:100',
            'week_parity' => 'nullable|string|max:20',
        ]);

        $item = LectureSchedule::findOrFail($id);
        $item->update(array_merge(
            $request->only([
                'subject_name', 'employee_name', 'auditorium_name',
                'training_type_name', 'group_name',
                'group_source', 'floor', 'building_name', 'weeks', 'week_parity',
            ]),
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
            'group_source' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:50',
            'building_name' => 'nullable|string|max:255',
            'weeks' => 'nullable|string|max:100',
            'week_parity' => 'nullable|string|max:20',
        ]);

        $item = LectureSchedule::create($request->only([
            'batch_id', 'week_day', 'lesson_pair_code',
            'subject_name', 'group_name', 'employee_name',
            'auditorium_name', 'training_type_name',
            'group_source', 'floor', 'building_name', 'weeks', 'week_parity',
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
     * AJAX: O'quv reja bilan solishtirish
     */
    public function compareCurriculum(Request $request)
    {
        $batchId = $request->input('batch_id');
        $batch = LectureScheduleBatch::findOrFail($batchId);

        $service = new LectureScheduleConflictService();
        $result = $service->compareWithCurriculum($batch);

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

    /**
     * Jadval ko'rinishida Excel ga eksport
     */
    public function exportGrid(Request $request, $id)
    {
        $batch = LectureScheduleBatch::findOrFail($id);
        $week = $request->input('week') ? (int) $request->input('week') : null;
        $weekStr = $week ? "hafta_{$week}" : 'barchasi';
        $fileName = 'jadval_grid_' . str_replace([' ', '.'], '_', $batch->file_name) . "_{$weekStr}_" . date('Y_m_d') . '.xlsx';

        return Excel::download(new LectureScheduleGridExport($batch, $week), $fileName);
    }

    /**
     * Chop etish uchun HTML ko'rinishda jadval (PDF o'rniga)
     */
    public function exportPdf(Request $request, $id)
    {
        $batch = LectureScheduleBatch::findOrFail($id);
        $week = $request->input('week') ? (int) $request->input('week') : null;

        $items = $batch->items()
            ->orderBy('week_day')
            ->orderBy('lesson_pair_code')
            ->get();

        if ($week) {
            $items = $items->filter(function ($item) use ($week) {
                $parity = mb_strtolower(trim($item->week_parity ?? ''));
                if ($parity === 'juft' && $week % 2 !== 0) return false;
                if ($parity === 'toq' && $week % 2 !== 1) return false;
                if (empty($item->weeks)) return true;
                return $this->weekInRange($week, $item->weeks, $parity);
            });
        }

        $days = LectureSchedule::WEEK_DAYS;
        $pairTimes = LectureSchedule::PAIR_TIMES;
        $pairCodes = $items->pluck('lesson_pair_code')->unique()->sort()->values()->toArray();

        // Grid tuzish
        $grid = [];
        $groupSourceSeen = [];
        foreach ($items as $item) {
            $key = $item->week_day . '_' . $item->lesson_pair_code;
            if ($item->group_source) {
                $gsKey = $key . '|' . $item->group_source . '|' . ($item->auditorium_name ?? '');
                if (isset($groupSourceSeen[$gsKey])) continue;
                $groupSourceSeen[$gsKey] = true;
            }
            $grid[$key][] = $item;
        }

        $weekLabel = $week ? "{$week}-hafta" : 'Barcha haftalar';

        // Hafta label closure
        $weekLabelFn = function ($card) {
            $parity = mb_strtolower($card->week_parity ?? '');
            $weeks = $card->weeks;
            if (!$parity && !$weeks) return '';
            if ($parity === 'juft') return 'J';
            if ($parity === 'toq') return 'T';
            if ($weeks) return "1-{$weeks}";
            return '';
        };

        return view('admin.lecture-schedule.print', compact(
            'batch', 'days', 'pairTimes', 'pairCodes', 'grid', 'weekLabel', 'weekLabelFn'
        ));
    }

    /**
     * Hafta raqami berilgan oraliqqa kirishini tekshirish.
     *
     * weeks maydoni = darslar soni (semestr davomida necha marta dars bo'ladi).
     * Agar paritet berilgan bo'lsa, darslar faqat juft yoki toq haftalarda bo'ladi:
     *   - darslar_soni=3, juft  → 2, 4, 6 haftalar (max_week = min(3*2, 15) = 6)
     *   - darslar_soni=3, toq   → 1, 3, 5 haftalar (max_week = min(3*2-1, 15) = 5)
     *   - darslar_soni=15, toq  → 1, 3, 5,...,15    (max_week = min(29, 15) = 15)
     *   - darslar_soni=6, bo'sh → 1..6 haftalar     (max_week = 6)
     *
     * Formatlar: "1-8", "1,3,5,7", yoki bitta raqam "6" (darslar soni)
     */
    private function weekInRange(int $week, string $weeksStr, string $parity = ''): bool
    {
        $maxSemesterWeek = 15; // Semestr maksimal hafta soni
        $weeksStr = trim($weeksStr);

        // "1-8" formatida oraliq
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $weeksStr, $m)) {
            return $week >= (int) $m[1] && $week <= (int) $m[2];
        }

        // "1,3,5,7" formatida ro'yxat
        if (str_contains($weeksStr, ',')) {
            $weekList = array_map('intval', array_map('trim', explode(',', $weeksStr)));
            return in_array($week, $weekList);
        }

        // Bitta raqam = darslar soni
        // Paritetga qarab haqiqiy oxirgi haftani hisoblash, lekin semestrdan oshmasin
        if (is_numeric($weeksStr)) {
            $lessonCount = (int) $weeksStr;
            if ($parity === 'juft') {
                $maxWeek = min($lessonCount * 2, $maxSemesterWeek);
            } elseif ($parity === 'toq') {
                $maxWeek = min($lessonCount * 2 - 1, $maxSemesterWeek);
            } else {
                $maxWeek = min($lessonCount, $maxSemesterWeek);
            }
            return $week >= 1 && $week <= $maxWeek;
        }

        // Boshqa format — ko'rsatamiz (xavfsiz tarafdan)
        return true;
    }
}
