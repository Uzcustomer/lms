<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kafedra mudiri qaysi kafedraga mudirligi (admin tanlaydi).
 * HEMIS da xodimning bir nechta lavozimi bo'lsa (masalan bo'lim boshlig'i +
 * o'rindosh kafedra mudiri), import faqat bittasini saqlaydi — shu ustun ustun turadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->unsignedBigInteger('head_department_hemis_id')->nullable()->after('department_hemis_id');
            $table->index('head_department_hemis_id');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropIndex(['head_department_hemis_id']);
            $table->dropColumn('head_department_hemis_id');
        });
    }
};
