<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Umumiy rejimda talaba test sahifasi jarayonini (nechta javob, oxirgi aloqa)
 * so'rov yozuviga yuboradi — o'qituvchi "Kirish so'rovlari" oynasida
 * kompyuterlardagidek jonli ko'radi va to'xtata oladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fan_testi_entry_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('progress_answered')->nullable()->after('attempt_id');
            $table->timestamp('progress_at')->nullable()->after('progress_answered');
            $table->timestamp('stop_requested_at')->nullable()->after('progress_at');
        });
    }

    public function down(): void
    {
        Schema::table('fan_testi_entry_requests', function (Blueprint $table) {
            $table->dropColumn(['progress_answered', 'progress_at', 'stop_requested_at']);
        });
    }
};
