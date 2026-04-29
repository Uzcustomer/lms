<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('retake_application_groups', function (Blueprint $table) {
            $table->id();

            // Bitta arizadagi fanlarni birlashtiruvchi UUID
            $table->uuid('group_uuid')->unique();

            $table->unsignedBigInteger('student_id'); // students.id
            $table->unsignedBigInteger('student_hemis_id'); // students.hemis_id

            $table->unsignedBigInteger('window_id');

            // Kvitansiya
            $table->string('receipt_path');
            $table->decimal('receipt_amount', 12, 2);
            $table->decimal('credit_price_at_time', 12, 2);

            // Ixtiyoriy izoh (max 500 belgi)
            $table->text('comment')->nullable();

            // Yakuniy hujjatlar (uchchalasi tasdiqlagandan keyin generatsiya qilinadi)
            $table->string('docx_path')->nullable();
            $table->string('pdf_certificate_path')->nullable();

            // QR verification — DocumentVerification.token ga ishora
            $table->string('verification_token', 64)->nullable()->unique();

            $table->timestamps();

            $table->index('student_hemis_id');
            $table->index('window_id');

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('window_id')->references('id')->on('retake_application_windows')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_application_groups');
    }
};
