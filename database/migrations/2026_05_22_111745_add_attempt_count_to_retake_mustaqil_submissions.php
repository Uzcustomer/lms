<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mustaqil ta'lim uchun urinishlar sonini hisoblash.
 * Talaba 60 dan past baho olsa qayta yuklay oladi — lekin eng ko'pi 3 marta.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_mustaqil_submissions', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempt_count')->default(0)->after('graded_at');
        });

        // Mavjud yozuvlar — kamida 1 marta yuklangan
        DB::table('retake_mustaqil_submissions')
            ->whereNotNull('file_path')
            ->update(['attempt_count' => 1]);
    }

    public function down(): void
    {
        Schema::table('retake_mustaqil_submissions', function (Blueprint $table) {
            $table->dropColumn('attempt_count');
        });
    }
};
