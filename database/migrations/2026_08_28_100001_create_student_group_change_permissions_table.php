<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_group_change_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('enabled_by_id')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->unique('student_id');
            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_group_change_permissions');
    }
};
