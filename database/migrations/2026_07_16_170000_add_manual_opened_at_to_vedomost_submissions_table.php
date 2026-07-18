<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vedomost_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('vedomost_submissions', 'manual_opened_at')) {
                $table->timestamp('manual_opened_at')->nullable()->after('warned_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vedomost_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('vedomost_submissions', 'manual_opened_at')) {
                $table->dropColumn('manual_opened_at');
            }
        });
    }
};
