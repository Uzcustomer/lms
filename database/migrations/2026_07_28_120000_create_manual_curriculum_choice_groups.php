<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Qo'lda belgilanadigan TANLOV guruhlari.
     *
     * Namunaviy rejada tanlov fanlari blok ostidagi muqobil ro'yxat bo'lib
     * ("2.02 O'zbek/rus tili YOKI Tibbiyotda xorijiy til"), ishchi rejada esa
     * ko'pincha butunlay boshqacha — bitta qatorda "A / B" ko'rinishida —
     * yoziladi. Nom bo'yicha avtomatik moslash bunday hollarda ishlamaydi,
     * shu bois muqobillar va ularga mos ishchi fan(lar) qo'lda bog'lanadi.
     *
     * Guruh HEMIS o'quv rejasiga bog'lanadi (manual_curricula.id ga emas):
     * namunaviy reja qayta yuklanganda eski yozuv o'chib, yangisi yaratiladi —
     * qo'lda kiritilgan bog'lanishlar esa saqlanib qolishi kerak.
     */
    public function up(): void
    {
        Schema::create('manual_curriculum_choice_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curricula_hemis_id')->index();
            $table->string('label');                 // guruh nomi, masalan "2.02 Chet tili"
            $table->json('ref_names');               // namunaviy rejadagi muqobil fan nomlari
            $table->json('work_names');              // ishchi reja(lar)dagi mos fan nomlari
            $table->string('norm_name')->nullable(); // norma soat/kredit olinadigan muqobil
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_curriculum_choice_groups');
    }
};
