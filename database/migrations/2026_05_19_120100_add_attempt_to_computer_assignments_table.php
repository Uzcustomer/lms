<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // attempt ustuni boshqa branchda yoki qo'lda allaqachon qo'shilgan
        // bo'lishi mumkin — shu sabab idempotent: faqat yo'q bo'lsa qo'shamiz.
        if (!Schema::hasColumn('computer_assignments', 'attempt')) {
            Schema::table('computer_assignments', function (Blueprint $table) {
                // 1, 2 yoki 3 — qaysi urinish uchun bu komp tayinlangan.
                // Mavjud qatorlarning hammasi 1-urinish (eski xulq).
                $table->unsignedTinyInteger('attempt')->default(1)->after('yn_type');
            });
        }

        // Eski (schedule, student, yn_type) unique endi yetarli emas — bir
        // talaba 1- va 2-urinishga alohida qatorlar olishi mumkin. Eski
        // unique'ni o'chirib, attempt'ni ham hisobga oladigan yangisini
        // qo'shamiz. Indekslar har xil holatda bo'lishi mumkin — try/catch
        // bilan o'rab ketamiz.
        try {
            Schema::table('computer_assignments', function (Blueprint $table) {
                $table->dropUnique('comp_assign_unique_per_schedule');
            });
        } catch (\Throwable $e) {
            // Eski unique allaqachon yo'q — bo'lib ketadi.
        }

        try {
            Schema::table('computer_assignments', function (Blueprint $table) {
                $table->unique(
                    ['exam_schedule_id', 'student_id_number', 'yn_type', 'attempt'],
                    'comp_assign_unique_per_schedule_attempt'
                );
            });
        } catch (\Throwable $e) {
            // Yangi unique allaqachon mavjud — bo'lib ketadi.
        }

        try {
            Schema::table('computer_assignments', function (Blueprint $table) {
                $table->index(
                    ['exam_schedule_id', 'yn_type', 'attempt'],
                    'comp_assign_schedule_yn_attempt_idx'
                );
            });
        } catch (\Throwable $e) {
            // Indeks allaqachon mavjud — bo'lib ketadi.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('computer_assignments', function (Blueprint $table) {
                $table->dropIndex('comp_assign_schedule_yn_attempt_idx');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('computer_assignments', function (Blueprint $table) {
                $table->dropUnique('comp_assign_unique_per_schedule_attempt');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('computer_assignments', function (Blueprint $table) {
                $table->unique(
                    ['exam_schedule_id', 'student_id_number', 'yn_type'],
                    'comp_assign_unique_per_schedule'
                );
            });
        } catch (\Throwable $e) {}

        if (Schema::hasColumn('computer_assignments', 'attempt')) {
            Schema::table('computer_assignments', function (Blueprint $table) {
                $table->dropColumn('attempt');
            });
        }
    }
};
