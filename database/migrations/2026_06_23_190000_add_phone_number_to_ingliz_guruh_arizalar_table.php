<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ingliz_guruh_arizalar')) {
            return;
        }
        Schema::table('ingliz_guruh_arizalar', function (Blueprint $table) {
            if (!Schema::hasColumn('ingliz_guruh_arizalar', 'phone_number')) {
                $table->string('phone_number', 50)->nullable()->after('full_name');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ingliz_guruh_arizalar')) {
            return;
        }
        Schema::table('ingliz_guruh_arizalar', function (Blueprint $table) {
            if (Schema::hasColumn('ingliz_guruh_arizalar', 'phone_number')) {
                $table->dropColumn('phone_number');
            }
        });
    }
};
