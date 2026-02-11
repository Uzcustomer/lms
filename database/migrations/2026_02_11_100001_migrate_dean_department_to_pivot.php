<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Mavjud dekan rolga ega va department_hemis_id bo'lgan teacherlarni
        // pivot jadvalga ko'chiramiz
        $deans = DB::table('teachers')
            ->join('model_has_roles', function ($join) {
                $join->on('teachers.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', 'App\\Models\\Teacher');
            })
            ->join('roles', function ($join) {
                $join->on('model_has_roles.role_id', '=', 'roles.id')
                    ->where('roles.name', 'dekan');
            })
            ->whereNotNull('teachers.department_hemis_id')
            ->select('teachers.id as teacher_id', 'teachers.department_hemis_id')
            ->get();

        foreach ($deans as $dean) {
            DB::table('dean_faculties')->insertOrIgnore([
                'teacher_id' => $dean->teacher_id,
                'department_hemis_id' => $dean->department_hemis_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Pivot jadvaldan birinchi fakultetni teacherga qaytaramiz
        $deanFaculties = DB::table('dean_faculties')
            ->orderBy('teacher_id')
            ->orderBy('id')
            ->get()
            ->groupBy('teacher_id');

        foreach ($deanFaculties as $teacherId => $faculties) {
            DB::table('teachers')
                ->where('id', $teacherId)
                ->update(['department_hemis_id' => $faculties->first()->department_hemis_id]);
        }
    }
};
