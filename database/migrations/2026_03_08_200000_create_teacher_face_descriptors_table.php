<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_face_descriptors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id')->unique()->index();
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->json('descriptor');          // 128-dim Float32Array
            $table->string('source_image_url')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_face_descriptors');
    }
};
