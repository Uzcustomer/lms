<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->enum('messenger_type', ['telegram', 'whatsapp'])->default('telegram')->after('phone_country_iso2');
            $table->string('messenger_username')->after('messenger_type');
        });
    }

    public function down(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->dropColumn(['messenger_type', 'messenger_username']);
        });
    }
};
