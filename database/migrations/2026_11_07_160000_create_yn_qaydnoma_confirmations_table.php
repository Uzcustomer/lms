<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * YN qaydnomani amaliy o'qituvchi tekshirib "Tasdiqlash" bosgani: har bir tasdiqqa
 * unikal QR (document_verifications token) — qaydnomada o'qituvchi ismi yoniga bosiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yn_qaydnoma_confirmations', function (Blueprint $table) {
            $table->id();
            $table->string('subject_id');
            $table->string('semester_code');
            $table->string('group_hemis_id');
            $table->unsignedBigInteger('teacher_id');
            $table->string('teacher_name');
            $table->unsignedBigInteger('document_verification_id')->nullable();
            $table->json('weights')->nullable();
            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->index(['subject_id', 'semester_code', 'group_hemis_id'], 'ynqc_vedomost_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yn_qaydnoma_confirmations');
    }
};
