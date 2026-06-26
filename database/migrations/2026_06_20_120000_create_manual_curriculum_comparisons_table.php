<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Bajarilgan solishtirishlar tarixi: foydalanuvchi "Solishtirish" tugmasini
        // bosganda namunaviy (reference) va ishchi (working) reja juftligi shu yerga
        // saqlanadi va ro'yxatda ko'rsatiladi.
        Schema::create('manual_curriculum_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reference_id')
                ->constrained('manual_curricula')
                ->cascadeOnDelete();
            $table->foreignId('working_id')
                ->constrained('manual_curricula')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['reference_id', 'working_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_curriculum_comparisons');
    }
};
