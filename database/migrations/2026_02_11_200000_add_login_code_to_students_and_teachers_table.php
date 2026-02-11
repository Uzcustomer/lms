<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('login_code', 6)->nullable()->after('telegram_verified_at');
            $table->timestamp('login_code_expires_at')->nullable()->after('login_code');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->string('login_code', 6)->nullable()->after('telegram_verified_at');
            $table->timestamp('login_code_expires_at')->nullable()->after('login_code');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['login_code', 'login_code_expires_at']);
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['login_code', 'login_code_expires_at']);
        });
    }
};
