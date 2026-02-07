<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Teacher;
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

        return redirect()->route('admin.teachers.show', $teacher)
            ->with('success', 'Parol tiklandi (' . $newPassword . '). Xodim keyingi kirishda parolni o\'zgartirishi kerak.');
    }

    public function updateRoles(Request $request, Teacher $teacher)
    {
        $validRoleValues = array_values(array_map(fn ($r) => $r->value, ProjectRole::staffRoles()));

        $request->validate([
            'roles' => 'nullable|array',
            'roles.*' => 'in:' . implode(',', $validRoleValues),
            'department_hemis_id' => 'nullable|exists:departments,department_hemis_id',
        ]);

        $roles = $request->input('roles', []);

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $teacher->syncRoles($roles);

        $teacher->department_hemis_id = in_array(ProjectRole::DEAN->value, $roles)
            ? $request->department_hemis_id
            : null;
        $teacher->save();

        return redirect()->route('admin.teachers.show', $teacher)->with('success', 'Rollar muvaffaqiyatli yangilandi');
    }

    public function importTeachers()
    {
        Artisan::call('import:teachers');
        return redirect()->back()->with('success', 'Xodimlar import qilindi');
    }
}
