<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_capacity_overrides', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->time('work_hours_start')->nullable();
            $table->time('work_hours_end')->nullable();
            $table->time('lunch_start')->nullable();
            $table->time('lunch_end')->nullable();
            $table->unsignedInteger('computer_count')->nullable();
            $table->unsignedInteger('test_duration_minutes')->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_capacity_overrides');
    }
};
