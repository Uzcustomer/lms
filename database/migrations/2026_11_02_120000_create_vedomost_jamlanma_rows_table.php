<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasdiqlangan vedomostlar jamlanmasi uchun oldindan hisoblangan qatorlar.
 *
 * Jamlanma Excel har bosilganda har vedomost talabalarini (Mark hisobi, YN bosqichlari)
 * qaytadan hisoblardi — ko'p vedomostda juda sekin. Endi vedomost tasdiqlanishi bilan
 * uning jamlanma qatorlari fonda hisoblanib shu yerga yoziladi; jamlanma tayyor qatorlardan
 * bir zumda yig'iladi. Asosiy jadvalga emas — ro'yxat sahifasi vs.* ni o'qiydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vedomost_jamlanma_rows')) {
            return;
        }

        Schema::create('vedomost_jamlanma_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vedomost_submission_id')->unique();
            $table->longText('rows');
            $table->timestamp('built_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vedomost_jamlanma_rows');
    }
};
