<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_contracts', function (Blueprint $table) {
            $table->id();

            // Talaba ma'lumotlari
            $table->unsignedBigInteger('student_id');
            $table->string('student_hemis_id')->index();
            $table->string('student_full_name');
            $table->string('group_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('specialty_name')->nullable();
            $table->string('level_name')->nullable();

            // Shartnoma turi: 3_tomonlama, 4_tomonlama
            $table->string('contract_type')->default('3_tomonlama');

            // Bitiruvchi (talaba) to'ldirishi kerak bo'lgan ma'lumotlar
            $table->string('student_address')->nullable();
            $table->string('student_phone')->nullable();
            $table->string('student_passport')->nullable();
            $table->string('student_bank_account')->nullable();
            $table->string('student_bank_mfo')->nullable();
            $table->string('student_inn')->nullable();

            // Potensial ish beruvchi ma'lumotlari (talaba yoki registrator to'ldiradi)
            $table->string('employer_name')->nullable();
            $table->string('employer_address')->nullable();
            $table->string('employer_phone')->nullable();
            $table->string('employer_bank_account')->nullable();
            $table->string('employer_bank_mfo')->nullable();
            $table->string('employer_inn')->nullable();
            $table->string('employer_director_name')->nullable();
            $table->string('employer_director_position')->nullable();

            // 4-tomon (yashovchi joyi - MFY) ma'lumotlari
            $table->string('fourth_party_name')->nullable();
            $table->string('fourth_party_address')->nullable();
            $table->string('fourth_party_phone')->nullable();
            $table->string('fourth_party_director_name')->nullable();

            // Mutaxassislik bo'yicha
            $table->string('specialty_field')->nullable();

            // Status: pending, registrar_review, approved, rejected
            $table->string('status')->default('pending');
            $table->text('reject_reason')->nullable();

            // Registrator
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Yaratilgan hujjat
            $table->string('document_path')->nullable();

            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_contracts');
    }
};
