<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->boolean('oski_na')->default(false)->after('oski_date')->comment('OSKI nazorat turi yo\'q');
            $table->boolean('test_na')->default(false)->after('test_date')->comment('Test nazorat turi yo\'q');
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropColumn(['oski_na', 'test_na']);
        });
    }
};
