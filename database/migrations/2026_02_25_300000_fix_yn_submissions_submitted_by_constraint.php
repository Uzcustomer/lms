<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yn_submissions', function (Blueprint $table) {
            // Drop foreign key constraint so submitted_by can reference teachers table too
            $table->dropForeign(['submitted_by']);

            // Make submitted_by nullable for safety
            $table->unsignedBigInteger('submitted_by')->nullable()->change();

            // Add guard type to know which table submitted_by references
            $table->string('submitted_by_guard', 20)->default('web')->after('submitted_by');
        });
    }

    public function down(): void
    {
        Schema::table('yn_submissions', function (Blueprint $table) {
            $table->dropColumn('submitted_by_guard');
            $table->unsignedBigInteger('submitted_by')->nullable(false)->change();
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
