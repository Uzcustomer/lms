<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('sender_type')->nullable();
            $table->unsignedBigInteger('recipient_id');
            $table->string('recipient_type');
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('type')->default('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_draft')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['recipient_id', 'recipient_type', 'is_read']);
            $table->index(['sender_id', 'sender_type']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
