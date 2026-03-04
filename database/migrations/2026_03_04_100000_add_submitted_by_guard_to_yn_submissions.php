<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yn_submissions', function (Blueprint $table) {
            // Add submitted_by_guard column if it doesn't exist yet
            if (!Schema::hasColumn('yn_submissions', 'submitted_by_guard')) {
                $table->string('submitted_by_guard', 20)->default('web')->after('submitted_by');
            }

            // Drop foreign key on submitted_by so it can reference both users and teachers tables
            try {
                $table->dropForeign(['submitted_by']);
            } catch (\Exception $e) {
                // Foreign key may already be dropped
            }

            // Ensure submitted_by is nullable
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
