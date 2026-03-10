<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Face ID yurishlari logi
        Schema::create('face_id_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->nullable()->index();
            $table->string('student_id_number', 50)->nullable()->index();
            $table->enum('result', ['success', 'failed', 'liveness_failed', 'not_found', 'disabled'])->default('failed');
            $table->float('confidence')->nullable()->comment('0-1 orasida; 1 = to\'liq mos');
            $table->float('distance')->nullable()->comment('Euclidean masofa; 0 = to\'liq mos');
            $table->string('failure_reason')->nullable();
            $table->text('snapshot')->nullable()->comment('Base64 JPEG snapshot');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('set null');
        });

        // Talabalar yuz deskriptorlarini kesh saqlash
        Schema::create('face_id_descriptors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->unique()->index();
            $table->text('descriptor')->comment('JSON: 128 o\'lchovli Float32Array');
            $table->string('source_image_url')->nullable()->comment('Deskriptor olindi manzil');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_id_descriptors');
        Schema::dropIfExists('face_id_logs');
    }
};
