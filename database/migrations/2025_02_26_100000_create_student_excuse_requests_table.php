<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_excuse_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('student_hemis_id');
            $table->string('student_name');
            $table->string('group_name')->nullable();
            $table->string('type'); // exam_test, oski
            $table->string('subject_name');
            $table->text('reason');
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('admin_comment')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index('student_hemis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_excuse_requests');
    }
};
