<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "To'liq guruh" rejimida qilingan ko'chirishni belgilaydi.
 *
 * Bu rejimda talaba bo'sh joyi yo'q guruhga ham, o'zbek/rus guruhidan
 * ingliz guruhiga ham ko'chirilishi mumkin. Keyin hisobotda oddiy
 * ko'chirishdan ajratib ko'rsatish uchun alohida belgi saqlanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('distribution_draft_assignments')
            || Schema::hasColumn('distribution_draft_assignments', 'full_group_mode')) {
            return;
        }

        Schema::table('distribution_draft_assignments', function (Blueprint $table) {
            $table->boolean('full_group_mode')->default(false)->after('to_group_name');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('distribution_draft_assignments')
            || !Schema::hasColumn('distribution_draft_assignments', 'full_group_mode')) {
            return;
        }

        Schema::table('distribution_draft_assignments', function (Blueprint $table) {
            $table->dropColumn('full_group_mode');
        });
    }
};
