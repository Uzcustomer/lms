<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admission_indicators')) {
            return;
        }
        Schema::create('admission_indicators', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('qabul_yili')->index();          // Qabul yili
            $table->string('talim_turi', 60)->nullable();                 // Bakalavr / Magistr / Ordinatura ...
            $table->string('talim_shakli', 60)->nullable();               // Kunduzgi / Sirtqi / Kechki / Masofaviy
            $table->string('mutaxassislik', 255)->nullable();             // Yo'nalish / mutaxassislik nomi
            $table->string('mutaxassislik_kodi', 30)->nullable();         // Mutaxassislik kodi (masalan 60910200)
            $table->string('tolov_shakli', 60)->nullable();               // Davlat granti / To'lov-shartnoma
            $table->unsignedInteger('reja')->nullable();                  // Reja (kvota)
            $table->unsignedInteger('qabul_soni')->nullable();            // Qabul qilinganlar soni
            $table->decimal('min_ball', 6, 1)->nullable();                // Eng past o'tish bali
            $table->text('izoh')->nullable();                             // Izoh
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['qabul_yili', 'talim_turi', 'talim_shakli']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_indicators');
    }
};
