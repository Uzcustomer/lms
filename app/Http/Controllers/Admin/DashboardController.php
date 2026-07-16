<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportSchedulesPartiallyJob;
use App\Models\Deadline;
use App\Models\Setting;
use App\Models\Student;
use App\Services\ActivityLogService;
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
        $spravkaDays = Setting::get('spravka_deadline_days', 10);
        $mtDeadlineType = Setting::get('mt_deadline_type', 'before_last');
        $mtDeadlineTime = Setting::get('mt_deadline_time', '17:00');
        $mtMaxResubmissions = Setting::get('mt_max_resubmissions', 3);

        return view('admin.deadlines.show', compact('deadlines', 'spravkaDays', 'mtDeadlineType', 'mtDeadlineTime', 'mtMaxResubmissions'));
    }

    public function editDeadlines(): View
    {
        // $levels = Student::distinct()->get(['level_code', 'level_name']);
        $deadlines = Deadline::get();
        $spravkaDays = Setting::get('spravka_deadline_days', 10);
        $mtDeadlineType = Setting::get('mt_deadline_type', 'before_last');
        $mtDeadlineTime = Setting::get('mt_deadline_time', '17:00');
        $mtMaxResubmissions = Setting::get('mt_max_resubmissions', 3);

        return view('admin.deadlines.edit', compact('deadlines', 'spravkaDays', 'mtDeadlineType', 'mtDeadlineTime', 'mtMaxResubmissions'));
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

        if ($request->filled('mt_deadline_type')) {
            Setting::set('mt_deadline_type', $request->mt_deadline_type);
        }

        if ($request->filled('mt_deadline_time')) {
            Setting::set('mt_deadline_time', $request->mt_deadline_time);
        }

        if ($request->filled('mt_max_resubmissions')) {
            Setting::set('mt_max_resubmissions', (int) $request->mt_max_resubmissions);
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

        $userName = Auth::user()?->name ?? Auth::user()?->full_name ?? '';

        ActivityLogService::log('import', 'schedule', "Jadvallar sinxronizatsiyasi boshlandi: {$data['start_date']} — {$data['end_date']}");
        ImportSchedulesPartiallyJob::dispatch(
            $data['start_date'],
            $data['end_date'],
            $userName
        );
//            ->onQueue('imports');

        return back()->with('success', 'Sinxronizatsiya boshlandi, bu jarayon fon rejimida ishlaydi. Jarayon yakuniga yetmaguncha Baholarni ko\'rish va yuklash jarayonida muammolar bo\'lishi mumkun. Jarayon vaqti tanlangan vaqt oralig\'iga qarab farq qiladi!
        ');
    }

    public function importCurricula(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan O'quv rejalar sinxronizatsiyasi boshlandi");
        ActivityLogService::log('import', 'curriculum', "O'quv rejalar sinxronizatsiyasi boshlandi");
        Artisan::queue('import:curricula');
        return back()->with('success', 'O\'quv rejalarini import qilish boshlandi (fon rejimida).');
    }

    public function importCurriculumSubjects(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan O'quv reja fanlari sinxronizatsiyasi boshlandi");
        ActivityLogService::log('import', 'curriculum_subject', "O'quv reja fanlari sinxronizatsiyasi boshlandi");
        Artisan::queue('import:curriculum-subjects');
        return back()->with('success', 'O\'quv reja fanlarini import qilish boshlandi (fon rejimida).');
    }

    public function importGroups(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan Guruhlar sinxronizatsiyasi boshlandi");
        ActivityLogService::log('import', 'group', 'Guruhlar sinxronizatsiyasi boshlandi');
        Artisan::queue('import:groups');
        return back()->with('success', 'Guruhlarni import qilish boshlandi (fon rejimida).');
    }

    public function importSemesters(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan Semestrlar sinxronizatsiyasi boshlandi");
        ActivityLogService::log('import', 'semester', 'Semestrlar sinxronizatsiyasi boshlandi');
        Artisan::queue('import:semesters');
        return back()->with('success', 'Semestrlarni import qilish boshlandi (fon rejimida).');
    }

    public function importSpecialtiesDepartments(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan Mutaxassislik/Kafedralar sinxronizatsiyasi boshlandi");
        ActivityLogService::log('import', 'department', 'Mutaxassislik/Kafedralar sinxronizatsiyasi boshlandi');
        Artisan::queue('import:specialties-departments');
        return back()->with('success', 'Mutaxassislik va kafedralarni import qilish boshlandi (fon rejimida).');
    }

    public function importStudents(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan Talabalar sinxronizatsiyasi boshlandi");
        ActivityLogService::log('import', 'student', 'Talabalar sinxronizatsiyasi boshlandi');
        Artisan::queue('students:import');
        return back()->with('success', 'Talabalarni import qilish boshlandi (fon rejimida).');
    }

    public function importTeachers(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan O'qituvchilar sinxronizatsiyasi boshlandi");
        ActivityLogService::log('import', 'teacher', "O'qituvchilar sinxronizatsiyasi boshlandi");
        Artisan::queue('import:teachers');
        return back()->with('success', 'O\'qituvchilarni import qilish boshlandi (fon rejimida).');
    }

    public function importAttendanceControls(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan Davomat nazorati sinxronizatsiyasi boshlandi");
        ActivityLogService::log('import', 'attendance', 'Davomat nazorati sinxronizatsiyasi boshlandi');
        Artisan::queue('import:attendance-controls');
        return back()->with('success', 'Davomat nazorati import qilish boshlandi (fon rejimida).');
    }

    public function importCurriculumSubjectTeachers(): RedirectResponse
    {
        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan Fan-o'qituvchi biriktirishlar sinxronizatsiyasi boshlandi");
        ActivityLogService::log('import', 'curriculum_subject_teacher', "Fan-o'qituvchi biriktirishlar sinxronizatsiyasi boshlandi");
        Artisan::queue('import:curriculum-subject-teachers');
        return back()->with('success', 'Fan-o\'qituvchi biriktirishlarni import qilish boshlandi (fon rejimida).');
    }

    public function importAcademicRecords(): RedirectResponse
    {
        // Allaqachon ketayotgan bo'lsa bloklash
        if (\Illuminate\Support\Facades\Cache::get('academic_import_lock')) {
            return back()->with('error', 'Import allaqachon ketayapti. Tugashini kuting.');
        }

        $this->telegram->notify("👤 {$this->getUserInfo()} tomonidan Akkreditatsiya (academic_records) sinxronizatsiyasi boshlandi");
        ActivityLogService::log('import', 'academic_record', 'Akkreditatsiya sinxronizatsiyasi boshlandi');
        \Illuminate\Support\Facades\Cache::put('academic_import_progress', [
            'status'  => 'queued',
            'percent' => 0,
            'started_at' => now()->toDateTimeString(),
        ], 3600);

        \App\Jobs\ImportAcademicRecordsJob::dispatch();

        return back()->with('success', 'Akkreditatsiya (talaba baholari) importi boshlandi (fon rejimida). 359k+ yozuv, bir necha daqiqa olishi mumkin.');
    }

    public function academicRecordsProgress(): \Illuminate\Http\JsonResponse
    {
        $progress = \Illuminate\Support\Facades\Cache::get('academic_import_progress') ?: ['status' => 'idle'];
        $progress['last_synced_at'] = \Illuminate\Support\Facades\Cache::get('academic_records_last_synced_at')
            ?? \Illuminate\Support\Facades\Cache::remember(
                'academic_records_last_synced_fallback',
                120,
                fn () => \Illuminate\Support\Facades\DB::table('academic_records')->max('updated_at')
            );
        return response()->json($progress);
    }

    public function clearAcademicImportLock(): \Illuminate\Http\RedirectResponse
    {
        \Illuminate\Support\Facades\Cache::forget('academic_import_lock');
        \Illuminate\Support\Facades\Cache::forget('academic_import_progress');
        $this->telegram->notify("🔓 {$this->getUserInfo()} tomonidan import lock tozalandi");
        return back()->with('success', 'Import lock tozalandi. Endi import boshlasa bo\'ladi.');
    }
}
