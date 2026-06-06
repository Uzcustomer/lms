<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Xalqaro talaba viza arizalari jadvali.
 * Talaba ariza yuboradi → admin/registrator ko'rib chiqadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('student_hemis_id')->nullable();
            // Talaba kiritgan ma'lumotlar
            $table->string('student_number')->index();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->date('birth_date');
            $table->string('passport_number')->index();
            $table->string('phone_number'); // E.164: +998901234567
            $table->string('phone_dial_code', 8)->nullable();
            $table->string('phone_country_iso2', 4)->nullable();
            // Yuklangan fayllar — storage/app/visa-applications/{id}/{file}
            $table->string('passport_pdf_path')->nullable();
            $table->string('application_pdf_path')->nullable();
            $table->string('receipt_pdf_path')->nullable(); // talaba uchun chek (kelajakda)
            // Holat: pending → reviewing → approved/rejected
            $table->enum('status', ['pending', 'reviewing', 'approved', 'rejected'])->default('pending');
            $table->string('application_number', 8)->nullable()->unique(); // 4 xonali
            $table->text('admin_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_applications');
    }
};
