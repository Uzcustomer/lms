<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('independent_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('independent_id');
            $table->unsignedBigInteger('student_id');
            $table->string('student_hemis_id');
            $table->string('file_path');
            $table->string('file_original_name');
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->unique(['independent_id', 'student_id']);
            $table->index('independent_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('independent_submissions');
    }
};
