<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingliz_guruh_arizalar', function (Blueprint $table) {
            if (!Schema::hasColumn('ingliz_guruh_arizalar', 'rejection_reason_code')) {
                $table->string('rejection_reason_code', 50)->nullable()->after('english_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ingliz_guruh_arizalar', function (Blueprint $table) {
            if (Schema::hasColumn('ingliz_guruh_arizalar', 'rejection_reason_code')) {
                $table->dropColumn('rejection_reason_code');
            }
        });
    }
};
