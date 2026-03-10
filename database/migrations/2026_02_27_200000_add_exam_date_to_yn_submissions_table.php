<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yn_submissions', function (Blueprint $table) {
            $table->date('exam_date')->nullable()->after('submitted_at');
            $table->boolean('results_fetched')->default(false)->after('exam_date');
        });
    }

    public function down(): void
    {
        Schema::table('yn_submissions', function (Blueprint $table) {
            $table->dropColumn(['exam_date', 'results_fetched']);
        });
    }
};
