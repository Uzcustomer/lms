<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_photos', function (Blueprint $table) {
            $table->id();
            $table->string('student_id_number');
            $table->string('full_name');
            $table->string('group_name')->nullable();
            $table->string('semester_name')->nullable();
            $table->string('uploaded_by');
            $table->string('photo_path');
            $table->timestamps();

            $table->index('student_id_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_photos');
    }
};
