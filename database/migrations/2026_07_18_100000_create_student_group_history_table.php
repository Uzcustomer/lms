<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_group_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('group_hemis_id')->nullable();
            $table->string('group_name')->nullable();
            $table->string('specialty_name')->nullable();
            $table->string('education_year_name')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->index(['student_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_group_history');
    }
};
