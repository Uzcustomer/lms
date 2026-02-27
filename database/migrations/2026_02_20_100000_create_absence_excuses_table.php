<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('absence_excuses')) {
            return;
        }

        Schema::create('absence_excuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('student_hemis_id');
            $table->string('student_full_name');
            $table->string('group_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('reason');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_original_name');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('reviewed_by_name')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('approved_pdf_path')->nullable();
            $table->string('verification_token')->unique()->nullable();
            $table->timestamps();

            $table->index('student_id');
            $table->index('student_hemis_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_excuses');
    }
};
