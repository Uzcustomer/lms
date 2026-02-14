<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yn_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('subject_id');
            $table->string('semester_code');
            $table->string('group_hemis_id');
            $table->unsignedBigInteger('submitted_by');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['subject_id', 'semester_code', 'group_hemis_id'], 'yn_submissions_unique');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yn_submissions');
    }
};
