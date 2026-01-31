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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_hemis_id')->unique();
            $table->string('name');
            $table->unsignedBigInteger('department_hemis_id');
            $table->string('department_name');
            $table->string('department_code');
            $table->string('department_structure_type_code');
            $table->string('department_structure_type_name');
            $table->string('department_locality_type_code');
            $table->string('department_locality_type_name');
            $table->boolean('department_active');
            $table->unsignedBigInteger('specialty_hemis_id');
            $table->string('specialty_code');
            $table->string('specialty_name');
            $table->string('education_lang_code');
            $table->string('education_lang_name');
            $table->unsignedBigInteger('curriculum_hemis_id');
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
