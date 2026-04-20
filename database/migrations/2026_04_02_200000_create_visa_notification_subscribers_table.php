<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_notification_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('subscribable_type'); // User or Teacher
            $table->unsignedBigInteger('subscribable_id');
            $table->string('telegram_chat_id')->nullable();
            $table->timestamps();
            $table->unique(['subscribable_type', 'subscribable_id'], 'visa_notif_sub_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_notification_subscribers');
    }
};
