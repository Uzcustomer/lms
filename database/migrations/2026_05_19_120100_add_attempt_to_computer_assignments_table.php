<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_assignments', function (Blueprint $table) {
            // 1, 2 yoki 3 — qaysi urinish uchun bu komp tayinlangan. Mavjud
            // qatorlarning hammasi 1-urinish (eski xulq).
            $table->unsignedTinyInteger('attempt')->default(1)->after('yn_type');

            // Eski (schedule, student, yn_type) unique endi yetarli emas —
            // bir talaba 1- va 2-urinishga alohida qatorlar oladi. Yangi
            // unique key attempt'ni ham hisobga oladi.
            $table->dropUnique('comp_assign_unique_per_schedule');
            $table->unique(
                ['exam_schedule_id', 'student_id_number', 'yn_type', 'attempt'],
                'comp_assign_unique_per_schedule_attempt'
            );
            $table->index(['exam_schedule_id', 'yn_type', 'attempt'], 'comp_assign_schedule_yn_attempt_idx');
        });
    }

    public function down(): void
    {
        Schema::table('computer_assignments', function (Blueprint $table) {
            $table->dropIndex('comp_assign_schedule_yn_attempt_idx');
            $table->dropUnique('comp_assign_unique_per_schedule_attempt');
            $table->unique(
                ['exam_schedule_id', 'student_id_number', 'yn_type'],
                'comp_assign_unique_per_schedule'
            );
            $table->dropColumn('attempt');
        });
    }
};
