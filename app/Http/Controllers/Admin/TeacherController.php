<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Exports\TeacherExport;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Teacher;
use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        $query = Teacher::with('roles');

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('full_name', 'like', "%{$searchTerm}%")
                    ->orWhere('employee_id_number', 'like', "%{$searchTerm}%")
                    ->orWhere('department', 'like', "%{$searchTerm}%");
            });
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        if ($request->filled('staff_position')) {
            $query->where('staff_position', $request->staff_position);
        }

        if ($request->filled('role')) {
            $roleName = $request->role;
            $query->whereHas('roles', fn ($q) => $q->where('name', $roleName));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        } else {
            $query->where('is_active', true);
        }

        $teachers = $query->paginate(15)->withQueryString();

        $departments = Teacher::whereNotNull('department')->distinct()->pluck('department')->sort()->values();
        $positions = Teacher::whereNotNull('staff_position')->distinct()->pluck('staff_position')->sort()->values();
        $activeRoles = Role::where('guard_name', 'web')->pluck('name');

        return view('admin.teachers.index', compact('teachers', 'departments', 'positions', 'activeRoles'));
    }

    public function show(Teacher $teacher)
    {
        $departments = Department::where('structure_type_code', 11)->get();
        $roles = ProjectRole::staffRoles();
        return view('admin.teachers.show', compact('teacher', 'departments', 'roles'));
    }

    public function edit(Teacher $teacher)
    {
        $departments = Department::where('structure_type_code', 11)->get();
        $roles = ProjectRole::staffRoles();
        return view('admin.teachers.edit', compact('teacher', 'departments', 'roles'));
    }

    public function update(Request $request, Teacher $teacher)
    {
        $request->validate([
            'login' => 'required|string|max:255|unique:teachers,login,' . $teacher->id,
            'password' => 'nullable|string|min:6',
            'status' => 'required|boolean',
        ]);

        $teacher->login = $request->login;
        if ($request->filled('password')) {
            $teacher->password = Hash::make($request->password);
        }
        $teacher->status = $request->status;
        $teacher->save();

        return redirect()->route('admin.teachers.show', $teacher)->with('success', 'Xodim ma\'lumotlari yangilandi');
    }

    public function resetPassword(Teacher $teacher)
    {
        if (!$teacher->birth_date) {
            return redirect()->route('admin.teachers.show', $teacher)
                ->with('error', 'Xodimning tug\'ilgan sanasi mavjud emas, parolni tiklab bo\'lmaydi.');
        }

        $birthDate = Carbon::parse($teacher->birth_date);
        $newPassword = $birthDate->format('dmY'); // ddmmyyyy

        $teacher->password = Hash::make($newPassword);
        $teacher->must_change_password = true;
        $teacher->save();

        ActivityLogService::log('update', 'teacher', "Xodim paroli tiklandi: {$teacher->full_name}", $teacher);

        return redirect()->route('admin.teachers.show', $teacher)
            ->with('success', 'Parol tiklandi (' . $newPassword . '). Xodim keyingi kirishda parolni o\'zgartirishi kerak.');
    }

    public function updateRoles(Request $request, Teacher $teacher)
    {
        $validRoleValues = array_values(array_map(fn ($r) => $r->value, ProjectRole::staffRoles()));

        $roles = $request->input('roles', []);
        $isDean = in_array(ProjectRole::DEAN->value, $roles);

        $request->validate([
            'roles' => 'nullable|array',
            'roles.*' => 'in:' . implode(',', $validRoleValues),
            'dean_faculties' => [$isDean ? 'required' : 'nullable', 'array'],
            'dean_faculties.*' => 'exists:departments,department_hemis_id',
        ], [
            'dean_faculties.required' => 'Dekan roli uchun kamida bitta fakultetni tanlash majburiy.',
        ]);

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $oldRoles = $teacher->getRoleNames()->toArray();
        $teacher->syncRoles($roles);

        if ($isDean) {
            $teacher->deanFaculties()->sync($request->input('dean_faculties', []));
        } else {
            $teacher->deanFaculties()->detach();
        }

        ActivityLogService::log('update', 'teacher', "Xodim rollari yangilandi: {$teacher->full_name}", $teacher, [
            'roles' => $oldRoles,
        ], [
            'roles' => $roles,
        ]);

        return redirect()->route('admin.teachers.show', $teacher)->with('success', 'Rollar muvaffaqiyatli yangilandi');
    }

    public function updateContact(Request $request, Teacher $teacher)
    {
        $user = auth()->user();
        if (!$user->hasAnyRole(['superadmin', 'admin', 'kichik_admin'])) {
            abort(403, 'Sizda bu amalni bajarish huquqi yo\'q.');
        }

        $request->validate([
            'phone' => ['nullable', 'string', 'regex:/^\+\d{7,15}$/'],
            'telegram_username' => ['nullable', 'string', 'max:255'],
        ], [
            'phone.regex' => 'Telefon raqami noto\'g\'ri formatda. Masalan: +998901234567',
        ]);

        $teacher->phone = $request->phone;
        $teacher->telegram_username = $request->telegram_username;
        $teacher->save();

        return redirect()->route('admin.teachers.show', $teacher)->with('success', 'Aloqa ma\'lumotlari yangilandi');
    }

    public function exportExcel(Request $request)
    {
        ActivityLogService::log('export', 'teacher', 'Xodimlar ro\'yxati eksport qilindi');
        $export = new TeacherExport($request);
        $export->export();
    }

    public function importTeachers()
    {
        Artisan::call('import:teachers');
        ActivityLogService::log('import', 'teacher', 'Xodimlar HEMIS dan import qilindi');
        return redirect()->back()->with('success', 'Xodimlar import qilindi');
    }
}
