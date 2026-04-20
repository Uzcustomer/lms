<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ktr_plans') && !Schema::hasColumn('ktr_plans', 'created_by_guard')) {
            Schema::table('ktr_plans', function (Blueprint $table) {
                $table->string('created_by_guard', 10)->default('teacher')->after('created_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ktr_plans') && Schema::hasColumn('ktr_plans', 'created_by_guard')) {
            Schema::table('ktr_plans', function (Blueprint $table) {
                $table->dropColumn('created_by_guard');
            });
        }
    }
};
