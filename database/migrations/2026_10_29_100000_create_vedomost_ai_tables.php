<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vedomost AI tekshiruvi (Gemini) uchun kalitlar va so'rovlar jurnali.
 *
 * vedomost_ai_keys — admin qo'shadigan Gemini API kalitlari (shifrlangan),
 *   har biriga daqiqalik/kunlik limit va pullik/bepul belgisi.
 * vedomost_ai_requests — har bir Gemini so'rovi: kalit, model, vaqt, tokenlar,
 *   xato turi. AI sozlamalari sahifasidagi statistika shu jadvaldan hisoblanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vedomost_ai_keys')) {
            Schema::create('vedomost_ai_keys', function (Blueprint $table) {
                $table->id();
                $table->string('label', 100)->nullable();
                $table->text('api_key');
                $table->string('key_hint', 12)->nullable();
                $table->boolean('is_paid')->default(false);
                $table->boolean('enabled')->default(true);
                $table->unsignedInteger('rpm')->nullable();
                $table->unsignedInteger('daily_limit')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('last_error_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vedomost_ai_requests')) {
            Schema::create('vedomost_ai_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vedomost_submission_id')->nullable()->index();
                $table->unsignedBigInteger('vedomost_ai_key_id')->nullable()->index();
                $table->string('model', 80)->nullable();
                $table->string('purpose', 30)->default('transcribe');
                $table->string('status', 20); // ok | error
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->string('error_type', 40)->nullable();
                $table->text('error_message')->nullable();
                $table->unsignedInteger('latency_ms')->nullable();
                $table->unsignedInteger('input_tokens')->nullable();
                $table->unsignedInteger('output_tokens')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vedomost_ai_requests');
        Schema::dropIfExists('vedomost_ai_keys');
    }
};
