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
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('semester_hemis_id')->unique();
            $table->string('code');
            $table->string('name');
            $table->unsignedBigInteger('curriculum_hemis_id');
            $table->string('education_year');
            $table->string('level_code')->nullable();
            $table->string('level_name')->nullable();
            $table->boolean('current')->default(false);
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
