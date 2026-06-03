<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computer_assignments', function (Blueprint $table) {
            // Allow null so JIT-mode rows can hold a time slot without a
            // committed computer number until 5 min before planned_start.
            $table->unsignedSmallInteger('computer_number')->nullable()->change();
            // Admin-pinned: when true, JIT processor leaves the row alone
            // (admin manually committed a specific computer to this student).
            $table->boolean('is_pinned')->default(false)->after('is_reserve');
        });
    }

    public function down(): void
    {
        Schema::table('computer_assignments', function (Blueprint $table) {
            $table->dropColumn('is_pinned');
            $table->unsignedSmallInteger('computer_number')->nullable(false)->change();
        });
    }
};
