<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * HEMIS xodimning har bir ish joyini alohida yozuv (meta_id) qilib beradi:
 * asosiy ish joyi, o'rindoshlik, soatbay. teachers jadvalida faqat asosiysi
 * turadi — qolganlari shu yerda, masalan kafedra mudiri jadval tuzishda o'z
 * kafedrasidagi o'rindosh o'qituvchilarni ko'rishi uchun.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('teacher_employments')) {
            return;
        }
        Schema::create('teacher_employments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->unsignedBigInteger('meta_id')->unique();
            $table->unsignedBigInteger('department_hemis_id')->nullable()->index();
            $table->string('department_name')->nullable();
            $table->string('staff_position')->nullable();
            $table->string('employment_form')->nullable();
            $table->string('employment_staff')->nullable();
            $table->string('employee_status')->nullable();
            // teachers jadvaliga yozilgan (asosiy) ish joyi
            $table->boolean('is_primary')->default(false);
            // HEMIS da "Ishlamoqda"
            $table->boolean('is_working')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_employments');
    }
};
