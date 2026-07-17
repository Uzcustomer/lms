<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guruhlar uchun qo'lda tuzatishlar (override) — HEMIS to'g'rilanмагунча
 * guruh tilini to'g'rilash yoki guruhni hisobdan chiqarish uchun.
 * HEMIS re-import qilinganда ham bu jadval o'zgarmaydi (alohida saqlanadi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_hemis_id')->unique();
            $table->string('group_name')->nullable();
            // Til override: 'uz' | 'rus' | 'ing' | null (null = HEMISdagidek)
            $table->string('lang', 10)->nullable();
            // Hisobdan chiqarilsin (xato biriktirilgan / fantom guruh)
            $table->boolean('excluded')->default(false);
            $table->string('note')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_overrides');
    }
};
