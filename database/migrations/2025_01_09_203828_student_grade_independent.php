<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->unsignedBigInteger('independent_id')->nullable();

        });
        Schema::table('independents', function (Blueprint $table) {
            $table->boolean('status')->default(0);
            $table->string('grade_teacher')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->dropColumn([
                'independent_id',
            ]);
        });
        Schema::table('independents', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'grade_teacher'
            ]);
        });
    }
};