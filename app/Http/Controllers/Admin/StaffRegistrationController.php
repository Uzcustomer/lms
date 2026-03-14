<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Semester;
use App\Models\Specialty;
use App\Models\StaffRegistrationDivision;
use App\Models\Teacher;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StaffRegistrationController extends Controller
{
    /**
     * Registrator ofisi xodimlarini bo'linmalar kesimida ko'rsatish
     */
    public function index()
    {
        $divisions = StaffRegistrationDivision::with(['teacher', 'department', 'specialty'])
            ->orderBy('division_type')
            ->orderBy('department_hemis_id')
            ->orderBy('specialty_hemis_id')
            ->orderBy('level_code')
            ->get();

        $departments = Department::where('structure_type_code', '11')
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $registrators = Teacher::whereHas('roles', fn($q) => $q->where('name', 'registrator_ofisi'))
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get();

        $courses = Semester::whereNotNull('level_code')
            ->whereNotNull('level_name')
            ->select('level_code', 'level_name')
            ->distinct()
            ->orderBy('level_code')
            ->get();

        return view('admin.staff-registration.index', compact('divisions', 'departments', 'registrators', 'courses'));
    }

    /**
     * Yangi biriktirish saqlash
     */
    public function store(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'division_type' => 'required|in:front_office,back_office',
            'department_hemis_id' => 'required',
            'specialty_hemis_id' => 'nullable|string',
            'level_code' => 'nullable|string',
        ], [
            'teacher_id.required' => 'Xodimni tanlash majburiy.',
            'division_type.required' => 'Bo\'linma turini tanlash majburiy.',
            'department_hemis_id.required' => 'Fakultetni tanlash majburiy.',
        ]);

        $exists = StaffRegistrationDivision::where('division_type', $request->division_type)
            ->where('department_hemis_id', $request->department_hemis_id)
            ->where('specialty_hemis_id', $request->specialty_hemis_id)
            ->where('level_code', $request->level_code)
            ->exists();

        if ($exists) {
            return redirect()->back()->withInput()->with('error', 'Bu bo\'linma uchun allaqachon xodim biriktirilgan.');
        }

        try {
            $division = StaffRegistrationDivision::create($request->only([
                'teacher_id', 'division_type', 'department_hemis_id', 'specialty_hemis_id', 'level_code',
            ]));

            $teacher = Teacher::find($request->teacher_id);
            ActivityLogService::log('create', 'staff_registration', "Registrator bo'linma biriktirildi: {$teacher->full_name}", $division);

            return redirect()->route('admin.staff-registration.index')->with('success', 'Biriktirish muvaffaqiyatli saqlandi.');
        } catch (\Throwable $e) {
            Log::error('StaffRegistration store xatolik: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Saqlashda xatolik: ' . $e->getMessage());
        }
    }

    /**
     * Biriktirishni o'chirish
     */
    public function destroy(StaffRegistrationDivision $division)
    {
        try {
            $teacher = $division->teacher;
            $division->delete();

            ActivityLogService::log('delete', 'staff_registration', "Registrator bo'linma o'chirildi: {$teacher->full_name}", null);

            return redirect()->route('admin.staff-registration.index')->with('success', 'Biriktirish o\'chirildi.');
        } catch (\Throwable $e) {
            Log::error('StaffRegistration destroy xatolik: ' . $e->getMessage());
            return redirect()->back()->with('error', 'O\'chirishda xatolik: ' . $e->getMessage());
        }
    }

    /**
     * Fakultetga tegishli yo'nalishlarni olish (AJAX)
     */
    public function getSpecialties(Request $request)
    {
        $departmentHemisId = $request->input('department_hemis_id');

        $specialties = Specialty::where('department_hemis_id', $departmentHemisId)
            ->orderBy('name')
            ->select('specialty_hemis_id', 'name')
            ->get();

        return response()->json($specialties);
    }
}
