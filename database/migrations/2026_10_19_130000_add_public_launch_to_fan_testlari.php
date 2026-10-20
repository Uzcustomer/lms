<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * "Umumiy ochish" rejimi: test bitta havola orqali istalgan kompyuterda
 * ochiladi (ro'yxatdan o'tgan kompyuter shart emas). Talaba ID sini kiritgach
 * so'rovi o'qituvchining "Kirish so'rovlari" oynasiga tushadi; o'qituvchi
 * ruxsat bersa talabaga yuz tekshiruvi ochiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fan_testlari', function (Blueprint $table) {
            $table->timestamp('public_open_at')->nullable()->after('is_active');
            $table->string('public_open_by', 120)->nullable()->after('public_open_at');
        });

        Schema::create('fan_testi_entry_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fan_testi_id');
            $table->unsignedBigInteger('student_id');
            $table->string('student_name');
            $table->string('student_id_number', 64)->nullable();
            $table->string('group_name')->nullable();
            $table->string('faculty_name')->nullable();
            // Brauzer sessiyasiga bog'lovchi belgi: ruxsat faqat shu brauzerda ishlaydi
            $table->char('token', 64)->unique();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('decided_by', 120)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('attempt_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign('fan_testi_id', 'fter_test_fk')->references('id')->on('fan_testlari')->cascadeOnDelete();
            $table->index(['fan_testi_id', 'status'], 'fter_test_status_idx');
            $table->index(['fan_testi_id', 'student_id'], 'fter_test_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fan_testi_entry_requests');
        Schema::table('fan_testlari', function (Blueprint $table) {
            $table->dropColumn(['public_open_at', 'public_open_by']);
        });
    }
};
