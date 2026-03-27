<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('student_hemis_id');
            $table->string('student_name');
            $table->string('group_name')->nullable();
            $table->string('club_name');
            $table->string('club_place')->nullable();
            $table->string('club_day')->nullable();
            $table->string('club_time')->nullable();
            $table->string('kafedra_name')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reject_reason')->nullable();
            $table->timestamps();

            $table->index('student_hemis_id');
            $table->index('status');
            $table->unique(['student_id', 'club_name'], 'unique_student_club');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_memberships');
    }
};
