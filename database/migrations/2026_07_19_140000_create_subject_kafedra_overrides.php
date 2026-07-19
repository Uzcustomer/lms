<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fan → kafedra qo'lda tuzatishlari. Fanlar nomi har xil yozilgani uchun
 * avtomatik moslashtirish har doim to'g'ri chiqmaydi; foydalanuvchi qo'lda
 * to'g'rilaydi. Tuzatish fan nomining normallashtirilgan ko'rinishi bo'yicha
 * saqlanadi — shuning uchun reja qayta yuklansa/tahrirlansa ham (fan nomi
 * o'zgarmasa) qayta kiritish shart emas.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('subject_kafedra_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('norm_name')->unique();     // normallashtirilgan fan nomi (kalit)
            $table->string('sample_name')->nullable();  // odam o'qishi uchun asl nom namunasi
            $table->string('kafedra_name');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_kafedra_overrides');
    }
};
