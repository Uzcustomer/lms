<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kafedralarni fakultetlarga bog'lash (rasmda ko'rsatilgan tarkib asosida).
     *
     * 1-son Davolash fakulteti: 5 ta kafedra
     * 2-son Davolash fakulteti: 5 ta kafedra
     * Pediatriya fakulteti: 5 ta kafedra
     * Xalqaro ta'lim fakulteti: 4 ta kafedra
     */
    public function up(): void
    {
        if (!Schema::hasTable('departments')) {
            return;
        }

        // Fakultet -> kafedra nomlari (LIKE pattern orqali topiladi)
        $mappings = [
            // 1-SON DAVOLASH FAKULTETI
            '1-son Davolash' => [
                '%Akusherlik va ginekologiya%',
                '%Ichki kasalliklar propedevtikasi%reabilitologiya%',
                '%Ijtimoiy-gumanitar fanlar%',
                '%Patologik anatomiya%sud tibbiyoti%',
                '%Xirurgik kasalliklar%oilaviy shifokorlikda xirurgiya%',
            ],

            // 2-SON DAVOLASH FAKULTETI
            '2-son Davolash' => [
                '%Anatomiya va klinik anatomiya%',
                '%Ichki kasalliklar%harbiy dala terapiyasi%gematologiya%',
                '%Travmatologiya%ortopediya%harbiy dala jarrohligi%',
                '%Umumiy xirurgiya%bolalar xirurgiyasi%urologiya%',
                '%Yuqumli kasalliklar%dermatovenerologiya%',
            ],

            // PEDIATRIYA FAKULTETI
            'Pediatriya' => [
                '%Bolalar kasalliklari propedevtikasi%',
                '%Mikrobiologiya%jamoat salomatligi%gigiyena%',
                '%Normal va patologik fiziologiya%',
                '%Otorinolaringologiya%oftalmologiya%onkologiya%',
                '%Tibbiy psixologiya%nevrologiya%psixiatriya%',
            ],

            // XALQARO TA'LIM FAKULTETI
            'Xalqaro' => [
                '%Farmakologiya va klinik farmakologiya%',
                '%zbek va xorijiy tillar%',
                '%Tibbiy biologiya va gistologiya%',
                '%Tibbiy va biologik kimyo%',
            ],
        ];

        foreach ($mappings as $facultyPattern => $kafedraPatterns) {
            // Fakultetni topish (structure_type_code = '11')
            $faculty = DB::table('departments')
                ->where('structure_type_code', '11')
                ->where('name', 'LIKE', "%{$facultyPattern}%")
                ->first();

            if (!$faculty) {
                continue;
            }

            // Har bir kafedrani fakultetga bog'lash
            foreach ($kafedraPatterns as $pattern) {
                DB::table('departments')
                    ->where('name', 'LIKE', $pattern)
                    ->where('structure_type_code', '!=', '11')
                    ->update(['parent_id' => $faculty->department_hemis_id]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('departments')) {
            DB::table('departments')
                ->where('structure_type_code', '!=', '11')
                ->update(['parent_id' => null]);
        }
    }
};
