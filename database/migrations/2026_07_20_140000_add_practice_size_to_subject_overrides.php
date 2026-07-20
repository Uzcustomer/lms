<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fan uchun amaliy mashg'ulot guruh o'lchami (nechta talabalik guruhchada
 * o'tiladi): masalan klinik fanlar 10, biologiya/kimyo 15, ijtimoiy fanlar
 * 30. Kafedra kabi fan nomi bo'yicha saqlanadi. kafedra_name endi nullable —
 * yozuv faqat amaliy o'lcham uchun ham bo'lishi mumkin.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('subject_kafedra_overrides', function (Blueprint $table) {
            $table->unsignedSmallInteger('practice_group_size')->nullable()->after('department_id');
        });
        // kafedra_name faqat amaliy o'lcham saqlansa bo'sh satr bo'lishi mumkin (default '')
        DB::statement("ALTER TABLE subject_kafedra_overrides MODIFY kafedra_name VARCHAR(255) NOT NULL DEFAULT ''");
    }

    public function down(): void
    {
        Schema::table('subject_kafedra_overrides', function (Blueprint $table) {
            $table->dropColumn('practice_group_size');
        });
    }
};
