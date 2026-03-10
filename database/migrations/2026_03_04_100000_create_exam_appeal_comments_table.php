<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_appeal_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_appeal_id')->constrained('exam_appeals')->cascadeOnDelete();
            $table->enum('user_type', ['admin', 'student']);
            $table->unsignedBigInteger('user_id');
            $table->string('user_name');
            $table->text('comment');
            $table->timestamps();

            $table->index('exam_appeal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_appeal_comments');
    }
};
