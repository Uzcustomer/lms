<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hemis_quiz_results', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('attempt_id')->unique();

            $table->dateTime('date_start')->nullable();
            $table->dateTime('date_finish')->nullable();

            $table->string('category_path', 500)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('category_name', 255)->nullable();

            $table->string('faculty', 50)->nullable();
            $table->string('direction', 50)->nullable();
            $table->string('semester', 20)->nullable();

            $table->string('student_id', 100)->nullable();
            $table->string('student_name', 255)->nullable();

            $table->unsignedBigInteger('fan_id')->nullable();
            $table->string('fan_name', 255)->nullable();

            $table->string('quiz_type', 50)->nullable();
            $table->string('attempt_name', 255)->nullable();
            $table->string('shakl', 50)->nullable();
            $table->integer('attempt_number')->default(1);

            // WordPress dagidek
            $table->decimal('grade', 10, 2)->nullable();
            $table->decimal('old_grade', 10, 2)->nullable();

            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('course_idnumber', 100)->nullable();

            $table->tinyInteger('is_valid_format')->default(0);

            $table->dateTime('synced_at')->nullable();

            $table->timestamps(); // created_at, updated_at

            $table->tinyInteger('is_active')->default(1);

            // indexes (ixtiyoriy, lekin foydali)
            $table->index('student_id');
            $table->index('fan_id');
            $table->index('course_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hemis_quiz_results');
    }
};
