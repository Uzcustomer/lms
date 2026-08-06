<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akademik_mobillik_tasdiqlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')
                ->constrained('akademik_mobillik_arizalar')
                ->cascadeOnDelete();
            $table->string('role', 60);
            $table->string('status', 20);
            $table->unsignedBigInteger('reviewed_by_id')->nullable();
            $table->string('reviewed_by_name')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'role'], 'akademik_mobillik_ariza_role_unique');
            $table->index(['role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akademik_mobillik_tasdiqlari');
    }
};
