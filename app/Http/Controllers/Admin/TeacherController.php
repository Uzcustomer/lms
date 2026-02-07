<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Models\CurriculumWeek;
use App\Models\Department;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        $query = Teacher::query();

        if ($request->has('search')) {
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

    public function edit(Teacher $teacher)
    {
        $departments = Department::where('structure_type_code', 11)->get();
        $roles = ProjectRole::teacherRoles();
        return view('admin.teachers.edit', compact('teacher', 'departments', 'roles'));
    }

    public function update(Request $request, Teacher $teacher)
    {
        $validRoleValues = array_map(fn ($r) => $r->value, ProjectRole::teacherRoles());

        $request->validate([
            'login' => 'required|string|max:255|unique:teachers,login,' . $teacher->id,
            'password' => 'nullable|string|min:6',
            'roles' => 'required|array|min:1',
            'roles.*' => 'in:' . implode(',', $validRoleValues),
            'department_hemis_id' => 'nullable|exists:departments,department_hemis_id',
            'status' => 'required|boolean',
        ]);

        $teacher->login = $request->login;
        if ($request->filled('password')) {
            $teacher->password = Hash::make($request->password);
        }
        $teacher->department_hemis_id = in_array(ProjectRole::DEAN->value, $request->roles)
            ? $request->department_hemis_id
            : null;
        $teacher->status = $request->status;

        $teacher->save();
        $teacher->syncRoles($request->roles);

        return redirect()->route('admin.teachers.index')->with('success', 'O\'qituvchi ma\'lumotlari yangilandi');
    }

    public function importTeachers()
    {
        Artisan::call('import:teachers');
        return redirect()->back()->with('success', 'O\'qituvchilar import qilindi');
    }


}
