<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qabul oynasi sanasi uzaytirilganda (Override) ariza qabulini qayta
 * ochish uchun sana. Tugash sanasi N kunga uzaytirilsa, override
 * qilingan kundan boshlab N kun davomida ariza qabuli ochiq turadi.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_application_windows', function (Blueprint $table) {
            $table->date('application_reopen_until')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('retake_application_windows', function (Blueprint $table) {
            $table->dropColumn('application_reopen_until');
        });
    }
};
