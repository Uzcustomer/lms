<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `computer_assignments` jadvaliga `attempt` ustunini qo'shadi (1|2|3).
 *
 * Avval ustun yo'q edi va biriktirishlar faqat 1-urinish uchun yaratilardi —
 * shu sababli 2/3-urinish slot'larida bandlik dashboard'i "Topshirdi" hisobini
 * 1-urinish topshirganlardan oladi (eski yozuvlar ishlatilardi).
 *
 * Backfill: barcha mavjud yozuvlar 1-urinish hisoblanadi.
 *
 * Unique constraint (exam_schedule_id, student_id_number, yn_type) endi
 * attempt'ni ham qamrab oladi — bir talaba bir scheduleda 1/2/3 urinish
 * uchun alohida yozuvga ega bo'lishi mumkin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_assignments', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempt')->default(1)->after('yn_type');
        });

        // Eski yozuvlar 1-urinish hisoblanadi.
        \DB::table('computer_assignments')->update(['attempt' => 1]);

        Schema::table('computer_assignments', function (Blueprint $table) {
            $table->dropUnique('comp_assign_unique_per_schedule');
            $table->unique(
                ['exam_schedule_id', 'student_id_number', 'yn_type', 'attempt'],
                'comp_assign_unique_per_schedule'
            );
            $table->index(['exam_schedule_id', 'yn_type', 'attempt']);
        });
    }

    public function down(): void
    {
        Schema::table('computer_assignments', function (Blueprint $table) {
            $table->dropIndex(['exam_schedule_id', 'yn_type', 'attempt']);
            $table->dropUnique('comp_assign_unique_per_schedule');
            $table->unique(
                ['exam_schedule_id', 'student_id_number', 'yn_type'],
                'comp_assign_unique_per_schedule'
            );
            $table->dropColumn('attempt');
        });
    }
};
