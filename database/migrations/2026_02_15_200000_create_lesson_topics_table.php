<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_topics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_hemis_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('semester_code');
            $table->date('lesson_date');
            $table->unsignedBigInteger('topic_hemis_id');
            $table->string('topic_name');
            $table->unsignedBigInteger('assigned_by_id');
            $table->string('assigned_by_guard')->default('teacher');
            $table->timestamps();

            $table->unique(
                ['group_hemis_id', 'subject_id', 'semester_code', 'lesson_date', 'topic_hemis_id'],
                'lt_unique'
            );
            $table->index(['group_hemis_id', 'subject_id', 'semester_code'], 'lt_group_subject_semester');
            $table->index('topic_hemis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_topics');
    }
};
