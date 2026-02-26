<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kafedralarni fakultetlarga bog'lash: schedules jadvalidan faculty_id va department_id
     * ma'lumotlarini olib, departments jadvalidagi parent_id ni yangilash.
     */
    public function up(): void
    {
        if (!Schema::hasTable('schedules') || !Schema::hasTable('departments')) {
            return;
        }

        // Schedules jadvalidan kafedra-fakultet bog'lanishini olish
        $mappings = DB::table('schedules')
            ->select('department_id', 'faculty_id')
            ->whereNotNull('department_id')
            ->whereNotNull('faculty_id')
            ->where('department_id', '>', 0)
            ->where('faculty_id', '>', 0)
            ->distinct()
            ->get()
            ->unique('department_id');

        foreach ($mappings as $mapping) {
            // Kafedrani topish
            $kafedra = DB::table('departments')
                ->where('department_hemis_id', $mapping->department_id)
                ->first();

            // Fakultetni topish
            $faculty = DB::table('departments')
                ->where('department_hemis_id', $mapping->faculty_id)
                ->where('structure_type_code', '11')
                ->first();

            if ($kafedra && $faculty) {
                DB::table('departments')
                    ->where('id', $kafedra->id)
                    ->update(['parent_id' => $faculty->department_hemis_id]);
            }
        }
    }

    public function down(): void
    {
        // parent_id larni tozalash
        if (Schema::hasTable('departments')) {
            DB::table('departments')
                ->where('structure_type_code', '!=', '11')
                ->update(['parent_id' => null]);
        }
    }
};
