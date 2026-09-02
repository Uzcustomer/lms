<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('student_group_history')) {
            return;
        }

        Schema::table('student_group_history', function (Blueprint $table) {
            if (!Schema::hasColumn('student_group_history', 'semester_code')) {
                $table->string('semester_code')->nullable()->after('education_year_name');
            }
            if (!Schema::hasColumn('student_group_history', 'semester_name')) {
                $table->string('semester_name')->nullable()->after('semester_code');
            }
        });

        // Ochiq (hozirgi) yozuvga talabaning ayni paytdagi semestrini yozamiz.
        // Yopilgan yozuvlar bo'sh qoladi — o'sha davrdagi semestrni tiklab
        // bo'lmaydi, to'lov shakli backfilli bilan bir xil sabab.
        DB::table('student_group_history')
            ->whereNull('ended_at')
            ->whereNull('semester_name')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $student = DB::table('students')
                        ->where('id', $row->student_id)
                        ->first(['semester_code', 'semester_name']);

                    if (!$student || !$student->semester_name) {
                        continue;
                    }

                    DB::table('student_group_history')
                        ->where('id', $row->id)
                        ->update([
                            'semester_code' => $student->semester_code,
                            'semester_name' => $student->semester_name,
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('student_group_history')) {
            return;
        }

        Schema::table('student_group_history', function (Blueprint $table) {
            foreach (['semester_code', 'semester_name'] as $column) {
                if (Schema::hasColumn('student_group_history', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
