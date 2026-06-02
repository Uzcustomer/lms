<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vedomost_submissions', function (Blueprint $table) {
            // none | queued | running | done | error
            $table->string('ai_check_status', 20)->default('none')->after('rejection_reason');
            $table->string('ai_verdict', 20)->nullable()->after('ai_check_status'); // ok | issues
            $table->text('ai_summary')->nullable()->after('ai_verdict');
            $table->longText('ai_result')->nullable()->after('ai_summary'); // to'liq JSON (discrepancies)
            $table->text('ai_error')->nullable()->after('ai_result');
            $table->timestamp('ai_checked_at')->nullable()->after('ai_error');
        });
    }

    public function down(): void
    {
        Schema::table('vedomost_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'ai_check_status',
                'ai_verdict',
                'ai_summary',
                'ai_result',
                'ai_error',
                'ai_checked_at',
            ]);
        });
    }
};
