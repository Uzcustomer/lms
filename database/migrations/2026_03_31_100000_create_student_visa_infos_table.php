<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_visa_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // Tug'ilgan joy
            $table->string('birth_country')->nullable();
            $table->string('birth_region')->nullable();
            $table->string('birth_city')->nullable();

            // Pasport ma'lumotlari
            $table->string('passport_issued_place')->nullable();
            $table->string('passport_number')->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->date('birth_date')->nullable();

            // Vaqtinchalik ro'yxatga qo'yish (propiska)
            $table->date('registration_start_date')->nullable();
            $table->date('registration_end_date')->nullable();

            // Viza ma'lumotlari
            $table->string('visa_number')->nullable();
            $table->string('visa_type')->nullable();
            $table->date('visa_start_date')->nullable();
            $table->date('visa_end_date')->nullable();
            $table->integer('visa_entries_count')->nullable();
            $table->integer('visa_stay_days')->nullable();
            $table->string('visa_issued_place')->nullable();
            $table->date('visa_issued_date')->nullable();

            // Firma
            $table->string('firm')->nullable();
            $table->string('firm_custom')->nullable();

            // Fayl yuklash
            $table->string('passport_scan_path')->nullable();
            $table->string('visa_scan_path')->nullable();
            $table->string('registration_doc_path')->nullable();

            // Tasdiqlash
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Pasport xodimga berilganligi
            $table->boolean('passport_handed_over')->default(false);
            $table->timestamp('passport_handed_at')->nullable();
            $table->foreignId('passport_received_by')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('agreement_accepted')->default(false);

            $table->timestamps();

            $table->unique('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_visa_infos');
    }
};
