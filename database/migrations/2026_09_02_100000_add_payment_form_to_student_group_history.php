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
            if (!Schema::hasColumn('student_group_history', 'payment_form_code')) {
                $table->string('payment_form_code')->nullable()->after('specialty_name');
            }
            if (!Schema::hasColumn('student_group_history', 'payment_form_name')) {
                $table->string('payment_form_name')->nullable()->after('payment_form_code');
            }
        });

        // Mavjud yozuvlarda to'lov shakli yo'q. Ochiq (hozirgi) yozuvga talabaning
        // ayni paytdagi shaklini yozamiz — bu bilan hech bo'lmasa joriy holat
        // ko'rinadi. Yopilgan yozuvlar bo'sh qoladi, chunki o'sha davrdagi
        // shaklni tiklashning imkoni yo'q.
        DB::table('student_group_history')
            ->whereNull('ended_at')
            ->whereNull('payment_form_name')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $student = DB::table('students')
                        ->where('id', $row->student_id)
                        ->first(['payment_form_code', 'payment_form_name']);

                    if (!$student || !$student->payment_form_name) {
                        continue;
                    }

                    DB::table('student_group_history')
                        ->where('id', $row->id)
                        ->update([
                            'payment_form_code' => $student->payment_form_code,
                            'payment_form_name' => $student->payment_form_name,
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
            foreach (['payment_form_code', 'payment_form_name'] as $column) {
                if (Schema::hasColumn('student_group_history', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
