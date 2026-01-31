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
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('hemis_id');
            $table->bigInteger('meta_id')->nullable();
            $table->string('full_name');
            $table->string('short_name');
            $table->string('first_name');
            $table->string('second_name');
            $table->string('third_name')->nullable();
            $table->string('employee_id_number')->unique();
            $table->date('birth_date');
            $table->string('image')->nullable();
            $table->year('year_of_enter');
            $table->string('specialty')->nullable();
            $table->string('gender');
            $table->string('department');
            $table->string('employment_form');
            $table->string('employment_staff');
            $table->string('staff_position');
            $table->string('employee_status');
            $table->string('employee_type');
            $table->string('contract_number');
            $table->string('decree_number');
            $table->date('contract_date');
            $table->date('decree_date');
            $table->string('login')->unique()->nullable();
            $table->boolean('status')->default(true);
            $table->string('password')->nullable();
            $table->bigInteger('department_hemis_id')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
