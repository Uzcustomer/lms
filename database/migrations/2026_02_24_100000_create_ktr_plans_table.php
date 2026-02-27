<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ktr_plans')) {
            return;
        }

        Schema::create('ktr_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curriculum_subject_id');
            $table->unsignedSmallInteger('week_count');
            $table->json('plan_data');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique('curriculum_subject_id');
            $table->foreign('curriculum_subject_id')->references('id')->on('curriculum_subjects')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ktr_plans');
    }
};
