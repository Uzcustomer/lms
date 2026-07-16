<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_academic_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');

            // Darslarga qatnashish to'g'risidagi farmoyish
            $table->string('farmoyish_number')->nullable();
            $table->date('farmoyish_date')->nullable();
            $table->string('farmoyish_file_path')->nullable();
            $table->string('farmoyish_file_original_name')->nullable();

            // O'qishga qabul qilinganlik to'g'risidagi buyruq
            $table->string('qabul_number')->nullable();
            $table->date('qabul_date')->nullable();
            $table->string('qabul_file_path')->nullable();
            $table->string('qabul_file_original_name')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_academic_orders');
    }
};
