<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->unsignedBigInteger('quiz_result_id')->nullable()->after('test_id');
            $table->index('quiz_result_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->dropIndex(['quiz_result_id']);
            $table->dropColumn('quiz_result_id');
        });
    }
};
