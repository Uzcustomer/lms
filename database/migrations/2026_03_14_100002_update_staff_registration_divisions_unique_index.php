<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_registration_divisions', function (Blueprint $table) {
            // Eski unique indexni o'chirish
            $table->dropUnique('staff_reg_div_unique');
        });
    }

    public function down(): void
    {
        Schema::table('staff_registration_divisions', function (Blueprint $table) {
            $table->unique(
                ['division_type', 'department_hemis_id', 'specialty_hemis_id', 'level_code'],
                'staff_reg_div_unique'
            );
        });
    }
};
