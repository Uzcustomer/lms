<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduate_student_passports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('full_name_uz');
            $table->string('full_name_en');
            $table->string('passport_series', 2);
            $table->string('passport_number', 7);
            $table->string('passport_front_path');
            $table->string('passport_back_path');
            $table->string('foreign_passport_path')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduate_student_passports');
    }
};
