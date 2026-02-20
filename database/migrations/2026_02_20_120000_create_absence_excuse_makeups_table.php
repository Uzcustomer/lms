<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_excuse_makeups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('absence_excuse_id');
            $table->unsignedBigInteger('student_id');
            $table->string('subject_name');
            $table->string('subject_id')->nullable();
            $table->string('assessment_type');
            $table->string('assessment_type_code');
            $table->date('original_date');
            $table->date('makeup_date')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('absence_excuse_id')->references('id')->on('absence_excuses')->onDelete('cascade');
            $table->index('student_id');
            $table->index('absence_excuse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_excuse_makeups');
    }
};
