<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 3-so'rovdan boshlab har bir prorektor alohida tasdiqlaydi.
 * Qarorlar shu ustunda: {"teacher:12": {"name": ..., "decision": ..., "at": ...}}.
 * prorektor_status ustuni bosqichning yig'ma holati bo'lib qoladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_openings', function (Blueprint $table) {
            $table->json('prorektor_approvals')->nullable()->after('prorektor_status');
            // 2-so'rovdan boshlab tushuntirish xati eslatmasi o'qituvchi
            // profilida bir marta ko'rsatiladi — ko'rsatilgan vaqti shu yerda.
            $table->timestamp('explanation_notice_at')->nullable()->after('prorektor_approvals');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_openings', function (Blueprint $table) {
            $table->dropColumn(['prorektor_approvals', 'explanation_notice_at']);
        });
    }
};
