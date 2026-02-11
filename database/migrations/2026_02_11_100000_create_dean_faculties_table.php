<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dean_faculties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->bigInteger('department_hemis_id');
            $table->timestamps();

            $table->unique(['teacher_id', 'department_hemis_id']);
            $table->index('department_hemis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dean_faculties');
    }
};
