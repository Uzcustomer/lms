<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vedomost_submission_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vedomost_submission_id')->index();
            $table->string('action');                 // upload | review | approve | reject
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('note')->nullable();         // rad sababi va h.k.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vedomost_submission_logs');
    }
};
