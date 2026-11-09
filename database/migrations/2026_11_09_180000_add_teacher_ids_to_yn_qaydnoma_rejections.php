<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rad etilgan qaydnomani topshirgan o'qituvchilar (teachers.id) — tasdig'i o'chgandan keyin ham
 * "Vedomostlarim" da (o'tgan semestr bo'lsa ham) ko'rinishi uchun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yn_qaydnoma_rejections', function (Blueprint $table) {
            $table->json('teacher_ids')->nullable()->after('dean_name');
        });
    }

    public function down(): void
    {
        Schema::table('yn_qaydnoma_rejections', function (Blueprint $table) {
            $table->dropColumn('teacher_ids');
        });
    }
};
