<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_transfer_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('phone', 50);
            $table->text('reason');
            $table->string('order_path');
            $table->string('order_name');
            $table->string('order_mime', 100)->nullable();
            $table->unsignedBigInteger('order_size')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_transfer_applications');
    }
};
