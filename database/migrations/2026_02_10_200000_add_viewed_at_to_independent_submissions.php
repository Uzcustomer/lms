<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('independent_submissions', function (Blueprint $table) {
            $table->timestamp('viewed_at')->nullable()->after('submission_count');
        });
    }

    public function down(): void
    {
        Schema::table('independent_submissions', function (Blueprint $table) {
            $table->dropColumn('viewed_at');
        });
    }
};
