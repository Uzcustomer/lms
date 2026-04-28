<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('retake_application_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')
                ->constrained('retake_applications')
                ->cascadeOnDelete();

            // Aktor: Teacher yoki User. Tizim hodisalarida null bo'lishi mumkin.
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_guard', 10)->nullable();

            // Action enum (string): submitted, resubmitted, dean_approved, dean_rejected,
            // registrar_approved, registrar_rejected, academic_dept_approved,
            // academic_dept_rejected, assigned_to_group, period_opened, period_closed,
            // period_updated
            $table->string('action', 40);

            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['application_id', 'created_at'], 'retake_logs_app_created_idx');
            $table->index('action', 'retake_logs_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retake_application_logs');
    }
};
