<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('faculty_hemis_id')->nullable()->index();
            $table->string('faculty_name')->nullable();
            $table->unsignedBigInteger('specialty_hemis_id')->nullable()->index();
            $table->string('specialty_name')->nullable();
            $table->string('level_code', 20)->nullable()->index();
            $table->string('level_name')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable()->index();
            $table->unsignedBigInteger('teacher_hemis_id')->nullable()->index();
            $table->string('teacher_name')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('test_subject_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_subject_id')->constrained('test_subjects')->cascadeOnDelete();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->unsignedBigInteger('group_hemis_id')->nullable()->index();
            $table->string('group_name');
            $table->timestamps();

            $table->unique(['test_subject_id', 'group_id'], 'tsg_subject_group_unique');
        });

        Schema::create('test_subject_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_subject_id')->constrained('test_subjects')->cascadeOnDelete();
            $table->date('lesson_date')->index();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->unsignedInteger('topic_order')->default(1);
            $table->string('topic_title')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['test_subject_id', 'lesson_date', 'topic_order'], 'tsl_subject_date_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_subject_lessons');
        Schema::dropIfExists('test_subject_groups');
        Schema::dropIfExists('test_subjects');
    }
};
