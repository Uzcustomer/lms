<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_visa_info_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('visa_info_id')->nullable();

            // Snapshot fields (StudentVisaInfo dan)
            $table->string('birth_country')->nullable();
            $table->string('birth_region')->nullable();
            $table->string('birth_city')->nullable();
            $table->date('birth_date')->nullable();

            $table->string('passport_number', 50)->nullable();
            $table->string('passport_issued_place')->nullable();
            $table->date('passport_issued_date')->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->string('passport_scan_path', 500)->nullable();

            $table->date('registration_start_date')->nullable();
            $table->date('registration_end_date')->nullable();
            $table->string('registration_doc_path', 500)->nullable();
            $table->string('registration_process_status', 30)->nullable();

            $table->string('address_type', 20)->nullable();
            $table->text('current_address')->nullable();

            $table->string('visa_number', 50)->nullable();
            $table->string('visa_type', 20)->nullable();
            $table->date('visa_start_date')->nullable();
            $table->date('visa_end_date')->nullable();
            $table->string('visa_issued_place')->nullable();
            $table->date('visa_issued_date')->nullable();
            $table->integer('visa_entries_count')->nullable();
            $table->integer('visa_stay_days')->nullable();
            $table->string('visa_scan_path', 500)->nullable();
            $table->string('visa_process_status', 30)->nullable();

            $table->date('entry_date')->nullable();

            $table->string('firm', 50)->nullable();
            $table->string('firm_custom')->nullable();

            $table->string('status', 20)->nullable();
            $table->text('rejection_reason')->nullable();

            // Meta — kim, qachon, qanday o'zgartirgan
            $table->string('change_type', 50);
            $table->json('changed_fields')->nullable();
            $table->string('actor_type', 50)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_role', 50)->nullable();
            $table->text('note')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->index(['student_id', 'created_at']);
            $table->index('change_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_visa_info_histories');
    }
};
