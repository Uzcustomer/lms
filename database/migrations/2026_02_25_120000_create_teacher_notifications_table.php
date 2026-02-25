<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('teacher_notifications')) {
            Schema::create('teacher_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('teacher_id');
                $table->string('type')->default('ktr_change_request');
                $table->string('title');
                $table->text('message');
                $table->string('link')->nullable();
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index('teacher_id');
                $table->index(['teacher_id', 'read_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_notifications');
    }
};
