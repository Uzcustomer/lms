<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Window'da fakultet (department_hemis_id) ni bevosita saqlash.
 * Avval specialty_id orqali specialty -> department lookup qilinardi,
 * lekin specialty_hemis_id bir necha fakultet ostida takrorlanishi mumkin
 * (HEMIS'dagi ma'lumot tarkibi sababli) — bu noto'g'ri fakultet ko'rinishiga
 * olib kelar edi. Endi bevosita saqlanadi.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_application_windows', function (Blueprint $table) {
            $table->string('department_hemis_id', 64)->nullable()->index()->after('specialty_name');
        });

        // Mavjud yozuvlarni to'ldirish: specialty_id orqali department_hemis_id'ni
        // birinchi mos kelgan specialty'dan olamiz (eski yozuvlar uchun yaqinlashtirma)
        if (Schema::hasTable('specialties')) {
            \Illuminate\Support\Facades\DB::statement("
                UPDATE retake_application_windows w
                INNER JOIN specialties s ON s.specialty_hemis_id = w.specialty_id
                SET w.department_hemis_id = s.department_hemis_id
                WHERE w.department_hemis_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('retake_application_windows', function (Blueprint $table) {
            $table->dropIndex(['department_hemis_id']);
            $table->dropColumn('department_hemis_id');
        });
    }
};
