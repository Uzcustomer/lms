<?php

namespace App\Http\Controllers\Teacher\AcademicDept;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\RetakeApplicationWindow;
use App\Models\RetakeWindowSession;
use App\Models\Specialty;
use App\Models\Teacher;
use App\Services\Retake\RetakeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RetakeWindowSessionController extends Controller
{
    /**
     * Sessiyalar ro'yxati.
     */
    public function index()
    {
        $this->authorizeAccess();

        $sessions = RetakeWindowSession::query()
            ->withCount('windows')
            ->orderByDesc('is_closed')
            ->orderByDesc('created_at')
            ->orderBy('is_closed') // ochiqlar yuqorida
            ->get()
            ->sortBy(fn ($s) => [$s->is_closed ? 1 : 0, -$s->id])
            ->values();

        return view('teacher.academic-dept.retake-sessions.index', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * Yangi sessiya yaratish.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        /** @var Teacher $user */
        $user = RetakeAccess::currentStaff();

        RetakeWindowSession::create([
            'name' => $data['name'],
            'created_by_user_id' => $user->id,
            'created_by_name' => $user->full_name,
        ]);

        return redirect()->route('admin.retake-sessions.index')
            ->with('success', __('Sessiya yaratildi'));
    }

    /**
     * Sessiyani yopish — ichidagi pending arizalar avtomatik rad etilmaydi
     * (alohida cron komandasi tomonidan bajariladi).
     */
    public function close(int $sessionId): RedirectResponse
    {
        $this->authorizeAccess();

        $session = RetakeWindowSession::findOrFail($sessionId);

        if ($session->is_closed) {
            return redirect()->back()->withErrors(['session' => 'Sessiya allaqachon yopilgan']);
        }

        $session->update([
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        return redirect()->route('admin.retake-sessions.index')
            ->with('success', __('Sessiya yopildi'));
    }

    /**
     * Sessiya ichidagi oynalar — bu yerda yangi oyna ham yaratiladi.
     */
    public function show(int $sessionId, Request $request)
    {
        $this->authorizeAccess();

        $session = RetakeWindowSession::findOrFail($sessionId);

        $statusFilter = $request->input('status', 'all');
        $departmentId = $request->input('department_id');
        $levelCode = $request->input('level_code');

        $windowsQuery = RetakeApplicationWindow::query()
            ->where('session_id', $session->id)
            ->withCount('applicationGroups as applications_count')
            ->orderByDesc('start_date');

        if ($statusFilter !== 'all') {
            $today = now()->toDateString();
            match ($statusFilter) {
                'upcoming' => $windowsQuery->whereDate('start_date', '>', $today),
                'active' => $windowsQuery->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today),
                'closed' => $windowsQuery->whereDate('end_date', '<', $today),
                default => null,
            };
        }

        if ($departmentId) {
            $specialtyIds = Specialty::where('department_hemis_id', $departmentId)
                ->pluck('specialty_hemis_id');
            $windowsQuery->whereIn('specialty_id', $specialtyIds);
        }

        if ($levelCode) {
            $windowsQuery->where('level_code', $levelCode);
        }

        $windows = $windowsQuery->paginate(30)->withQueryString();

        $specialtyToFaculty = Specialty::query()
            ->join('departments', 'departments.department_hemis_id', '=', 'specialties.department_hemis_id')
            ->select('specialties.specialty_hemis_id as sp_hemis_id', 'departments.name as faculty_name')
            ->pluck('faculty_name', 'sp_hemis_id');

        $departments = Department::where('structure_type_code', 11)
            ->orderBy('name')
            ->get(['department_hemis_id', 'name']);
        $specialties = Specialty::orderBy('name')
            ->get(['id', 'specialty_hemis_id', 'name', 'department_hemis_id']);

        return view('teacher.academic-dept.retake-sessions.show', [
            'session' => $session,
            'windows' => $windows,
            'specialtyToFaculty' => $specialtyToFaculty,
            'departments' => $departments,
            'specialties' => $specialties,
            'levels' => $this->levels(),
            'semesters' => $this->semesters(),
            'statusFilter' => $statusFilter,
            'departmentIdFilter' => $departmentId,
            'levelCodeFilter' => $levelCode,
            'canOverride' => RetakeAccess::canOverride(RetakeAccess::currentStaff()),
        ]);
    }

    private function authorizeAccess(): void
    {
        if (!RetakeAccess::canManageAcademicDept(RetakeAccess::currentStaff())) {
            abort(403, 'Sizda qayta o\'qish sessiyalarini boshqarish ruxsati yo\'q');
        }
    }

    private function levels(): array
    {
        return [
            ['code' => '11', 'name' => '1-kurs'],
            ['code' => '12', 'name' => '2-kurs'],
            ['code' => '13', 'name' => '3-kurs'],
            ['code' => '14', 'name' => '4-kurs'],
            ['code' => '15', 'name' => '5-kurs'],
            ['code' => '16', 'name' => '6-kurs'],
        ];
    }

    private function semesters(): array
    {
        $items = [];
        for ($i = 1; $i <= 12; $i++) {
            $items[] = ['code' => (string) $i, 'name' => $i . '-semestr'];
        }
        return $items;
    }
}
