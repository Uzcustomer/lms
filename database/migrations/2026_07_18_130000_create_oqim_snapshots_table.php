<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oqim taqsimotining saqlangan holatlari (snapshot): registrator ofisi
 * optimizatsiyadan keyingi holatni qo'lda tahrirlab (talabalarni guruhlar
 * orasida ko'chirib) saqlaydi va TASDIQLAYDI. Har bir filtr konteksti
 * (ta'lim turi, fakultet, ta'lim, variant, me'yorlar, fakultetlararo)
 * uchun bitta yozuv (context_key bo'yicha).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oqim_snapshots', function (Blueprint $table) {
            $table->id();
            // Filtr kontekstini bir xil aniqlash uchun barqaror kalit (hash)
            $table->string('context_key', 64)->unique();
            // Kontekst (o'qish uchun): filtr parametrlari
            $table->json('context')->nullable();
            // Saqlangan (qo'lda tahrirlangan) optimizatsiyadan keyingi layout — bloklar JSON
            $table->longText('data')->nullable();
            // 'draft' (qoralama) | 'approved' (tasdiqlangan)
            $table->string('status', 20)->default('draft');
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oqim_snapshots');
    }
};
