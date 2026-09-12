<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eski (arxivlangan) ovozlar.
 *
 * Registrator talabaning rejasini bekor qilsa, uning ovozi o'chirilmaydi —
 * "eski" deb belgilanadi (archived_at) va talaba qaytadan ovoz bera oladi.
 * Shuning uchun bir talabada bir nechta ovoz bo'lishi mumkin: student_id
 * endi unique emas, faqat bitta faol (archived_at = null) ovoz bo'ladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_votes', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->unsignedBigInteger('archived_by')->nullable();
        });

        // Tashqi kalit unique indeksga tayanadi — avval kalit olib tashlanadi,
        // unique oddiy indeksga almashtiriladi, keyin kalit qayta qo'yiladi.
        Schema::table('distribution_votes', function (Blueprint $table) {
            $table->dropForeign('dv_student_fk');
        });

        Schema::table('distribution_votes', function (Blueprint $table) {
            $table->dropUnique('dv_student_unique');
            $table->index('student_id', 'dv_student_idx');
        });

        Schema::table('distribution_votes', function (Blueprint $table) {
            $table->foreign('student_id', 'dv_student_fk')
                ->references('id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Qaytarishda har talabaga bitta ovoz qolishi kerak — eski ovozlar o'chiriladi.
        \Illuminate\Support\Facades\DB::table('distribution_votes')->whereNotNull('archived_at')->delete();

        Schema::table('distribution_votes', function (Blueprint $table) {
            $table->dropForeign('dv_student_fk');
        });

        Schema::table('distribution_votes', function (Blueprint $table) {
            $table->dropIndex('dv_student_idx');
            $table->unique('student_id', 'dv_student_unique');
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['archived_at', 'archived_by']);
        });

        Schema::table('distribution_votes', function (Blueprint $table) {
            $table->foreign('student_id', 'dv_student_fk')
                ->references('id')->on('students')->cascadeOnDelete();
        });
    }
};
