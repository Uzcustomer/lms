<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('retake_application_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('application_id')->nullable(); // ariza alohida log
            $table->unsignedBigInteger('group_id')->nullable();       // ariza-guruh log (yuborilganda)
            $table->unsignedBigInteger('user_id')->nullable();        // null bo'lsa system

            $table->string('action', 60); // submitted, dean_approved, dean_rejected, ...
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('application_id');
            $table->index('group_id');
            $table->index('user_id');
            $table->index(['action', 'created_at']);

            $table->foreign('application_id')
                ->references('id')->on('retake_applications')
                ->onDelete('cascade');

            $table->foreign('group_id')
                ->references('id')->on('retake_application_groups')
                ->onDelete('cascade');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_application_logs');
    }
};
