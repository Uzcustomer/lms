<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportSchedulesPartiallyJob;
use App\Models\Deadline;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard');
    }


    public function showDeadlines(): View
    {
        $deadlines = Deadline::with('level')->get();

        return view('admin.deadlines.show', compact('deadlines'));
    }

    public function editDeadlines(): View
    {
        // $levels = Student::distinct()->get(['level_code', 'level_name']);
        $deadlines = Deadline::get();

        return view('admin.deadlines.edit', compact('deadlines'));
    }

    // Update deadlines
    public function updateDeadlines(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'deadlines' => 'required|array',
            'deadlines.*.days' => 'required|integer|min:1',
            'deadlines.*.joriy' => 'required|integer|min:1',
            'deadlines.*.mustaqil_talim' => 'required|integer|min:1',
        ]);

        foreach ($validated['deadlines'] as $levelCode => $deadlineData) {
            Deadline::updateOrCreate(
                ['level_code' => $levelCode],
                [
                    'deadline_days' => $deadlineData['days'],
                    'joriy' => $deadlineData['joriy'],
                    'mustaqil_talim' => $deadlineData['mustaqil_talim'],
                ]
            );
        }

        return redirect()->route('admin.deadlines')->with('success', 'Deadlinelar muvafaqiyatli yangilandi!');
    }

    public function indexSynchronizes(): View
    {
        return view('admin.synchronizes.index');
    }

    // Update deadlines
    public function importSchedulesPartialy(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        ImportSchedulesPartiallyJob::dispatch(
            $data['start_date'],
            $data['end_date']
        );
//            ->onQueue('imports');

        return back()->with('success', 'Sinxronizatsiya boshlandi, bu jarayon fon rejimida ishlaydi. Jarayon yakuniga yetmaguncha Baholarni ko\'rish va yuklash jarayonida muammolar bo\'lishi mumkun. Jarayon vaqti tanlangan vaqt oralig\'iga qarab farq qiladi!
        ');
    }
}
