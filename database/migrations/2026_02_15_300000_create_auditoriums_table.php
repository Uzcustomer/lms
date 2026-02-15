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
        Schema::create('auditoriums', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('volume')->default(0);
            $table->boolean('active')->default(true);
            $table->string('building_id')->nullable();
            $table->string('building_name')->nullable();
            $table->string('auditorium_type_code')->nullable();
            $table->string('auditorium_type_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditoriums');
    }
};
