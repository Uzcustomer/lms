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
        Schema::create('curricula', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curricula_hemis_id')->unique();
            $table->string('name');
            $table->unsignedBigInteger('specialty_hemis_id');
            $table->unsignedBigInteger('department_hemis_id');
            $table->string('education_year_code');
            $table->string('education_year_name');
            $table->boolean('current');
            $table->string('education_type_code');
            $table->string('education_type_name');
            $table->string('education_form_code');
            $table->string('education_form_name');
            $table->string('marking_system_code');
            $table->string('marking_system_name');
            $table->integer('marking_system_minimum_limit');
            $table->float('marking_system_gpa_limit');
            $table->integer('semester_count');
            $table->integer('education_period');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curricula');
    }
};
