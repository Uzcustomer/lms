<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->date('lesson_start_date')->nullable()->after('subject_name')->comment('Dars boshlanish sanasi');
            $table->date('lesson_end_date')->nullable()->after('lesson_start_date')->comment('Dars tugash sanasi');
            $table->boolean('oski_na')->default(false)->after('oski_date')->comment('OSKI nazorat turi yo\'q');
            $table->boolean('test_na')->default(false)->after('test_date')->comment('Test nazorat turi yo\'q');
        });
    }

    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropColumn(['lesson_start_date', 'lesson_end_date', 'oski_na', 'test_na']);
        });
    }
};
