<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Exports\TeacherExport;
use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\Semester;
use App\Models\Teacher;
use App\Services\ActivityLogService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        try {
            $teacher->load('responsibleSubjects');
        } catch (\Exception $e) {
            // teacher_responsible_subjects jadvali mavjud bo'lmasa
        }
        $departments = Department::where('structure_type_code', '11')
            ->where('active', true)
            ->orderBy('name')
            ->get();
        $roles = ProjectRole::staffRoles();
        return view('admin.teachers.show', compact('teacher', 'departments', 'roles'));
    }

    public function edit(Teacher $teacher)
    {
        $departments = Department::where('structure_type_code', '11')
            ->where('active', true)
            ->orderBy('name')
            ->get();
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
            $teacher->password = $request->password;
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

        try {
            $birthDate = Carbon::parse($teacher->birth_date);
            $newPassword = $birthDate->format('dmY'); // ddmmyyyy

            $teacher->password = $newPassword;
            $teacher->must_change_password = true;
            $teacher->save();

            // Agar admin teacher guard orqali kirib, o'z parolini tiklayotgan bo'lsa,
            // sessiyani yangilash kerak (aks holda password hash mos kelmay logout bo'ladi)
            $currentTeacher = Auth::guard('teacher')->user();
            if ($currentTeacher && $currentTeacher->id === $teacher->id) {
                // Sessiyada saqlangan password hash ni yangilash
                Auth::guard('teacher')->login($teacher->fresh());
            }

            try {
                ActivityLogService::log('update', 'teacher', "Xodim paroli tiklandi: {$teacher->full_name}", $teacher);
            } catch (\Throwable $e) {
                Log::warning('Parol tiklash activity log xatosi: ' . $e->getMessage());
            }

            // Telegram orqali login va yangi parolni yuborish
            if ($teacher->telegram_chat_id) {
                try {
                    $telegramService = new TelegramService();
                    $telegramService->sendToUser(
                        $teacher->telegram_chat_id,
                        "Sizning parolingiz tiklandi.\n\nLogin: {$teacher->login}\nYangi parol: {$newPassword}\n\nTizimga kirganingizda parolni o'zgartiring."
                    );
                } catch (\Throwable $e) {
                    Log::warning('Parol tiklash telegram xatosi: ' . $e->getMessage());
                }
            }

            return redirect()->route('admin.teachers.show', $teacher)
                ->with('success', 'Parol tiklandi. Login: ' . $teacher->login . ', Yangi parol: ' . $newPassword . '. Xodim keyingi kirishda parolni o\'zgartirishi kerak.');

        } catch (\Throwable $e) {
            Log::error('Parol tiklashda xato', [
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->full_name,
                'guard' => Auth::getDefaultDriver(),
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('admin.teachers.show', $teacher)
                ->with('error', 'Parol tiklashda xato yuz berdi: ' . $e->getMessage());
        }
    }

    public function updateRoles(Request $request, Teacher $teacher)
    {
        $validRoleValues = array_values(array_map(fn ($r) => $r->value, ProjectRole::staffRoles()));

        $roles = $request->input('roles', []);
        $isDean = in_array(ProjectRole::DEAN->value, $roles);
        $isSubjectResponsible = in_array(ProjectRole::SUBJECT_RESPONSIBLE->value, $roles);

        $request->validate([
            'roles' => 'nullable|array',
            'roles.*' => 'in:' . implode(',', $validRoleValues),
            'dean_faculties' => [$isDean ? 'required' : 'nullable', 'array'],
            'dean_faculties.*' => 'exists:departments,department_hemis_id',
            'responsible_subjects' => [$isSubjectResponsible ? 'required' : 'nullable', 'array'],
            'responsible_subjects.*' => 'exists:curriculum_subjects,id',
        ], [
            'dean_faculties.required' => 'Dekan roli uchun kamida bitta fakultetni tanlash majburiy.',
            'responsible_subjects.required' => "Fan mas'uli roli uchun kamida bitta fanni tanlash majburiy.",
        ]);

        try {
            foreach ($roles as $roleName) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            }

            $oldRoles = $teacher->getRoleNames()->toArray();
            $teacher->syncRoles($roles);
        } catch (\Throwable $e) {
            Log::error('updateRoles - syncRoles xatolik: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Rollarni saqlashda xatolik: ' . $e->getMessage());
        }

        if ($isDean) {
            try {
                $teacher->deanFaculties()->sync($request->input('dean_faculties', []));
            } catch (\Throwable $e) {
                Log::error('updateRoles - deanFaculties xatolik: ' . $e->getMessage());
                return redirect()->back()->withInput()->with('error', 'Dekan fakultetlarini saqlashda xatolik: ' . $e->getMessage());
            }
        } else {
            try {
                $teacher->deanFaculties()->detach();
            } catch (\Throwable $e) {
                Log::error('updateRoles - deanFaculties detach xatolik: ' . $e->getMessage());
            }
        }

        if ($isSubjectResponsible) {
            if (!Schema::hasTable('teacher_responsible_subjects')) {
                Log::error('updateRoles - teacher_responsible_subjects jadvali mavjud emas');
                return redirect()->back()->withInput()->with('error', "teacher_responsible_subjects jadvali mavjud emas. Serverda 'php artisan migrate' buyrug'ini ishga tushiring.");
            }
            try {
                $teacher->responsibleSubjects()->sync($request->input('responsible_subjects', []));
            } catch (\Throwable $e) {
                Log::error('updateRoles - responsibleSubjects xatolik: ' . $e->getMessage());
                return redirect()->back()->withInput()->with('error', "Fanlarni saqlashda xatolik: " . $e->getMessage());
            }
        } else {
            try {
                $teacher->responsibleSubjects()->detach();
            } catch (\Throwable $e) {
                Log::error('updateRoles - responsibleSubjects detach xatolik: ' . $e->getMessage());
            }
        }

        ActivityLogService::log('update', 'teacher', "Xodim rollari yangilandi: {$teacher->full_name}", $teacher, [
            'roles' => $oldRoles ?? [],
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

    public function searchSubjects(Request $request)
    {
        $search = $request->input('q', '');
        $levelCode = $request->input('level_code', '');
        $teacherId = $request->input('teacher_id');
        $filterDept = $request->input('filter_dept', '1') !== '0';
        $currentSemester = $request->input('current_semester', '1');

        $teacher = $teacherId ? Teacher::find($teacherId) : null;

        // O'qituvchining biriktirilgan fan ID larini olish
        $assignedSubjectIds = collect();
        if ($teacher) {
            $assignedSubjectIds = $teacher->responsibleSubjects()
                ->pluck('curriculum_subjects.id');
        }

        // KTR bilan bir xil query — GROUP BY siz, har bir qator alohida
        $query = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'c.specialty_hemis_id')
            ->where('cs.is_active', true)
            ->whereNotNull('cs.subject_name');

        // Joriy semestr filtri (KTR kabi — curriculum_weeks asosida)
        if ($currentSemester == '1') {
            $query->whereIn('s.semester_hemis_id', function ($sub) {
                $sub->select('semester_hemis_id')
                    ->from('curriculum_weeks')
                    ->groupBy('semester_hemis_id')
                    ->havingRaw('MIN(start_date) <= NOW() AND MAX(end_date) >= NOW()');
            });
        }

        // Kafedra bo'yicha filtrlash
        if ($filterDept && $teacher) {
            $query->where(function ($q) use ($teacher) {
                if ($teacher->department_hemis_id) {
                    $q->where('cs.department_id', $teacher->department_hemis_id);
                }
                if ($teacher->department) {
                    $q->orWhere('cs.department_name', $teacher->department);
                }
                $q->orWhereNull('cs.department_id');
            });
        }

        // Fan nomi bo'yicha qidirish
        if ($search) {
            $query->where('cs.subject_name', 'like', "%{$search}%");
        }

        // Kurs (level_code) bo'yicha filtrlash
        if ($levelCode) {
            $query->where('s.level_code', $levelCode);
        }

        $subjects = $query
            ->select([
                'cs.id',
                'cs.subject_name',
                'cs.subject_code',
                'cs.semester_code',
                'cs.semester_name',
                'cs.department_name',
                'c.education_type_name',
                'f.name as faculty_name',
                'sp.name as specialty_name',
                's.level_name',
                's.level_code',
            ])
            ->orderBy('cs.subject_name')
            ->orderBy('cs.semester_code')
            ->get();

        // Har bir fan uchun is_assigned flagini qo'shish
        $subjects->transform(function ($subject) use ($assignedSubjectIds) {
            $subject->is_assigned = $assignedSubjectIds->contains($subject->id);
            return $subject;
        });

        return response()->json($subjects);
    }

    public function getSubjectCourses()
    {
        $courses = Semester::whereNotNull('level_code')
            ->whereNotNull('level_name')
            ->select('level_code', 'level_name')
            ->distinct()
            ->orderBy('level_code')
            ->get();

        return response()->json($courses);
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
