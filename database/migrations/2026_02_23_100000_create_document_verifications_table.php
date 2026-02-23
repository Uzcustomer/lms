<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('document_type');
            $table->string('subject_name')->nullable();
            $table->string('group_names')->nullable();
            $table->string('semester_name')->nullable();
            $table->string('department_name')->nullable();
            $table->string('generated_by')->nullable();
            $table->timestamp('generated_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_verifications');
    }
};
