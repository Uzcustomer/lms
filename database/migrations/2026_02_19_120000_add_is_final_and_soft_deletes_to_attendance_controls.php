<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_controls', function (Blueprint $table) {
            $table->boolean('is_final')->default(false)->after('load');
            $table->softDeletes();
        });

        // Eski yozuvlarni yakunlangan deb belgilash (qayta import qilmaslik uchun)
        // Faqat bugundan oldingi yozuvlar is_final=true bo'ladi
        DB::table('attendance_controls')
            ->where('lesson_date', '<', now()->startOfDay())
            ->update(['is_final' => true]);
    }

    public function down(): void
    {
        Schema::table('attendance_controls', function (Blueprint $table) {
            $table->dropColumn('is_final');
            $table->dropSoftDeletes();
        });
    }
};
