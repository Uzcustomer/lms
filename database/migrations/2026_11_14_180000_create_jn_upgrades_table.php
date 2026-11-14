<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "JN 70%" (grades:jn70): YN imtihonlari chegara sanadan (26.10.2026) oldin tugagan fanda asosiy imtihonga
 * qatnashmagan talabaning keyingi urinishlari (12-qo'shimcha, 12a, 12b) uchun formula bo'yicha oshirilgan JN.
 * Asl baholar va JN o'zgarmaydi — qiymat shu jadvalda; --revert yozuvlarni o'chiradi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jn_upgrades', function (Blueprint $table) {
            $table->id();
            $table->string('student_hemis_id');
            $table->string('group_hemis_id');
            $table->string('subject_id');
            $table->string('semester_code');
            $table->unsignedSmallInteger('jn_original');
            $table->unsignedSmallInteger('jn_upgraded');
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->unique(['student_hemis_id', 'subject_id', 'semester_code'], 'jn_upgrades_student_subject_unique');
            $table->index(['group_hemis_id', 'subject_id', 'semester_code'], 'jn_upgrades_vedomost_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jn_upgrades');
    }
};
