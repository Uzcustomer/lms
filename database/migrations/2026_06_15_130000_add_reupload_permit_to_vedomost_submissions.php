<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rad etilgan vedomostni qayta yuklashga ruxsat (o'quv prorektori).
 *
 * Oldin: status 'rejected' bo'lsa o'qituvchi/admin darhol qayta yuklay olardi.
 * Endi: rad etilgandan keyin qayta yuklash uchun avval O'QUV PROREKTORI
 * "qayta yuklashga ruxsat" tugmasini bosishi kerak. Ruxsat berilgach
 * (reupload_allowed_at to'ldiriladi) faqat shu holatda qayta yuklash mumkin.
 * Qayta yuklab bo'lingach ruxsat "iste'mol qilinadi" (yana null bo'ladi).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vedomost_submissions')) {
            return;
        }

        Schema::table('vedomost_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('vedomost_submissions', 'reupload_allowed_at')) {
                $table->timestamp('reupload_allowed_at')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('vedomost_submissions', 'reupload_allowed_by')) {
                $table->unsignedBigInteger('reupload_allowed_by')->nullable()->after('reupload_allowed_at');
            }
            if (!Schema::hasColumn('vedomost_submissions', 'reupload_allowed_by_name')) {
                $table->string('reupload_allowed_by_name')->nullable()->after('reupload_allowed_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vedomost_submissions')) {
            return;
        }

        Schema::table('vedomost_submissions', function (Blueprint $table) {
            foreach (['reupload_allowed_at', 'reupload_allowed_by', 'reupload_allowed_by_name'] as $col) {
                if (Schema::hasColumn('vedomost_submissions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
