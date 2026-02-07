<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

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

        $teachers = $query->paginate(15);

        return view('admin.teachers.index', compact('teachers'));
    }

    public function show(Teacher $teacher)
    {
        $departments = Department::where('structure_type_code', 11)->get();
        $roles = ProjectRole::teacherRoles();
        return view('admin.teachers.show', compact('teacher', 'departments', 'roles'));
    }

    public function edit(Teacher $teacher)
    {
        $departments = Department::where('structure_type_code', 11)->get();
        $roles = ProjectRole::teacherRoles();
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

    public function updateRoles(Request $request, Teacher $teacher)
    {
        $validRoleValues = array_map(fn ($r) => $r->value, ProjectRole::teacherRoles());

        $request->validate([
            'roles' => 'nullable|array',
            'roles.*' => 'in:' . implode(',', $validRoleValues),
            'department_hemis_id' => 'nullable|exists:departments,department_hemis_id',
        ]);

        $roles = $request->input('roles', []);

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
