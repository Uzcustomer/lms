<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guruh sig'imi — qo'lda o'zgartirilgan qiymatlar.
 *
 * Standart sig'im kursga qarab hisoblanadi (1-3 kurs 15 ta, 4-6 kurs 10 ta),
 * shuning uchun bu yerda faqat standartdan farq qiladigan guruhlar saqlanadi.
 * Yozuvi yo'q guruh standart sig'imda ishlaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_group_capacities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_hemis_id')->unique('dgc_group_unique');
            $table->unsignedInteger('capacity');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_group_capacities');
    }
};
