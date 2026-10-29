<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Davlat bo'yicha qoida: talabaga "turish asosi" tanlovi ko'rsatiladimi
 * va ko'rsatilmasa qaysi asos avtomatik qo'yiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visa_country_rules')) {
            return;
        }

        Schema::create('visa_country_rules', function (Blueprint $table) {
            $table->id();
            $table->string('country_name')->unique();
            $table->boolean('show_choice')->default(true);
            $table->string('default_basis', 30)->default('visa');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_country_rules');
    }
};
