<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Talabaning qayta topshirish (otrabotka) vaqtini bron qilishi. id Moodle'ga
 * booking_id sifatida yuboriladi (local_otrabotka_book_retake). Bitta mavzu
 * (talaba + fan + dars sanasi) uchun bir vaqtda faqat bitta "booked" yozuv —
 * buni RetakeBookingService ta'minlaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retake_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_hemis_id');
            $table->unsignedBigInteger('subject_id');
            $table->date('lesson_date');
            $table->unsignedBigInteger('grade_id')->nullable(); // student_grades.id

            // Moodle get_debts dan
            $table->string('subject_name')->nullable();
            $table->unsignedInteger('mavzu')->nullable();
            $table->string('reason', 20)->nullable(); // nb | low_grade
            $table->date('deadline')->nullable();

            $table->dateTime('slot_start');
            $table->dateTime('slot_end');

            $table->string('status', 20)->default('booked'); // booked | cancelled | missed | rejected
            $table->string('moodle_sync', 20)->default('pending'); // pending | ok | failed
            $table->unsignedSmallInteger('sync_attempts')->default(0);
            $table->string('moodle_code', 50)->nullable();
            $table->text('moodle_message')->nullable();

            $table->dateTime('synced_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('student_hemis_id');
            $table->index(['student_hemis_id', 'subject_id', 'lesson_date', 'status'], 'retake_bookings_debt_status_idx');
            $table->index(['moodle_sync', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_bookings');
    }
};
