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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_hemis_id')->unique();
            $table->string('name');
            $table->string('code');
            $table->string('structure_type_code');
            $table->string('structure_type_name');
            $table->string('locality_type_code');
            $table->string('locality_type_name');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
