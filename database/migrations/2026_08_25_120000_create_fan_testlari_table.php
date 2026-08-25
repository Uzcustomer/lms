<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fan_testlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_subject_id')
                ->constrained('curriculum_subjects')
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->default(20);
            $table->unsignedTinyInteger('pass_percent')->nullable();
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('show_result_after_submit')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('questions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('teachers')->nullOnDelete();
            $table->timestamps();

            $table->index(['curriculum_subject_id', 'is_active'], 'fan_testlari_subject_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fan_testlari');
    }
};
