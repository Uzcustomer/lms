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
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('specialty_hemis_id')->unique();
            $table->string('code');
            $table->string('name');
            $table->unsignedBigInteger('department_hemis_id')->nullable();
            $table->string('department_name')->nullable();
            $table->string('department_code')->nullable();
            $table->string('locality_type_code');
            $table->string('locality_type_name');
            $table->string('education_type_code');
            $table->string('education_type_name');
            $table->string('bachelor_specialty_code')->nullable();
            $table->string('bachelor_specialty_name')->nullable();
            $table->string('master_specialty_code')->nullable();
            $table->string('master_specialty_name')->nullable();
            $table->string('doctorate_specialty_code')->nullable();
            $table->string('doctorate_specialty_name')->nullable();
            $table->string('ordinature_specialty_code')->nullable();
            $table->string('ordinature_specialty_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialties');
    }
};
