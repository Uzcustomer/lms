<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ktr_change_requests')) {
            return;
        }

        Schema::create('ktr_change_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curriculum_subject_id');
            $table->unsignedBigInteger('requested_by');
            $table->string('requested_by_guard')->default('teacher');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index('curriculum_subject_id');
            $table->index('requested_by');
        });

        Schema::create('ktr_change_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_request_id')->constrained('ktr_change_requests')->onDelete('cascade');
            $table->string('role'); // kafedra_mudiri, dekan, registrator_ofisi
            $table->string('approver_name');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index('change_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ktr_change_approvals');
        Schema::dropIfExists('ktr_change_requests');
    }
};
