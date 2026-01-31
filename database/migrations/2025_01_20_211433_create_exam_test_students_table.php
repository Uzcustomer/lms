<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_test_students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_test_id');
            $table->unsignedBigInteger('student_hemis_id');
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_test_students');
    }
};