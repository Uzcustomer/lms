<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ingliz_guruh_arizalar')) {
            return;
        }
        Schema::create('ingliz_guruh_arizalar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->nullable()->index();
            $table->unsignedBigInteger('student_hemis_id')->nullable()->index();
            $table->string('full_name');
            $table->string('faculty_name')->nullable();
            $table->string('specialty_name')->nullable();
            $table->string('course_name')->nullable();
            $table->string('semester_name')->nullable();
            $table->string('group_name')->nullable();
            $table->string('english_level', 30)->nullable();
            $table->string('certificate_pdf_path')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingliz_guruh_arizalar');
    }
};
