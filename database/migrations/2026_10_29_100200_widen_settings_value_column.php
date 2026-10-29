<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * settings.value: string(255) -> text.
 *
 * Xalqaro talabalar sozlamalari (firmalar ro'yxati, viza turlari,
 * yotoqxona manzili) JSON/matn ko'rinishida saqlanadi va 255 belgidan
 * oshib ketishi mumkin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->text('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Uzun qiymat bo'lsa MySQL o'zi rad etadi — ma'lumot jim kesilmaydi
        Schema::table('settings', function (Blueprint $table) {
            $table->string('value')->nullable()->change();
        });
    }
};
