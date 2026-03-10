<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add submitted_by_guard column if it doesn't exist yet
        if (!Schema::hasColumn('yn_submissions', 'submitted_by_guard')) {
            Schema::table('yn_submissions', function (Blueprint $table) {
                $table->string('submitted_by_guard', 20)->default('web')->after('submitted_by');
            });
        }

        // Drop foreign key on submitted_by only if it exists
        $fkExists = DB::select("
            SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'yn_submissions'
              AND CONSTRAINT_NAME = 'yn_submissions_submitted_by_foreign'
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");

        if ($fkExists) {
            Schema::table('yn_submissions', function (Blueprint $table) {
                $table->dropForeign(['submitted_by']);
            });
        }

        // Ensure submitted_by is nullable
        Schema::table('yn_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('yn_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('yn_submissions', 'submitted_by_guard')) {
                $table->dropColumn('submitted_by_guard');
            }
        });
    }
};
