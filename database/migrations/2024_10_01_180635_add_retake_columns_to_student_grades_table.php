<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->float('retake_grade')->nullable();
            $table->unsignedBigInteger('graded_by_user_id')->nullable();
            $table->timestamp('retake_graded_at')->nullable();

            $table->foreign('graded_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->dropForeign(['graded_by_user_id']);
            $table->dropColumn(['retake_grade', 'graded_by_user_id', 'retake_graded_at']);
        });
    }
};
