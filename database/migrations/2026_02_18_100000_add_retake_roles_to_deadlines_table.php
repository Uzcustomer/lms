<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            $table->boolean('retake_by_test_markazi')->default(false);
            $table->boolean('retake_by_oqituvchi')->default(false);
        });

        // Default qiymatlarni o'rnatish:
        // 1,2,3-kurs (level_code: 11,12,13) → test_markazi
        // 4,5,6-kurs (level_code: 14,15,16) → oqituvchi
        DB::table('deadlines')
            ->whereIn('level_code', ['11', '12', '13'])
            ->update(['retake_by_test_markazi' => true, 'retake_by_oqituvchi' => false]);

        DB::table('deadlines')
            ->whereIn('level_code', ['14', '15', '16'])
            ->update(['retake_by_test_markazi' => false, 'retake_by_oqituvchi' => true]);
    }

    public function down(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            $table->dropColumn(['retake_by_test_markazi', 'retake_by_oqituvchi']);
        });
    }
};
