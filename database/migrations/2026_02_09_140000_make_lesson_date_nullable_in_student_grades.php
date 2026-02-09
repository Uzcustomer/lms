<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->timestamp('lesson_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            $table->timestamp('lesson_date')->nullable(false)->change();
        });
    }
};
