<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('independent_grade_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('independent_id');
            $table->unsignedBigInteger('student_id');
            $table->string('student_hemis_id');
            $table->float('grade');
            $table->unsignedInteger('submission_number');
            $table->string('graded_by')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->index(['independent_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('independent_grade_history');
    }
};
