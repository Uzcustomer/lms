<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fan testi ochiladigan sinf kompyuterlari. Har bir kompyuter brauzerida
     * maxfiy belgi (cookie) turadi; bazada uning faqat sha256 xeshi saqlanadi.
     * Belgi test sahifasi ochilganda yangilanadi — eski belgi keyinroq kelib
     * qolsa, u nusxalangan deb hisoblanadi va kompyuter bekor qilinadi.
     */
    public function up(): void
    {
        if (Schema::hasTable('test_kiosk_devices')) {
            return;
        }

        Schema::create('test_kiosk_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('token_hash', 64)->unique();
            $table->char('previous_token_hash', 64)->nullable()->index();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('registered_by_user_id')->nullable();
            $table->string('registered_by_name')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_kiosk_devices');
    }
};
