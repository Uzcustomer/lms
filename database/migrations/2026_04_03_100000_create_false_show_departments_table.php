<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('false_show_departments', function (Blueprint $table) {
            $table->id();
            $table->string('department_hemis_id');
            $table->boolean('enabled')->default(false);
            $table->timestamps();
            $table->unique('department_hemis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('false_show_departments');
    }
};
