<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('students') || Schema::hasColumn('students', 'is_starosta')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            // Guruh starostasi (yetakchisi) — tyutor tomonidan belgilanadi.
            // HEMIS importi bu ustunga tegmaydi (mahalliy maydon), shuning uchun saqlanib qoladi.
            $table->boolean('is_starosta')->default(false)->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('students') && Schema::hasColumn('students', 'is_starosta')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('is_starosta');
            });
        }
    }
};
