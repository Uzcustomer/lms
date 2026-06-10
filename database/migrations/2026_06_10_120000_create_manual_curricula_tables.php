<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Akkreditatsiya uchun qo'lda (Excel orqali) yuklanadigan o'quv rejalar:
        // namunaviy (DTS asosidagi) va ishchi (OTM tomonidan tuzilgan) rejalar
        Schema::create('manual_curricula', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['namunaviy', 'ishchi'])->index();
            $table->string('name'); // masalan: "5510100 Davolash ishi (2020) namunaviy"
            $table->string('specialty_code')->nullable()->index();
            $table->string('specialty_name')->nullable();
            $table->string('plan_year')->nullable(); // masalan: "2021/2022"
            $table->unsignedTinyInteger('education_period')->nullable(); // o'qish muddati (yil)
            $table->string('file_original_name')->nullable();
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('manual_curriculum_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_curriculum_id')
                ->constrained('manual_curricula')
                ->cascadeOnDelete();
            $table->string('block')->nullable(); // majburiy / tanlov / amaliyot / davlat imtihoni ...
            $table->string('subject_code')->nullable();
            $table->string('subject_name');
            // Ishchi rejadagi nom namunaviy rejadagidan farq qilsa, mosligini
            // aniq ko'rsatish uchun namunaviy rejadagi nomi shu ustunga yoziladi
            $table->string('reference_name')->nullable();
            $table->unsignedTinyInteger('kurs')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->decimal('total_hours', 8, 2)->nullable();
            $table->decimal('audit_total', 8, 2)->nullable();
            $table->decimal('lecture', 8, 2)->nullable();
            $table->decimal('practice', 8, 2)->nullable();
            $table->decimal('laboratory', 8, 2)->nullable();
            $table->decimal('seminar', 8, 2)->nullable();
            $table->decimal('independent', 8, 2)->nullable();
            $table->decimal('credit', 6, 2)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_curriculum_subjects');
        Schema::dropIfExists('manual_curricula');
    }
};
