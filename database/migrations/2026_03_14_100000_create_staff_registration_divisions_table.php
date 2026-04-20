<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_registration_divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->enum('division_type', ['front_office', 'back_office']);
            $table->bigInteger('department_hemis_id');
            $table->string('specialty_hemis_id')->nullable();
            $table->string('level_code')->nullable();
            $table->timestamps();

            $table->index('teacher_id');
            $table->index('department_hemis_id');
            $table->index('division_type');
            $table->unique(
                ['division_type', 'department_hemis_id', 'specialty_hemis_id', 'level_code'],
                'staff_reg_div_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_registration_divisions');
    }
};
