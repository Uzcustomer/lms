<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('academic_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id')->unique();
            $table->unsignedBigInteger('curriculum_id')->nullable();
            $table->string('education_year')->nullable();
            $table->string('semester_id')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('semester_name')->nullable();
            $table->string('student_name')->nullable();
            $table->string('subject_name')->nullable();
            $table->integer('total_acload')->nullable();
            $table->string('credit')->nullable();
            $table->string('total_point')->nullable();
            $table->string('grade')->nullable();
            $table->boolean('finish_credit_status')->default(false);
            $table->boolean('retraining_status')->default(false);
            $table->timestamp('hemis_created_at')->nullable();
            $table->timestamp('hemis_updated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_records');
    }
};
