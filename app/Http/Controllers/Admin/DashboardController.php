<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportSchedulesPartiallyJob;
use App\Models\Deadline;
use App\Models\Setting;
use App\Models\Student;
use App\Services\TelegramService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        protected TelegramService $telegram
    ) {}

    protected function getUserInfo(): string
    {
        $user = Auth::user();
        $name = $user->name ?? $user->full_name ?? 'Noma\'lum';
        $role = $user->getRoleNames()->first() ?? 'user';
        return "{$name} ({$role})";
    }
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
        $spravkaDays = Setting::get('spravka_deadline_days', 10);

        return view('admin.deadlines.edit', compact('deadlines', 'spravkaDays'));
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

        if ($request->filled('spravka_deadline_days')) {
            Setting::set('spravka_deadline_days', (int) $request->spravka_deadline_days);
        }

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

        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan Jadvallar sinxronizatsiyasi boshlandi ({$data['start_date']} — {$data['end_date']})");

        ImportSchedulesPartiallyJob::dispatch(
            $data['start_date'],
            $data['end_date']
        );
//            ->onQueue('imports');

        return back()->with('success', 'Sinxronizatsiya boshlandi, bu jarayon fon rejimida ishlaydi. Jarayon yakuniga yetmaguncha Baholarni ko\'rish va yuklash jarayonida muammolar bo\'lishi mumkun. Jarayon vaqti tanlangan vaqt oralig\'iga qarab farq qiladi!
        ');
    }

    public function importCurricula(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan O'quv rejalar sinxronizatsiyasi boshlandi");
        Artisan::queue('import:curricula');
        return back()->with('success', 'O\'quv rejalarini import qilish boshlandi (fon rejimida).');
    }

    public function importCurriculumSubjects(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan O'quv reja fanlari sinxronizatsiyasi boshlandi");
        Artisan::queue('import:curriculum-subjects');
        return back()->with('success', 'O\'quv reja fanlarini import qilish boshlandi (fon rejimida).');
    }

    public function importGroups(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan Guruhlar sinxronizatsiyasi boshlandi");
        Artisan::queue('import:groups');
        return back()->with('success', 'Guruhlarni import qilish boshlandi (fon rejimida).');
    }

    public function importSemesters(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan Semestrlar sinxronizatsiyasi boshlandi");
        Artisan::queue('import:semesters');
        return back()->with('success', 'Semestrlarni import qilish boshlandi (fon rejimida).');
    }

    public function importSpecialtiesDepartments(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan Mutaxassislik/Kafedralar sinxronizatsiyasi boshlandi");
        Artisan::queue('import:specialties-departments');
        return back()->with('success', 'Mutaxassislik va kafedralarni import qilish boshlandi (fon rejimida).');
    }

    public function importStudents(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan Talabalar sinxronizatsiyasi boshlandi");
        Artisan::queue('students:import');
        return back()->with('success', 'Talabalarni import qilish boshlandi (fon rejimida).');
    }

    public function importTeachers(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan O'qituvchilar sinxronizatsiyasi boshlandi");
        Artisan::queue('import:teachers');
        return back()->with('success', 'O\'qituvchilarni import qilish boshlandi (fon rejimida).');
    }
}
