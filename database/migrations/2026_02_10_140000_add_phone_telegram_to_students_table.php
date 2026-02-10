<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('must_change_password');
            $table->string('telegram_username')->nullable()->after('phone');
            $table->string('telegram_chat_id')->nullable()->after('telegram_username');
            $table->string('telegram_verification_code')->nullable()->after('telegram_chat_id');
            $table->timestamp('telegram_verified_at')->nullable()->after('telegram_verification_code');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['phone', 'telegram_username', 'telegram_chat_id', 'telegram_verification_code', 'telegram_verified_at']);
        });
    }
};
