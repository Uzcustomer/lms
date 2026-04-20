<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('teacher_id');
            $table->string('teacher_name');
            $table->string('group_hemis_id')->nullable();
            $table->string('group_name')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->index(['student_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_history');
    }
};
