<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yn_consents', function (Blueprint $table) {
            $table->id();
            $table->string('student_hemis_id');
            $table->string('subject_id');
            $table->string('semester_code');
            $table->string('group_hemis_id');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['student_hemis_id', 'subject_id', 'semester_code'], 'yn_consents_unique');
            $table->index(['group_hemis_id', 'subject_id', 'semester_code'], 'yn_consents_group_subject_semester');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yn_consents');
    }
};
